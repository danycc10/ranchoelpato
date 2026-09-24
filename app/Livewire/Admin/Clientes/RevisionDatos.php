<?php

namespace App\Livewire\Admin\Clientes;

use App\Models\SolicitudDatosCliente;
use App\Services\Clientes\SolicitudDatosClienteService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Lo que los clientes capturaron con su liga, en espera de revisión.
 *
 * Nada de esto tocó el catálogo todavía: aquí se compara contra lo que ya se
 * tenía y se decide qué pasa.
 */
class RevisionDatos extends Component
{
    use AuthorizesRequests, WithPagination;

    /** Campos elegidos por solicitud: [solicitud_id => [campo, ...]] */
    public array $seleccion = [];

    public function mount(): void
    {
        $this->authorize('clientes.ver');
    }

    /**
     * Lo capturado contra lo que ya tenía el cliente, campo por campo.
     *
     * @return array<int, array{campo: string, etiqueta: string, actual: ?string, nuevo: string, cambia: bool}>
     */
    public function comparacion(SolicitudDatosCliente $solicitud): array
    {
        $filas = [];

        foreach ((array) $solicitud->datos as $campo => $nuevo) {
            $etiqueta = SolicitudDatosClienteService::CAMPOS[$campo] ?? $campo;
            $actual = $solicitud->cliente?->{$campo};

            $filas[] = [
                'campo' => $campo,
                'etiqueta' => $etiqueta,
                'actual' => $actual,
                'nuevo' => $nuevo,
                // Lo que ya coincide no vale la pena revisarlo.
                'cambia' => trim((string) $actual) !== trim((string) $nuevo),
            ];
        }

        return $filas;
    }

    /** Arranca con todo lo que cambia palomeado. */
    public function camposPorDefecto(SolicitudDatosCliente $solicitud): array
    {
        $campos = collect($this->comparacion($solicitud))
            ->where('cambia', true)
            ->pluck('campo')
            ->all();

        if ($solicitud->ine_frente) {
            $campos[] = 'ine_frente';
        }

        if ($solicitud->ine_reverso) {
            $campos[] = 'ine_reverso';
        }

        return $campos;
    }

    public function aplicar(int $solicitudId, SolicitudDatosClienteService $solicitudes): void
    {
        $this->authorize('clientes.ver');

        $solicitud = SolicitudDatosCliente::with('cliente')->findOrFail($solicitudId);

        $campos = $this->seleccion[$solicitudId] ?? $this->camposPorDefecto($solicitud);

        if (empty($campos)) {
            $this->dispatch('toast', type: 'warning', message: 'No elegiste ningún dato que aplicar.');

            return;
        }

        $cliente = $solicitudes->aplicar($solicitud, auth()->user(), $campos);

        activity()
            ->causedBy(auth()->user())
            ->performedOn($cliente)
            ->withProperties(['campos' => $campos])
            ->log('Aplicó los datos que capturó el cliente');

        unset($this->seleccion[$solicitudId]);

        $this->dispatch('toast', type: 'success', message: 'Datos aplicados a '.$cliente->nombre_completo.'.');
    }

    public function descartar(int $solicitudId, SolicitudDatosClienteService $solicitudes): void
    {
        $this->authorize('clientes.ver');

        $solicitud = SolicitudDatosCliente::findOrFail($solicitudId);

        $solicitudes->cancelar($solicitud, 'Descartada en la revisión.');

        $this->dispatch('toast', type: 'success', message: 'Solicitud descartada.');
    }

    public function render()
    {
        $solicitudes = SolicitudDatosCliente::query()
            ->with('cliente')
            ->porRevisar()
            ->latest('capturada_en')
            ->paginate(10);

        // La selección tiene que existir de verdad, no solo verse palomeada:
        // wire:model manda sobre el atributo checked del HTML, así que si el
        // arreglo estuviera vacío se aplicaría algo distinto de lo que se ve.
        foreach ($solicitudes as $solicitud) {
            if (! array_key_exists($solicitud->id, $this->seleccion)) {
                $this->seleccion[$solicitud->id] = $this->camposPorDefecto($solicitud);
            }
        }

        return view('livewire.admin.clientes.revision-datos', [
            'solicitudes' => $solicitudes,
        ])->layout('layouts.app');
    }
}
