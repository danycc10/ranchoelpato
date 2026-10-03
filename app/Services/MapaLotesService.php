<?php

namespace App\Services;

use App\Models\Fraccionamiento;
use App\Models\Lote;

class MapaLotesService
{
    public const MAPAS = ['finca-escondida' => 'Finca Escondida'];

    public function datos(Fraccionamiento $fraccionamiento): array
    {
        abort_unless(isset(self::MAPAS[$fraccionamiento->mapa_clave]), 404);
        $plano = json_decode(file_get_contents(resource_path('mapas/'.$fraccionamiento->mapa_clave.'.json')), true, 512, JSON_THROW_ON_ERROR);
        // Cada registro conserva su propietario; compartir plano combina únicamente la vista.
        $fincas = Fraccionamiento::with('propietario')->where('mapa_clave', $fraccionamiento->mapa_clave)->orderBy('nombre')->get();
        $todos = Lote::with('fraccionamiento.propietario')->whereIn('fraccionamiento_id', $fincas->modelKeys())->get();
        $registrados = $todos->groupBy(fn (Lote $lote) => $this->numero($lote->lote));
        $vinculados = [];
        foreach ($plano['lotes'] as &$figura) {
            $candidatos = $registrados->get($this->numero($figura['numero']), collect());
            $lote = $candidatos->count() === 1 ? $candidatos->first() : null;
            $figura['estatus'] = $lote?->estatus ?? 'sin_vincular';
            $figura['manzana'] = $lote?->manzana;
            $figura['clave'] = $lote?->clave;
            $figura['fraccionamiento'] = $lote?->fraccionamiento?->nombre;
            $figura['propietario'] = $lote?->fraccionamiento?->propietario?->nombre;
            $figura['area_registrada'] = $lote?->area_m2;
            $figura['precio_lista'] = $lote?->precio_lista;
            $figura['vinculado'] = (bool) $lote;
            $figura['aviso'] = $candidatos->count() > 1
                ? 'Número repetido entre los registros del mapa: '.$candidatos->map(fn (Lote $candidato) => $candidato->fraccionamiento->nombre.' (manzana '.$candidato->manzana.')')->implode(', ').'. Revisar la vinculación.'
                : ($lote ? null : 'Este lote del plano no tiene registro en las fincas vinculadas. Su disponibilidad no está confirmada.');
            $figura['catalogo_url'] = $lote ? route('admin.lotes', [
                'fraccionamientoFilter' => $lote->fraccionamiento_id,
                'manzanaFilter' => $lote->manzana,
                'loteFilter' => $lote->lote,
            ]) : null;
            if ($lote) {
                $vinculados[] = $lote->id;
            }
        }
        unset($figura);

        return $plano + [
            'fraccionamiento' => $fraccionamiento->nombre,
            'fincas' => $fincas->map(fn (Fraccionamiento $finca) => [
                'nombre' => $finca->nombre,
                'propietario' => $finca->propietario?->nombre,
            ])->all(),
            'vinculados' => count($vinculados),
            'registros_sin_figura' => $todos->whereNotIn('id', $vinculados)->count(),
            'actualizado' => now()->toIso8601String(),
        ];
    }

    private function numero(string $valor): string
    {
        $valor = trim($valor);

        return ctype_digit($valor) ? (string) ((int) $valor) : mb_strtoupper($valor);
    }
}
