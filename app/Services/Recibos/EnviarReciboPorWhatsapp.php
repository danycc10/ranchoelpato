<?php

namespace App\Services\Recibos;

use App\Models\Notificacion;
use App\Models\Recibo;
use App\Services\Notificaciones\ProcesarNotificacionService;
use Illuminate\Support\Facades\DB;

/**
 * Manda el PDF de un recibo por WhatsApp.
 *
 * Lo usan el pago que llega por comprobante y el cliente que no quiere su
 * recibo en papel. Queda como notificación en cola y se intenta mandar en
 * cuanto se confirma la transacción; si WhatsApp falla, se queda en cola y el
 * programador lo reintenta.
 */
class EnviarReciboPorWhatsapp
{
    public function encolar(Recibo $recibo, string $telefono): Notificacion
    {
        $notificacion = Notificacion::create([
            'canal' => 'whatsapp',
            'tipo' => 'recibo_pago',
            'cliente_id' => $recibo->cliente_id,
            'contrato_id' => $recibo->contrato_id,
            'cuota_id' => $recibo->cuota_id,
            'destino' => $telefono,
            'payload' => [
                'recibo_id' => $recibo->id,
                'folio' => $recibo->folio,
                'mensaje' => 'Recibimos tu pago. Adjuntamos tu recibo '.$recibo->folio
                    .' por $'.number_format((float) $recibo->monto, 2).'. ¡Gracias!',
            ],
            'estatus' => 'en_cola',
        ]);

        $notificacionId = (int) $notificacion->id;

        DB::afterCommit(function () use ($notificacionId): void {
            $pendiente = Notificacion::find($notificacionId);

            if (! $pendiente || $pendiente->estatus !== 'en_cola') {
                return;
            }

            app(ProcesarNotificacionService::class)->procesar(
                $pendiente,
                mantenerEnColaSiFalla: true,
            );
        });

        return $notificacion;
    }
}
