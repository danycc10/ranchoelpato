<?php

namespace App\Livewire\Admin\Roles;

use Livewire\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class Index extends Component
{
    public ?int $roleId = null;

    public array $permisosSeleccionados = [];

    protected function ensurePuedeEditar(): void
    {
        abort_unless(auth()->user()?->can('roles.editar'), 403);
    }

    public function seleccionarRol(int $id): void
    {
        abort_unless(auth()->user()?->can('roles.ver'), 403);

        $role = Role::findOrFail($id);
        $this->roleId = $role->id;
        $this->permisosSeleccionados = $role->permissions()->pluck('name')->values()->toArray();
    }

    public function guardar(): void
    {
        $this->ensurePuedeEditar();

        $role = Role::findOrFail($this->roleId);

        $permsValidos = Permission::query()
            ->whereIn('name', $this->permisosSeleccionados)
            ->pluck('name')
            ->toArray();

        $role->syncPermissions($permsValidos);

        $this->dispatch('toast', type: 'success', message: 'Permisos del rol actualizados.');
    }

    public function render()
    {
        abort_unless(auth()->user()?->can('roles.ver'), 403);

        return view('livewire.admin.roles.index', [
            'roles' => Role::orderBy('name')->get(),
            'permisos' => Permission::orderBy('name')->get(),
            'roleActual' => $this->roleId ? Role::with('permissions')->find($this->roleId) : null,
        ])->layout('layouts.app');
    }
}
