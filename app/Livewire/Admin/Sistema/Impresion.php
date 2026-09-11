<?php

namespace App\Livewire\Admin\Sistema;

use App\Enums\PrintingMode;
use App\Models\PrintAgent;
use App\Models\User;
use App\Services\Impresion\ModoImpresionSelector;
use App\Services\Impresion\PrintAgentPairingService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

/**
 * Equipos con impresión directa: el modo de impresión del sistema y la clave
 * que empareja la computadora de cada usuario con su agente local.
 */
class Impresion extends Component
{
    use AuthorizesRequests;

    public string $modoImpresion = PrintingMode::Browser->value;

    public ?int $usuarioId = null;

    public string $nombreEquipo = '';

    /** Se muestra una sola vez, al generarla: en la base solo queda su hash. */
    public ?string $claveGenerada = null;

    public ?string $claveDeUsuario = null;

    public function mount(ModoImpresionSelector $selector): void
    {
        $this->authorize('sistema.ver');

        $this->modoImpresion = $selector->activo()->value;
    }

    public function guardarModo(ModoImpresionSelector $selector): void
    {
        $this->authorize('sistema.ver');

        $selector->seleccionar($this->modoImpresion);

        activity()
            ->causedBy(auth()->user())
            ->withProperties(['modo' => $this->modoImpresion])
            ->log('Modo de impresión actualizado');

        $this->dispatch('toast', type: 'success', message: 'Modo de impresión actualizado.');
    }

    public function generarClave(PrintAgentPairingService $pairing): void
    {
        $this->authorize('sistema.ver');

        $this->validate([
            'usuarioId' => ['required', 'integer', 'exists:users,id'],
            'nombreEquipo' => ['nullable', 'string', 'max:80'],
        ], [], [
            'usuarioId' => 'usuario',
            'nombreEquipo' => 'nombre del equipo',
        ]);

        $usuario = User::findOrFail($this->usuarioId);

        $resultado = $pairing->pair($usuario, auth()->user(), $this->nombreEquipo ?: null);

        $this->claveGenerada = $resultado['token'];
        $this->claveDeUsuario = $usuario->name;
        $this->nombreEquipo = '';

        activity()
            ->causedBy(auth()->user())
            ->performedOn($usuario)
            ->withProperties(['agente' => $resultado['agent']->name])
            ->log('Clave de impresión generada');

        $this->dispatch(
            'toast',
            type: 'success',
            message: 'Clave generada. Cópiala ahora: no se vuelve a mostrar.'
        );
    }

    public function revocar(int $agentId, PrintAgentPairingService $pairing): void
    {
        $this->authorize('sistema.ver');

        $agente = PrintAgent::findOrFail($agentId);

        $pairing->revoke($agente);

        activity()
            ->causedBy(auth()->user())
            ->withProperties(['agente' => $agente->name])
            ->log('Equipo de impresión desactivado');

        $this->dispatch('toast', type: 'success', message: 'Equipo desactivado.');
    }

    public function olvidarClave(): void
    {
        $this->claveGenerada = null;
        $this->claveDeUsuario = null;
    }

    public function render()
    {
        return view('livewire.admin.sistema.impresion', [
            'agentes' => PrintAgent::with('user')->orderBy('name')->get(),
            'usuarios' => User::orderBy('name')->get(['id', 'name']),
            'modos' => PrintingMode::cases(),
        ])->layout('layouts.app');
    }
}
