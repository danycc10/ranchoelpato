<?php

namespace App\Livewire\Admin\Periodos;

use App\Models\Periodo;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    // Tabla
    public string $q = '';

    public string $sortBy = 'anio';

    public string $sortDir = 'desc';

    // Modal / edición
    public bool $modal = false;

    public ?int $editId = null;

    // Campos
    public string $tipo = 'mensual'; // mensual|anual

    public int $anio = 2026;

    public ?int $mes = null;

    public string $nombre = '';

    protected $queryString = [
        'q' => ['except' => ''],
        'sortBy' => ['except' => 'anio'],
        'sortDir' => ['except' => 'desc'],
    ];

    public function mount(): void
    {
        // Default a año actual
        $this->anio = (int) now()->format('Y');
    }

    public function updatingQ(): void
    {
        $this->resetPage();
    }

    public function updatedTipo(string $value): void
    {
        // Si cambia a anual, limpia mes
        if ($value === 'anual') {
            $this->mes = null;
        }
    }

    public function sort(string $key): void
    {
        $allowed = ['id', 'tipo', 'anio', 'mes', 'nombre'];

        if (! in_array($key, $allowed, true)) {
            $key = 'anio';
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
        $it = Periodo::query()->findOrFail($id);

        $this->editId = (int) $it->id;
        $this->tipo = (string) $it->tipo;
        $this->anio = (int) $it->anio;
        $this->mes = $it->mes !== null ? (int) $it->mes : null;
        $this->nombre = (string) $it->nombre;

        $this->modal = true;
    }

    public function guardar(): void
    {
        $base = $this->validate([
            'tipo' => ['required', 'in:mensual,anual'],
            'anio' => ['required', 'integer', 'min:2000', 'max:2100'],
            'mes' => ['nullable', 'integer', 'min:1', 'max:12'],
            'nombre' => ['required', 'string', 'max:255'],
        ]);

        // Lógica de negocio:
        if ($base['tipo'] === 'mensual') {
            if ($base['mes'] === null) {
                $this->addError('mes', 'El mes es requerido cuando el tipo es mensual.');

                return;
            }
        } else {
            // anual
            $base['mes'] = null;
        }

        $base['nombre'] = trim($base['nombre']);

        if ($this->editId) {
            $it = Periodo::query()->findOrFail($this->editId);
            $it->update($base);
            $this->dispatch('toast', type: 'success', message: 'Actualizado correctamente.');
        } else {
            Periodo::query()->create($base);
            $this->dispatch('toast', type: 'success', message: 'Creado correctamente.');
        }

        $this->modal = false;
        $this->editId = null;
        $this->resetForm();
    }

    public function eliminar(int $id): void
    {
        // Si tiene recibos, no borrar
        $it = Periodo::query()->withCount('recibos')->findOrFail($id);
        if (($it->recibos_count ?? 0) > 0) {
            $this->dispatch('toast', type: 'error', message: 'No se puede eliminar: ya existe en recibos.');

            return;
        }

        $it->delete();
        $this->dispatch('toast', type: 'success', message: 'Eliminado correctamente.');
    }

    protected function resetForm(): void
    {
        $this->tipo = 'mensual';
        $this->anio = (int) now()->format('Y');
        $this->mes = null;
        $this->nombre = '';
        $this->resetErrorBag();
    }

    protected function applySearch(Builder $query): Builder
    {
        $term = trim($this->q);
        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $qq) use ($term) {
            $qq->where('nombre', 'like', "%{$term}%")
                ->orWhere('tipo', 'like', "%{$term}%")
                ->orWhere('anio', 'like', "%{$term}%")
                ->orWhere('mes', 'like', "%{$term}%");
        });
    }

    protected function applySort(Builder $query): Builder
    {
        $dir = strtolower($this->sortDir) === 'desc' ? 'desc' : 'asc';
        $key = $this->sortBy ?: 'anio';

        $allowed = ['id', 'tipo', 'anio', 'mes', 'nombre'];
        if (! in_array($key, $allowed, true)) {
            $key = 'anio';
        }

        return $query->orderBy($key, $dir);
    }

    public function render()
    {
        $query = Periodo::query();
        $query = $this->applySearch($query);
        $query = $this->applySort($query);

        return view('livewire.admin.periodos.index', [
            'items' => $query->paginate(10),
        ])->layout('layouts.app');
    }
}
