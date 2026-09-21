<?php

namespace App\Services\Impresion;

use App\Models\PrintAgent;
use App\Models\Recibo;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Manda un ticket a la impresora del cajero, si tiene su equipo emparejado.
 *
 * Quien no usa agente no recibe aviso: caer al navegador es lo normal para él,
 * no un error. Solo se avisa cuando sí tiene equipo y aun así no se pudo.
 */
class EnvioDeTicket
{
    public function __construct(protected PrintJobService $cola) {}

    /**
     * @return array{enviado: bool, aviso: string|null}
     */
    public function enviar(?User $usuario, Recibo $recibo): array
    {
        if (! $usuario || ! PrintAgent::deUsuario($usuario)) {
            return ['enviado' => false, 'aviso' => null];
        }

        try {
            $this->cola->queueRecibo($usuario, $recibo);
        } catch (Throwable $e) {
            Log::warning('No se pudo encolar el ticket en el agente local', [
                'recibo_id' => $recibo->id,
                'user_id' => $usuario->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'enviado' => false,
                'aviso' => $e->getMessage().' Se abrirá el ticket para imprimir desde el navegador.',
            ];
        }

        return ['enviado' => true, 'aviso' => 'Ticket enviado a tu impresora.'];
    }
}
