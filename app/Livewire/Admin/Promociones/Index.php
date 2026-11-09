<?php

namespace App\Livewire\Admin\Promociones;

use App\Models\Fraccionamiento;
use App\Models\Promocion;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $q = '';

    public string $sortBy = 'nombre';

    public string $sortDir = 'asc';

    public bool $modal = false;

    public ?int $editId = null;

    /** @var array<string,mixed> */
    public array $form = [
        'nombre' => '',
        'codigo' => '',
        'tipo' => 'diferir_primer_pago',
        'dias_diferidos' => null,
        'numero_cuotas' => null,
        'porcentaje' => null,
        'monto_fijo' => null,
        'monto_pago_inicial' => null,
        'meses_pago_inicial' => null,
        'monto_pago_posterior' => null,
        'abono_por_bloque' => null,
        'bonificacion_por_bloque' => null,
        'fraccionamiento_ids' => [],
        'activa' => true,
        'fecha_inicio' => null,
        'fecha_fin' => null,
    ];

    protected $queryString = [
        'q' => ['except' => ''],
        'sortBy' => ['except' => 'nombre'],
        'sortDir' => ['except' => 'asc'],
    ];

    public function updatingQ(): void
    {
        $this->resetPage();
    }

    public function sort(string $field): void
    {
        if ($this->sortBy === $field) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';

            return;
        }

        $this->sortBy = $field;
        $this->sortDir = 'asc';
    }

    public function crear(): void
    {
        $this->resetValidation();
        $this->editId = null;
        $this->form = [
            'nombre' => '',
            'codigo' => '',
            'tipo' => 'diferir_primer_pago',
            'dias_diferidos' => null,
            'numero_cuotas' => null,
            'porcentaje' => null,
            'monto_fijo' => null,
            'monto_pago_inicial' => null,
            'meses_pago_inicial' => null,
            'monto_pago_posterior' => null,
            'abono_por_bloque' => null,
            'bonificacion_por_bloque' => null,
            'fraccionamiento_ids' => [],
            'activa' => true,
            'fecha_inicio' => null,
            'fecha_fin' => null,
        ];
        $this->modal = true;
    }

    public function editar(int $id): void
    {
        $this->resetValidation();

        $p = Promocion::query()->findOrFail($id);

        $this->editId = $p->id;
        $this->form = [
            'nombre' => (string) $p->nombre,
            'codigo' => (string) $p->codigo,
            'tipo' => (string) $p->tipo,
            'dias_diferidos' => $p->dias_diferidos,
            'numero_cuotas' => $p->numero_cuotas,
            'porcentaje' => $p->porcentaje,
            'monto_fijo' => $p->monto_fijo,
            'monto_pago_inicial' => $p->monto_pago_inicial,
            'meses_pago_inicial' => $p->meses_pago_inicial,
            'monto_pago_posterior' => $p->monto_pago_posterior,
            'abono_por_bloque' => $p->abono_por_bloque,
            'bonificacion_por_bloque' => $p->bonificacion_por_bloque,
            'fraccionamiento_ids' => $p->fraccionamientos()->pluck('fraccionamientos.id')->map(fn ($id) => (string) $id)->all(),
            'activa' => (bool) $p->activa,
            'fecha_inicio' => optional($p->fecha_inicio)->toDateString(),
            'fecha_fin' => optional($p->fecha_fin)->toDateString(),
        ];

        $this->modal = true;
    }

    public function updatedFormNombre(): void
    {
        // si no han escrito código, lo generamos en vivo
        if (! filled($this->form['codigo'] ?? null)) {
            $this->form['codigo'] = Str::slug($this->form['nombre'] ?? '');
        }
    }

    public function updatedFormTipo(): void
    {
        // limpia campos no usados según tipo
        $tipo = $this->form['tipo'] ?? '';

        if ($tipo !== 'diferir_primer_pago') {
            $this->form['dias_diferidos'] = null;
        }
        if ($tipo !== 'cuotas_fijas') {
            $this->form['numero_cuotas'] = null;
        }
        if ($tipo !== 'descuento_porcentaje') {
            $this->form['porcentaje'] = null;
        }
        if ($tipo !== 'descuento_fijo') {
            $this->form['monto_fijo'] = null;
        }
        if ($tipo !== 'pago_escalonado') {
            $this->form['monto_pago_inicial'] = null;
            $this->form['meses_pago_inicial'] = null;
            $this->form['monto_pago_posterior'] = null;
        }
        if ($tipo !== Promocion::ADELANTA_Y_GANA) {
            $this->form['abono_por_bloque'] = null;
            $this->form['bonificacion_por_bloque'] = null;
            $this->form['fraccionamiento_ids'] = [];
        }
    }

    protected function rules(): array
    {
        $id = $this->editId;

        return [
            'form.nombre' => ['required', 'string', 'max:255'],
            'form.codigo' => [
                'required',
                'string',
                'max:255',
                Rule::unique('promociones', 'codigo')->ignore($id),
            ],
            'form.tipo' => ['required', Rule::in([
                'diferir_primer_pago',
                'cuotas_fijas',
                'descuento_porcentaje',
                'descuento_fijo',
                'pago_escalonado',
                Promocion::ADELANTA_Y_GANA,
            ])],

            'form.dias_diferidos' => ['nullable', 'integer', 'min:1', 'max:365'],
            'form.numero_cuotas' => ['nullable', 'integer', 'min:1', 'max:500'],
            'form.porcentaje' => ['nullable', 'numeric', 'min:0.01', 'max:100'],
            'form.monto_fijo' => ['nullable', 'numeric', 'min:0.01'],
            'form.monto_pago_inicial' => ['nullable', 'numeric', 'min:0.01'],
            'form.meses_pago_inicial' => ['nullable', 'integer', 'min:1', 'max:120'],
            'form.monto_pago_posterior' => ['nullable', 'numeric', 'min:0.01'],
            'form.abono_por_bloque' => ['nullable', 'numeric', 'min:1'],
            'form.bonificacion_por_bloque' => ['nullable', 'numeric', 'min:0.01'],
            'form.fraccionamiento_ids' => ['array'],
            'form.fraccionamiento_ids.*' => ['integer', 'exists:fraccionamientos,id'],

            'form.activa' => ['boolean'],
            'form.fecha_inicio' => ['nullable', 'date'],
            'form.fecha_fin' => ['nullable', 'date', 'after_or_equal:form.fecha_inicio'],
        ];
    }

    protected function validarTipoCampos(): void
    {
        $tipo = $this->form['tipo'] ?? '';

        if ($tipo === 'diferir_primer_pago' && ! filled($this->form['dias_diferidos'])) {
            $this->addError('form.dias_diferidos', 'Requerido para diferir primer pago.');
        }

        if ($tipo === 'cuotas_fijas' && ! filled($this->form['numero_cuotas'])) {
            $this->addError('form.numero_cuotas', 'Requerido para cuotas fijas.');
        }

        if ($tipo === 'descuento_porcentaje' && ! filled($this->form['porcentaje'])) {
            $this->addError('form.porcentaje', 'Requerido para descuento por porcentaje.');
        }

        if ($tipo === 'descuento_fijo' && ! filled($this->form['monto_fijo'])) {
            $this->addError('form.monto_fijo', 'Requerido para descuento fijo.');
        }

        if ($tipo === 'pago_escalonado') {
            foreach ([
                'monto_pago_inicial' => 'Requerido: lo que paga durante la promoción.',
                'meses_pago_inicial' => 'Requerido: cuántos meses dura la promoción.',
                'monto_pago_posterior' => 'Requerido: lo que paga después de la promoción.',
            ] as $campo => $mensaje) {
                if (! filled($this->form[$campo] ?? null)) {
                    $this->addError('form.'.$campo, $mensaje);
                }
            }
        }

        if ($tipo === Promocion::ADELANTA_Y_GANA) {
            foreach ([
                'abono_por_bloque' => 'Requerido: cada cuánto abono se gana la bonificación.',
                'bonificacion_por_bloque' => 'Requerido: cuánto se bonifica por cada bloque.',
            ] as $campo => $mensaje) {
                if (! filled($this->form[$campo] ?? null)) {
                    $this->addError('form.'.$campo, $mensaje);
                }
            }

            if (empty($this->form['fraccionamiento_ids'])) {
                $this->addError('form.fraccionamiento_ids', 'Marca al menos un fraccionamiento.');
            }
        }
    }

    public function guardar(): void
    {
        // ✅ si viene vacío, lo generamos SÍ o SÍ para evitar tu error
        $this->form['codigo'] = trim((string) ($this->form['codigo'] ?? ''));
        if ($this->form['codigo'] === '') {
            $this->form['codigo'] = Str::slug((string) ($this->form['nombre'] ?? ''));
        }

        $this->validate();
        $this->validarTipoCampos();

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        $esAdelantaYGana = $this->form['tipo'] === Promocion::ADELANTA_Y_GANA;

        $promocion = Promocion::query()->updateOrCreate(
            ['id' => $this->editId],
            [
                'nombre' => $this->form['nombre'],
                'codigo' => $this->form['codigo'],
                'tipo' => $this->form['tipo'],

                'dias_diferidos' => $this->form['tipo'] === 'diferir_primer_pago' ? (int) $this->form['dias_diferidos'] : null,
                'numero_cuotas' => $this->form['tipo'] === 'cuotas_fijas' ? (int) $this->form['numero_cuotas'] : null,
                'porcentaje' => $this->form['tipo'] === 'descuento_porcentaje' ? (float) $this->form['porcentaje'] : null,
                'monto_fijo' => $this->form['tipo'] === 'descuento_fijo' ? (float) $this->form['monto_fijo'] : null,

                'monto_pago_inicial' => $this->form['tipo'] === 'pago_escalonado' ? (float) $this->form['monto_pago_inicial'] : null,
                'meses_pago_inicial' => $this->form['tipo'] === 'pago_escalonado' ? (int) $this->form['meses_pago_inicial'] : null,
                'monto_pago_posterior' => $this->form['tipo'] === 'pago_escalonado' ? (float) $this->form['monto_pago_posterior'] : null,

                'abono_por_bloque' => $esAdelantaYGana ? (float) $this->form['abono_por_bloque'] : null,
                'bonificacion_por_bloque' => $esAdelantaYGana ? (float) $this->form['bonificacion_por_bloque'] : null,

                'activa' => (bool) ($this->form['activa'] ?? true),
                'fecha_inicio' => $this->form['fecha_inicio'] ?: null,
                'fecha_fin' => $this->form['fecha_fin'] ?: null,
            ]
        );

        $promocion->fraccionamientos()->sync(
            $esAdelantaYGana ? array_map('intval', $this->form['fraccionamiento_ids']) : []
        );

        $this->modal = false;
        $this->editId = null;

        $this->dispatch('toast', type: 'success', message: 'Promoción guardada.');
    }

    public function eliminar(int $id): void
    {
        // Al borrarla, sus abonos se quedarían sin promoción y se perdería
        // cuántas veces se usó: la que ya se cobró se desactiva, no se borra.
        if (Promocion::query()->whereKey($id)->whereHas('abonosCapital')->exists()) {
            $this->dispatch('toast', type: 'error', message: 'Esta promoción ya se usó en recibos. Desactívala en lugar de eliminarla.');

            return;
        }

        Promocion::query()->whereKey($id)->delete();
        $this->dispatch('toast', type: 'success', message: 'Promoción eliminada.');
    }

    public function render()
    {
        $rows = Promocion::query()
            ->when($this->q !== '', function ($q) {
                $term = '%'.$this->q.'%';
                $q->where('nombre', 'like', $term)
                    ->orWhere('codigo', 'like', $term)
                    ->orWhere('tipo', 'like', $term);
            })
            // Cuántas veces se cobró cada una; los anulados no cuentan. Solo
            // las de cobranza (Adelanta y gana) generan abonos.
            ->withCount(['abonosCapital as usos' => fn ($q) => $q->whereNull('anulado_at')])
            ->withSum(['abonosCapital as abonado' => fn ($q) => $q->whereNull('anulado_at')], 'monto_abono')
            ->withSum(['abonosCapital as bonificado' => fn ($q) => $q->whereNull('anulado_at')], 'monto_bonificacion')
            ->orderBy($this->sortBy, $this->sortDir)
            ->paginate(12);

        return view('livewire.admin.promociones.index', [
            'rows' => $rows,
            'fraccionamientos' => $this->modal && ($this->form['tipo'] ?? '') === Promocion::ADELANTA_Y_GANA
                ? Fraccionamiento::query()->orderBy('nombre')->get(['id', 'nombre'])
                : collect(),
        ])->layout('layouts.app');
    }
}
