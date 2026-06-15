<?php

namespace App\Services\Contratos;

use App\Models\Contrato;
use App\Models\Cuota;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ContratoCuotasReprogramarService
{
    /**
     * Reprograma fechas de cuotas en cadena a partir de una nueva fecha inicial.
     *
     * @param  Contrato $contrato
     * @param  Carbon   $nuevaFechaPrimera  (fecha_vencimiento para la primera cuota)
     * @param  bool     $soloPendientes     true = solo pendientes; false = todas
     */
    public static function reprogramar(Contrato $contrato, Carbon $nuevaFechaPrimera, bool $soloPendientes = false): void
    {
        DB::transaction(function () use ($contrato, $nuevaFechaPrimera, $soloPendientes) {

            // 🔒 Bloquea el contrato para evitar concurrencia
            $contrato = Contrato::query()
                ->whereKey($contrato->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Trae cuotas en orden
            $cuotasQuery = Cuota::query()
                ->where('contrato_id', $contrato->id)
                ->orderBy('numero');

            if ($soloPendientes) {
                $cuotasQuery->where('estatus', '!=', 'pagada');
            }

            $cuotas = $cuotasQuery->get();
            if ($cuotas->isEmpty()) return;

            $fecha = $nuevaFechaPrimera->copy()->startOfDay();
            $frecuencia = (string) $contrato->frecuencia;

            // ✅ Alinear día_semana / dia_mes del contrato con la nueva primera fecha
            if ($frecuencia === 'semanal') {
                $contrato->dia_semana = (int) $fecha->dayOfWeekIso; // 1..7
            } else {
                // tu sistema trabaja con 1..28
                $contrato->dia_mes = max(1, min(28, (int) $fecha->day));
            }
            $contrato->save();

            foreach ($cuotas as $idx => $cuota) {

                // 1ra cuota = fecha indicada
                // siguientes = cadena por frecuencia
                if ($idx > 0) {
                    if ($frecuencia === 'semanal') {
                        $fecha = $fecha->copy()->addWeek();
                    } else {
                        $fecha = $fecha->copy()->addMonthNoOverflow();
                    }
                }

                $cuota->fecha_vencimiento = $fecha->toDateString();
                $cuota->save();
            }
        });
    }
}