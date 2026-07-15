<?php

namespace App\Livewire\Admin\Contratos;

use App\Models\Contrato;
use App\Models\ContratoHistorial;
use App\Models\Cuota;
use App\Models\Recibo;
use App\Services\Contratos\ContratoCuotasReprogramarService;
use App\Services\Contratos\ContratoPlanService;
use App\Services\Contratos\ContratoWordService;
use App\Services\Contratos\CuotaPagoRollbackService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithFileUploads;

class Show extends Component
{
    use WithFileUploads;

    public Contrato $contrato;

    public $documentoScan;

    // Modal documentos legales
    public bool $modalContratoOpen = false;

    public ?string $documentoAccion = null;

    public string $documentoTipoSeleccionado = 'contrato';

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

    // ✅ Abono a capital
    public bool $showAbonoCapital = false;

    public string $abonoCapitalMonto = '';

    public string $abonoCapitalFecha = '';

    public string $abonoCapitalObservaciones = '';

    public function mount(string $uuid): void
    {
        $contratoModel = Contrato::withTrashed()
            ->where('uuid', $uuid)
            ->firstOrFail();

        $this->loadContrato($contratoModel);
    }

    private function loadContrato(Contrato $contrato): void
    {
        $this->contrato = $contrato->load([
            'cliente',
            'lote.fraccionamiento',
            'lote.fraccionamiento.propietario',
            'cuotas' => fn ($q) => $q->orderBy('numero'),
            'promocion',
            'historial' => fn ($q) => $q->with('user')->latest('id')->limit(100),
        ]);
    }

    public function getDiasSemanaProperty(): array
    {
        return ContratoPlanService::diasSemana();
    }

    // ===================== DOCUMENTOS LEGALES =====================

    public function abrirModalContrato(): void
    {
        abort_unless(auth()->user()?->can('sistema.ver'), 403);

        $this->contrato->refresh();
        $this->loadContrato($this->contrato);

        $this->documentoAccion = null;
        $this->documentoTipoSeleccionado = 'contrato';
        $this->resetDocumentoUpload();

        $this->modalContratoOpen = true;
    }

    public function cerrarModalContrato(): void
    {
        $this->modalContratoOpen = false;
        $this->documentoAccion = null;
        $this->documentoTipoSeleccionado = 'contrato';
        $this->resetDocumentoUpload();
    }

    public function seleccionarAccionDocumento(string $accion): void
    {
        abort_unless(auth()->user()?->can('sistema.ver'), 403);

        if (! in_array($accion, ['descargar', 'subir'], true)) {
            return;
        }

        $this->documentoAccion = $accion;
        $this->resetDocumentoUpload();
    }

    public function seleccionarDocumentoContrato(string $tipo): void
    {
        abort_unless(auth()->user()?->can('sistema.ver'), 403);

        if (! ContratoWordService::documentType($tipo)) {
            return;
        }

        $this->documentoTipoSeleccionado = $tipo;
        $this->resetDocumentoUpload();
    }

    public function volverAccionesDocumento(): void
    {
        $this->documentoAccion = null;
        $this->resetDocumentoUpload();
    }

    public function subirArchivoContrato(): void
    {
        abort_unless(auth()->user()?->can('sistema.ver'), 403);

        $documento = $this->documentoSeleccionadoConfig();

        $this->validate([
            'documentoScan' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ], [
            'documentoScan.required' => 'Debes seleccionar un archivo PDF.',
            'documentoScan.mimes' => 'El archivo debe ser un PDF.',
            'documentoScan.max' => 'El PDF no debe pesar más de 10 MB.',
        ]);

        $field = $documento['scan_field'];
        $archivoAnterior = $this->contrato->{$field};

        if (! empty($archivoAnterior) && Storage::disk('private')->exists($archivoAnterior)) {
            Storage::disk('private')->delete($archivoAnterior);
        }

        $path = $this->documentoScan->storeAs(
            $this->buildDocumentoDirectory(),
            $documento['scan_filename'],
            'private'
        );

        $updates = [$field => $path, 'archivo_contrato_disk' => 'private'];

        if (str_starts_with($documento['key'], 'convenio_')) {
            $updates['alerta_convenio'] = true;
        }

        $this->contrato->update($updates);

        $this->registrarHistorialDocumento(
            $documento,
            $archivoAnterior,
            $path,
            'Se subió o reemplazó el PDF escaneado de '.$documento['label'].'.'
        );

        $this->resetDocumentoUpload();

        $this->contrato->refresh();
        $this->loadContrato($this->contrato);

        $this->dispatch('toast', type: 'success', message: 'El PDF escaneado se guardó correctamente.');
    }

