<?php

namespace App\Services\Recibos;

use App\Models\Notificacion;

/**
 * Qué recibos salieron por WhatsApp y, por lo tanto, no se imprimen.
 *
 * Son los del cliente que pidió no imprimir sus recibos y los de pagos que
 * llegaron por comprobante: a los dos se les manda el PDF. Se toma de la
 * notificación y no de la marca del cliente, porque la marca puede cambiar
 * después y al que no tiene WhatsApp se le imprimió como siempre.
 */
class RecibosPorWhatsapp
{
    /** De mejor a peor: si un envío salió, el recibo le llegó. */
    private const PRIORIDAD = ['enviado' => 3, 'en_cola' => 2, 'fallido' => 1];

    /**
     * @param  iterable<int, int|null>  $reciboIds
     * @return array<int, string> recibo_id => enviado | en_cola | fallido
     */
    public function estatusPorRecibo(iterable $reciboIds): array
    {
        $ids = collect($reciboIds)->filter()->map(fn ($id) => (int) $id)->unique()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $estatus = [];

        Notificacion::query()
            ->where('canal', 'whatsapp')
            ->where('tipo', 'recibo_pago')
            ->whereIn('payload->recibo_id', $ids->all())
            ->get(['payload', 'estatus'])
            ->each(function (Notificacion $n) use (&$estatus): void {
                $id = (int) ($n->payload['recibo_id'] ?? 0);
                $actual = $estatus[$id] ?? null;

                if ($actual === null || (self::PRIORIDAD[$n->estatus] ?? 0) > (self::PRIORIDAD[$actual] ?? 0)) {
                    $estatus[$id] = $n->estatus;
                }
            });

        return $estatus;
    }
}
