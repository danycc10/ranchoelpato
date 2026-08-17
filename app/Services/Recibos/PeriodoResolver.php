<?php

namespace App\Services\Recibos;

use App\Models\Periodo;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Resuelve el periodo mensual que le corresponde a un recibo.
 *
 * El catálogo de periodos se alimentaba a mano, así que cualquier cuota de un
 * mes que nadie había dado de alta terminaba con periodo vacío en el recibo.
 * Aquí el mes se crea la primera vez que se necesita, con el mismo formato de
 * nombre que ya usa el catálogo ("AGOSTO 2026").
 */
class PeriodoResolver
{
    public function idParaFecha($fecha): ?int
    {
        $periodo = $this->paraFecha($fecha);

        return $periodo?->id;
    }

    public function paraFecha($fecha): ?Periodo
    {
        $fecha = $this->normalizar($fecha);

        if (! $fecha) {
            return null;
        }

        return Periodo::firstOrCreate(
            [
                'tipo' => 'mensual',
                'anio' => (int) $fecha->year,
                'mes' => (int) $fecha->month,
            ],
            [
                'nombre' => $this->nombre($fecha),
            ]
        );
    }

    protected function normalizar($fecha): ?CarbonInterface
    {
        if ($fecha instanceof CarbonInterface) {
            return $fecha;
        }

        if (blank($fecha)) {
            return null;
        }

        try {
            return Carbon::parse($fecha);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function nombre(CarbonInterface $fecha): string
    {
        return mb_strtoupper(
            $fecha->locale('es')->translatedFormat('F Y'),
            'UTF-8'
        );
    }
}
