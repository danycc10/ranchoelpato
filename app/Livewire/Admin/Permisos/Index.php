<?php

namespace App\Livewire\Admin\Permisos;

use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Permission;

class Index extends Component
{
    use WithPagination;

    protected string $paginationTheme = 'tailwind';

    public string $busqueda = '';

    public string $name = '';

    public string $guard_name = 'web';

    public bool $mostrarModalCrear = false;

    public ?int $permisoIdEditar = null;

    protected function rules(): array
    {
        return [
            'name' => 'required|string|min:2|max:255|unique:permissions,name',
            'guard_name' => 'required|string|max:255',
        ];
    }

    public function updatingBusqueda(): void
    {
        $this->resetPage();
    }

    public function abrirModalCrear(): void
    {
        $this->resetValidation();
        $this->reset(['name', 'permisoIdEditar']);
        $this->guard_name = 'web';
        $this->mostrarModalCrear = true;
    }

    public function cerrarModalCrear(): void
    {
        $this->mostrarModalCrear = false;
        $this->resetValidation();
        $this->reset(['name', 'permisoIdEditar']);
        $this->guard_name = 'web';
    }

    public function guardar(): void
    {
        $this->validate();

        Permission::create([
            'name' => trim($this->name),
            'guard_name' => $this->guard_name,
        ]);

        session()->flash('success', 'Permiso creado correctamente.');

        $this->cerrarModalCrear();
        $this->resetPage();
    }

    public function eliminar(int $id): void
    {
        $permiso = Permission::findOrFail($id);

        // Protección opcional para permisos críticos
        // if (in_array($permiso->name, ['super-admin'])) {
        //     session()->flash('error', 'Ese permiso no se puede eliminar.');
        //     return;
        // }

        $permiso->delete();

        session()->flash('success', 'Permiso eliminado correctamente.');
        $this->resetPage();
    }

    public function getPermisosProperty()
    {
        return Permission::query()
            ->when($this->busqueda !== '', function ($q) {
                $q->where('name', 'like', '%'.trim($this->busqueda).'%')
                    ->orWhere('guard_name', 'like', '%'.trim($this->busqueda).'%');
            })
            ->orderBy('name')
            ->paginate(12);
    }

    public function render()
    {
        return view('livewire.admin.permisos.index', [
            'permisos' => $this->permisos,
        ])->layout('layouts.app');
    }
}