    public function eliminarArchivoContrato(): void
    {
        abort_unless(auth()->user()?->can('sistema.ver'), 403);

        $documento = $this->documentoSeleccionadoConfig();
        $field = $documento['scan_field'];
        $archivoAnterior = $this->contrato->{$field};

        if (! $archivoAnterior) {
            return;
        }

        if (Storage::disk('private')->exists($archivoAnterior)) {
            Storage::disk('private')->delete($archivoAnterior);
        }

        $this->contrato->update([
            $field => null,
        ]);

        $this->registrarHistorialDocumento(
            $documento,
            $archivoAnterior,
            null,
            'Se eliminó el PDF escaneado de '.$documento['label'].'.'
        );

        $this->resetDocumentoUpload();

        $this->contrato->refresh();
        $this->loadContrato($this->contrato);

        $this->dispatch('toast', type: 'success', message: 'El PDF escaneado fue eliminado correctamente.');
    }

    public function abrirAbonoCapital(): void
    {
        $this->abonoCapitalMonto = '';
        $this->abonoCapitalFecha = now()->toDateString();
        $this->abonoCapitalObservaciones = '';
        $this->resetErrorBag();
        $this->showAbonoCapital = true;
    }

    public function confirmarAbonoCapital(): void
    {
        abort_unless(auth()->user()?->can('contratos.editar'), 403);

        $saldoActual = (float) ($this->contrato->saldo_actual ?? 0);

        $this->validate([
            'abonoCapitalMonto' => [
                'required',
                'numeric',
                'min:0.01',
                "max:{$saldoActual}",
            ],
            'abonoCapitalFecha' => ['required', 'date'],
            'abonoCapitalObservaciones' => ['nullable', 'string', 'max:500'],
        ], [
            'abonoCapitalMonto.required' => 'El monto es requerido.',
            'abonoCapitalMonto.numeric'  => 'El monto debe ser numérico.',
            'abonoCapitalMonto.min'      => 'El monto debe ser mayor a cero.',
            'abonoCapitalMonto.max'      => "El monto no puede exceder el saldo actual (\${$saldoActual}).",
            'abonoCapitalFecha.required' => 'La fecha es requerida.',
            'abonoCapitalFecha.date'     => 'La fecha no es válida.',
        ]);

        DB::transaction(function () {
            $contrato = Contrato::query()
                ->lockForUpdate()
                ->findOrFail($this->contrato->id);

            $monto         = round((float) $this->abonoCapitalMonto, 2);
            $saldoAnterior = round((float) ($contrato->saldo_actual ?? 0), 2);
            $saldoNuevo    = round(max(0, $saldoAnterior - $monto), 2);

            $contrato->update(['saldo_actual' => $saldoNuevo]);

            // Eliminar cuotas del final hasta consumir el abono
            $cuotasPendientes = Cuota::where('contrato_id', $contrato->id)
                ->whereIn('estatus', ['pendiente', 'vencida'])
                ->where('es_anualidad', 0)
                ->where(fn ($q) => $q->whereNull('pagado_total')->orWhere('pagado_total', 0))
                ->orderByDesc('fecha_vencimiento')
                ->orderByDesc('numero')
                ->lockForUpdate()
                ->get();

            $abonoRestante    = $monto;
            $cuotasEliminadas = 0;

            foreach ($cuotasPendientes as $cuota) {
                if ($abonoRestante <= 0) break;

                $pendiente = round((float) $cuota->monto - (float) ($cuota->pagado_total ?? 0), 2);

                if ($abonoRestante >= $pendiente) {
                    $cuota->delete();
                    $cuotasEliminadas++;
                    $abonoRestante = round($abonoRestante - $pendiente, 2);
                } else {
                    // El abono cubre solo parte de esta cuota: reducir su monto
                    $cuota->update(['monto' => round($pendiente - $abonoRestante, 2)]);
                    $abonoRestante = 0;
                }
            }

            $this->actualizarEstatusContratoSiLiquidado($contrato);
            $contrato->refresh();

            ContratoHistorial::create([
                'contrato_id' => $contrato->id,
                'user_id'     => auth()->id(),
                'tipo'        => 'abono_capital',
                'antes'       => ['saldo_actual' => $saldoAnterior],
                'despues'     => ['saldo_actual' => $saldoNuevo, 'monto_abono' => $monto, 'cuotas_eliminadas' => $cuotasEliminadas],
                'saldo_anterior' => $saldoAnterior,
                'saldo_nuevo'    => $saldoNuevo,
                'nota'        => $this->abonoCapitalObservaciones ?: null,
            ]);
        });

        $this->showAbonoCapital = false;
        $this->loadContrato($this->contrato->refresh());
        $this->dispatch('toast', type: 'success', message: 'Abono a capital registrado correctamente.');
    }

