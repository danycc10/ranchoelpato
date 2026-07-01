<?php

namespace App\Livewire\Admin\ContratosServicios;

use App\Models\Contrato;
use App\Models\ContratoHistorial;
use App\Models\Cuota;
use App\Models\Recibo;
use App\Services\Contratos\ContratoCuotasReprogramarService;
use App\Services\Contratos\ContratoWordService;
use App\Services\Contratos\CuotaPagoRollbackService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
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

    public bool $modalContratoOpen = false;

    public ?string $documentoAccion = null;

    public string $documentoTipoSeleccionado = 'contrato';

    // ✅ Reprogramar
    public bool $showReprogramar = false;

    public ?string $nuevaFechaPrimerPago = null;

    // ✅ Anular pago/recibo
    public bool $showAnularPago = false;

    public ?int $cuotaAnularId = null;

    public ?array $anularPreview = null;

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
            'lote.fraccionamiento',
            'lote.fraccionamiento.propietario',
            'promocion',
            'contratoBase.cliente',
            'contratoBase.lote.fraccionamiento',
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
            'documentoScan.max' => 'El PDF no debe pesar mas de 10 MB.',
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

        $this->contrato->update([
            $field => $path,
            'archivo_contrato_disk' => 'private',
        ]);

        $this->registrarHistorialDocumento(
            $documento,
            $archivoAnterior,
            $path,
            'Se subio o reemplazo el PDF escaneado de '.$documento['label'].'.'
        );

        $this->resetDocumentoUpload();

        $this->contrato->refresh();
        $this->loadContrato($this->contrato);

        $this->dispatch('toast', type: 'success', message: 'El PDF escaneado se guardo correctamente.');
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
            'Se elimino el PDF escaneado de '.$documento['label'].'.'
        );

        $this->resetDocumentoUpload();

        $this->contrato->refresh();
        $this->loadContrato($this->contrato);

        $this->dispatch('toast', type: 'success', message: 'El PDF escaneado fue eliminado correctamente.');
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
        $contratoSlug = Str::slug((string) ($this->contrato->folio_contrato ?: $this->contrato->uuid)) ?: $this->contrato->uuid;

        return "contratos/{$fincaSlug}/{$loteSlug}/servicios/{$contratoSlug}";
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

        $tieneReciboHistorico = Recibo::query()
            ->where('cuota_id', $cuota->id)
            ->where('contrato_id', $this->contrato->id)
            ->where('es_historico', true)
            ->exists();

        if ($tieneReciboHistorico) {
            session()->flash('ok', 'La cuota ya tiene un recibo historico activo. Se requiere sincronizar la cuota.');

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
            'saldo_actual' => $this->recalcularSaldoContrato($contrato->id),
            'liquidado_at' => $tieneCuotasPendientes
                ? null
                : ($contrato->liquidado_at ?: now()),
        ];

        $contrato->update($data);
    }

    // ===================== ANULAR PAGO / RECIBO =====================

    public function confirmarAnularPago(int $cuotaId): void
    {
        abort_unless(auth()->user()?->can('recibos.eliminar'), 403);

        $cuota = Cuota::query()
            ->where('contrato_id', $this->contrato->id)
            ->findOrFail($cuotaId);

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
            session()->flash('ok', 'No se encontraron recibos activos para esa cuota.');

            return;
        }

        $recibosPreview = $recibos->map(function ($recibo) {
            $pagos = collect($recibo->pagosDetalle ?? []);

            return [
                'recibo_id' => (int) $recibo->id,
                'folio' => (string) $recibo->folio,
                'concepto' => (string) ($recibo->tipoCobro?->nombre ?? '-'),
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
                        $cuenta = '-';
                    }

                    return [
                        'id' => (int) $p->id,
                        'fecha' => optional($p->created_at)?->format('d/m/Y H:i'),
                        'monto' => (float) ($p->monto ?? 0),
                        'forma_pago' => (string) ($p->formaPago?->nombre ?? '-'),
                        'cuenta' => $cuenta,
                        'referencia' => (string) ($p->referencia ?? ''),
                    ];
                })->values()->all(),
            ];
        })->values();

        $this->cuotaAnularId = $cuotaId;
        $this->anularPreview = [
            'cuota_id' => (int) $cuota->id,
            'cuota_numero' => (int) ($cuota->numero ?? 0),
            'recibos_count' => (int) $recibos->count(),
            'recibos_total' => (float) $recibos->sum(fn ($r) => (float) ($r->monto ?? 0)),
            'pagos_total' => (float) $recibosPreview->sum(fn ($r) => (float) ($r['pagos_total'] ?? 0)),
            'recibos' => $recibosPreview->all(),
        ];
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
        $this->actualizarEstatusContratoSiLiquidado($contrato);
        $contrato->refresh();
        $saldoNuevo = (float) ($contrato->saldo_actual ?? $saldoNuevo);

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
        $this->anularPreview = null;
        $this->motivoAnulacion = 'Corrección de pago/recibo';

        session()->flash('ok', 'Pago/recibo anulado correctamente.');
    }

    public function render()
    {
        $cuotas = $this->cuotasQuery()->get();

        return view('livewire.admin.contratos-servicios.show', [
            'cuotas' => $cuotas,
            'documentosContrato' => ContratoWordService::documentTypes(),
        ])->layout('layouts.app');
    }
}
