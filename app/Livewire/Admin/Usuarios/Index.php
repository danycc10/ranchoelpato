<?php

namespace App\Livewire\Admin\Usuarios;

use App\Models\User;
use App\Models\Propietario;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class Index extends Component
{
    use WithPagination;

    public string $q = '';

    public bool $modal = false;
    public ?int $userId = null;

    public ?string $roleSeleccionado = null;
    public array $permisosSeleccionados = [];

    public bool $mostrarPermisosRol = true;

    // ✅ NUEVO
    public ?int $propietario_id = null;

    public function updatingQ(): void
    {
        $this->resetPage();
    }

    protected function ensurePuedeEditar(): void
    {
        abort_unless(auth()->user()?->can('usuarios.editar'), 403);
    }

    public function abrir(int $userId): void
    {
        $this->ensurePuedeEditar();

        $user = User::query()->findOrFail($userId);

        $this->userId = $user->id;

        // ✅ cargar propietario
        $this->propietario_id = $user->propietario_id;

        $this->roleSeleccionado = $user->roles()->pluck('name')->first();

        $this->permisosSeleccionados = $user->permissions()->pluck('name')->values()->toArray();

        $this->modal = true;
    }

    public function cerrar(): void
    {
        $this->modal = false;
        $this->userId = null;
        $this->roleSeleccionado = null;
        $this->permisosSeleccionados = [];
        $this->propietario_id = null; // ✅ reset
    }

    public function guardar(): void
    {
        $this->ensurePuedeEditar();

        $this->validate([
            'userId' => ['required', 'integer', 'exists:users,id'],
            'roleSeleccionado' => ['nullable', 'string', 'exists:roles,name'],
            'permisosSeleccionados.*' => ['string', 'exists:permissions,name'],
            'propietario_id' => ['nullable', 'integer', 'exists:propietarios,id'], // ✅ nuevo
        ]);

        $user = User::query()->findOrFail($this->userId);

        // ✅ guardar propietario
        $user->propietario_id = $this->propietario_id;
        $user->save();

        $rolesValidos = [];
        if ($this->roleSeleccionado) {
            $rol = Role::query()
                ->where('name', $this->roleSeleccionado)
                ->first();

            if ($rol) {
                $rolesValidos = [$rol->name];
            }
        }

        $permsValidos = Permission::query()
            ->whereIn('name', $this->permisosSeleccionados)
            ->pluck('name')
            ->toArray();

        $user->syncRoles($rolesValidos);
        $user->syncPermissions($permsValidos);

        $user->load('roles', 'permissions');

        activity()
            ->causedBy(auth()->user())
            ->performedOn($user)
            ->withProperties([
                'role' => $this->roleSeleccionado,
                'permisos_directos' => $permsValidos,
                'propietario_id' => $this->propietario_id, // ✅ log
            ])
            ->log('Usuario actualizado');

        $this->dispatch('toast', type: 'success', message: 'Usuario actualizado correctamente.');

        $this->cerrar();
    }

    public function render()
    {
        abort_unless(auth()->user()?->can('usuarios.ver'), 403);

        $roles = Role::query()->orderBy('name')->get(['id', 'name']);
        $permisos = Permission::query()->orderBy('name')->get(['id', 'name']);

        // ✅ propietarios
        $propietarios = Propietario::query()
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        $users = User::query()
            ->when(trim($this->q) !== '', function ($q) {
                $term = trim($this->q);
                $q->where(function ($sub) use ($term) {
                    $sub->where('name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%");
                });
            })
            ->with(['roles', 'permissions'])
            ->orderBy('name')
            ->paginate(12);

        $userActual = null;
        $permisosEfectivos = [];

        if ($this->userId) {
            $userActual = User::with(['roles', 'permissions'])->find($this->userId);
            $permisosEfectivos = $userActual?->getAllPermissions()->pluck('name')->toArray() ?? [];
        }

        return view('livewire.admin.usuarios.index', [
            'users' => $users,
            'roles' => $roles,
            'permisos' => $permisos,
            'propietarios' => $propietarios, // ✅
            'userActual' => $userActual,
            'permisosEfectivos' => $permisosEfectivos,
        ])->layout('layouts.app');
    }
}