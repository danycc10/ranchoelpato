<?php

namespace App\Services\Contratos;

use App\Models\Contrato;
use App\Services\MapaLotesService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class AnexoPlanoService
{
    private const VERSION_DISENO = 2;

    public function preparar(Contrato $contrato): array
    {
        return DB::transaction(function () use ($contrato) {
            $actual = Contrato::with('lote.fraccionamiento')->lockForUpdate()->findOrFail($contrato->id);
            if ($guardado = $actual->anexo_plano) {
                if ($guardado['lote_id'] !== $actual->lote_id) {
                    throw new RuntimeException('El plano conservado corresponde a otro lote. Revise el anexo del contrato antes de regenerarlo.');
                }
                if (! Storage::disk('private')->exists($guardado['imagen'])) {
                    throw new RuntimeException('Falta la copia conservada del plano del contrato. Restaure el archivo antes de regenerar el documento.');
                }
                if (hash_file('sha256', Storage::disk('private')->path($guardado['imagen'])) !== $guardado['sha256']) {
                    throw new RuntimeException('La copia conservada del plano fue modificada. Revise el archivo antes de regenerar el documento.');
                }

                if (($guardado['version_diseno'] ?? 1) < self::VERSION_DISENO) {
                    $mapa = app(MapaLotesService::class)->datosPorClave($guardado['mapa_clave']);
                    $figuras = array_values(array_filter($mapa['lotes'], fn (array $figura) => (string) $figura['id'] === (string) $guardado['figura_id']));
                    if (count($figuras) !== 1) {
                        throw new RuntimeException('No se encuentra la figura conservada del plano para actualizar su diseño.');
                    }
                    $png = $this->dibujar($mapa, $figuras[0]);
                    $sha = hash('sha256', $png);
                    $path = 'contratos/'.$actual->uuid.'/anexos/plano/'.$sha.'.png';
                    if (! Storage::disk('private')->put($path, $png)) {
                        throw new RuntimeException('No se pudo conservar el plano actualizado del contrato.');
                    }
                    $guardado['versiones_anteriores'][] = [
                        'imagen' => $guardado['imagen'],
                        'sha256' => $guardado['sha256'],
                        'version_diseno' => $guardado['version_diseno'] ?? 1,
                    ];
                    $guardado['imagen'] = $path;
                    $guardado['sha256'] = $sha;
                    $guardado['version_diseno'] = self::VERSION_DISENO;
                    $guardado['diseno_actualizado_en'] = now()->toIso8601String();
                    $actual->forceFill(['anexo_plano' => $guardado])->save();
                    $contrato->setAttribute('anexo_plano', $guardado);
                }

                return $guardado;
            }
            $lote = $actual->lote;
            $finca = $lote?->fraccionamiento;
            $anexo = [
                'folio' => $actual->folio_contrato ?: $actual->uuid,
                'lote_id' => $actual->lote_id,
                'proyecto' => $finca?->nombre,
                'manzana' => $lote?->manzana,
                'lote' => $lote?->lote,
                'area' => $actual->area_m2_snapshot ?? $lote?->area_m2,
                'medidas' => [],
                'imagen' => null,
                'pendiente' => null,
            ];
            foreach (['norte' => 'Norte', 'sur' => 'Sur', 'este' => 'Este', 'oeste' => 'Oeste'] as $campo => $nombre) {
                $anexo['medidas'][] = ['lado' => $nombre,
                    'medida' => $actual->{'medida_'.$campo.'_snapshot'} ?? $lote?->{'medida_'.$campo},
                    'colindancia' => $actual->{'colindancia_'.$campo.'_snapshot'} ?? $lote?->{'colindancia_'.$campo}];
            }
            if (! $finca || ! isset(MapaLotesService::MAPAS[$finca->mapa_clave])) {
                $anexo['pendiente'] = 'Vincule el fraccionamiento a un mapa antes de integrar el plano.';

                return $anexo;
            }
            $mapa = app(MapaLotesService::class)->datos($finca);
            $figuras = array_values(array_filter($mapa['lotes'], fn (array $figura) => $figura['lote_id'] === $lote->id));
            if (count($figuras) !== 1) {
                $anexo['pendiente'] = 'No se identificó una figura única para este lote. Revise la numeración, manzana y registros vinculados al mapa.';

                return $anexo;
            }
            $figura = $figuras[0];
            $png = $this->dibujar($mapa, $figura);
            $path = 'contratos/'.$actual->uuid.'/anexos/plano/'.hash('sha256', $png).'.png';
            if (! Storage::disk('private')->put($path, $png)) {
                throw new RuntimeException('No se pudo conservar el plano del contrato.');
            }
            $anexo['imagen'] = $path;
            $anexo['mapa_clave'] = $finca->mapa_clave;
            $anexo['figura_id'] = $figura['id'];
            $anexo['generado_en'] = now()->toIso8601String();
            $anexo['sha256'] = hash('sha256', $png);
            $anexo['version_diseno'] = self::VERSION_DISENO;
            $actual->forceFill(['anexo_plano' => $anexo])->save();
            $contrato->setAttribute('anexo_plano', $anexo);

            return $anexo;
        });
    }

    private function dibujar(array $mapa, array $seleccion): string
    {
        $imagen = imagecreatetruecolor(1800, 1200);
        try {
            $blanco = imagecolorallocate($imagen, 255, 255, 255);
            $borde = imagecolorallocate($imagen, 105, 116, 126);
            $fondo = imagecolorallocate($imagen, 237, 240, 242);
            $destacado = imagecolorallocate($imagen, 169, 197, 214);
            $tinta = imagecolorallocate($imagen, 15, 39, 55);
            imagefill($imagen, 0, 0, $blanco);
            imageantialias($imagen, true);
            $xs = array_column($seleccion['puntos'], 0);
            $ys = array_column($seleccion['puntos'], 1);
            $ancho = max($xs) - min($xs);
            $alto = max($ys) - min($ys);
            $margen = max($ancho, $alto) * .85 + 3;
            $detalle = [min($xs) - $margen, min($ys) - $margen, $ancho + 2 * $margen, $alto + 2 * $margen];
            foreach ([[$mapa['viewBox'], [30, 70, 1080, 1090], 'VISTA GENERAL'], [$detalle, [1140, 70, 630, 1090], 'DETALLE DEL LOTE']] as [$vista, $panel, $titulo]) {
                [$x, $y, $w, $h] = $panel;
                $this->rotulo($imagen, $titulo, (int) ($x + $w / 2), 35, $tinta, 2);
                // Separate canvases also clip antialiased GD lines at the panel edges.
                $lienzo = imagecreatetruecolor($w, $h);
                imagefill($lienzo, 0, 0, $blanco);
                imageantialias($lienzo, true);
                $escala = min(($w - 30) / $vista[2], ($h - 30) / $vista[3]);
                $ox = ($w - $vista[2] * $escala) / 2;
                $oy = ($h - $vista[3] * $escala) / 2;
                $punto = fn (array $p) => [(int) round($ox + ($p[0] - $vista[0]) * $escala), (int) round($oy + ($p[1] - $vista[1]) * $escala)];
                foreach ($mapa['lotes'] as $figura) {
                    $puntos = array_merge(...array_map($punto, $figura['puntos']));
                    $elegido = $figura['id'] === $seleccion['id'];
                    imagefilledpolygon($lienzo, $puntos, $elegido ? $destacado : $fondo);
                    imagepolygon($lienzo, $puntos, $borde);
                }
                foreach ($mapa['lineas'] as $linea) {
                    for ($i = 1; $i < count($linea); $i++) {
                        [$a, $b] = [$punto($linea[$i - 1]), $punto($linea[$i])];
                        imageline($lienzo, $a[0], $a[1], $b[0], $b[1], $borde);
                    }
                }
                imageantialias($lienzo, false);
                imagesetthickness($lienzo, 5);
                imagepolygon($lienzo, array_merge(...array_map($punto, $seleccion['puntos'])), $tinta);
                imagesetthickness($lienzo, 1);
                foreach ($mapa['lotes'] as $figura) {
                    $elegido = $figura['id'] === $seleccion['id'];
                    // The overview locates the selected lot; numbering belongs in the detail.
                    if (! $elegido && ($titulo === 'VISTA GENERAL' || $figura['area_plano'] * $escala * $escala < 1100)) {
                        continue;
                    }
                    [$cx, $cy] = $punto($figura['centro']);
                    $texto = (string) $figura['numero'];
                    $mitadRotulo = strlen($texto) * imagefontwidth(5);
                    if (! $elegido && ($cx < $mitadRotulo + 6 || $cx > $w - $mitadRotulo - 6 || $cy < 22 || $cy > $h - 22)) {
                        continue;
                    }
                    if ($elegido && $titulo === 'VISTA GENERAL') {
                        imageline($lienzo, $cx, $cy, $cx - 50, $cy - 25, $tinta);
                        $this->rotulo($lienzo, $texto, $cx - 65, $cy - 42, $tinta, 2);
                    } else {
                        $this->rotulo($lienzo, $texto, $cx, $cy, $tinta, $elegido ? 3 : 2);
                    }
                }
                imagecopy($imagen, $lienzo, $x, $y, 0, 0, $w, $h);
                imagedestroy($lienzo);
                imagerectangle($imagen, $x, $y, $x + $w, $y + $h, $borde);
            }
            ob_start();
            imagepng($imagen);

            return ob_get_clean();
        } finally {
            imagedestroy($imagen);
        }
    }

    private function rotulo(\GdImage $destino, string $texto, int $x, int $y, int $color, int $escala): void
    {
        $w = max(1, strlen($texto) * imagefontwidth(5));
        $h = imagefontheight(5);
        $rotulo = imagecreatetruecolor($w, $h);
        try {
            imagealphablending($rotulo, false);
            imagefill($rotulo, 0, 0, imagecolorallocatealpha($rotulo, 255, 255, 255, 127));
            imagesavealpha($rotulo, true);
            imagestring($rotulo, 5, 0, 0, $texto, $color);
            imagecopyresampled($destino, $rotulo, (int) ($x - $w * $escala / 2), (int) ($y - $h * $escala / 2), 0, 0, $w * $escala, $h * $escala, $w, $h);
        } finally {
            imagedestroy($rotulo);
        }
    }
}