    public function toggleAlertaConvenio(): void
    {
        $this->contrato->update(['alerta_convenio' => ! $this->contrato->alerta_convenio]);
        $this->contrato->refresh();

        $estado = $this->contrato->alerta_convenio ? 'activada' : 'desactivada';
        $this->dispatch('toast', type: 'success', message: "Alerta de convenio {$estado}.");
    }

    private function documentoSeleccionadoConfig(): array
    {
        $documento = ContratoWordService::documentType($this->documentoTipoSeleccionado);

        abort_unless($documento, 404);

        return $documento;
    }

    private function resetDocumentoUpload(): void
    {
        $this->reset('documentoScan');
        $this->resetErrorBag('documentoScan');
        $this->resetValidation('documentoScan');
    }

    private function buildDocumentoDirectory(): string
    {
        $this->contrato->loadMissing([
            'lote.fraccionamiento',
        ]);

        $nombreFinca = trim((string) ($this->contrato->lote?->fraccionamiento?->nombre ?? 'sin-finca'));
        $nombreLote = trim((string) (
            $this->contrato->lote?->lote
            ?? $this->contrato->lote?->clave
            ?? ('lote-'.($this->contrato->lote_id ?? $this->contrato->id))
        ));

        $fincaSlug = Str::slug($nombreFinca) ?: 'sin-finca';
        $loteSlug = Str::slug($nombreLote) ?: 'sin-lote';

        return "contratos/{$fincaSlug}/{$loteSlug}";
    }

    private function registrarHistorialDocumento(
        array $documento,
        ?string $archivoAnterior,
        ?string $archivoNuevo,
        string $nota
    ): void {
        ContratoHistorial::create([
            'contrato_id' => $this->contrato->id,
            'user_id' => auth()->id(),
            'tipo' => 'archivo_documento_contrato',
            'antes' => [
                'documento_tipo' => $documento['key'],
                'documento_label' => $documento['label'],
                'archivo' => $archivoAnterior,
            ],
            'despues' => [
                'documento_tipo' => $documento['key'],
                'documento_label' => $documento['label'],
                'archivo' => $archivoNuevo,
            ],
            'saldo_anterior' => (float) ($this->contrato->saldo_actual ?? 0),
            'saldo_nuevo' => (float) ($this->contrato->saldo_actual ?? 0),
            'nota' => $nota,
        ]);
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
            ->selectRaw('MAX(CAST(SUBSTRING(folio, 5) AS UNSIGNED)) as max_num')
            ->lockForUpdate()
            ->value('max_num');

        $siguiente = $ultimoNumero + 1;

        return 'REC-'.str_pad((string) $siguiente, 6, '0', STR_PAD_LEFT);
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

        $data['liquidado_at'] = $tieneCuotasPendientes
            ? null
            : ($contrato->liquidado_at ?: now());

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
                'pagosDetalle' => fn ($q) => $q
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
                'pagos_total' => (float) $pagos->sum(fn ($p) => (float) ($p->monto ?? 0)),
                'pagos' => $pagos->map(function ($p) {
                    $cuenta = trim(
                        ($p->cuentaBancaria?->alias ?? '')
                            .(($p->cuentaBancaria?->banco ?? '') ? ' - '.$p->cuentaBancaria->banco : '')
                            .(($p->cuentaBancaria?->numero ?? '') ? ' ('.$p->cuentaBancaria->numero.')' : '')
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
            'recibos_total' => (float) $recibos->sum(fn ($r) => (float) ($r->monto ?? 0)),
            'pagos_total' => (float) $recibosPreview->sum(fn ($r) => (float) ($r['pagos_total'] ?? 0)),
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
                'pagosDetalle' => fn ($q) => $q
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
                'pagos_total' => (float) $pagos->sum(fn ($p) => (float) ($p->monto ?? 0)),
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
            'recibos_total' => (float) collect($recibosAntes)->sum(fn ($r) => (float) ($r['monto'] ?? 0)),
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
                'pagosDetalle' => fn ($q) => $q->withTrashed(),
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
        return view('livewire.admin.contratos.show', [
            'documentosContrato' => ContratoWordService::documentTypes(),
        ])
            ->layout('layouts.app');
    }
}
