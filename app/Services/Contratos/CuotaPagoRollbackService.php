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

            $reciboIdsPagos = Pago::query()
                ->where('contrato_id', $contrato->id)
                ->where('cuota_id', $cuota->id)
                ->lockForUpdate()
                ->pluck('recibo_id')
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

            // Un recibo puede cubrir varias cuotas, así que la anulación es
            // todo o nada: se arrastran todas las cuotas de esos recibos para
            // no dejar cuotas pagadas colgando de un recibo anulado.
            $pagos = Pago::query()
                ->where('contrato_id', $contrato->id)
                ->where(function ($query) use ($cuota, $reciboIds) {
                    $query->where('cuota_id', $cuota->id);

                    if (! empty($reciboIds)) {
                        $query->orWhereIn('recibo_id', $reciboIds);
                    }
                })
                ->lockForUpdate()
                ->get();

            $cuotaIdsAfectadas = $pagos->pluck('cuota_id')
                ->filter()
                ->merge([$cuota->id])
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

            // ✅ Reset de todas las cuotas que cubrían esos recibos
            $cuotasAfectadas = Cuota::query()
                ->where('contrato_id', $contrato->id)
                ->whereIn('id', $cuotaIdsAfectadas)
                ->lockForUpdate()
                ->get();

            foreach ($cuotasAfectadas as $c) {
                $c->pagado_total = 0;
                $c->recargo_aplicado = 0;
                $c->estatus = 'pendiente';
                $c->origen_pago = null;
                $c->observaciones_pago = null;
                $c->save();
            }

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
