<?php

namespace Tests\Feature;

use App\Livewire\Admin\Clientes\Index as ClientesIndex;
use App\Models\Cliente;
use App\Models\User;
use App\Services\Clientes\ClientePorTelefonoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Un cliente con varios teléfonos.
 *
 * El principal se queda en `clientes.telefono`, que es el que leen contratos,
 * plantillas y reportes. Los demás viven aparte, para que no haya dos lugares
 * diciendo cuál es el de siempre.
 */
class ClienteVariosTelefonosTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function se_le_pueden_guardar_telefonos_de_mas(): void
    {
        Livewire::actingAs($this->capturista())
            ->test(ClientesIndex::class)
            ->call('crear')
            ->set('form.nombres', 'Dora')
            ->set('form.apellidos', 'Cardona')
            ->set('form.telefono', '8781112233')
            ->call('agregarTelefono')
            ->set('telefonosExtra.0.telefono', '8784445566')
            ->set('telefonosExtra.0.uso', 'whatsapp')
            ->set('telefonosExtra.0.etiqueta', 'Recados con su hija')
            ->call('guardar')
            ->assertHasNoErrors();

        $cliente = Cliente::sole();

        $this->assertSame('8781112233', $cliente->telefono);

        $extra = $cliente->telefonosAdicionales()->sole();

        $this->assertSame('8784445566', $extra->telefono);
        $this->assertSame('whatsapp', $extra->uso);
        $this->assertSame('Recados con su hija', $extra->etiqueta);
    }

    #[Test]
    public function un_numero_para_llamar_y_otro_para_whatsapp(): void
    {
        $cliente = Cliente::create([
            'nombres' => 'Dora',
            'apellidos' => 'Cardona',
            'telefono' => '8781112233',
            'telefono_uso' => 'llamadas',
        ]);

        $cliente->telefonosAdicionales()->create([
            'telefono' => '8784445566',
            'uso' => 'whatsapp',
        ]);

        $cliente->refresh();

        $this->assertSame('8781112233', $cliente->telefonoParaLlamar());
        $this->assertSame('8784445566', $cliente->telefonoParaWhatsapp());

        // Cada uno sale solo en su lista.
        $this->assertSame(['8781112233'], $cliente->telefonosParaLlamar()->all());
        $this->assertSame(['8784445566'], $cliente->telefonosParaWhatsapp()->all());
    }

    #[Test]
    public function con_un_solo_numero_sirve_para_las_dos_cosas(): void
    {
        $cliente = Cliente::create([
            'nombres' => 'Dora',
            'apellidos' => 'Cardona',
            'telefono' => '8781112233',
        ]);

        // Es la regla: quien solo tiene uno no tiene que separar nada.
        $this->assertSame('ambos', $cliente->telefono_uso);
        $this->assertSame('8781112233', $cliente->telefonoParaLlamar());
        $this->assertSame('8781112233', $cliente->telefonoParaWhatsapp());
    }

    #[Test]
    public function subir_a_principal_se_lleva_tambien_el_uso(): void
    {
        $cliente = Cliente::create([
            'nombres' => 'Dora',
            'apellidos' => 'Cardona',
            'telefono' => '8781112233',
            'telefono_uso' => 'llamadas',
        ]);

        $cliente->telefonosAdicionales()->create([
            'telefono' => '8784445566',
            'uso' => 'whatsapp',
        ]);

        Livewire::actingAs($this->capturista())
            ->test(ClientesIndex::class)
            ->call('editar', $cliente->id)
            ->call('hacerPrincipal', 0)
            ->assertSet('form.telefono', '8784445566')
            ->assertSet('form.telefono_uso', 'whatsapp')
            ->assertSet('telefonosExtra.0.telefono', '8781112233')
            ->assertSet('telefonosExtra.0.uso', 'llamadas')
            ->call('guardar')
            ->assertHasNoErrors();

        $cliente->refresh();

        $this->assertSame('whatsapp', $cliente->telefono_uso);
        $this->assertSame('llamadas', $cliente->telefonosAdicionales()->sole()->uso);
    }

    #[Test]
    public function el_whatsapp_reconoce_al_cliente_por_su_segundo_numero(): void
    {
        $cliente = Cliente::create([
            'nombres' => 'Dora',
            'apellidos' => 'Cardona',
            'telefono' => '8781112233',
        ]);

        $cliente->telefonosAdicionales()->create(['telefono' => '8784445566']);

        $servicio = app(ClientePorTelefonoService::class);

        // Es lo que mas importa: antes su comprobante llegaba sin dueño.
        $this->assertSame($cliente->id, $servicio->buscarUnico('8784445566')?->id);

        // Y con la lada que manda WhatsApp.
        $this->assertSame($cliente->id, $servicio->buscarUnico('5218784445566')?->id);
    }

    #[Test]
    public function no_adivina_si_el_numero_esta_en_dos_clientes(): void
    {
        $uno = Cliente::create(['nombres' => 'Dora', 'apellidos' => 'Cardona', 'telefono' => '8781112233']);
        $otro = Cliente::create(['nombres' => 'Pedro', 'apellidos' => 'Luna', 'telefono' => '8789990000']);

        // El mismo numero como adicional de uno y principal del otro.
        $uno->telefonosAdicionales()->create(['telefono' => '8789990000']);

        $this->assertNull(app(ClientePorTelefonoService::class)->buscarUnico('8789990000'));
        $this->assertNotNull($otro->id);
    }

    #[Test]
    public function el_buscador_lo_encuentra_por_cualquiera_de_sus_numeros(): void
    {
        $cliente = Cliente::create(['nombres' => 'Dora', 'apellidos' => 'Cardona', 'telefono' => '8781112233']);
        $cliente->telefonosAdicionales()->create(['telefono' => '8784445566']);

        Cliente::create(['nombres' => 'Pedro', 'apellidos' => 'Luna', 'telefono' => '8789990000']);

        Livewire::actingAs($this->capturista())
            ->test(ClientesIndex::class)
            ->set('q', '8784445566')
            ->assertSee('Cardona')
            ->assertDontSee('Luna');
    }

    #[Test]
    public function volver_principal_a_otro_baja_al_que_estaba(): void
    {
        $cliente = Cliente::create(['nombres' => 'Dora', 'apellidos' => 'Cardona', 'telefono' => '8781112233']);
        $cliente->telefonosAdicionales()->create(['telefono' => '8784445566', 'etiqueta' => 'Nuevo']);

        // Cambio de numero: el de siempre pasa a ser el de recados, no se pierde.
        Livewire::actingAs($this->capturista())
            ->test(ClientesIndex::class)
            ->call('editar', $cliente->id)
            ->call('hacerPrincipal', 0)
            ->assertSet('form.telefono', '8784445566')
            ->assertSet('telefonosExtra.0.telefono', '8781112233')
            ->call('guardar')
            ->assertHasNoErrors();

        $cliente->refresh();

        $this->assertSame('8784445566', $cliente->telefono);
        $this->assertSame('8781112233', $cliente->telefonosAdicionales()->sole()->telefono);
    }

    #[Test]
    public function un_adicional_igual_al_principal_no_se_duplica(): void
    {
        Livewire::actingAs($this->capturista())
            ->test(ClientesIndex::class)
            ->call('crear')
            ->set('form.nombres', 'Dora')
            ->set('form.apellidos', 'Cardona')
            ->set('form.telefono', '8781112233')
            ->call('agregarTelefono')
            ->set('telefonosExtra.0.telefono', '8781112233')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame(0, Cliente::sole()->telefonosAdicionales()->count());
    }

    #[Test]
    public function un_adicional_mal_escrito_no_pasa(): void
    {
        Livewire::actingAs($this->capturista())
            ->test(ClientesIndex::class)
            ->call('crear')
            ->set('form.nombres', 'Dora')
            ->set('form.apellidos', 'Cardona')
            ->call('agregarTelefono')
            ->set('telefonosExtra.0.telefono', '123')
            ->call('guardar')
            ->assertHasErrors('telefonosExtra.0.telefono');

        $this->assertSame(0, Cliente::count());
    }

    #[Test]
    public function quitar_uno_lo_borra_de_la_base(): void
    {
        $cliente = Cliente::create(['nombres' => 'Dora', 'apellidos' => 'Cardona', 'telefono' => '8781112233']);
        $cliente->telefonosAdicionales()->create(['telefono' => '8784445566']);

        Livewire::actingAs($this->capturista())
            ->test(ClientesIndex::class)
            ->call('editar', $cliente->id)
            ->call('quitarTelefono', 0)
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame(0, $cliente->telefonosAdicionales()->count());
        $this->assertSame('8781112233', $cliente->refresh()->telefono);
    }

    #[Test]
    public function todos_los_telefonos_salen_con_el_principal_al_frente(): void
    {
        $cliente = Cliente::create(['nombres' => 'Dora', 'apellidos' => 'Cardona', 'telefono' => '8781112233']);
        $cliente->telefonosAdicionales()->create(['telefono' => '8784445566']);
        $cliente->telefonosAdicionales()->create(['telefono' => '8787778899']);

        $this->assertSame(
            ['8781112233', '8784445566', '8787778899'],
            $cliente->refresh()->todosLosTelefonos()->all()
        );
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
