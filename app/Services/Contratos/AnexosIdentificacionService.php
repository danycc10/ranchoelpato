<?php

namespace App\Services\Contratos;

use App\Models\Contrato;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use RuntimeException;

class AnexosIdentificacionService
{
    /** Solo lee el catálogo aprobado; nunca las capturas pendientes de una liga. */
    public function estado(Contrato $contrato): array
    {
        $contrato->loadMissing(['cliente', 'lote.fraccionamiento.propietario']);
        $resultado = [];

        foreach (['A' => 'vendedor', 'B' => 'comprador'] as $letra => $parte) {
            $persona = $parte === 'vendedor' ? $contrato->lote?->fraccionamiento?->propietario : $contrato->cliente;
            $clave = $letra.'-'.($persona?->id ?? 0);
            $guardado = $contrato->anexos_identificacion[$clave] ?? null;
            $anexo = $guardado ?? [
                'clave' => $clave,
                'nombre' => $contrato->{$parte.'_nombre_legal'} ?: ($persona?->nombre_legal ?: ($persona?->nombre_completo ?: $persona?->nombre)),
                'tipo' => $persona?->identificacion_tipo ?: 'INE',
                'numero' => $persona?->identificacion_numero,
                'folio' => $contrato->folio_contrato ?: $contrato->uuid,
                'lote' => $contrato->lote?->lote,
                'manzana' => $contrato->lote?->manzana,
                'proyecto' => $contrato->lote?->fraccionamiento?->nombre,
                'imagenes' => [],
            ];

            if (! $guardado) {
                // Un par del contrato tiene prioridad; no mezclar caras de dos identificaciones.
                $usaContrato = filled($contrato->{$parte.'_ine_frente'}) || filled($contrato->{$parte.'_ine_reverso'});
                $requiereReverso = mb_strtoupper($anexo['tipo']) === 'INE';
                $contratoCompleto = $this->imagenValida($contrato->credenciales_disk ?: 'private', $contrato->{$parte.'_ine_frente'})
                    && (! $requiereReverso || $this->imagenValida($contrato->credenciales_disk ?: 'private', $contrato->{$parte.'_ine_reverso'}));
                $catalogoCompleto = $this->imagenValida($persona?->documentos_disk ?: 'private', $persona?->ine_frente)
                    && (! $requiereReverso || $this->imagenValida($persona?->documentos_disk ?: 'private', $persona?->ine_reverso));
                // Una captura aprobada puede completar contratos que nacieron sin ambas caras.
                if (! $contratoCompleto && $catalogoCompleto) {
                    $usaContrato = false;
                }
                foreach (['frente', 'reverso'] as $lado) {
                    $anexo['imagenes'][$lado] = [
                        'path' => $usaContrato ? $contrato->{$parte.'_ine_'.$lado} : $persona?->{'ine_'.$lado},
                        'disk' => $usaContrato ? ($contrato->credenciales_disk ?: 'private') : ($persona?->documentos_disk ?: 'private'),
                    ];
                }
            }

            $faltantes = [];
            foreach ($anexo['imagenes'] as $lado => $imagen) {
                $path = $imagen['path'];
                $requerida = $lado === 'frente' || mb_strtoupper($anexo['tipo']) === 'INE' || filled($path);
                if ($requerida && ! $this->imagenValida($imagen['disk'], $path)) {
                    $faltantes[] = $lado;
                }
            }

            $resultado[$letra] = $anexo + ['conservado' => (bool) $guardado];
            $resultado[$letra]['faltantes'] = $faltantes;
        }

        return $resultado;
    }

    private function imagenValida(string $disk, ?string $path): bool
    {
        return $path && Storage::disk($disk)->exists($path)
            && (bool) @getimagesize(Storage::disk($disk)->path($path));
    }

    public function completos(Contrato $contrato): bool
    {
        foreach ($this->estado($contrato) as $anexo) {
            if ($anexo['faltantes']) {
                return false;
            }
        }

        return true;
    }

    /** Conserva una copia independiente por parte, al generar por primera vez su anexo completo. */
    public function preparar(Contrato $contrato): array
    {
        return DB::transaction(function () use ($contrato) {
            $actual = Contrato::query()->lockForUpdate()->findOrFail($contrato->id);
            $anexos = $this->estado($actual);
            $guardados = $actual->anexos_identificacion ?? [];

            foreach ($anexos as $letra => &$anexo) {
                if ($anexo['conservado'] || $anexo['faltantes']) {
                    continue;
                }

                foreach ($anexo['imagenes'] as $lado => &$imagen) {
                    if (! $imagen['path']) {
                        continue;
                    }
                    // PNG también permite incorporar las fotos WebP al Word sin depender de Office.
                    // El lector respeta la orientación EXIF de las fotografías del teléfono.
                    $png = (string) (new ImageManager(new Driver))
                        ->read(Storage::disk($imagen['disk'])->path($imagen['path']))->toPng();
                    $path = 'contratos/'.$actual->uuid.'/anexos/'.$anexo['clave'].'/'.$lado.'-'.hash('sha256', $png).'.png';
                    if (! Storage::disk('private')->put($path, $png)) {
                        throw new RuntimeException('No se pudo conservar la identificación del anexo '.$letra.'.');
                    }
                    $imagen = ['disk' => 'private', 'path' => $path];
                }
                unset($imagen);
                $anexo['generado_en'] = now()->toIso8601String();
                $guardados[$anexo['clave']] = array_diff_key($anexo, array_flip(['conservado', 'faltantes']));
                $anexo['conservado'] = true;
            }
            unset($anexo);

            if ($guardados !== ($actual->anexos_identificacion ?? [])) {
                $actual->forceFill(['anexos_identificacion' => $guardados])->save();
            }
            $contrato->setAttribute('anexos_identificacion', $guardados);

            return $anexos;
        });
    }
}
