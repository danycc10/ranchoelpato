<?php

namespace App\Services;

use App\Models\Fraccionamiento;
use App\Models\Lote;

class MapaLotesService
{
    public const MAPAS = [
        'finca-escondida' => 'Finca Escondida',
        'ballesteros' => 'Ballesteros',
        'finca-del-rio' => 'Finca del Río',
        'finca-espinoza' => 'Finca Espinoza',
        'finca-del-norte' => 'Finca del Norte',
        'finca-miranda' => 'Finca Miranda',
        'reyes' => 'Reyes',
    ];

    public function datos(Fraccionamiento $fraccionamiento): array
    {
        abort_unless(isset(self::MAPAS[$fraccionamiento->mapa_clave]), 404);
        $plano = json_decode(file_get_contents(resource_path('mapas/'.$fraccionamiento->mapa_clave.'.json')), true, 512, JSON_THROW_ON_ERROR);
        // Cada registro conserva su propietario; compartir plano combina únicamente la vista.
        $fincas = Fraccionamiento::with('propietario')->where('mapa_clave', $fraccionamiento->mapa_clave)->orderBy('nombre')->get();
        $todos = Lote::with('fraccionamiento.propietario')->whereIn('fraccionamiento_id', $fincas->modelKeys())->get();
        $registrados = $todos->groupBy(fn (Lote $lote) => $this->numero($lote->lote));
        $figurasPorClave = collect($plano['lotes'])->countBy(fn (array $figura) => $this->claveFigura($figura));
        $vinculados = [];
        foreach ($plano['lotes'] as &$figura) {
            $figura['id'] ??= $figura['numero'];
            $candidatos = $registrados->get($this->numero($figura['numero']), collect());
            if (isset($figura['manzana_plano'])) {
                $candidatos = $candidatos->filter(fn (Lote $candidato) => $this->numero($candidato->manzana) === $this->numero($figura['manzana_plano']));
            }
            // Un registro tampoco puede colorear dos figuras con el mismo número.
            $figuraAmbigua = $figurasPorClave[$this->claveFigura($figura)] > 1;
            $lote = ! $figuraAmbigua && $candidatos->count() === 1 ? $candidatos->first() : null;
            $figura['estatus'] = $lote?->estatus ?? 'sin_vincular';
            $figura['manzana'] = $lote?->manzana ?? $figura['manzana_plano'] ?? null;
            $figura['clave'] = $lote?->clave;
            $figura['fraccionamiento'] = $lote?->fraccionamiento?->nombre;
            $figura['propietario'] = $lote?->fraccionamiento?->propietario?->nombre;
            $figura['area_registrada'] = $lote?->area_m2;
            $figura['precio_lista'] = $lote?->precio_lista;
            $figura['vinculado'] = (bool) $lote;
            $figura['aviso'] = $candidatos->count() > 1
                ? 'Número repetido entre los registros del mapa: '.$candidatos->map(fn (Lote $candidato) => $candidato->fraccionamiento->nombre.' (manzana '.$candidato->manzana.')')->implode(', ').'. Revisar la vinculación.'
                : ($lote ? null : 'Este lote del plano no tiene registro en las fincas vinculadas. Su disponibilidad no está confirmada.');
            if ($figuraAmbigua) {
                $figura['aviso'] = 'Este número aparece en varias figuras del plano. Falta identificar a qué fraccionamiento o etapa pertenece cada figura; su disponibilidad no está confirmada.';
            }
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
        // El catálogo admite "17 A" y el CAD usa "17A".
        $valor = preg_replace('/\s+/u', '', mb_strtoupper(trim($valor)));

        return preg_replace('/^0+(?=\d)/', '', $valor);
    }

    private function claveFigura(array $figura): string
    {
        return $this->numero($figura['manzana_plano'] ?? '').'|'.$this->numero($figura['numero']);
    }
}
