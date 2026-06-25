<?php

namespace App\Livewire\Admin\Promociones;

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
            ])],

            'form.dias_diferidos' => ['nullable', 'integer', 'min:1', 'max:365'],
            'form.numero_cuotas' => ['nullable', 'integer', 'min:1', 'max:500'],
            'form.porcentaje' => ['nullable', 'numeric', 'min:0.01', 'max:100'],
            'form.monto_fijo' => ['nullable', 'numeric', 'min:0.01'],

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

        Promocion::query()->updateOrCreate(
            ['id' => $this->editId],
            [
                'nombre' => $this->form['nombre'],
                'codigo' => $this->form['codigo'],
                'tipo' => $this->form['tipo'],

                'dias_diferidos' => $this->form['tipo'] === 'diferir_primer_pago' ? (int) $this->form['dias_diferidos'] : null,
                'numero_cuotas' => $this->form['tipo'] === 'cuotas_fijas' ? (int) $this->form['numero_cuotas'] : null,
                'porcentaje' => $this->form['tipo'] === 'descuento_porcentaje' ? (float) $this->form['porcentaje'] : null,
                'monto_fijo' => $this->form['tipo'] === 'descuento_fijo' ? (float) $this->form['monto_fijo'] : null,

                'activa' => (bool) ($this->form['activa'] ?? true),
                'fecha_inicio' => $this->form['fecha_inicio'] ?: null,
                'fecha_fin' => $this->form['fecha_fin'] ?: null,
            ]
        );

        $this->modal = false;
        $this->editId = null;

        $this->dispatch('toast', type: 'success', message: 'Promoción guardada.');
    }

    public function eliminar(int $id): void
    {
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
            ->orderBy($this->sortBy, $this->sortDir)
            ->paginate(12);

        return view('livewire.admin.promociones.index', [
            'rows' => $rows,
        ])->layout('layouts.app');
    }
}
