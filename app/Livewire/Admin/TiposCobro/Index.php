<?php

namespace App\Livewire\Admin\TiposCobro;

use App\Models\Propietario;
use App\Models\TipoCobro;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    // Tabla
    public string $q = '';

    public string $sortBy = 'id';

    public string $sortDir = 'asc';

    // Modal / edición
    public bool $modal = false;

    public ?int $editId = null;

    // Campos
    public string $nombre = '';

    public ?string $categoria = null;

    public bool $requiere_periodo = false;

    public bool $activa = true;

    public ?int $propietario_contable_id = null;

    protected $queryString = [
        'q' => ['except' => ''],
        'sortBy' => ['except' => 'id'],
        'sortDir' => ['except' => 'asc'],
    ];

    public function updatingQ(): void
    {
        $this->resetPage();
    }

    public function sort(string $key): void
    {
        $allowed = [
            'id',
            'nombre',
            'categoria',
            'requiere_periodo',
            'activa',
        ];

        if (! in_array($key, $allowed, true)) {
            $key = 'id';
        }

        if ($this->sortBy === $key) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $key;
            $this->sortDir = 'asc';
        }

        $this->resetPage();
    }

    public function crear(): void
    {
        $this->editId = null;
        $this->resetForm();
        $this->modal = true;
    }

    public function editar(int $id): void
    {
        $it = TipoCobro::query()->findOrFail($id);

        $this->editId = (int) $it->id;
        $this->nombre = (string) $it->nombre;
        $this->categoria = $it->categoria;
        $this->requiere_periodo = (bool) $it->requiere_periodo;
        $this->activa = (bool) $it->activa;
        $this->propietario_contable_id = $it->propietario_contable_id
            ? (int) $it->propietario_contable_id
            : null;

        $this->modal = true;
    }

    public function guardar(): void
    {
        $data = $this->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'categoria' => ['nullable', 'string', 'max:255'],
            'requiere_periodo' => ['boolean'],
            'activa' => ['boolean'],
            'propietario_contable_id' => ['nullable', 'integer', 'exists:propietarios,id'],
        ]);

        // Normaliza strings
        $data['nombre'] = trim($data['nombre']);
        $data['categoria'] = is_string($data['categoria'] ?? null)
            ? (trim($data['categoria']) !== '' ? trim($data['categoria']) : null)
            : null;

        if ($this->editId) {
            $it = TipoCobro::query()->findOrFail($this->editId);
            $it->update($data);

            $this->dispatch('toast', type: 'success', message: 'Actualizado correctamente.');
        } else {
            TipoCobro::query()->create($data);

            $this->dispatch('toast', type: 'success', message: 'Creado correctamente.');
        }

        $this->modal = false;
        $this->editId = null;
        $this->resetForm();
    }

    public function eliminar(int $id): void
    {
        TipoCobro::query()->whereKey($id)->delete();
        $this->dispatch('toast', type: 'success', message: 'Eliminado correctamente.');
    }

    protected function resetForm(): void
    {
        $this->nombre = '';
        $this->categoria = null;
        $this->requiere_periodo = false;
        $this->activa = true;
        $this->propietario_contable_id = null;
    }

    protected function applySearch(Builder $query): Builder
    {
        $term = trim($this->q);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $qq) use ($term) {
            $qq->where('nombre', 'like', "%{$term}%")
                ->orWhere('categoria', 'like', "%{$term}%")
                ->orWhereHas('propietarioContable', function (Builder $qp) use ($term) {
                    $qp->where('nombre', 'like', "%{$term}%");
                });
        });
    }

    protected function applySort(Builder $query): Builder
    {
        $key = $this->sortBy ?: 'id';
        $dir = strtolower($this->sortDir) === 'desc' ? 'desc' : 'asc';

        $allowed = ['id', 'nombre', 'categoria', 'requiere_periodo', 'activa'];
        if (! in_array($key, $allowed, true)) {
            $key = 'id';
        }

        return $query->orderBy($key, $dir);
    }

    public function render()
    {
        $query = TipoCobro::query()
            ->with('propietarioContable');

        $query = $this->applySearch($query);
        $query = $this->applySort($query);

        return view('livewire.admin.tipos-cobro.index', [
            'items' => $query->paginate(10),
            'propietarios' => Propietario::query()
                ->orderBy('nombre')
                ->get(['id', 'nombre']),
        ])->layout('layouts.app');
    }
}
