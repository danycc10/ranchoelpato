<?php

namespace App\Livewire\Publico;

use App\Models\SolicitudDatosCliente;
use App\Services\Clientes\SolicitudDatosClienteService;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Formulario que abre el cliente con su liga temporal.
 *
 * Es la única pantalla del sistema sin sesión, así que solo conoce su propia
 * solicitud: no expone el catálogo ni permite tocar otro registro.
 */
class CapturaDatosCliente extends Component
{
    use WithFileUploads;

    public string $token = '';

    public ?SolicitudDatosCliente $solicitud = null;

    /** @var array<string, string|null> */
    public array $datos = [];

    public $ine_frente;

    public $ine_reverso;

    public bool $enviado = false;

    public function mount(string $token, SolicitudDatosClienteService $solicitudes): void
    {
        $this->token = $token;
        $this->solicitud = $solicitudes->abiertaPorToken($token);

        if (! $this->solicitud) {
            return;
        }

        // Arranca con lo que ya se sabe, para que solo complete lo que falte.
        $cliente = $this->solicitud->cliente;

        foreach (array_keys(SolicitudDatosClienteService::CAMPOS) as $campo) {
            $this->datos[$campo] = (string) ($cliente->{$campo} ?? '');
        }
    }

    public function getEtiquetasProperty(): array
    {
        return SolicitudDatosClienteService::CAMPOS;
    }

    public function getEstadosCivilesProperty(): array
    {
        return ['Soltero(a)', 'Casado(a)', 'Divorciado(a)', 'Viudo(a)', 'Unión libre'];
    }

    public function getIdentificacionesProperty(): array
    {
        return ['INE', 'Pasaporte', 'Cédula profesional', 'Licencia de conducir'];
    }

    /**
     * Avisa en cuanto eligen el archivo, sin esperar a que manden el
     * formulario: así se dan cuenta ahí mismo de que no era una foto.
     */
    public function updatedIneFrente(): void
    {
        $this->validateOnly('ine_frente', ['ine_frente' => ['nullable', 'image', 'max:8192']]);
    }

    public function updatedIneReverso(): void
    {
        $this->validateOnly('ine_reverso', ['ine_reverso' => ['nullable', 'image', 'max:8192']]);
    }

    public function quitarFrente(): void
    {
        $this->ine_frente = null;
    }

    public function quitarReverso(): void
    {
        $this->ine_reverso = null;
    }

    public function guardar(SolicitudDatosClienteService $solicitudes): void
    {
        if (! $this->solicitud || ! $this->solicitud->abierta()) {
            $this->solicitud = null;

            return;
        }

        $this->validate([
            'datos.nombre_legal' => ['required', 'string', 'max:255'],
            'datos.curp' => ['nullable', 'string', 'max:30'],
            'datos.rfc' => ['nullable', 'string', 'max:30'],
            'datos.telefono' => ['required', 'string', 'max:30'],
            'datos.correo' => ['nullable', 'email:filter', 'max:255'],
            'datos.direccion' => ['nullable', 'string', 'max:255'],
            'datos.nacionalidad' => ['nullable', 'string', 'max:60'],
            'datos.estado_civil' => ['nullable', 'string', 'max:60'],
            'datos.ocupacion' => ['nullable', 'string', 'max:60'],
            'datos.identificacion_tipo' => ['nullable', 'string', 'max:60'],
            'datos.identificacion_numero' => ['nullable', 'string', 'max:60'],
            'ine_frente' => ['nullable', 'image', 'max:8192'],
            'ine_reverso' => ['nullable', 'image', 'max:8192'],
        ], [], $this->nombresDeCampos());

        $base = 'clientes/'.$this->solicitud->cliente_id.'/identificacion';

        $solicitudes->guardarCaptura(
            $this->solicitud,
            $this->datos,
            $this->ine_frente?->store($base, 'private'),
            $this->ine_reverso?->store($base, 'private'),
        );

        $this->enviado = true;
    }

    /** @return array<string, string> */
    protected function nombresDeCampos(): array
    {
        $nombres = ['ine_frente' => 'foto del frente', 'ine_reverso' => 'foto del reverso'];

        foreach (SolicitudDatosClienteService::CAMPOS as $campo => $etiqueta) {
            $nombres['datos.'.$campo] = mb_strtolower($etiqueta);
        }

        return $nombres;
    }

    public function render()
    {
        return view('livewire.publico.captura-datos-cliente')
            ->layout('layouts.guest', ['title' => 'Completa tus datos']);
    }
}
