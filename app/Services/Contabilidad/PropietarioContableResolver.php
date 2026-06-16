<?php

namespace App\Services\Contabilidad;

use App\Models\Fraccionamiento;
use App\Models\TipoCobro;
use App\Models\TipoCobroPropietarioConfig;

class PropietarioContableResolver
{
    public function resolver(
        int $tipoCobroId,
        ?int $fraccionamientoId = null,
        ?int $formaPagoId = null
    ): ?int {
        $config = TipoCobroPropietarioConfig::query()
            ->where('activo', true)
            ->where('tipo_cobro_id', $tipoCobroId)
            ->where(function ($query) use ($fraccionamientoId) {
                $query->where('fraccionamiento_id', $fraccionamientoId)
                    ->orWhereNull('fraccionamiento_id');
            })
            ->where(function ($query) use ($formaPagoId) {
                $query->where('forma_pago_id', $formaPagoId)
                    ->orWhereNull('forma_pago_id');
            })
            ->orderByRaw('
                CASE 
                    WHEN fraccionamiento_id IS NOT NULL AND forma_pago_id IS NOT NULL THEN 3
                    WHEN fraccionamiento_id IS NOT NULL AND forma_pago_id IS NULL THEN 2
                    WHEN fraccionamiento_id IS NULL AND forma_pago_id IS NOT NULL THEN 1
                    ELSE 0
                END DESC
            ')
            ->orderByDesc('prioridad')
            ->first();

        if ($config) {
            return $config->propietario_id;
        }

        $tipoCobro = TipoCobro::find($tipoCobroId);

        if ($tipoCobro?->propietario_contable_id) {
            return $tipoCobro->propietario_contable_id;
        }

        if ($fraccionamientoId) {
            return Fraccionamiento::find($fraccionamientoId)?->propietario_id;
        }

        return null;
    }
}
