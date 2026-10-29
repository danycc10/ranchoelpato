<?php

namespace App\Services\Reportes;

use Illuminate\Support\Facades\DB;

/**
 * Por dónde se atendió al cliente de un recibo.
 *
 * Efectivo y tarjeta se cobran en persona en la oficina; transferencia y
 * depósito (OXXO) llegan por WhatsApp. Un recibo con varios pagos cuenta como
 * de oficina si cualquiera de ellos fue en efectivo o tarjeta: el cliente
 * estuvo ahí.
 */
class CanalRecibo
{
    public const OFICINA = 'oficina';

    public const WHATSAPP = 'whatsapp';

    public const NOMBRES = [self::OFICINA => 'Oficina', self::WHATSAPP => 'WhatsApp'];

    public const DESCRIPCIONES = [self::OFICINA => 'Efectivo y tarjeta', self::WHATSAPP => 'Transferencia y depósito'];

    /** Formas de pago que se cobran en persona. */
    private const EN_PERSONA = ['EFECTIVO', 'TARJETA'];

    /** SQL que vale 1 cuando el recibo de la tabla dada se atendió en la oficina. */
    public static function sqlEsOficina(string $tabla = 'recibos'): string
    {
        $ids = implode(',', self::formasDeOficina());

        if ($ids === '') {
            return '0';
        }

        return "(CASE WHEN {$tabla}.forma_pago_id IN ({$ids}) OR EXISTS ("
            ."SELECT 1 FROM recibos_pagos WHERE recibos_pagos.recibo_id = {$tabla}.id"
            ." AND recibos_pagos.deleted_at IS NULL AND recibos_pagos.forma_pago_id IN ({$ids})"
            .') THEN 1 ELSE 0 END)';
    }

    /** Deja solo los recibos del canal; sin canal no filtra. */
    public static function filtrar($query, ?string $canal, string $tabla = 'recibos')
    {
        return match ($canal) {
            self::OFICINA => $query->whereRaw(self::sqlEsOficina($tabla).' = 1'),
            self::WHATSAPP => $query->whereRaw(self::sqlEsOficina($tabla).' = 0'),
            default => $query,
        };
    }

    /** @return array<int, int> */
    public static function formasDeOficina(): array
    {
        return DB::table('formas_pago')
            ->get(['id', 'nombre'])
            ->filter(fn ($f) => collect(self::EN_PERSONA)->contains(fn ($nombre) => str_contains(mb_strtoupper((string) $f->nombre), $nombre)))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
