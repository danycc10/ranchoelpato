<?php

namespace App\Exports\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Un recibo puede cubrir varias cuotas (cuando el cliente abona varias de un
 * jalón). Los reportes clasifican el dinero por la fecha de vencimiento de la
 * cuota, así que no basta con `recibos.cuota_id`: hay que repartir el recibo
 * entre las cuotas que realmente cubrió, que es lo que guarda la tabla `pagos`.
 *
 * Para un recibo de una sola cuota la proporción es 1 y el resultado es
 * idéntico al de antes, así que los reportes históricos no cambian.
 */
class ReciboCuotaSplit
{
    /** Alias de la tabla derivada con el monto por (recibo, cuota). */
    public const ALIAS = 'recibo_cuota';

    /** Alias de la tabla derivada con el total pagado por recibo. */
    public const ALIAS_TOTAL = 'recibo_cuota_total';

    /**
     * Aplica los joins necesarios y deja disponible la cuota correcta en
     * `cuotas`. Sustituye al `leftJoin('cuotas', 'cuotas.id', '=', 'recibos.cuota_id')`.
     */
    public static function aplicar(Builder $query): Builder
    {
        return $query
            ->leftJoinSub(
                self::montoPorCuota(),
                self::ALIAS,
                fn ($join) => $join->on(self::ALIAS.'.recibo_id', '=', 'recibos.id')
            )
            ->leftJoinSub(
                self::montoPorRecibo(),
                self::ALIAS_TOTAL,
                fn ($join) => $join->on(self::ALIAS_TOTAL.'.recibo_id', '=', 'recibos.id')
            )
            ->leftJoin('cuotas', function ($join) {
                $join->on('cuotas.id', '=', DB::raw(
                    'COALESCE('.self::ALIAS.'.cuota_id, recibos.cuota_id)'
                ));
            });
    }

    /**
     * Expresión para el monto de la fila, repartido según la cuota.
     *
     * @param  string  $montoBase  columna con el monto a repartir (normalmente
     *                             `recibos_pagos.monto`, una forma de pago).
     */
    public static function monto(string $montoBase): string
    {
        return $montoBase.' * COALESCE('
            .self::ALIAS.'.monto_cuota / NULLIF('.self::ALIAS_TOTAL.'.monto_total, 0)'
            .', 1)';
    }

    protected static function montoPorCuota(): Builder
    {
        return self::pagosVigentes()
            ->selectRaw('pagos.recibo_id, pagos.cuota_id, SUM(pagos.monto) as monto_cuota')
            ->groupBy('pagos.recibo_id', 'pagos.cuota_id');
    }

    protected static function montoPorRecibo(): Builder
    {
        return self::pagosVigentes()
            ->selectRaw('pagos.recibo_id, SUM(pagos.monto) as monto_total')
            ->groupBy('pagos.recibo_id');
    }

    protected static function pagosVigentes(): Builder
    {
        return DB::table('pagos')
            ->whereNotNull('pagos.recibo_id')
            ->whereNull('pagos.deleted_at')
            ->whereNull('pagos.anulado_at');
    }
}
