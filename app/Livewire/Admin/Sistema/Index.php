<?php

namespace App\Livewire\Admin\Sistema;

use App\Services\Contratos\CondicionesCesionService;
use App\Services\Whatsapp\WhatsappProviderSelector;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class Index extends Component
{
    use AuthorizesRequests;

    public string $whatsappProveedor = WhatsappProviderSelector::OPENWA;

    /**
     * Condiciones con las que nacen los contratos de cesión de derechos.
     *
     * @var array<string, string>
     */
    public array $cesion = CondicionesCesionService::DEFAULTS;

    public function mount(WhatsappProviderSelector $selector): void
    {
        // Permiso para ver el módulo Sistema
        $this->authorize('sistema.ver');
        $this->whatsappProveedor = $selector->activo();
        $this->cesion = CondicionesCesionService::porDefecto();
    }

    public function guardarCondicionesCesion(): void
    {
        $this->authorize('sistema.ver');

        $this->validate([
            'cesion.posesion_entrega' => ['required', 'string', 'max:120'],
            'cesion.servicios_estado' => ['required', 'string', 'max:60'],
            'cesion.servicios_a_cargo' => ['required', 'string', 'max:60'],
            'cesion.parcialidades_rescision' => ['required', 'integer', 'min:1', 'max:99'],
            'cesion.tolerancia_medidas' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        CondicionesCesionService::guardarPorDefecto($this->cesion);

        activity()
            ->causedBy(auth()->user())
            ->withProperties($this->cesion)
            ->log('Condiciones del contrato de cesión actualizadas');

        $this->dispatch('toast', type: 'success', message: 'Condiciones del contrato actualizadas.');
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

    public function goOpenAiUsos(): void
    {
        $this->authorize('logs.ver');
        $this->redirectRoute('admin.sistema.openai-usos.index');
    }

    public function goPermisos()
    {
        return redirect()->route('admin.permisos.index');
    }

    public function guardarWhatsappProveedor(WhatsappProviderSelector $selector): void
    {
        $this->authorize('sistema.ver');

        if (! config('services.whatsapp.provider_switching_enabled', false)) {
            throw ValidationException::withMessages([
                'whatsappProveedor' => 'El cambio de integración está desactivado.',
            ]);
        }

        $this->validate([
            'whatsappProveedor' => [
                'required',
                Rule::in($selector->disponibles()),
            ],
        ]);

        if (! $this->proveedorConfigurado($this->whatsappProveedor)) {
            throw ValidationException::withMessages([
                'whatsappProveedor' => $this->whatsappProveedor === WhatsappProviderSelector::META
                    ? 'Completa las credenciales META_WHATSAPP_* en .env antes de activar Meta.'
                    : 'Completa OPENWA_API_KEY y OPENWA_SESSION_ID en .env antes de activar OpenWA.',
            ]);
        }

        $selector->seleccionar($this->whatsappProveedor);

        activity()
            ->causedBy(auth()->user())
            ->withProperties(['proveedor' => $this->whatsappProveedor])
            ->log('Proveedor de WhatsApp actualizado');

        $this->dispatch('toast', type: 'success', message: 'Integración de WhatsApp actualizada.');
    }

    public function render()
    {
        return view('livewire.admin.sistema.index', [
            'openWaConfigurado' => $this->proveedorConfigurado(WhatsappProviderSelector::OPENWA),
            'metaConfigurado' => $this->proveedorConfigurado(WhatsappProviderSelector::META),
            'metaHabilitado' => (bool) config('services.meta_whatsapp.enabled', false),
            'cambioWhatsappHabilitado' => (bool) config('services.whatsapp.provider_switching_enabled', false),
            'openWaWebhookUrl' => route('webhooks.openwa'),
            'metaWebhookUrl' => route('webhooks.meta.receive'),
        ])
            ->layout('layouts.app');
    }

    protected function proveedorConfigurado(string $proveedor): bool
    {
        if ($proveedor === WhatsappProviderSelector::META) {
            return filled(config('services.meta_whatsapp.access_token'))
                && filled(config('services.meta_whatsapp.phone_number_id'))
                && filled(config('services.meta_whatsapp.verify_token'))
                && filled(config('services.meta_whatsapp.app_secret'));
        }

        return filled(config('services.openwa.api_key'))
            && filled(config('services.openwa.session_id'))
            && filled(config('services.openwa.webhook_secret'));
    }
}
