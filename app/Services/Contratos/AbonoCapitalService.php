<?php

namespace App\Services\Contratos;

use App\Models\AbonoCapital;
use App\Models\Contrato;
use App\Models\ContratoHistorial;
use App\Models\Cuota;
use App\Models\Promocion;
use App\Models\Recibo;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Abono extraordinario al saldo de un contrato.
 *
 * El saldo baja por el abono más la bonificación, si la hay, y se quitan
 * cuotas del final del calendario por el mismo monto: el cliente termina
 * antes, pero sigue pagando lo mismo en las mismas fechas. Lo quitado queda
 * guardado para regresarlo si el abono se anula.
 */
class AbonoCapitalService
{
    /**
     * Lo más que se puede abonar: lo que suman las cuotas que aún no reciben
     * ningún pago, sin pasarse del saldo.
     *
     * Al anular un recibo el saldo se recalcula sumando las cuotas
     * pendientes; si el abono no se descontara de las cuotas, ese recálculo
     * lo borraría.
     */
    public function maximoAplicable(Contrato $contrato): float
    {
        $recortable = (float) $this->cuotasRecortables($contrato->id)
            ->get()
            ->sum(fn (Cuota $c) => $this->pendiente($c));

        return round(max(0, min((float) $contrato->saldo_actual, $recortable)), 2);
    }

    public function aplicar(
        Contrato $contrato,
        float $abono,
        float $bonificacion = 0,
        ?Recibo $recibo = null,
        ?Promocion $promocion = null,
        ?string $nota = null,
    ): AbonoCapital {
        return DB::transaction(function () use ($contrato, $abono, $bonificacion, $recibo, $promocion, $nota) {
            $contrato = Contrato::query()->lockForUpdate()->findOrFail($contrato->id);

            $abono = round($abono, 2);
            $bonificacion = round(max(0, $bonificacion), 2);
            $total = round($abono + $bonificacion, 2);

            $saldoAnterior = round((float) $contrato->saldo_actual, 2);
            $saldoNuevo = round(max(0, $saldoAnterior - $total), 2);

            // Primero las mensualidades del final; las anualidades solo si
            // el abono alcanza para más que eso.
            $cuotas = $this->cuotasRecortables($contrato->id)
                ->orderBy('es_anualidad')
                ->orderByDesc('fecha_vencimiento')
                ->orderByDesc('numero')
                ->lockForUpdate()
                ->get();

            $restante = $total;
            $eliminadas = [];
            $reducida = null;

            foreach ($cuotas as $cuota) {
                if ($restante <= 0) {
                    break;
                }

                $pendiente = $this->pendiente($cuota);

                if ($restante >= $pendiente) {
                    $eliminadas[] = $cuota->getAttributes();
                    $cuota->delete();
                    $restante = round($restante - $pendiente, 2);

                    continue;
                }

                $reducida = ['id' => $cuota->id, 'monto_anterior' => (float) $cuota->monto];
                $cuota->update(['monto' => round((float) $cuota->monto - $restante, 2)]);
                $restante = 0;
            }

            $contrato->saldo_actual = $saldoNuevo;

            $quedanPendientes = Cuota::query()
                ->where('contrato_id', $contrato->id)
                ->whereNotIn('estatus', ['pagada', 'anulada'])
                ->exists();

            if (! $quedanPendientes) {
                $contrato->estatus = 'liquidado';
                $contrato->liquidado_at ??= now();
            }

            $contrato->save();

            $abonoCapital = AbonoCapital::create([
                'contrato_id' => $contrato->id,
                'recibo_id' => $recibo?->id,
                'promocion_id' => $bonificacion > 0 ? $promocion?->id : null,
                'monto_abono' => $abono,
                'monto_bonificacion' => $bonificacion,
                'saldo_anterior' => $saldoAnterior,
                'saldo_nuevo' => $saldoNuevo,
                'cuotas_recortadas' => ['eliminadas' => $eliminadas, 'reducida' => $reducida],
                'created_by_user_id' => auth()->id(),
            ]);

            ContratoHistorial::create([
                'contrato_id' => $contrato->id,
                'user_id' => auth()->id(),
                'tipo' => 'abono_capital',
                'antes' => ['saldo_actual' => $saldoAnterior],
                'despues' => array_filter([
                    'saldo_actual' => $saldoNuevo,
                    'monto_abono' => $abono,
                    'bonificacion' => $bonificacion ?: null,
                    'promocion' => $bonificacion > 0 ? $promocion?->nombre : null,
                    'recibo' => $recibo?->folio,
                    'cuotas_eliminadas' => count($eliminadas),
                ], fn ($valor) => $valor !== null),
                'saldo_anterior' => $saldoAnterior,
                'saldo_nuevo' => $saldoNuevo,
                'nota' => $nota ?: null,
            ]);

            return $abonoCapital;
        });
    }

    /**
     * Regresa el saldo y las cuotas que quitó el abono.
     *
     * Solo el último abono del contrato: uno anterior pudo recortar cuotas que
     * el siguiente ya volvió a tocar.
     */
    public function revertir(AbonoCapital $abono, int $userId): void
    {
        DB::transaction(function () use ($abono, $userId) {
            $abono = AbonoCapital::query()->lockForUpdate()->findOrFail($abono->id);

            if ($abono->anulado_at) {
                return;
            }

            $posterior = AbonoCapital::query()
                ->with('recibo')
                ->where('contrato_id', $abono->contrato_id)
                ->where('id', '>', $abono->id)
                ->whereNull('anulado_at')
                ->latest('id')
                ->first();

            if ($posterior) {
                throw new DomainException(
                    'Este contrato tiene un abono a capital posterior'
                    .($posterior->recibo ? ' (recibo '.$posterior->recibo->folio.')' : '')
                    .'. Anula primero ese.'
                );
            }

            $contrato = Contrato::query()->lockForUpdate()->findOrFail($abono->contrato_id);

            $recortadas = $abono->cuotas_recortadas ?? [];

            foreach ($recortadas['eliminadas'] ?? [] as $atributos) {
                DB::table('cuotas')->insert($atributos);
            }

            if (! empty($recortadas['reducida'])) {
                Cuota::query()
                    ->whereKey($recortadas['reducida']['id'])
                    ->update(['monto' => $recortadas['reducida']['monto_anterior']]);
            }

            $contrato->saldo_actual = round((float) $contrato->saldo_actual + $abono->totalAplicado(), 2);

            if ($contrato->estatus === 'liquidado' && $contrato->saldo_actual > 0) {
                $contrato->estatus = 'activo';
                $contrato->liquidado_at = null;
            }

            $contrato->save();

            $abono->update([
                'anulado_at' => now(),
                'anulado_por_user_id' => $userId,
            ]);
        });
    }

    /** Las cuotas que se pueden quitar: las que no han recibido ningún pago. */
    protected function cuotasRecortables(int $contratoId)
    {
        return Cuota::query()
            ->where('contrato_id', $contratoId)
            ->whereNotIn('estatus', ['pagada', 'anulada'])
            ->where(fn ($q) => $q->whereNull('pagado_total')->orWhere('pagado_total', 0));
    }

    protected function pendiente(Cuota $cuota): float
    {
        return round((float) $cuota->monto - (float) ($cuota->pagado_total ?? 0), 2);
    }
}
