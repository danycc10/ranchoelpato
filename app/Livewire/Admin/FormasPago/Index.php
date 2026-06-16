<?php

namespace App\Livewire\Admin\FormasPago;

use App\Models\FormaPago;
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

    // Campos (ya NO usamos $form genérico)
    public string $nombre = '';

    public bool $requiere_cuenta = false;

    public bool $activa = true;

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
        $allowed = ['id', 'nombre', 'requiere_cuenta', 'activa'];

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
        $it = FormaPago::query()->findOrFail($id);

        $this->editId = (int) $it->id;
        $this->nombre = (string) $it->nombre;
        $this->requiere_cuenta = (bool) $it->requiere_cuenta;
        $this->activa = (bool) $it->activa;

        $this->modal = true;
    }

    public function guardar(): void
    {
        $data = $this->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'requiere_cuenta' => ['boolean'],
            'activa' => ['boolean'],
        ]);

        $data['nombre'] = trim($data['nombre']);

        if ($this->editId) {
            $it = FormaPago::query()->findOrFail($this->editId);
            $it->update($data);

            $this->dispatch('toast', type: 'success', message: 'Actualizado correctamente.');
        } else {
            FormaPago::query()->create($data);

            $this->dispatch('toast', type: 'success', message: 'Creado correctamente.');
        }

        $this->modal = false;
        $this->editId = null;
        $this->resetForm();
    }

    public function eliminar(int $id): void
    {
        // Si quieres "proteger" cuando ya tenga recibos, aquí es el lugar:
        // $it = FormaPago::query()->withCount('recibos')->findOrFail($id);
        // if ($it->recibos_count > 0) { ... return; }

        FormaPago::query()->whereKey($id)->delete();
        $this->dispatch('toast', type: 'success', message: 'Eliminado correctamente.');
    }

    protected function resetForm(): void
    {
        $this->nombre = '';
        $this->requiere_cuenta = false;
        $this->activa = true;
    }

    protected function applySearch(Builder $query): Builder
    {
        $term = trim($this->q);
        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $qq) use ($term) {
            $qq->where('nombre', 'like', "%{$term}%");
        });
    }

    protected function applySort(Builder $query): Builder
    {
        $key = $this->sortBy ?: 'id';
        $dir = strtolower($this->sortDir) === 'desc' ? 'desc' : 'asc';

        $allowed = ['id', 'nombre', 'requiere_cuenta', 'activa'];
        if (! in_array($key, $allowed, true)) {
            $key = 'id';
        }

        return $query->orderBy($key, $dir);
    }

    public function render()
    {
        $query = FormaPago::query();
        $query = $this->applySearch($query);
        $query = $this->applySort($query);

        return view('livewire.admin.formas-pago.index', [
            'items' => $query->paginate(10),
        ])->layout('layouts.app');
    }
}
