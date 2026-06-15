<?php

namespace App\Livewire\Admin\CuentasBancarias;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\CuentaBancaria;
use App\Models\Propietario;
use App\Models\Recibo;
use Illuminate\Database\Eloquent\Builder;

class Index extends Component
{
    use WithPagination;

    // Tabla
    public string $q = '';
    public string $sortBy = 'alias'; // default como tu CRUD genérico
    public string $sortDir = 'asc';

    // Modal / edición
    public bool $modal = false;
    public ?int $editId = null;

    // Campos
    public ?int $propietario_id = null;
    public string $alias = '';
    public ?string $banco = null;
    public ?string $tipo = null;
    public ?string $numero = null;
    public bool $activa = true;

    protected $queryString = [
        'q' => ['except' => ''],
        'sortBy' => ['except' => 'alias'],
        'sortDir' => ['except' => 'asc'],
    ];

    public function updatingQ(): void
    {
        $this->resetPage();
    }

    public function sort(string $key): void
    {
        $allowed = ['propietario', 'alias', 'banco', 'tipo', 'numero', 'activa', 'id'];

        if (! in_array($key, $allowed, true)) {
            $key = 'alias';
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
        $it = CuentaBancaria::query()->findOrFail($id);

        $this->editId = (int) $it->id;
        $this->propietario_id = (int) $it->propietario_id;
        $this->alias = (string) $it->alias;
        $this->banco = $it->banco;
        $this->tipo = $it->tipo;
        $this->numero = $it->numero;
        $this->activa = (bool) $it->activa;

        $this->modal = true;
    }

    public function guardar(): void
    {
        $data = $this->validate([
            'propietario_id' => ['required', 'exists:propietarios,id'],
            'alias' => ['required', 'string', 'max:255'],
            'banco' => ['nullable', 'string', 'max:255'],
            'tipo' => ['nullable', 'string', 'max:50'],
            'numero' => ['nullable', 'string', 'max:255'],
            'activa' => ['boolean'],
        ]);

        // Normaliza strings
        $data['alias'] = trim($data['alias']);
        foreach (['banco', 'tipo', 'numero'] as $k) {
            $data[$k] = is_string($data[$k] ?? null)
                ? (trim($data[$k]) !== '' ? trim($data[$k]) : null)
                : null;
        }

        if ($this->editId) {
            $it = CuentaBancaria::query()->findOrFail($this->editId);
            $it->update($data);
            $this->dispatch('toast', type: 'success', message: 'Actualizado correctamente.');
        } else {
            CuentaBancaria::query()->create($data);
            $this->dispatch('toast', type: 'success', message: 'Creado correctamente.');
        }

        $this->modal = false;
        $this->editId = null;
        $this->resetForm();
    }

    public function eliminar(int $id): void
    {
        // ✅ Evita borrar si ya se usó en recibos (tu FK real es cuentas_bancarias_id)
        $tieneRecibos = Recibo::query()->where('cuentas_bancarias_id', $id)->exists();
        if ($tieneRecibos) {
            $this->dispatch('toast', type: 'error', message: 'No se puede eliminar: ya existe en recibos.');
            return;
        }

        CuentaBancaria::query()->whereKey($id)->delete();
        $this->dispatch('toast', type: 'success', message: 'Eliminado correctamente.');
    }

    protected function resetForm(): void
    {
        $this->propietario_id = null;
        $this->alias = '';
        $this->banco = null;
        $this->tipo = null;
        $this->numero = null;
        $this->activa = true;
    }

    protected function applySearch(Builder $query): Builder
    {
        $term = trim($this->q);
        if ($term === '') return $query;

        return $query->where(function (Builder $qq) use ($term) {
            $qq->where('alias', 'like', "%{$term}%")
               ->orWhere('banco', 'like', "%{$term}%")
               ->orWhere('tipo', 'like', "%{$term}%")
               ->orWhere('numero', 'like', "%{$term}%")
               ->orWhereHas('propietario', fn (Builder $p) => $p->where('nombre', 'like', "%{$term}%"));
        });
    }

    protected function applySort(Builder $query): Builder
    {
        $dir = strtolower($this->sortDir) === 'desc' ? 'desc' : 'asc';
        $key = $this->sortBy ?: 'alias';

        // ✅ Orden por relación: propietario.nombre
        if ($key === 'propietario') {
            return $query
                ->leftJoin('propietarios', 'propietarios.id', '=', 'cuentas_bancarias.propietario_id')
                ->orderBy('propietarios.nombre', $dir)
                ->select('cuentas_bancarias.*');
        }

        $allowed = ['id', 'alias', 'banco', 'tipo', 'numero', 'activa'];
        if (! in_array($key, $allowed, true)) {
            $key = 'alias';
        }

        return $query->orderBy($key, $dir);
    }

    public function render()
    {
        $propietarios = Propietario::query()
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        $query = CuentaBancaria::query()->with('propietario');
        $query = $this->applySearch($query);
        $query = $this->applySort($query);

        return view('livewire.admin.cuentas-bancarias.index', [
            'items' => $query->paginate(10),
            'propietarios' => $propietarios,
        ])->layout('layouts.app');
    }
}
