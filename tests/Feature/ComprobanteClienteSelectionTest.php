<?php

namespace Tests\Feature;

use App\Livewire\Admin\Comprobantes\Index;
use App\Models\Cliente;
use App\Models\ComprobantePago;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ComprobanteClienteSelectionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function bloquea_la_bandeja_cuando_esta_deshabilitada_en_el_admin(): void
    {
        config()->set('services.whatsapp.comprobantes_admin_enabled', false);

        Livewire::test(Index::class)->assertStatus(404);
    }

    #[Test]
    public function permite_vincular_manualmente_un_cliente_cuando_openwa_envia_un_lid_sin_telefono(): void
    {
        config()->set('services.whatsapp.comprobantes_admin_enabled', true);

        // Contrato usa SoftDeletes en el modelo; la migración histórica de la
        // base de pruebas no contiene aún esa columna que sí existe en operación.
        Schema::table('contratos', fn (Blueprint $table) => $table->softDeletes());

        $usuario = User::factory()->create();
        $cliente = Cliente::create([
            'nombres' => 'Daniel',
            'apellidos' => 'De la Cruz',
            'telefono' => '8781238424',
        ]);
        $comprobante = ComprobantePago::create([
            'wa_message_id' => 'wa-lid-sin-telefono',
            'wa_chat_id' => '123456789@lid',
            'telefono' => '',
            'recibido_en' => now(),
            'estatus' => ComprobantePago::ESTATUS_POR_VALIDAR,
            'monto_detectado' => 200,
            'fecha_detectada' => now()->toDateString(),
        ]);

        Livewire::actingAs($usuario)
            ->test(Index::class)
            ->call('abrir', $comprobante->id)
            ->set('clienteSearch', 'Daniel Cruz')
            ->assertSet('clientesResultados.0.id', $cliente->id)
            ->call('seleccionarClienteDesdeBusqueda', $cliente->id)
            ->assertSet('cliente_id', $cliente->id)
            ->assertSet('clienteSearch', 'Daniel De la Cruz');

        $this->assertDatabaseHas('comprobantes_pago', [
            'id' => $comprobante->id,
            'cliente_id' => $cliente->id,
            'telefono' => '8781238424',
        ]);
    }
}
