<?php

namespace App\Livewire\Admin\ContratosServicios;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Contrato;
use App\Models\ContratoHistorial;
use App\Models\Cuota;
use App\Models\Recibo;
use App\Services\Contratos\ContratoCuotasReprogramarService;
use App\Services\Contratos\CuotaPagoRollbackService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Show extends Component
{
 

    public Contrato $contrato;
   

    // ✅ Reprogramar
    public bool $showReprogramar = false;
    public ?string $nuevaFechaPrimerPago = null;

    // ✅ Anular pago/recibo
    public bool $showAnularPago = false;
    public ?int $cuotaAnularId = null;
    public string $motivoAnulacion = 'Corrección de pago/recibo';

    // ✅ Pagada histórica
    public bool $showMarcarPagada = false;
    public ?int $cuotaIdMarcarPagada = null;
    public string $observacionPagoHistorico = 'Pago histórico registrado fuera del sistema.';

    public function mount(Contrato $contrato): void
    {
        if (($contrato->tipo ?? 'terreno') !== 'servicio') {
            abort(404);
        }

        $this->loadContrato($contrato);
    }

    private function loadContrato(Contrato $contrato): void
    {
        $this->contrato = $contrato->load([
            'cliente',
            'lote',
            'contratoBase.cliente',
            'contratoBase.lote',
            'historial' => fn ($q) => $q->with('user')->latest('id')->limit(100),
        ]);
    }

    public function getServicioNombreProperty(): string
    {
        return ($this->contrato->servicio_tipo ?? '') === 'electricidad'
            ? 'Electricidad'
            : 'Agua';
    }

    public function getBaseProperty(): ?Contrato
    {
        return $this->contrato->contratoBase;
    }

    protected function cuotasQuery()
    {
        return Cuota::query()
            ->where('contrato_id', $this->contrato->id)
            ->orderBy('numero');
    }

    // ===================== REPROGRAMAR =====================

    public function abrirReprogramar(): void
    {
        abort_unless(auth()->user()?->can('sistema.ver'), 403);

        $this->showReprogramar = true;

        $first = $this->cuotasQuery()->first();
        $this->nuevaFechaPrimerPago = optional($first?->fecha_vencimiento)->format('Y-m-d');
    }

public function guardarReprogramacion(): void
{
    abort_unless(auth()->user()?->can('sistema.ver'), 403);

    $this->validate([
        'nuevaFechaPrimerPago' => ['required', 'date'],
    ]);

    $contrato = Contrato::query()->findOrFail($this->contrato->id);

    $antes = [
        'primer_vencimiento' => optional(
            Cuota::query()
                ->where('contrato_id', $contrato->id)
                ->orderBy('numero')
                ->first()?->fecha_vencimiento
        )?->toDateString(),
        'frecuencia' => $contrato->frecuencia,
    ];

    $saldoAnterior = (float) ($contrato->saldo_actual ?? 0);

    $fecha = Carbon::parse($this->nuevaFechaPrimerPago)->startOfDay();

    ContratoCuotasReprogramarService::reprogramar(
        $contrato,
        $fecha,
        false
    );

    $contrato->refresh();

    $despues = [
        'primer_vencimiento' => optional(
            Cuota::query()
                ->where('contrato_id', $contrato->id)
                ->orderBy('numero')
                ->first()?->fecha_vencimiento
        )?->toDateString(),
        'frecuencia' => $contrato->frecuencia,
    ];

    ContratoHistorial::create([
        'contrato_id' => $contrato->id,
        'user_id' => auth()->id(),
        'tipo' => 'reprogramacion',
        'antes' => $antes,
        'despues' => $despues,
        'saldo_anterior' => $saldoAnterior,
        'saldo_nuevo' => (float) ($contrato->saldo_actual ?? 0),
        'motivo' => 'Reprogramación de calendario',
        'nota' => 'Reprogramación desde pantalla del contrato de servicio.',
    ]);

    // ✅ recargar hasta después de guardar historial
    $contrato->refresh();
    $this->loadContrato($contrato);

    $this->showReprogramar = false;
    session()->flash('ok', 'Todas las cuotas fueron recalculadas.');
}

    // ===================== PAGADA HISTÓRICA =====================

    public function confirmarMarcarPagada(int $cuotaId): void
    {
        abort_unless(auth()->user()?->can('recibos.eliminar'), 403);

        $cuota = Cuota::query()
            ->where('contrato_id', $this->contrato->id)
            ->findOrFail($cuotaId);

        $estatus = strtolower((string) $cuota->estatus);
        $tienePago = ((float) ($cuota->pagado_total ?? 0) > 0) || $estatus === 'pagada';

        if ($tienePago) {
            session()->flash('ok', 'La cuota ya tiene pago registrado.');
            return;
        }

        $this->cuotaIdMarcarPagada = $cuotaId;
        $this->observacionPagoHistorico = 'Pago histórico registrado fuera del sistema.';
        $this->showMarcarPagada = true;
    }

    public function marcarPagadaConfirmado(): void
    {
        abort_unless(auth()->user()?->can('recibos.eliminar'), 403);

        $this->validate([
            'cuotaIdMarcarPagada' => ['required', 'integer'],
            'observacionPagoHistorico' => ['nullable', 'string', 'max:255'],
        ], [
            'cuotaIdMarcarPagada.required' => 'No se seleccionó una cuota.',
        ]);

        DB::transaction(function () {
            $contrato = Contrato::query()
                ->lockForUpdate()
                ->findOrFail($this->contrato->id);

            $saldoAnterior = (float) ($contrato->saldo_actual ?? 0);

            $cuota = Cuota::query()
                ->where('contrato_id', $contrato->id)
                ->lockForUpdate()
                ->findOrFail((int) $this->cuotaIdMarcarPagada);

            $estatusActual = strtolower((string) $cuota->estatus);
            $pagadoOriginal = (float) ($cuota->pagado_total ?? 0);

            $tienePago = ($pagadoOriginal > 0) || $estatusActual === 'pagada';

            if ($tienePago) {
                throw ValidationException::withMessages([
                    'observacionPagoHistorico' => 'La cuota ya tiene pago registrado.',
                ]);
            }

            $ahora = now();

            $cuota->update([
                'estatus' => 'pagada',
                'pagado_total' => (float) $cuota->monto,
                'origen_pago' => 'historico',
                'observaciones_pago' => $this->observacionPagoHistorico ?: 'Pago histórico registrado fuera del sistema.',
            ]);

            $this->crearReciboHistoricoConFolioSeguro(
                $contrato,
                $cuota,
                $ahora
            );

            $saldoNuevo = $this->recalcularSaldoContrato($contrato->id);

            $contrato->update([
                'saldo_actual' => $saldoNuevo,
            ]);

            ContratoHistorial::create([
                'contrato_id' => $contrato->id,
                'user_id' => auth()->id(),
                'tipo' => 'pago_historico',
                'antes' => [
                    'cuota_id' => $cuota->id,
                    'cuota_numero' => $cuota->numero,
                    'estatus' => $estatusActual,
                    'pagado_total' => $pagadoOriginal,
                ],
                'despues' => [
                    'cuota_id' => $cuota->id,
                    'cuota_numero' => $cuota->numero,
                    'estatus' => 'pagada',
                    'pagado_total' => (float) $cuota->monto,
                    'origen_pago' => 'historico',
                ],
                'saldo_anterior' => $saldoAnterior,
                'saldo_nuevo' => $saldoNuevo,
                'motivo' => $this->observacionPagoHistorico ?: 'Pago histórico',
                'nota' => 'Cuota de contrato de servicio marcada como pagada histórica.',
            ]);
        });

        $this->showMarcarPagada = false;
        $this->cuotaIdMarcarPagada = null;
        $this->observacionPagoHistorico = 'Pago histórico registrado fuera del sistema.';

        $this->contrato->refresh();
        $this->loadContrato($this->contrato);

        session()->flash('ok', 'La cuota fue marcada como pagada histórica correctamente.');
    }

    protected function crearReciboHistoricoConFolioSeguro(Contrato $contrato, Cuota $cuota, Carbon $ahora): Recibo
    {
        $intentos = 0;
        $maxIntentos = 5;

        do {
            try {
                return Recibo::create([
                    'uuid' => (string) Str::uuid(),
                    'folio' => $this->generarFolioRecibo(),
                    'fecha' => $ahora->toDateString(),
                    'anio' => (int) $ahora->year,
                    'semana_pago' => (int) $ahora->weekOfYear,
                    'semana_del_anio' => (int) $ahora->weekOfYear,
                    'mes_del_anio' => (int) $ahora->month,
                    'cliente_id' => $contrato->cliente_id,
                    'contrato_id' => $contrato->id,
                    'cuota_id' => $cuota->id,
                    'lote_id' => $contrato->lote_id,
                    'tipos_cobro_id' => 1,
                    'forma_pago_id' => 1,
                    'cuentas_bancarias_id' => null,
                    'periodo_id' => null,
                    'monto' => (float) $cuota->monto,
                    'observaciones' => $this->observacionPagoHistorico ?: 'Pago histórico registrado fuera del sistema. No afecta reportes.',
                    'capturado_por_user_id' => auth()->id(),
                    'afecta_reportes' => false,
                    'tipo_movimiento' => 'historico',
                    'es_historico' => true,
                ]);
            } catch (QueryException $e) {
                $sqlState = $e->errorInfo[0] ?? null;
                $driverCode = $e->errorInfo[1] ?? null;
                $esDuplicado = $sqlState === '23000' && (int) $driverCode === 1062;

                if (! $esDuplicado) {
                    throw $e;
                }

                $intentos++;

                if ($intentos >= $maxIntentos) {
                    throw ValidationException::withMessages([
                        'observacionPagoHistorico' => 'No se pudo generar un folio único para el recibo. Intenta nuevamente.',
                    ]);
                }
            }
        } while (true);
    }

    protected function generarFolioRecibo(): string
    {
        $ultimoNumero = (int) Recibo::query()
            ->withTrashed()
            ->whereRaw("folio REGEXP '^REC-[0-9]{6}$'")
            ->selectRaw("MAX(CAST(SUBSTRING(folio, 5) AS UNSIGNED)) as max_num")
            ->lockForUpdate()
            ->value('max_num');

        $siguiente = $ultimoNumero + 1;

        return 'REC-' . str_pad((string) $siguiente, 6, '0', STR_PAD_LEFT);
    }

    protected function recalcularSaldoContrato(int $contratoId): float
    {
        $saldoNuevo = Cuota::query()
            ->where('contrato_id', $contratoId)
            ->get()
            ->sum(function ($q) {
                $monto = (float) ($q->monto ?? 0);
                $pagado = (float) ($q->pagado_total ?? 0);
                $condonado = (float) ($q->condonado_total ?? 0);

                return max(0, $monto - $pagado - $condonado);
            });

        return round((float) $saldoNuevo, 2);
    }

    // ===================== ANULAR PAGO / RECIBO =====================

    public function confirmarAnularPago(int $cuotaId): void
    {
        abort_unless(auth()->user()?->can('recibos.eliminar'), 403);

        $this->cuotaAnularId = $cuotaId;
        $this->motivoAnulacion = 'Corrección de pago/recibo';
        $this->showAnularPago = true;
    }

public function anularPagoConfirmado(): void
{
    abort_unless(auth()->user()?->can('recibos.eliminar'), 403);

    $this->validate([
        'cuotaAnularId' => ['required', 'integer'],
        'motivoAnulacion' => ['required', 'string', 'max:255'],
    ]);

    $contrato = Contrato::query()->findOrFail($this->contrato->id);
    $saldoAnterior = (float) ($contrato->saldo_actual ?? 0);

    $cuota = Cuota::query()
        ->where('contrato_id', $contrato->id)
        ->where('id', (int) $this->cuotaAnularId)
        ->first();

    // ✅ tomar el recibo antes de anularlo
    $recibo = Recibo::query()
        ->withTrashed()
        ->where('contrato_id', $contrato->id)
        ->where('cuota_id', (int) $this->cuotaAnularId)
        ->latest('id')
        ->first();

    $antes = [
        'cuota_id' => (int) $this->cuotaAnularId,
        'cuota_numero' => (int) ($cuota?->numero ?? 0),
        'pagado_total' => (float) ($cuota?->pagado_total ?? 0),
        'estatus' => (string) ($cuota?->estatus ?? ''),
        'folio_recibo' => (string) ($recibo?->folio ?? ''),
        'recibo_id' => (int) ($recibo?->id ?? 0),
    ];

    CuotaPagoRollbackService::anularPagoReciboDeCuota(
        $contrato,
        (int) $this->cuotaAnularId,
        (int) auth()->id(),
        $this->motivoAnulacion
    );

    $saldoNuevo = $this->recalcularSaldoContrato($contrato->id);

    $contrato->update([
        'saldo_actual' => $saldoNuevo,
    ]);

    $contrato->refresh();

    $cuota2 = Cuota::query()
        ->where('contrato_id', $contrato->id)
        ->find((int) $this->cuotaAnularId);

    $despues = [
        'cuota_id' => (int) $this->cuotaAnularId,
        'cuota_numero' => (int) ($cuota2?->numero ?? 0),
        'pagado_total' => (float) ($cuota2?->pagado_total ?? 0),
        'estatus' => (string) ($cuota2?->estatus ?? ''),
        'folio_recibo_anulado' => (string) ($recibo?->folio ?? ''),
    ];

    ContratoHistorial::create([
        'contrato_id' => $contrato->id,
        'user_id' => auth()->id(),
        'tipo' => 'anular_pago',
        'antes' => $antes,
        'despues' => $despues,
        'saldo_anterior' => $saldoAnterior,
        'saldo_nuevo' => $saldoNuevo,
        'motivo' => $this->motivoAnulacion,
        'nota' => 'Anulación de pago/recibo desde contrato de servicio.',
    ]);

    // ✅ recargar hasta después de guardar historial
    $contrato->refresh();
    $this->loadContrato($contrato);

    $this->showAnularPago = false;
    $this->cuotaAnularId = null;
    $this->motivoAnulacion = 'Corrección de pago/recibo';

    session()->flash('ok', 'Pago/recibo anulado correctamente.');
}
public function render()
{
    $cuotas = $this->cuotasQuery()->get();

    return view('livewire.admin.contratos-servicios.show', [
        'cuotas' => $cuotas,
    ])->layout('layouts.app');
}
}