<?php

namespace App\Services\Recibos;

use App\Models\Contrato;
use App\Models\Cuota;
use App\Models\FormaPago;
use App\Models\Promocion;
use App\Services\Contratos\AbonoCapitalService;
use Carbon\Carbon;

/**
 * Promoción «Adelanta y gana»: por cada bloque completo de abono
 * extraordinario en efectivo se bonifica un monto al saldo del terreno.
 *
 * Solo aplica a terrenos de los fraccionamientos marcados, dentro de la
 * vigencia, pagando todo en efectivo y con el cliente al corriente: el pago
 * ordinario del periodo se cobra aparte y no cuenta para la bonificación.
 */
class AdelantaYGana
{
    public function __construct(private AbonoCapitalService $abonos) {}

    /**
     * @param  array<int, int|null>  $formasPagoIds
     * @return array{promocion: ?Promocion, bonificacion: float, bloques: int, aviso: ?string}
     */
    public function evaluar(Contrato $contrato, float $abono, array $formasPagoIds, Carbon $fecha): array
    {
        $vigentes = $this->vigentes($fecha);

        if ($vigentes->isEmpty() || $contrato->tipo === 'servicio') {
            return $this->resultado();
        }

        $fraccionamientoId = $contrato->lote?->fraccionamiento_id;

        $promocion = $vigentes->first(
            fn (Promocion $p) => $p->fraccionamientos->contains('id', $fraccionamientoId)
        );

        if (! $promocion) {
            return $this->resultado(aviso: 'Este fraccionamiento no participa en «'.$vigentes->first()->nombre.'».');
        }

        if ($contrato->estatus !== 'activo') {
            return $this->resultado($promocion, aviso: 'La promoción es solo para contratos vigentes.');
        }

        if ($abono <= 0) {
            return $this->resultado($promocion);
        }

        if (! $this->todoEnEfectivo($formasPagoIds)) {
            return $this->resultado($promocion, aviso: 'La promoción es solo en efectivo: con otra forma de pago no hay bonificación.');
        }

        $pendiente = $this->pagoOrdinarioPendiente($contrato, $fecha);

        if ($pendiente) {
            return $this->resultado($promocion, aviso: 'Debe estar al corriente: falta el pago #'.$pendiente->numero
                .' del '.$pendiente->fecha_vencimiento->format('d/m/Y').'. Cóbralo primero como pago ordinario.');
        }

        $bloque = (float) $promocion->abono_por_bloque;
        $bloques = $bloque > 0 ? (int) floor(round($abono / $bloque, 6)) : 0;

        if ($bloques === 0) {
            return $this->resultado($promocion, aviso: 'Le faltan $'.number_format($bloque - $abono, 2)
                .' para completar un bloque de $'.number_format($bloque, 2).'.');
        }

        $bonificacion = $bloques * (float) $promocion->bonificacion_por_bloque;

        // La bonificación no puede pasarse de lo que queda por pagar.
        $tope = max(0, $this->abonos->maximoAplicable($contrato) - $abono);

        if ($bonificacion > $tope) {
            return $this->resultado($promocion, $tope, $bloques,
                'La bonificación se limita a $'.number_format($tope, 2).' porque es lo que le queda por pagar.');
        }

        return $this->resultado($promocion, $bonificacion, $bloques);
    }

    /**
     * La primera cuota que debería estar pagada antes del abono: cualquier
     * mensualidad que vence hasta el fin de la semana o del mes en curso, o
     * una anualidad ya vencida.
     */
    public function pagoOrdinarioPendiente(Contrato $contrato, Carbon $fecha): ?Cuota
    {
        $finPeriodo = $contrato->frecuencia === 'semanal'
            ? $fecha->copy()->endOfWeek(Carbon::SUNDAY)
            : $fecha->copy()->endOfMonth();

        return Cuota::query()
            ->where('contrato_id', $contrato->id)
            ->whereNotIn('estatus', ['pagada', 'anulada'])
            ->where(fn ($q) => $q
                ->where(fn ($q) => $q->where('es_anualidad', false)->whereDate('fecha_vencimiento', '<=', $finPeriodo))
                ->orWhere(fn ($q) => $q->where('es_anualidad', true)->whereDate('fecha_vencimiento', '<=', $fecha)))
            ->orderBy('fecha_vencimiento')
            ->orderBy('numero')
            ->first();
    }

    protected function vigentes(Carbon $fecha)
    {
        return Promocion::query()
            ->with('fraccionamientos:id')
            ->where('tipo', Promocion::ADELANTA_Y_GANA)
            ->where('activa', true)
            ->where(fn ($q) => $q->whereNull('fecha_inicio')->orWhereDate('fecha_inicio', '<=', $fecha))
            ->where(fn ($q) => $q->whereNull('fecha_fin')->orWhereDate('fecha_fin', '>=', $fecha))
            ->orderBy('id')
            ->get();
    }

    /** @param  array<int, int|null>  $formasPagoIds */
    protected function todoEnEfectivo(array $formasPagoIds): bool
    {
        $ids = collect($formasPagoIds)->filter()->map(fn ($id) => (int) $id)->unique();

        if ($ids->isEmpty()) {
            return false;
        }

        $nombres = FormaPago::query()->whereIn('id', $ids)->pluck('nombre');

        return $nombres->count() === $ids->count()
            && $nombres->every(fn ($nombre) => str_contains(mb_strtoupper((string) $nombre), 'EFECTIVO'));
    }

    protected function resultado(?Promocion $promocion = null, float $bonificacion = 0, int $bloques = 0, ?string $aviso = null): array
    {
        return [
            'promocion' => $promocion,
            'bonificacion' => round($bonificacion, 2),
            'bloques' => $bloques,
            'aviso' => $aviso,
        ];
    }
}
