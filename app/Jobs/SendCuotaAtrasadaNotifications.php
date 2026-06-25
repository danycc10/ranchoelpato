<?php

namespace App\Jobs;

use App\Models\Cuota;
use App\Notifications\CuotaAtrasadaNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendCuotaAtrasadaNotifications implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public array $cuotaIds) {}

    public function handle(): void
    {
        Log::info('SendCuotaAtrasadaNotifications iniciado', [
            'cuota_ids' => $this->cuotaIds,
        ]);

        $cuotas = Cuota::query()
            ->with(['contrato.cliente'])
            ->whereIn('id', $this->cuotaIds)
            ->get();

        Log::info('Cuotas encontradas', [
            'total' => $cuotas->count(),
        ]);

        foreach ($cuotas as $cuota) {

            try {

                $cliente = $cuota->contrato?->cliente;

                if (! $cliente) {

                    Log::warning('Cuota sin cliente', [
                        'cuota_id' => $cuota->id,
                    ]);

                    continue;
                }

                $recargo = $this->calcularRecargo($cuota);

                Log::info('Procesando cuota atrasada', [
                    'cuota_id' => $cuota->id,
                    'cliente_id' => $cliente->id,
                    'correo' => $cliente->correo,
                    'telefono' => $cliente->telefono,
                    'recargo' => $recargo,
                ]);

                /*
                |--------------------------------------------------------------------------
                | CORREO
                |--------------------------------------------------------------------------
                */
                if (! empty($cliente->correo)) {

                    Log::info('Enviando notificación correo', [
                        'correo' => $cliente->correo,
                    ]);

                    $cliente->notify(
                        new CuotaAtrasadaNotification($cuota, $recargo)
                    );

                    Log::info('Notificación enviada OK', [
                        'correo' => $cliente->correo,
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | WHATSAPP
                |--------------------------------------------------------------------------
                */
                if (! empty($cliente->telefono)) {

                    Log::info('Cliente tiene teléfono', [
                        'telefono' => $cliente->telefono,
                    ]);

                    // app(\App\Services\WhatsappService::class)->sendAtraso(...)
                }

            } catch (\Throwable $e) {

                Log::error('Error enviando cuota atrasada', [
                    'cuota_id' => $cuota->id ?? null,
                    'error' => $e->getMessage(),
                    'archivo' => $e->getFile(),
                    'linea' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }
    }

    protected function calcularRecargo(Cuota $cuota): float
    {
        return round(((float) $cuota->monto) * 0.05, 2);
    }
}
