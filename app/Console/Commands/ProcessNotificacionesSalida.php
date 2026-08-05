<?php

namespace App\Console\Commands;

use App\Models\Notificacion;
use App\Services\Notificaciones\ProcesarNotificacionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessNotificacionesSalida extends Command
{
    protected $signature = 'notificaciones:procesar {--limit=50}';

    protected $description = 'Procesa notificaciones en cola (correo / whatsapp) y marca enviado/fallido';

    public function handle(ProcesarNotificacionService $procesador): int
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

        $enviadas = 0;
        $fallidas = 0;

        foreach ($items as $n) {
            try {
                $procesador->procesar($n);

                $enviadas++;

            } catch (\Throwable $e) {
                $fallidas++;

                $this->error("Error en notificación #{$n->id}: {$e->getMessage()}");
            }
        }

        $this->info("Procesadas: {$items->count()} (enviadas: {$enviadas}, fallidas: {$fallidas})");

        return self::SUCCESS;
    }
}
