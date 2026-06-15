<?php

namespace App\Livewire\Admin\Contratos;

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\Contrato;
use App\Models\ContratoHistorial;
use App\Models\Cuota;
use App\Models\Recibo;
use App\Models\ReciboPago;
use App\Services\Contratos\ContratoPlanService;
use App\Services\Contratos\ContratoCuotasReprogramarService;
use App\Services\Contratos\CuotaPagoRollbackService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Show extends Component
{
    use WithFileUploads;

    public Contrato $contrato;

    public $pdfContrato;

    // Modal contrato digital
    public bool $modalContratoOpen = false;
    public ?string $contratoArchivoPath = null;
    public ?string $contratoArchivoUrl = null;
    public ?string $contratoArchivoNombre = null;
    public ?string $contratoArchivoUuid = null;

    // ✅ Reprogramar
    public bool $showReprogramar = false;
    public ?string $nuevaFechaPrimerPago = null;

    // ✅ Anular pago/recibo por cuota
    public bool $showAnularPago = false;
    public ?int $cuotaAnularId = null;
    public string $motivoAnulacion = 'Corrección de pago/recibo';

    // ✅ Preview de anulación
    public ?array $anularPreview = null;

    // ✅ Marcar pagado histórico
    public bool $showMarcarPagada = false;
    public ?int $cuotaIdMarcarPagada = null;
    public string $observacionPagoHistorico = 'Pago histórico registrado fuera del sistema.';

    public function mount(Contrato $contrato): void
    {
        $this->loadContrato($contrato);
    }

    private function loadContrato(Contrato $contrato): void
    {
        $this->contrato = $contrato->load([
            'cliente',
            'lote.fraccionamiento',
            'lote.fraccionamiento.propietario',
            'cuotas' => fn($q) => $q->orderBy('numero'),
            'promocion',
            'historial' => fn($q) => $q->with('user')->latest('id')->limit(100),
        ]);
    }

    public function getDiasSemanaProperty(): array
    {
        return ContratoPlanService::diasSemana();
    }

    // ===================== CONTRATO DIGITAL =====================

    public function abrirModalContrato(): void
    {
        abort_unless(auth()->user()?->can('sistema.ver'), 403);

        $this->contrato->refresh();
        $this->loadContrato($this->contrato);

        $this->contratoArchivoPath = $this->contrato->archivo_contrato;
        $this->contratoArchivoUuid = $this->contrato->uuid;

        if ($this->contrato->archivo_contrato) {
            $this->contratoArchivoNombre = basename($this->contrato->archivo_contrato);
            $this->contratoArchivoUrl = route('admin.private.contratos.pdf', $this->contrato->uuid)
                . '?v=' . urlencode((string) $this->contrato->archivo_contrato);
        } else {
            $this->contratoArchivoNombre = null;
            $this->contratoArchivoUrl = null;
        }

        $this->resetErrorBag('pdfContrato');
        $this->resetValidation('pdfContrato');

        $this->modalContratoOpen = true;
    }

    public function cerrarModalContrato(): void
    {
        $this->modalContratoOpen = false;
        $this->reset('pdfContrato');
        $this->resetErrorBag('pdfContrato');
        $this->resetValidation('pdfContrato');
    }

    public function subirArchivoContrato(): void
    {
        abort_unless(auth()->user()?->can('sistema.ver'), 403);

        $this->validate([
            'pdfContrato' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ], [
            'pdfContrato.required' => 'Debes seleccionar un archivo PDF.',
            'pdfContrato.mimes' => 'El archivo debe ser un PDF.',
            'pdfContrato.max' => 'El PDF no debe pesar más de 10 MB.',
        ]);

        $this->contrato->loadMissing([
            'lote.fraccionamiento',
        ]);

        $nombreFinca = trim((string) ($this->contrato->lote?->fraccionamiento?->nombre ?? 'sin-finca'));
        $nombreLote  = trim((string) (
            $this->contrato->lote?->lote
            ?? $this->contrato->lote?->clave
            ?? ('lote-' . ($this->contrato->lote_id ?? $this->contrato->id))
        ));

        $fincaSlug = Str::slug($nombreFinca);
        $loteSlug  = Str::slug($nombreLote);

        if ($fincaSlug === '') {
            $fincaSlug = 'sin-finca';
        }

        if ($loteSlug === '') {
            $loteSlug = 'sin-lote';
        }

        $directory = "contratos/{$fincaSlug}/{$loteSlug}";
        $filename = 'contrato.pdf';

        $archivoAnterior = $this->contrato->archivo_contrato;

        if (!empty($archivoAnterior) && Storage::disk('private')->exists($archivoAnterior)) {
            Storage::disk('private')->delete($archivoAnterior);
        }

        $path = $this->pdfContrato->storeAs(
            $directory,
            $filename,
            'private'
        );

        $this->contrato->update([
            'archivo_contrato' => $path,
        ]);

        ContratoHistorial::create([
            'contrato_id' => $this->contrato->id,
            'user_id' => auth()->id(),
            'tipo' => 'archivo_contrato',
            'antes' => [
                'archivo_contrato' => $archivoAnterior,
            ],
            'despues' => [
                'archivo_contrato' => $path,
            ],
            'saldo_anterior' => (float) ($this->contrato->saldo_actual ?? 0),
            'saldo_nuevo' => (float) ($this->contrato->saldo_actual ?? 0),
            'motivo' => 'Carga de contrato digital',
            'nota' => 'Se subió o reemplazó el PDF del contrato.',
        ]);

        $this->reset('pdfContrato');

        $this->contrato->refresh();
        $this->loadContrato($this->contrato);

        $this->contratoArchivoPath = $this->contrato->archivo_contrato;
        $this->contratoArchivoUuid = $this->contrato->uuid;
        $this->contratoArchivoNombre = basename((string) $this->contrato->archivo_contrato);
        $this->contratoArchivoUrl = route('admin.private.contratos.pdf', $this->contrato->uuid)
            . '?v=' . urlencode((string) $this->contrato->archivo_contrato);

        $this->dispatch('toast', type: 'success', message: 'El PDF del contrato se guardó correctamente.');
    }

    public function eliminarArchivoContrato(): void
    {
        abort_unless(auth()->user()?->can('sistema.ver'), 403);

        $archivoAnterior = $this->contrato->archivo_contrato;

        if (!$archivoAnterior) {
            return;
        }

        if (Storage::disk('private')->exists($archivoAnterior)) {
            Storage::disk('private')->delete($archivoAnterior);
        }

        $this->contrato->update([
            'archivo_contrato' => null,
        ]);

        ContratoHistorial::create([
            'contrato_id' => $this->contrato->id,
            'user_id' => auth()->id(),
            'tipo' => 'archivo_contrato',
            'antes' => [
                'archivo_contrato' => $archivoAnterior,
            ],
            'despues' => [
                'archivo_contrato' => null,
            ],
            'saldo_anterior' => (float) ($this->contrato->saldo_actual ?? 0),
            'saldo_nuevo' => (float) ($this->contrato->saldo_actual ?? 0),
            'motivo' => 'Eliminación de contrato digital',
            'nota' => 'Se eliminó el PDF del contrato.',
        ]);

        $this->reset('pdfContrato');

        $this->contrato->refresh();
        $this->loadContrato($this->contrato);

        $this->contratoArchivoPath = null;
        $this->contratoArchivoUuid = $this->contrato->uuid;
        $this->contratoArchivoNombre = null;
        $this->contratoArchivoUrl = null;

        $this->dispatch('toast', type: 'success', message: 'El PDF del contrato fue eliminado correctamente.');
    }

    // ===================== REPROGRAMAR =====================

    public function abrirReprogramar(): void
    {
        abort_unless(auth()->user()?->can('sistema.ver'), 403);

        $this->showReprogramar = true;

        $first = $this->contrato->cuotas()
            ->orderBy('numero')
            ->first();

        $this->nuevaFechaPrimerPago = optional($first?->fecha_vencimiento)->format('Y-m-d');
    }

    public function guardarReprogramacion(): void
    {
        abort_unless(auth()->user()?->can('sistema.ver'), 403);

        $this->validate([
            'nuevaFechaPrimerPago' => ['required', 'date'],
        ]);

        $antes = [
            'primer_vencimiento' => optional(
                $this->contrato->cuotas()->orderBy('numero')->first()?->fecha_vencimiento
            )?->toDateString(),
            'frecuencia' => $this->contrato->frecuencia,
        ];

        $fecha = Carbon::parse($this->nuevaFechaPrimerPago)->startOfDay();

        ContratoCuotasReprogramarService::reprogramar(
            $this->contrato,
            $fecha,
            false
        );

        $this->contrato->refresh();
        $this->loadContrato($this->contrato);

        $despues = [
            'primer_vencimiento' => optional(
                $this->contrato->cuotas()->orderBy('numero')->first()?->fecha_vencimiento
            )?->toDateString(),
            'frecuencia' => $this->contrato->frecuencia,
        ];

        ContratoHistorial::create([
            'contrato_id' => $this->contrato->id,
            'user_id' => auth()->id(),
            'tipo' => 'reprogramacion',
            'antes' => $antes,
            'despues' => $despues,
            'saldo_anterior' => (float) ($this->contrato->saldo_actual ?? 0),
            'saldo_nuevo' => (float) ($this->contrato->saldo_actual ?? 0),
            'motivo' => 'Reprogramación de calendario',
            'nota' => 'Reprogramación desde pantalla del contrato.',
        ]);

        $this->contrato->refresh();
        $this->loadContrato($this->contrato);

        $this->showReprogramar = false;

        $this->dispatch('toast', type: 'success', message: 'Todas las cuotas fueron recalculadas correctamente.');
    }

    // ===================== MARCAR PAGADO HISTÓRICO =====================

    public function confirmarMarcarPagada(int $cuotaId): void
    {
        abort_unless(auth()->user()?->can('recibos.eliminar'), 403);

        $cuota = Cuota::query()
            ->where('contrato_id', $this->contrato->id)
            ->findOrFail($cuotaId);

        $estatus = strtolower((string) $cuota->estatus);
        $tienePago = ((float) ($cuota->pagado_total ?? 0) > 0) || $estatus === 'pagada';

        if ($tienePago) {
            $this->dispatch('toast', type: 'warning', message: 'La cuota ya tiene pago registrado.');
            return;
        }

        $tieneReciboHistorico = Recibo::query()
            ->where('cuota_id', $cuota->id)
            ->where('contrato_id', $this->contrato->id)
            ->where('es_historico', true)
            ->exists();

        if ($tieneReciboHistorico) {
            $this->dispatch('toast', type: 'warning', message: 'La cuota ya tiene un recibo histórico activo. Se requiere sincronizar la cuota.');
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

            $reciboHistoricoExistente = Recibo::query()
                ->where('cuota_id', $cuota->id)
                ->where('contrato_id', $contrato->id)
                ->where('es_historico', true)
                ->lockForUpdate()
                ->first();

            if ($reciboHistoricoExistente) {
                $cuota->update([
                    'estatus' => 'pagada',
                    'pagado_total' => (float) $cuota->monto,
                    'origen_pago' => 'historico',
                    'observaciones_pago' => $this->observacionPagoHistorico ?: 'Pago histórico sincronizado desde recibo existente.',
                ]);

                $saldoNuevo = $this->recalcularSaldoContrato($contrato->id);

                $contrato->update([
                    'saldo_actual' => $saldoNuevo,
                ]);

                $this->actualizarEstatusContratoSiLiquidado($contrato);
                $contrato->refresh();
                $saldoNuevo = (float) ($contrato->saldo_actual ?? $saldoNuevo);

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
                    'motivo' => 'Sincronización de cuota con recibo histórico existente',
                    'nota' => 'La cuota estaba pendiente pero ya existía un recibo histórico activo.',
                ]);

                return;
            }

            Recibo::create([
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

            $cuota->update([
                'estatus' => 'pagada',
                'pagado_total' => (float) $cuota->monto,
                'origen_pago' => 'historico',
                'observaciones_pago' => $this->observacionPagoHistorico ?: 'Pago histórico registrado fuera del sistema.',
            ]);

            $saldoNuevo = $this->recalcularSaldoContrato($contrato->id);

            $contrato->update([
                'saldo_actual' => $saldoNuevo,
            ]);

            $this->actualizarEstatusContratoSiLiquidado($contrato);
            $contrato->refresh();
            $saldoNuevo = (float) ($contrato->saldo_actual ?? $saldoNuevo);

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
                'nota' => 'Cuota marcada como pagada histórica desde pantalla del contrato.',
            ]);
        });

        $this->showMarcarPagada = false;
        $this->cuotaIdMarcarPagada = null;
        $this->observacionPagoHistorico = 'Pago histórico registrado fuera del sistema.';

        $this->contrato->refresh();
        $this->loadContrato($this->contrato);

        $this->dispatch('toast', type: 'success', message: 'La cuota fue marcada como pagada histórica correctamente.');
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

    protected function actualizarEstatusContratoSiLiquidado(Contrato $contrato): void
    {
        $tieneCuotasPendientes = Cuota::query()
            ->where('contrato_id', $contrato->id)
            ->where('estatus', '!=', 'pagada')
            ->exists();

        $nuevoEstatus = $tieneCuotasPendientes ? 'activo' : 'liquidado';

        $data = [
            'estatus' => $nuevoEstatus,
        ];

        if (isset($contrato->saldo_actual)) {
            $data['saldo_actual'] = $this->recalcularSaldoContrato($contrato->id);
        }

        if (isset($contrato->fecha_liquidacion)) {
            $data['fecha_liquidacion'] = $tieneCuotasPendientes
                ? null
                : now()->toDateString();
        }

        $contrato->update($data);
    }

    // ===================== ANULAR PAGO/RECIBO =====================

    public function confirmarAnularPago(int $cuotaId): void
    {
        abort_unless(auth()->user()?->can('recibos.eliminar'), 403);

        $cuota = $this->contrato->cuotas()
            ->where('id', $cuotaId)
            ->firstOrFail();

        $recibos = Recibo::query()
            ->with([
                'tipoCobro',
                'pagosDetalle' => fn($q) => $q
                    ->whereNull('anulado_at')
                    ->whereNull('deleted_at')
                    ->with([
                        'formaPago',
                        'cuentaBancaria',
                    ]),
            ])
            ->where('contrato_id', $this->contrato->id)
            ->where('cuota_id', $cuotaId)
            ->whereNull('anulado_at')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get();

        if ($recibos->isEmpty()) {
            $this->dispatch('toast', type: 'warning', message: 'No se encontraron recibos activos para esa cuota.');
            return;
        }

        $recibosPreview = $recibos->map(function ($recibo) {
            $pagos = collect($recibo->pagosDetalle ?? []);

            return [
                'recibo_id' => (int) $recibo->id,
                'folio' => (string) $recibo->folio,
                'concepto' => (string) ($recibo->tipoCobro?->nombre ?? '—'),
                'monto_recibo' => (float) ($recibo->monto ?? 0),
                'pagos_count' => (int) $pagos->count(),
                'pagos_total' => (float) $pagos->sum(fn($p) => (float) ($p->monto ?? 0)),
                'pagos' => $pagos->map(function ($p) {
                    $cuenta = trim(
                        ($p->cuentaBancaria?->alias ?? '')
                            . (($p->cuentaBancaria?->banco ?? '') ? ' - ' . $p->cuentaBancaria->banco : '')
                            . (($p->cuentaBancaria?->numero ?? '') ? ' (' . $p->cuentaBancaria->numero . ')' : '')
                    );

                    if ($cuenta === '') {
                        $cuenta = '—';
                    }

                    return [
                        'id' => (int) $p->id,
                        'fecha' => optional($p->created_at)?->format('d/m/Y H:i'),
                        'monto' => (float) ($p->monto ?? 0),
                        'forma_pago' => (string) ($p->formaPago?->nombre ?? '—'),
                        'cuenta' => $cuenta,
                        'referencia' => (string) ($p->referencia ?? ''),
                    ];
                })->values()->all(),
            ];
        })->values();

        $this->cuotaAnularId = $cuotaId;
        $this->motivoAnulacion = 'Corrección de pago/recibo';

        $this->anularPreview = [
            'cuota_id' => (int) $cuota->id,
            'cuota_numero' => (int) ($cuota->numero ?? 0),
            'recibos_count' => (int) $recibos->count(),
            'recibos_total' => (float) $recibos->sum(fn($r) => (float) ($r->monto ?? 0)),
            'pagos_total' => (float) $recibosPreview->sum(fn($r) => (float) ($r['pagos_total'] ?? 0)),
            'recibos' => $recibosPreview->all(),
        ];

        $this->showAnularPago = true;
    }

    public function anularPagoConfirmado(): void
    {
        abort_unless(auth()->user()?->can('recibos.eliminar'), 403);

        $this->validate([
            'cuotaAnularId' => ['required', 'integer'],
            'motivoAnulacion' => ['required', 'string', 'max:255'],
        ]);

        $cuota = $this->contrato->cuotas()
            ->where('id', (int) $this->cuotaAnularId)
            ->first();

        $saldoAnteriorContrato = (float) ($this->contrato->saldo_actual ?? 0);

        // ✅ SOLO recibos activos antes de anular
        $recibos = Recibo::query()
            ->with([
                'tipoCobro',
                'pagosDetalle' => fn($q) => $q
                    ->whereNull('deleted_at')
                    ->whereNull('anulado_at')
                    ->with(['formaPago', 'cuentaBancaria']),
            ])
            ->where('contrato_id', $this->contrato->id)
            ->where('cuota_id', (int) $this->cuotaAnularId)
            ->whereNull('deleted_at')
            ->whereNull('anulado_at')
            ->orderBy('id')
            ->get();

        // ✅ Guardar exactamente cuáles recibos se van a afectar
        $reciboIdsAfectados = $recibos->pluck('id')->all();

        $recibosAntes = $recibos->map(function ($recibo) {
            $pagos = collect($recibo->pagosDetalle ?? []);

            return [
                'recibo_id' => (int) $recibo->id,
                'folio' => (string) ($recibo->folio ?? ''),
                'concepto' => (string) ($recibo->tipoCobro?->nombre ?? ''),
                'monto' => (float) ($recibo->monto ?? 0),
                'deleted_at' => optional($recibo->deleted_at)?->toDateTimeString(),
                'anulado_at' => optional($recibo->anulado_at)?->toDateTimeString(),
                'pagos_count' => (int) $pagos->count(),
                'pagos_total' => (float) $pagos->sum(fn($p) => (float) ($p->monto ?? 0)),
                'pagos' => $pagos->map(function ($p) {
                    return [
                        'id' => (int) $p->id,
                        'monto' => (float) ($p->monto ?? 0),
                        'forma_pago' => (string) ($p->formaPago?->nombre ?? ''),
                        'cuenta_bancaria_id' => (int) ($p->cuenta_bancaria_id ?? 0),
                        'deleted_at' => optional($p->deleted_at)?->toDateTimeString(),
                    ];
                })->values()->all(),
            ];
        })->values()->all();

        $antes = [
            'cuota_id' => (int) $this->cuotaAnularId,
            'cuota_numero' => (int) ($cuota?->numero ?? 0),
            'pagado_total' => (float) ($cuota?->pagado_total ?? 0),
            'estatus' => (string) ($cuota?->estatus ?? ''),
            'origen_pago' => (string) ($cuota?->origen_pago ?? ''),
            'recibos_count' => (int) count($recibosAntes),
            'recibos_total' => (float) collect($recibosAntes)->sum(fn($r) => (float) ($r['monto'] ?? 0)),
            'recibos' => $recibosAntes,
        ];

        CuotaPagoRollbackService::anularPagoReciboDeCuota(
            $this->contrato,
            (int) $this->cuotaAnularId,
            (int) auth()->id(),
            $this->motivoAnulacion
        );

        $this->contrato->refresh();

        $this->actualizarEstatusContratoSiLiquidado($this->contrato);

        $this->loadContrato($this->contrato);

        $cuota2 = $this->contrato->cuotas->firstWhere('id', (int) $this->cuotaAnularId);

        // ✅ SOLO volver a leer los mismos recibos afectados
        $recibosDespues = Recibo::query()
            ->withTrashed()
            ->with([
                'tipoCobro',
                'pagosDetalle' => fn($q) => $q->withTrashed(),
            ])
            ->whereIn('id', $reciboIdsAfectados)
            ->orderBy('id')
            ->get();

        $recibosDespuesData = $recibosDespues->map(function ($recibo) {
            return [
                'recibo_id' => (int) $recibo->id,
                'folio' => (string) ($recibo->folio ?? ''),
                'concepto' => (string) ($recibo->tipoCobro?->nombre ?? ''),
                'monto' => (float) ($recibo->monto ?? 0),
                'deleted_at' => optional($recibo->deleted_at)?->toDateTimeString(),
                'anulado_at' => optional($recibo->anulado_at)?->toDateTimeString(),
                'pagos_count' => (int) collect($recibo->pagosDetalle ?? [])->count(),
                'pagos' => collect($recibo->pagosDetalle ?? [])->map(function ($p) {
                    return [
                        'id' => (int) $p->id,
                        'monto' => (float) ($p->monto ?? 0),
                        'deleted_at' => optional($p->deleted_at)?->toDateTimeString(),
                    ];
                })->values()->all(),
            ];
        })->values()->all();

        $saldoNuevoContrato = (float) ($this->contrato->saldo_actual ?? 0);

        $despues = [
            'cuota_id' => (int) $this->cuotaAnularId,
            'cuota_numero' => (int) ($cuota2?->numero ?? 0),
            'pagado_total' => (float) ($cuota2?->pagado_total ?? 0),
            'estatus' => (string) ($cuota2?->estatus ?? ''),
            'origen_pago' => (string) ($cuota2?->origen_pago ?? ''),
            'recibos_count' => (int) count($recibosDespuesData),
            'recibos' => $recibosDespuesData,
        ];

        ContratoHistorial::create([
            'contrato_id' => $this->contrato->id,
            'user_id' => auth()->id(),
            'tipo' => 'anular_pago',
            'antes' => $antes,
            'despues' => $despues,
            'saldo_anterior' => $saldoAnteriorContrato,
            'saldo_nuevo' => $saldoNuevoContrato,
            'motivo' => $this->motivoAnulacion,
            'nota' => 'Anulación de todos los recibos y recibos_pagos de la cuota desde pantalla del contrato.',
        ]);

        $this->contrato->refresh();
        $this->loadContrato($this->contrato);

        $this->showAnularPago = false;
        $this->cuotaAnularId = null;
        $this->motivoAnulacion = 'Corrección de pago/recibo';
        $this->anularPreview = null;

        $this->dispatch('toast', type: 'success', message: 'Todos los recibos y pagos de la cuota fueron anulados correctamente.');
    }

    public function render()
    {
        return view('livewire.admin.contratos.show')
            ->layout('layouts.app');
    }
}
