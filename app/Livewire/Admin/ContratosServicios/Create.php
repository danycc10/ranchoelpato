<?php

namespace App\Livewire\Admin\ContratosServicios;

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

use App\Models\Contrato;
use App\Models\Cuota;
use App\Models\Promocion;
use App\Services\Contratos\ContratoPlanService;

class Create extends Component
{
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
        if (!$this->promocion_id) return null;
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
            ->with(['cliente','lote'])
            ->where('estatus', 'activo')
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
                'label' =>
                    ($c->folio_contrato ?? 'SIN-FOLIO')
                    . ' · ' . ($c->cliente->nombre_completo ?? 'Sin cliente')
                    . ' · ' . ($c->lote->clave ?? 'Sin lote'),
            ])
            ->toArray();
    }

    public function selectContratoBase(int $id): void
    {
        $this->contrato_base_id = $id;

        $c = Contrato::query()->with(['cliente','lote'])->find($id);
        $this->contrato_q = $c
            ? (($c->folio_contrato ?? '') . ' · ' . ($c->cliente->nombre_completo ?? '') . ' · ' . ($c->lote->clave ?? ''))
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

    protected function recalcular(): void
    {
        $this->precio_total = max(0, (float) $this->precio_total);
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
                'servicio_tipo' => ['required', Rule::in(['agua','electricidad'])],
                'fecha_inicio' => ['required', 'date'],
                'frecuencia' => ['required', Rule::in(['semanal','mensual'])],
                'promocion_id' => ['nullable'],
            ]);

            if ($this->frecuencia === 'semanal') {
                $this->validate(['dia_semana' => ['required','integer','min:1','max:7']]);
            } else {
                $this->validate(['dia_mes' => ['required','integer','min:1','max:31']]);
            }
        }

        if ($this->step === 2) {
            $this->validate([
                'precio_total' => ['required','numeric','min:0.01'],
                'enganche' => ['required','numeric','min:0'],
                'monto_pago' => ['required','numeric','min:0.01'],
                'tipo_recargo' => ['required', Rule::in(['fijo','porcentaje'])],
                'valor_recargo' => ['required','numeric','min:0'],
                'dias_gracia' => ['required','integer','min:0'],
            ]);

            if ($this->enganche > $this->precio_total) {
                $this->addError('enganche', 'El enganche no puede ser mayor al precio total.');
            }
        }
    }

    protected function generarFolioServicio(Contrato $base): string
    {
        $sigla = $this->servicio_tipo === 'agua' ? 'AG' : 'EL';
        return ($base->folio_contrato ?? 'CT') . "-{$sigla}-" . now()->format('YmdHis');
    }

    public function guardar()
    {
        $this->step = 1; $this->validateStep();
        $this->step = 2; $this->validateStep();

        $data = $this->payloadContrato();
        ContratoPlanService::aplicarPromocionEconomica($data, $this->promocion);

        DB::transaction(function () use ($data) {
            $base = Contrato::query()->lockForUpdate()->findOrFail($this->contrato_base_id);

            $contrato = Contrato::query()->create([
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
            ]);

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

        $this->dispatch('toast', type: 'success', message: 'Contrato de servicio creado y cuotas generadas.');
        return redirect()->to(route('admin.contratos-servicios.index'));
    }

    public function render()
    {
        $promos = Promocion::query()
            ->where('activa', true)
            ->orderBy('nombre')
            ->get(['id','nombre','tipo','dias_diferidos','numero_cuotas']);

        return view('livewire.admin.contratos-servicios.create', [
            'promociones' => $promos,
        ])->layout('layouts.app');
    }
}
