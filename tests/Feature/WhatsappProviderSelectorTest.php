<?php

namespace Tests\Feature;

use App\Services\Whatsapp\WhatsappProviderSelector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WhatsappProviderSelectorTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function usa_env_como_respaldo_y_persiste_la_eleccion_del_administrador(): void
    {
        config()->set('services.whatsapp.provider', 'openwa');
        config()->set('services.meta_whatsapp.enabled', true);
        $selector = app(WhatsappProviderSelector::class);

        $this->assertSame(WhatsappProviderSelector::OPENWA, $selector->activo());

        $selector->seleccionar(WhatsappProviderSelector::META);

        $this->assertSame(WhatsappProviderSelector::META, $selector->activo());
        $this->assertDatabaseHas('ajustes_sistema', [
            'clave' => 'whatsapp_provider',
            'valor' => 'meta',
        ]);
    }
}
