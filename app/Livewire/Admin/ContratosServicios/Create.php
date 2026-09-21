<?php

namespace App\Livewire\Admin\ContratosServicios;

use App\Models\Contrato;
use App\Models\CuentaBancaria;
use App\Models\Cuota;
use App\Models\FormaPago;
use App\Models\Promocion;
use App\Services\Contratos\ContratoPlanService;
use App\Services\Contratos\ContratoWordService;
use App\Services\Contratos\VentaContadoService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Create extends Component
{
    /**
     * Contratos de terreno a los que se les puede colgar un servicio.
     *
     * El terreno ya liquidado sigue siendo del cliente: puede contratar luz o
     * agua igual que uno que sigue pagando.
     */
    private const ESTATUS_BASE = ['activo', 'liquidado'];

    public int $step = 1;

    public string $contrato_q = '';

    public array $contratos_suggest = [];

    public ?int $contrato_base_id = null;

    public string $servicio_tipo = 'agua';

    public ?int $promocion_id = null;

    public string $fecha_inicio = '';

    public string $frecuencia = 'mensual';

    public ?int $dia_semana = null;

    public ?int $dia_mes = null;

    /** Venta de contado: el servicio se paga completo al firmar, sin cuotas. */
    public bool $es_contado = false;

    public ?int $contado_forma_pago_id = null;

    public ?int $contado_cuentas_bancarias_id = null;

    public ?string $contado_fecha = null;

    public ?string $contado_observaciones = null;

    public float $precio_total = 0.0;

    public float $enganche = 0.0;

    public float $saldo_inicial = 0.0;

    public float $saldo_actual = 0.0;

    public float $monto_pago = 0.0;

    public string $tipo_recargo = 'fijo';

    public float $valor_recargo = 0.0;

    public int $dias_gracia = 0;

    public string $diaSemanaTexto = '';

    public array $planPreview = [];

    public function getPromocionProperty(): ?Promocion
    {
        if (! $this->promocion_id) {
            return null;
        }

        return Promocion::query()->find($this->promocion_id);
    }

    public function getDiasSemanaProperty(): array
    {
        return ContratoPlanService::diasSemana();
    }

    public function mount(): void
    {
        $this->fecha_inicio = now()->toDateString();
        $this->frecuencia = 'mensual';
        $this->dia_mes = (int) now()->day;

        $this->actualizarDiaTextoYDefaults();
        $this->recalcular();
        $this->refreshPreview();
    }

    public function updatedContratoQ(): void
    {
        $q = trim($this->contrato_q);

        if ($q === '' || mb_strlen($q) < 2) {
            $this->contrato_base_id = null;
            $this->contratos_suggest = [];

            return;
        }

        $this->contratos_suggest = Contrato::query()
            ->with(['cliente', 'lote'])
            // Un lote ya pagado también puede contratar el servicio.
            ->whereIn('estatus', self::ESTATUS_BASE)
            ->where('tipo', 'terreno')
            ->where(function ($qq) use ($q) {
                $qq->where('folio_contrato', 'like', "%{$q}%")
                    ->orWhereHas('cliente', function ($c) use ($q) {
                        $c->where('nombres', 'like', "%{$q}%")
                            ->orWhere('apellidos', 'like', "%{$q}%");
                    });
            })
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(fn ($c) => [
                'id' => (int) $c->id,
                'label' => ($c->folio_contrato ?? 'SIN-FOLIO')
                    .' · '.($c->cliente->nombre_completo ?? 'Sin cliente')
                    .' · '.($c->lote->clave ?? 'Sin lote'),
                // Se marca el que ya está pagado para no confundirlo al elegir.
                'estatus' => $c->estatus === 'activo' ? null : mb_strtoupper((string) $c->estatus),
            ])
            ->toArray();
    }

    public function selectContratoBase(int $id): void
    {
        $this->contrato_base_id = $id;

        $c = Contrato::query()->with(['cliente', 'lote'])->find($id);
        $this->contrato_q = $c
            ? (($c->folio_contrato ?? '').' · '.($c->cliente->nombre_completo ?? '').' · '.($c->lote->clave ?? ''))
            : '';

        $this->contratos_suggest = [];

        $this->recalcular();
        $this->refreshPreview();
    }

    public function updatedFechaInicio(): void
    {
        $this->actualizarDiaTextoYDefaults();
        $this->refreshPreview();
    }

    public function updatedFrecuencia(): void
    {
        $this->actualizarDiaTextoYDefaults();
        $this->refreshPreview();
    }

    public function updated($key): void
    {
        if (in_array($key, [
            'contrato_base_id',
            'servicio_tipo',
            'promocion_id',
            'es_contado',
            'precio_total',
            'enganche',
            'monto_pago',
            'dia_semana',
            'dia_mes',
            'frecuencia',
            'tipo_recargo',
            'valor_recargo',
            'dias_gracia',
        ], true)) {
            $this->recalcular();
            $this->refreshPreview();
        }
    }

    protected function actualizarDiaTextoYDefaults(): void
    {
        if (! $this->fecha_inicio) {
            $this->diaSemanaTexto = '';

            return;
        }

        $d = Carbon::parse($this->fecha_inicio);
        $iso = $d->isoWeekday();

        $this->diaSemanaTexto = $this->diasSemana[$iso] ?? '';

        if ($this->frecuencia === 'semanal') {
            $this->dia_mes = null;
            $this->dia_semana = $this->dia_semana ?? $iso;
        } else {
            $this->dia_semana = null;
            $this->dia_mes = $this->dia_mes ?? (int) $d->day;
        }
    }

    /**
     * Datos del comprador para el contrato de servicio.
     *
     * Salen del cliente actual; si a ese cliente le falta algo, se cae a lo
     * que el contrato de terreno tenga guardado.
     *
     * @return array<string, string|null>
     */
    protected function datosDelComprador(Contrato $base): array
    {
        $delContrato = [
            'comprador_nombre_legal' => $base->comprador_nombre_legal,
            'comprador_curp' => $base->comprador_curp,
            'comprador_ine_frente' => $base->comprador_ine_frente,
            'comprador_ine_reverso' => $base->comprador_ine_reverso,
        ];

        $cliente = $base->cliente;

        if (! $cliente) {
            return $delContrato;
        }

        $datos = [];

        foreach ($cliente->datosComoComprador() as $campo => $valor) {
            $datos[$campo] = $valor ?: $delContrato[$campo];
        }

        return $datos;
    }

    protected function recalcular(): void
    {
        $this->precio_total = max(0, (float) $this->precio_total);

        if ($this->es_contado) {
            // Se paga completo al firmar: no hay saldo ni calendario.
            $this->enganche = 0;
            $this->saldo_inicial = 0;
            $this->saldo_actual = 0;
            $this->monto_pago = 0;
            $this->valor_recargo = 0;
            $this->dias_gracia = 0;
            $this->planPreview = [];

            return;
        }

        $this->enganche = max(0, (float) $this->enganche);
        $this->monto_pago = max(0, (float) $this->monto_pago);

        $this->saldo_inicial = round(max(0, $this->precio_total - $this->enganche), 2);
        $this->saldo_actual = $this->saldo_inicial;

        $this->valor_recargo = max(0, (float) $this->valor_recargo);
        $this->dias_gracia = max(0, (int) $this->dias_gracia);
    }

    protected function payloadContrato(): array
    {
        return [
            'fecha_inicio' => $this->fecha_inicio,
            'frecuencia' => $this->frecuencia,
            'dia_semana' => $this->frecuencia === 'semanal' ? $this->dia_semana : null,
            'dia_mes' => $this->frecuencia === 'mensual' ? $this->dia_mes : null,
            'precio_total' => $this->precio_total,
            'enganche' => $this->enganche,
            'saldo_inicial' => $this->saldo_inicial,
            'saldo_actual' => $this->saldo_actual,
            'monto_pago' => $this->monto_pago,
            'tipo_recargo' => $this->tipo_recargo,
            'valor_recargo' => $this->valor_recargo,
            'dias_gracia' => $this->dias_gracia,
        ];
    }

    protected function refreshPreview(): void
    {
        $this->planPreview = [];

        if ($this->es_contado) {
            return;
        }

        if (! $this->fecha_inicio || $this->saldo_inicial <= 0 || $this->monto_pago <= 0 || ! $this->contrato_base_id) {
            return;
        }

        try {
            $data = $this->payloadContrato();

            ContratoPlanService::aplicarPromocionEconomica($data, $this->promocion);

            $this->planPreview = array_slice(
                ContratoPlanService::generarCuotas($data, $this->promocion),
                0,
                12
            );
        } catch (\Throwable $e) {
            $this->planPreview = [];
        }
    }

    public function next(): void
    {
        $this->validateStep();
        $this->step = min(3, $this->step + 1);
    }

    public function back(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    protected function validateStep(): void
    {
        if ($this->step === 1) {
            $this->validate([
                'contrato_base_id' => ['required', 'exists:contratos,id'],
                'servicio_tipo' => ['required', Rule::in(['agua', 'electricidad'])],
                'fecha_inicio' => ['required', 'date'],
                'frecuencia' => ['required', Rule::in(['semanal', 'mensual'])],
                'promocion_id' => ['nullable'],
            ]);

            if ($this->frecuencia === 'semanal') {
                $this->validate(['dia_semana' => ['required', 'integer', 'min:1', 'max:7']]);
            } else {
                $this->validate(['dia_mes' => ['required', 'integer', 'min:1', 'max:31']]);
            }
        }

        if ($this->step === 2) {

            if ($this->es_contado) {

                // Solo importa el precio y como entro el dinero.
                $this->validate([
                    'precio_total' => ['required', 'numeric', 'min:0.01'],
                    'contado_forma_pago_id' => ['required', 'integer', 'exists:formas_pago,id'],
                    'contado_fecha' => ['required', 'date'],
                    'contado_cuentas_bancarias_id' => [
                        $this->contadoRequiereCuenta ? 'required' : 'nullable',
                        'integer',
                        'exists:cuentas_bancarias,id',
                    ],
                    'contado_observaciones' => ['nullable', 'string', 'max:255'],
                ], [], [
                    'contado_forma_pago_id' => 'forma de pago',
                    'contado_fecha' => 'fecha del pago',
                    'contado_cuentas_bancarias_id' => 'cuenta bancaria',
                ]);

                return;
            }

            $this->validate([
                'precio_total' => ['required', 'numeric', 'min:0.01'],
                'enganche' => ['required', 'numeric', 'min:0'],
                'monto_pago' => ['required', 'numeric', 'min:0.01'],
                'tipo_recargo' => ['required', Rule::in(['fijo', 'porcentaje'])],
                'valor_recargo' => ['required', 'numeric', 'min:0'],
                'dias_gracia' => ['required', 'integer', 'min:0'],
            ]);

            if ($this->enganche > $this->precio_total) {
                $this->addError('enganche', 'El enganche no puede ser mayor al precio total.');
            }
        }
    }

    protected function generarFolioServicio(Contrato $base): string
    {
        $sigla = $this->servicio_tipo === 'agua' ? 'AG' : 'EL';

        return ($base->folio_contrato ?? 'CT')."-{$sigla}-".now()->format('YmdHis');
    }

    public function getFormasPagoContadoProperty()
    {
        return FormaPago::query()->orderBy('nombre')->get(['id', 'nombre']);
    }

    public function getCuentasBancariasContadoProperty()
    {
        return CuentaBancaria::query()
            ->where('activa', true)
            ->orderBy('alias')
            ->get(['id', 'alias', 'banco']);
    }

    /** Las formas de pago que exigen decir a qué cuenta entró el dinero. */
    public function getContadoRequiereCuentaProperty(): bool
    {
        if (! $this->contado_forma_pago_id) {
            return false;
        }

        $nombre = mb_strtoupper((string) FormaPago::find($this->contado_forma_pago_id)?->nombre);

        return $nombre !== '' && ! str_contains($nombre, 'EFECTIVO');
    }

    public function guardar(
        ContratoWordService $contratoWordService,
        VentaContadoService $ventaContado,
    ) {
        $this->step = 1;
        $this->validateStep();
        $this->step = 2;
        $this->validateStep();

        $data = $this->payloadContrato();

        if (! $this->es_contado) {
            ContratoPlanService::aplicarPromocionEconomica($data, $this->promocion);
        }

        $contrato = null;

        DB::transaction(function () use ($data, &$contrato, $ventaContado) {
            $base = Contrato::query()->lockForUpdate()->findOrFail($this->contrato_base_id);

            $contrato = Contrato::query()->create(array_merge([
                'cliente_id' => $base->cliente_id,
                'lote_id' => $base->lote_id,
                'promocion_id' => $this->promocion_id,
                'folio_contrato' => $this->generarFolioServicio($base),

                'fecha_inicio' => $data['fecha_inicio'],
                'frecuencia' => $data['frecuencia'],
                'dia_semana' => $data['dia_semana'],
                'dia_mes' => $data['dia_mes'],
                'precio_total' => $data['precio_total'],
                'enganche' => $data['enganche'],
                'saldo_inicial' => $data['saldo_inicial'],
                'saldo_actual' => $data['saldo_actual'],
                'monto_pago' => $data['monto_pago'],
                'tipo_recargo' => $data['tipo_recargo'],
                'valor_recargo' => $data['valor_recargo'],
                'dias_gracia' => $data['dias_gracia'],
                'estatus' => 'activo',

                'tipo' => 'servicio',
                'servicio_tipo' => $this->servicio_tipo,
                'contrato_base_id' => $base->id,
                'archivo_contrato_disk' => 'private',

                'vendedor_nombre_legal' => $base->vendedor_nombre_legal,
                'vendedor_curp' => $base->vendedor_curp,
                // El comprador se toma del cliente de hoy, no de lo que el
                // contrato de terreno haya congelado en su momento: si ese
                // contrato cambió de cliente, su copia nombra al anterior.
                ...$this->datosDelComprador($base),

                'vendedor_ine_frente' => $base->vendedor_ine_frente,
                'vendedor_ine_reverso' => $base->vendedor_ine_reverso,
                'credenciales_disk' => $base->credenciales_disk,

                'area_m2_snapshot' => $base->area_m2_snapshot,
                'medida_norte_snapshot' => $base->medida_norte_snapshot,
                'medida_sur_snapshot' => $base->medida_sur_snapshot,
                'medida_este_snapshot' => $base->medida_este_snapshot,
                'medida_oeste_snapshot' => $base->medida_oeste_snapshot,
                'colindancia_norte_snapshot' => $base->colindancia_norte_snapshot,
                'colindancia_sur_snapshot' => $base->colindancia_sur_snapshot,
                'colindancia_este_snapshot' => $base->colindancia_este_snapshot,
                'colindancia_oeste_snapshot' => $base->colindancia_oeste_snapshot,
            ], $this->es_contado
                ? $ventaContado->camposDelContrato(
                    (float) $data['precio_total'],
                    Carbon::parse($this->contado_fecha),
                )
                : []
            ));

            if ($this->es_contado) {
                $ventaContado->generarRecibo($contrato, [
                    'forma_pago_id' => (int) $this->contado_forma_pago_id,
                    'cuentas_bancarias_id' => $this->contado_cuentas_bancarias_id,
                    'fecha' => $this->contado_fecha,
                    'observaciones' => $this->contado_observaciones,
                ], auth()->id());

                // Pagado al firmar: no hay calendario que generar.
                return;
            }

            $plan = ContratoPlanService::generarCuotas($data, $this->promocion);

            foreach ($plan as $row) {
                Cuota::query()->create([
                    'contrato_id' => $contrato->id,
                    'numero' => $row['numero'],
                    'fecha_vencimiento' => $row['fecha_vencimiento'],
                    'monto' => $row['monto'],
                    'pagado_total' => 0,
                    'condonado_total' => 0,
                    'recargo_aplicado' => 0,
                    'estatus' => 'pendiente',
                    'condonada' => 0,
                    'condonada_at' => null,
                ]);
            }
        });

        // El contrato de financiamiento eléctrico se deja listo desde la creación;
        // si la plantilla falla, el contrato ya quedó guardado y el Word se
        // vuelve a intentar al descargarlo desde la pantalla del contrato.
        if ($contrato && $this->servicio_tipo === 'electricidad') {
            try {
                $contrato->update([
                    'archivo_contrato_financiamiento_electrico_docx' => $contratoWordService->generarDocx(
                        $contrato,
                        'contrato_financiamiento_electrico'
                    ),
                    'archivo_contrato_disk' => 'private',
                ]);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $this->dispatch('toast', type: 'success', message: $this->es_contado
            ? 'Contrato de servicio de contado creado, con su recibo generado.'
            : 'Contrato de servicio creado y cuotas generadas.');

        return redirect()->route('admin.contratos-servicios.show', $contrato?->uuid);
    }

    public function render()
    {
        $promos = Promocion::query()
            ->where('activa', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'tipo', 'dias_diferidos', 'numero_cuotas']);

        return view('livewire.admin.contratos-servicios.create', [
            'promociones' => $promos,
        ])->layout('layouts.app');
    }
}
