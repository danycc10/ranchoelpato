<?php

namespace App\Services\Contratos;

use App\Models\Contrato;
use App\Models\Cuota;
use App\Models\Pago;
use App\Models\Recibo;
use Illuminate\Support\Facades\DB;

class CuotaPagoRollbackService
{
    public static function anularPagoReciboDeCuota(
        Contrato $contrato,
        int $cuotaId,
        int $userId,
        string $motivo = 'Anulación desde contrato'
    ): void {
        DB::transaction(function () use ($contrato, $cuotaId, $userId, $motivo) {

            $ahora = now();

            $contrato = Contrato::query()
                ->whereKey($contrato->id)
                ->lockForUpdate()
                ->firstOrFail();

            $cuota = Cuota::query()
                ->where('id', $cuotaId)
                ->where('contrato_id', $contrato->id)
                ->lockForUpdate()
                ->firstOrFail();

            $pagos = Pago::query()
                ->where('contrato_id', $contrato->id)
                ->where('cuota_id', $cuota->id)
                ->lockForUpdate()
                ->get();

            $reciboIdsPagos = $pagos->pluck('recibo_id')
                ->filter()
                ->unique()
                ->values()
                ->all();

            $reciboIdsHistoricos = Recibo::query()
                ->where('contrato_id', $contrato->id)
                ->where('cuota_id', $cuota->id)
                ->where('es_historico', true)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->pluck('id')
                ->all();

            $reciboIds = collect($reciboIdsPagos)
                ->merge($reciboIdsHistoricos)
                ->filter()
                ->unique()
                ->values()
                ->all();

            // ✅ Anular pagos
            foreach ($pagos as $p) {
                $p->anulado_at = $ahora;
                $p->anulado_por_user_id = $userId;
                $p->anulado_motivo = $motivo;
                $p->save();

                $p->delete(); // soft delete
            }

            // ✅ Anular recibos relacionados
            if (! empty($reciboIds)) {
                $recibos = Recibo::query()
                    ->whereIn('id', $reciboIds)
                    ->lockForUpdate()
                    ->get();

                foreach ($recibos as $r) {
                    $r->anulado_at = $ahora;
                    $r->anulado_por_user_id = $userId;
                    $r->anulado_motivo = $motivo;
                    $r->es_historico = false;      // ✅ importante
                    $r->tipo_movimiento = 'anulado';
                    $r->save();

                    $r->delete(); // soft delete
                }
            }

            // ✅ Reset cuota
            $cuota->pagado_total = 0;
            $cuota->recargo_aplicado = 0;
            $cuota->estatus = 'pendiente';
            $cuota->origen_pago = null;
            $cuota->observaciones_pago = null;
            $cuota->save();

            // ✅ Recalcular saldo REAL desde cuotas
            $saldo = (float) Cuota::query()
                ->where('contrato_id', $contrato->id)
                ->get()
                ->sum(function ($q) {
                    $monto = (float) ($q->monto ?? 0);
                    $pagado = (float) ($q->pagado_total ?? 0);
                    $condonado = (float) ($q->condonado_total ?? 0);

                    return max(0, $monto - $pagado - $condonado);
                });

            $saldo = round($saldo, 2);

            $contrato->saldo_actual = $saldo;
            $contrato->estatus = ($saldo <= 0.00001) ? 'liquidado' : 'activo';
            $contrato->liquidado_at = ($saldo <= 0.00001)
                ? ($contrato->liquidado_at ?: now())
                : null;
            $contrato->save();
        });
    }
}
