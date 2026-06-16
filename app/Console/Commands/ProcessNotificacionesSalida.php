<?php

namespace App\Console\Commands;

use App\Mail\CuotaAtrasadaMail;
use App\Models\Notificacion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ProcessNotificacionesSalida extends Command
{
    protected $signature = 'notificaciones:procesar {--limit=50}';

    protected $description = 'Procesa notificaciones en cola (correo / whatsapp) y marca enviado/fallido';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');

        $items = Notificacion::query()
            ->where('estatus', 'en_cola')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        Log::info('Notificaciones encontradas en cola', [
            'total' => $items->count(),
            'ids' => $items->pluck('id')->toArray(),
        ]);

        if ($items->isEmpty()) {
            $this->info('No hay notificaciones en cola.');

            return self::SUCCESS;
        }

        foreach ($items as $n) {
            try {

                $payload = is_array($n->payload)
                    ? $n->payload
                    : (array) $n->payload;

                if ($n->canal === 'correo') {

                    Mail::to($n->destino)
                        ->send(new CuotaAtrasadaMail($payload));

                }

                if ($n->canal === 'whatsapp') {

                }

                $n->estatus = 'enviado';
                $n->enviado_en = now();
                $n->error = null;
                $n->save();

            } catch (\Throwable $e) {
                $n->estatus = 'fallido';
                $n->error = mb_substr($e->getMessage(), 0, 255);
                $n->save();

                Log::error('Error procesando notificación', [
                    'id' => $n->id ?? null,
                    'canal' => $n->canal ?? null,
                    'destino' => $n->destino ?? null,
                    'error' => $e->getMessage(),
                    'archivo' => $e->getFile(),
                    'linea' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);

                $this->error("Error en notificación #{$n->id}: {$e->getMessage()}");
            }
        }

        $this->info("Procesadas: {$items->count()}");

        return self::SUCCESS;
    }
}
