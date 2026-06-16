<?php

namespace App\Livewire\Admin\Recibos;

use App\Models\Contrato;
use App\Models\ContratoHistorial;
use App\Models\CuentaBancaria;
use App\Models\Cuota;
use App\Models\FormaPago;
use App\Models\Recibo;
use App\Models\TipoCobro;
use App\Services\Contratos\CuotaPagoRollbackService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Edit extends Component
{
    public string $uuid;

    public Recibo $recibo;

    public ?string $fecha = null;

    public ?int $tipos_cobro_id = null;

    public ?string $observaciones = null;

    public bool $showAnularRecibo = false;

    public string $motivoAnulacion = 'Corrección de recibo';

    public ?array $anularPreview = null;

    public array $pagos = [];

    // MODAL RECARGO
    public bool $showRecargoModal = false;

    public ?float $recargo_monto = null;

    public ?int $recargo_forma_pago_id = null;

    public ?int $recargo_cuentas_bancarias_id = null;

    public ?string $recargo_referencia = null;

    public ?string $recargo_observaciones = null;

    public bool $recargo_requiere_cuenta = false;

    public function mount(string $uuid): void
    {
        $this->uuid = $uuid;

        $this->recibo = Recibo::query()
            ->with([
                'tipoCobro',
                'cliente',
                'contrato',
                'cuota',
                'lote.fraccionamiento',
                'periodo',
                'pagosDetalle.formaPago',
                'pagosDetalle.cuentaBancaria',
            ])
            ->where('uuid', $uuid)
            ->firstOrFail();

        if ($this->recibo->trashed()) {
            abort(403, 'No se puede editar un recibo eliminado.');
        }

        if (! empty($this->recibo->anulado_at)) {
            abort(403, 'No se puede editar un recibo anulado.');
        }

        $this->fecha = optional($this->recibo->fecha)->format('Y-m-d');
        $this->tipos_cobro_id = $this->recibo->tipos_cobro_id;
        $this->observaciones = $this->recibo->observaciones ?? null;

        $this->pagos = $this->recibo->pagosDetalle
            ->map(function ($pago) {
                return [
                    'id' => $pago->id,
                    'orden' => (int) ($pago->orden ?? 0),
                    'forma_pago_id' => $pago->forma_pago_id ? (int) $pago->forma_pago_id : null,
                    'cuentas_bancarias_id' => $pago->cuenta_bancaria_id ? (int) $pago->cuenta_bancaria_id : null,
                    'monto' => $pago->monto !== null ? (float) $pago->monto : null,
                    'referencia' => $pago->referencia ?? null,
                    'requiere_cuenta' => (bool) ($pago->formaPago?->requiere_cuenta),
                    'forma_nombre' => $pago->formaPago?->nombre,
                    'cuenta_alias' => $pago->cuentaBancaria?->alias,
                ];
            })
            ->values()
            ->toArray();
    }

    public function updatedPagos($value, $name): void
    {
        $partes = explode('.', (string) $name);

        if (count($partes) === 2 && $partes[1] === 'forma_pago_id') {
            $index = (int) $partes[0];
            $formaId = $this->pagos[$index]['forma_pago_id'] ?? null;

            $forma = $formaId ? FormaPago::find($formaId) : null;
            $requiereCuenta = (bool) ($forma?->requiere_cuenta);

            $this->pagos[$index]['requiere_cuenta'] = $requiereCuenta;

            if (! $requiereCuenta) {
                $this->pagos[$index]['cuentas_bancarias_id'] = null;
            }
        }
    }

    public function updatedRecargoFormaPagoId($value): void
    {
        $forma = $value ? FormaPago::find($value) : null;
        $this->recargo_requiere_cuenta = (bool) ($forma?->requiere_cuenta);

        if (! $this->recargo_requiere_cuenta) {
            $this->recargo_cuentas_bancarias_id = null;
        }
    }

    public function abrirModalRecargo(): void
    {
        $this->recibo->refresh();
        $this->recibo->load(['cuota', 'contrato', 'cliente', 'lote']);

        if (! $this->recibo->cuota_id || ! $this->recibo->cuota) {
            $this->dispatch('toast', type: 'error', message: 'Este recibo no tiene cuota relacionada.');

            return;
        }

        $this->recargo_monto = null;
        $this->recargo_forma_pago_id = $this->recibo->forma_pago_id;
        $this->recargo_cuentas_bancarias_id = $this->recibo->cuentas_bancarias_id;
        $this->recargo_referencia = null;
        $this->recargo_observaciones = 'Recargo generado desde recibo '.$this->recibo->folio;

        $forma = $this->recargo_forma_pago_id ? FormaPago::find($this->recargo_forma_pago_id) : null;
        $this->recargo_requiere_cuenta = (bool) ($forma?->requiere_cuenta);

        $this->showRecargoModal = true;
    }

    public function cerrarModalRecargo(): void
    {
        $this->showRecargoModal = false;
    }

    public function guardarRecargo(): void
    {
        $forma = $this->recargo_forma_pago_id ? FormaPago::find($this->recargo_forma_pago_id) : null;
        $requiereCuenta = (bool) ($forma?->requiere_cuenta);

        $data = $this->validate([
            'recargo_monto' => ['required', 'numeric', 'min:0.01'],
            'recargo_forma_pago_id' => ['required', 'integer', 'exists:formas_pago,id'],
            'recargo_cuentas_bancarias_id' => [
                Rule::requiredIf($requiereCuenta),
                'nullable',
                'integer',
                'exists:cuentas_bancarias,id',
            ],
            'recargo_referencia' => ['nullable', 'string', 'max:255'],
            'recargo_observaciones' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->recibo->refresh();
        $this->recibo->load(['cuota', 'contrato', 'cliente', 'lote']);

        if (! $this->recibo->cuota_id || ! $this->recibo->cuota) {
            $this->dispatch('toast', type: 'error', message: 'Este recibo no tiene cuota relacionada.');

            return;
        }

        $tipoRecargoId = TipoCobro::query()
            ->whereRaw('UPPER(nombre) LIKE ?', ['%RECARGO%'])
            ->orderBy('id')
            ->value('id');

        if (! $tipoRecargoId) {
            $this->dispatch('toast', type: 'error', message: 'No existe un tipo de cobro RECARGO.');

            return;
        }

        DB::transaction(function () use ($data, $tipoRecargoId) {
            $cuota = $this->recibo->cuota;

            $fecha = now();

            $reciboRecargo = $this->crearReciboConFolioSeguro([
                'fecha' => $fecha->toDateString(),
                'anio' => (int) $fecha->format('Y'),

                'semana_pago' => (int) ($cuota->numero ?? 0),
                'semana_del_anio' => (int) $fecha->format('W'),
                'mes_del_anio' => (int) $fecha->format('m'),

                'cliente_id' => $this->recibo->cliente_id,
                'contrato_id' => $this->recibo->contrato_id,
                'lote_id' => $this->recibo->lote_id,
                'cuota_id' => $cuota->id,

                'tipos_cobro_id' => $tipoRecargoId,
                'forma_pago_id' => $data['recargo_forma_pago_id'],

                'cuentas_bancarias_id' => $this->formaPagoEsEfectivo($data['recargo_forma_pago_id'])
                    ? null
                    : ($data['recargo_cuentas_bancarias_id'] ?? null),

                'periodo_id' => $this->recibo->periodo_id,

                'propietario_contable_id' => $this->recibo->propietario_contable_id,

                'monto' => round((float) $data['recargo_monto'], 2),

                'saldo_anterior' => null,
                'saldo_posterior' => null,

                'observaciones' => trim(
                    'RECARGO de cuota #'.$cuota->numero.
                    ' — generado desde recibo '.$this->recibo->folio.
                    ' '.
                    ($data['recargo_observaciones'] ?? '')
                ),

                'capturado_por_user_id' => auth()->id(),

                'tipo_movimiento' => 'recargo',
                'afecta_reportes' => true,

                'evidencia_path' => null,
                'evidencia_disk' => null,
                'evidencia_mime' => null,
                'evidencia_size' => null,
            ]);

            $reciboRecargo->pagosDetalle()->create([
                'orden' => 1,
                'forma_pago_id' => $data['recargo_forma_pago_id'],
                'cuenta_bancaria_id' => $data['recargo_cuentas_bancarias_id'] ?? null,
                'monto' => round((float) $data['recargo_monto'], 2),
                'fecha_efectiva' => Carbon::parse($reciboRecargo->fecha)->toDateString(),
                'referencia' => $data['recargo_referencia'] ?? null,
            ]);

            if (class_exists(ContratoHistorial::class)) {
                ContratoHistorial::create([
                    'contrato_id' => $this->recibo->contrato_id,
                    'user_id' => auth()->id(),
                    'tipo' => 'recargo_generado',
                    'antes' => [
                        'recibo_origen_id' => $this->recibo->id,
                        'recibo_origen_folio' => $this->recibo->folio,
                        'cuota_id' => $cuota->id,
                        'cuota_numero' => $cuota->numero,
                    ],
                    'despues' => [
                        'recibo_recargo_id' => $reciboRecargo->id,
                        'recibo_recargo_folio' => $reciboRecargo->folio,
                        'monto' => (float) $reciboRecargo->monto,
                    ],
                    'saldo_anterior' => (float) ($this->recibo->contrato?->saldo_actual ?? 0),
                    'saldo_nuevo' => (float) ($this->recibo->contrato?->saldo_actual ?? 0),
                    'motivo' => 'Recargo generado manualmente',
                    'nota' => 'Recargo creado desde la pantalla de edición del recibo.',
                ]);
            }
        });

        $this->showRecargoModal = false;

        $this->dispatch('toast', type: 'success', message: 'Recargo generado correctamente.');

        $this->redirectRoute('admin.recibos.index', navigate: true);
    }

    protected function crearReciboConFolioSeguro(array $data, int $maxIntentos = 10): Recibo
    {
        $anio = (int) $data['anio'];

        for ($i = 0; $i < $maxIntentos; $i++) {
            $data['folio'] = $this->generarFolio($anio);

            try {
                return Recibo::create($data);
            } catch (\Throwable $e) {
                if (! $this->isDuplicateKeyException($e)) {
                    throw $e;
                }

                usleep(50000);
            }
        }

        throw ValidationException::withMessages([
            'folio' => 'No fue posible asignar un folio único. Intenta nuevamente.',
        ]);
    }

    protected function generarFolio(?int $anio = null): string
    {
        $anio = $anio ?: now()->year;

        $ultimo = Recibo::withTrashed()
            ->where('anio', $anio)
            ->where('folio', 'regexp', '^R-'.$anio.'-[0-9]{6}$')
            ->orderByDesc('folio')
            ->value('folio');

        $n = $ultimo ? ((int) substr($ultimo, -6)) + 1 : 1;

        return 'R-'.$anio.'-'.str_pad($n, 6, '0', STR_PAD_LEFT);
    }

    public function confirmarAnularRecibo(): void
    {
        abort_unless(auth()->user()?->can('recibos.eliminar'), 403);

        $this->recibo->refresh();

        $this->recibo->load([
            'tipoCobro',
            'cliente',
            'contrato',
            'cuota',
            'pagosDetalle' => fn ($q) => $q
                ->whereNull('anulado_at')
                ->whereNull('deleted_at')
                ->with(['formaPago', 'cuentaBancaria']),
        ]);

        if (! empty($this->recibo->anulado_at) || $this->recibo->trashed()) {
            $this->dispatch('toast', type: 'warning', message: 'Este recibo ya está anulado o eliminado.');

            return;
        }

        if (! $this->recibo->contrato_id || ! $this->recibo->cuota_id) {
            $this->dispatch('toast', type: 'error', message: 'Este recibo no tiene contrato o cuota relacionada.');

            return;
        }

        $pagos = collect($this->recibo->pagosDetalle ?? []);

        $this->anularPreview = [
            'recibo_id' => (int) $this->recibo->id,
            'folio' => (string) $this->recibo->folio,
            'cliente' => (string) ($this->recibo->cliente?->nombre_completo ?? '—'),
            'cuota_id' => (int) $this->recibo->cuota_id,
            'cuota_numero' => (int) ($this->recibo->cuota?->numero ?? 0),
            'concepto' => (string) ($this->recibo->tipoCobro?->nombre ?? '—'),
            'monto_recibo' => (float) ($this->recibo->monto ?? 0),
            'pagos_count' => (int) $pagos->count(),
            'pagos_total' => (float) $pagos->sum(fn ($p) => (float) ($p->monto ?? 0)),
            'pagos' => $pagos->map(function ($p) {
                return [
                    'id' => (int) $p->id,
                    'monto' => (float) ($p->monto ?? 0),
                    'forma_pago' => (string) ($p->formaPago?->nombre ?? '—'),
                    'cuenta' => (string) ($p->cuentaBancaria?->alias ?? '—'),
                    'referencia' => (string) ($p->referencia ?? ''),
                ];
            })->values()->all(),
        ];

        $this->motivoAnulacion = 'Corrección de recibo';
        $this->showAnularRecibo = true;
    }

    protected function formaPagoEsEfectivo(?int $formaPagoId): bool
    {
        if (! $formaPagoId) {
            return false;
        }

        $forma = FormaPago::find($formaPagoId);

        if (! $forma) {
            return false;
        }

        return str_contains(
            mb_strtolower($forma->nombre),
            'efectivo'
        );
    }

    public function anularReciboConfirmado(): void
    {
        abort_unless(auth()->user()?->can('recibos.eliminar'), 403);

        $this->validate([
            'motivoAnulacion' => ['required', 'string', 'max:255'],
        ]);

        $this->recibo->refresh();

        if (! empty($this->recibo->anulado_at) || $this->recibo->trashed()) {
            $this->dispatch('toast', type: 'warning', message: 'Este recibo ya está anulado o eliminado.');

            return;
        }

        if (! $this->recibo->contrato_id || ! $this->recibo->cuota_id) {
            $this->dispatch('toast', type: 'error', message: 'Este recibo no tiene contrato o cuota relacionada.');

            return;
        }

        $contrato = Contrato::query()->findOrFail($this->recibo->contrato_id);

        $saldoAnteriorContrato = (float) ($contrato->saldo_actual ?? 0);

        $cuota = Cuota::query()
            ->where('contrato_id', $contrato->id)
            ->find($this->recibo->cuota_id);

        $recibos = Recibo::query()
            ->with([
                'tipoCobro',
                'pagosDetalle' => fn ($q) => $q
                    ->whereNull('deleted_at')
                    ->whereNull('anulado_at')
                    ->with(['formaPago', 'cuentaBancaria']),
            ])
            ->where('contrato_id', $contrato->id)
            ->where('cuota_id', $this->recibo->cuota_id)
            ->whereNull('deleted_at')
            ->whereNull('anulado_at')
            ->orderBy('id')
            ->get();

        $reciboIdsAfectados = $recibos->pluck('id')->all();

        $antes = [
            'origen' => 'editar_recibo',
            'recibo_actual_id' => (int) $this->recibo->id,
            'recibo_actual_folio' => (string) $this->recibo->folio,
            'cuota_id' => (int) $this->recibo->cuota_id,
            'cuota_numero' => (int) ($cuota?->numero ?? 0),
            'pagado_total' => (float) ($cuota?->pagado_total ?? 0),
            'estatus' => (string) ($cuota?->estatus ?? ''),
            'recibos_count' => (int) $recibos->count(),
            'recibos_total' => (float) $recibos->sum(fn ($r) => (float) ($r->monto ?? 0)),
        ];

        CuotaPagoRollbackService::anularPagoReciboDeCuota(
            $contrato,
            (int) $this->recibo->cuota_id,
            (int) auth()->id(),
            $this->motivoAnulacion
        );

        $contrato->refresh();
        $this->actualizarEstatusContratoSiLiquidado($contrato);
        $contrato->refresh();

        $cuotaDespues = Cuota::query()
            ->where('contrato_id', $contrato->id)
            ->find($this->recibo->cuota_id);

        $recibosDespues = Recibo::query()
            ->withTrashed()
            ->whereIn('id', $reciboIdsAfectados)
            ->orderBy('id')
            ->get();

        $despues = [
            'origen' => 'editar_recibo',
            'recibo_actual_id' => (int) $this->recibo->id,
            'recibo_actual_folio' => (string) $this->recibo->folio,
            'cuota_id' => (int) $this->recibo->cuota_id,
            'cuota_numero' => (int) ($cuotaDespues?->numero ?? 0),
            'pagado_total' => (float) ($cuotaDespues?->pagado_total ?? 0),
            'estatus' => (string) ($cuotaDespues?->estatus ?? ''),
            'recibos' => $recibosDespues->map(function ($recibo) {
                return [
                    'recibo_id' => (int) $recibo->id,
                    'folio' => (string) $recibo->folio,
                    'monto' => (float) ($recibo->monto ?? 0),
                    'deleted_at' => optional($recibo->deleted_at)?->toDateTimeString(),
                    'anulado_at' => optional($recibo->anulado_at)?->toDateTimeString(),
                ];
            })->values()->all(),
        ];

        if (class_exists(ContratoHistorial::class)) {
            ContratoHistorial::create([
                'contrato_id' => $contrato->id,
                'user_id' => auth()->id(),
                'tipo' => 'anular_recibo',
                'antes' => $antes,
                'despues' => $despues,
                'saldo_anterior' => $saldoAnteriorContrato,
                'saldo_nuevo' => (float) ($contrato->saldo_actual ?? 0),
                'motivo' => $this->motivoAnulacion,
                'nota' => 'Anulación ejecutada desde la pantalla de edición del recibo.',
            ]);
        }

        $this->showAnularRecibo = false;
        $this->motivoAnulacion = 'Corrección de recibo';
        $this->anularPreview = null;

        $this->dispatch('toast', type: 'success', message: 'El recibo y los pagos de la cuota fueron anulados correctamente.');

        $this->redirectRoute('admin.recibos.index', navigate: true);
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

    protected function rules(): array
    {
        $rules = [
            'fecha' => ['required', 'date'],
            'tipos_cobro_id' => ['required', 'integer', 'exists:tipos_cobro,id'],
            'observaciones' => ['nullable', 'string', 'max:1000'],

            'pagos' => ['required', 'array', 'min:1'],
            'pagos.*.id' => ['required', 'integer', 'exists:recibos_pagos,id'],
            'pagos.*.orden' => ['nullable', 'integer'],
            'pagos.*.forma_pago_id' => ['required', 'integer', 'exists:formas_pago,id'],
            'pagos.*.monto' => ['required', 'numeric', 'min:0.01'],
            'pagos.*.referencia' => ['nullable', 'string', 'max:255'],
        ];

        foreach ($this->pagos as $i => $pago) {
            $requiereCuenta = false;

            if (! empty($pago['forma_pago_id'])) {
                $forma = FormaPago::find($pago['forma_pago_id']);
                $requiereCuenta = (bool) ($forma?->requiere_cuenta);
            }

            $rules["pagos.{$i}.cuentas_bancarias_id"] = [
                Rule::requiredIf($requiereCuenta),
                'nullable',
                'integer',
                'exists:cuentas_bancarias,id',
            ];
        }

        return $rules;
    }

    protected $validationAttributes = [
        'fecha' => 'fecha',
        'tipos_cobro_id' => 'tipo de cobro',
        'observaciones' => 'observaciones',
        'pagos' => 'pagos',
        'pagos.*.forma_pago_id' => 'forma de pago',
        'pagos.*.cuentas_bancarias_id' => 'cuenta bancaria',
        'pagos.*.monto' => 'monto',
        'pagos.*.referencia' => 'referencia',
        'recargo_monto' => 'monto del recargo',
        'recargo_forma_pago_id' => 'forma de pago del recargo',
        'recargo_cuentas_bancarias_id' => 'cuenta bancaria del recargo',
        'recargo_referencia' => 'referencia del recargo',
        'recargo_observaciones' => 'observaciones del recargo',
    ];

    public function guardar(): void
    {
        $data = $this->validate();

        DB::transaction(function () use ($data) {
            $this->recibo->loadMissing(['pagosDetalle', 'contrato']);

            $antes = [
                'fecha' => $this->recibo->fecha?->format('Y-m-d'),
                'tipos_cobro_id' => $this->recibo->tipos_cobro_id,
                'monto_total' => (float) $this->recibo->monto,
                'observaciones' => $this->recibo->observaciones ?? null,
                'periodo_id' => $this->recibo->periodo_id,
                'pagos' => $this->recibo->pagosDetalle->map(function ($p) {
                    return [
                        'id' => $p->id,
                        'orden' => $p->orden,
                        'forma_pago_id' => $p->forma_pago_id,
                        'cuenta_bancaria_id' => $p->cuenta_bancaria_id,
                        'monto' => (float) $p->monto,
                        'referencia' => $p->referencia,
                    ];
                })->values()->toArray(),
            ];

            $totalNuevo = collect($data['pagos'])->sum(fn ($p) => (float) $p['monto']);
            $primerPago = collect($data['pagos'])->first();

            $this->recibo->update([
                'fecha' => $data['fecha'],
                'tipos_cobro_id' => $data['tipos_cobro_id'],
                'monto' => $totalNuevo,
                'observaciones' => $data['observaciones'] ?? null,
                'forma_pago_id' => count($data['pagos']) === 1 ? ($primerPago['forma_pago_id'] ?? null) : null,
                'cuenta_bancaria_id' => count($data['pagos']) === 1 ? ($primerPago['cuentas_bancarias_id'] ?? null) : null,
            ]);

            foreach ($data['pagos'] as $pagoData) {
                $pago = $this->recibo->pagosDetalle()
                    ->where('id', $pagoData['id'])
                    ->firstOrFail();

                $pago->update([
                    'forma_pago_id' => $pagoData['forma_pago_id'],
                    'cuenta_bancaria_id' => $pagoData['cuentas_bancarias_id'] ?? null,
                    'monto' => $pagoData['monto'],
                    'referencia' => $pagoData['referencia'] ?? null,
                ]);
            }

            $this->recibo->refresh();
            $this->recibo->load(['pagosDetalle', 'contrato']);

            if (class_exists(ContratoHistorial::class)) {
                ContratoHistorial::create([
                    'contrato_id' => $this->recibo->contrato_id,
                    'user_id' => auth()->id(),
                    'tipo' => 'edicion_recibo',
                    'antes' => $antes,
                    'despues' => [
                        'fecha' => $this->recibo->fecha?->format('Y-m-d'),
                        'tipos_cobro_id' => $this->recibo->tipos_cobro_id,
                        'monto_total' => (float) $this->recibo->monto,
                        'observaciones' => $this->recibo->observaciones ?? null,
                        'periodo_id' => $this->recibo->periodo_id,
                        'pagos' => $this->recibo->pagosDetalle->map(function ($p) {
                            return [
                                'id' => $p->id,
                                'orden' => $p->orden,
                                'forma_pago_id' => $p->forma_pago_id,
                                'cuenta_bancaria_id' => $p->cuenta_bancaria_id,
                                'monto' => (float) $p->monto,
                                'referencia' => $p->referencia,
                            ];
                        })->values()->toArray(),
                    ],
                    'saldo_anterior' => (float) ($this->recibo->contrato?->saldo_actual ?? 0),
                    'saldo_nuevo' => (float) ($this->recibo->contrato?->saldo_actual ?? 0),
                    'motivo' => 'Edición manual de recibo',
                    'nota' => 'Se editaron datos del recibo y sus pagos detalle sin modificar el periodo.',
                ]);
            }
        });

        $this->dispatch('toast', type: 'success', message: 'Recibo actualizado correctamente.');

        $this->redirectRoute('admin.recibos.index', navigate: true);
    }

    public function getFormasPagoProperty()
    {
        return FormaPago::orderBy('nombre')->get();
    }

    public function getTiposCobroProperty()
    {
        return TipoCobro::orderBy('nombre')->get();
    }

    public function getCuentasProperty()
    {
        return CuentaBancaria::orderBy('alias')->get();
    }

    public function render()
    {
        return view('livewire.admin.recibos.edit')
            ->layout('layouts.app');
    }
}
