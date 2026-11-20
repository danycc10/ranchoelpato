<?php

namespace Tests\Feature;

use App\Livewire\Admin\Clientes\Index as ClientesIndex;
use App\Models\Cliente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClientesInactivosFiltroTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function la_lista_esconde_los_desactivados_hasta_que_se_piden(): void
    {
        Cliente::create(['nombres' => 'Dora', 'apellidos' => 'Activa', 'estatus' => 'activo']);
        Cliente::create(['nombres' => 'Rosa', 'apellidos' => 'Apagada', 'estatus' => 'inactivo']);

        Livewire::actingAs($this->capturista())
            ->test(ClientesIndex::class)
            ->assertSee('Activa')
            ->assertDontSee('Apagada')
            ->set('verInactivos', true)
            ->assertSee('Activa')
            ->assertSee('Apagada');
    }

    #[Test]
    public function al_desactivar_un_cliente_sale_de_la_lista(): void
    {
        $cliente = Cliente::create(['nombres' => 'Dora', 'apellidos' => 'Activa', 'estatus' => 'activo']);

        Livewire::actingAs($this->capturista())
            ->test(ClientesIndex::class)
            ->assertSee('Activa')
            ->call('toggleEstatus', $cliente->id)
            ->assertDontSee('Activa')
            ->set('verInactivos', true)
            ->assertSee('Activa');

        $this->assertSame('inactivo', $cliente->fresh()->estatus);
    }

    private function capturista(): User
    {
        $usuario = User::factory()->create();

        $rol = Role::findOrCreate('admin', 'web');

        foreach (['admin.ver', 'clientes.ver', 'clientes.editar'] as $permiso) {
            $rol->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
        }

        return tap($usuario)->assignRole($rol);
    }
}
