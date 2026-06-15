<?php

namespace App\Livewire\Admin\Sistema;

use Livewire\Component;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class Index extends Component
{
    use AuthorizesRequests;

    public function mount(): void
    {
        // Permiso para ver el módulo Sistema
        $this->authorize('sistema.ver');
    }

    public function goUsuarios(): void
    {
        $this->authorize('usuarios.ver');
        $this->redirectRoute('admin.usuarios.index');
    }

    public function goRoles(): void
    {
        $this->authorize('roles.ver');
        $this->redirectRoute('admin.roles.index');
    }

    public function goLogs(): void
    {
        $this->authorize('logs.ver');
        $this->redirectRoute('admin.logs.index');
    }
    
        public function goPermisos()
{
    return redirect()->route('admin.permisos.index');
}

    public function render()
    {
        return view('livewire.admin.sistema.index')
            ->layout('layouts.app');
    }
}
