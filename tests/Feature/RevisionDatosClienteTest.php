<?php

namespace Tests\Feature;

use App\Livewire\Admin\Clientes\Index as ClientesIndex;
use App\Livewire\Admin\Clientes\RevisionDatos;
use App\Models\Cliente;
use App\Models\SolicitudDatosCliente;
use App\Models\User;
use App\Services\Clientes\SolicitudDatosClienteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * El lado del sistema: generar la liga y revisar lo que el cliente capturó.
 */
class RevisionDatosClienteTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function el_sistema_genera_la_liga_para_copiarla(): void
    {
        $cliente = $this->cliente();

        Livewire::actingAs($this->usuario())
            ->test(ClientesIndex::class)
            ->call('generarLigaDatos', $cliente->id)
            ->assertDispatched('toast')
            ->assertSet('ligaDatosCliente', $cliente->nombre_completo);

        $this->assertSame(1, SolicitudDatosCliente::count());
    }

    #[Test]
    public function la_tabla_ofrece_las_tres_acciones_con_su_etiqueta(): void
    {
        $this->cliente();

        $html = Livewire::actingAs($this->usuario())
            ->test(ClientesIndex::class)
            ->html();

        // Los botones de la tabla son solo icono: sin estas etiquetas nadie
        // sabría cuál es cuál, ni con lector de pantalla ni al pasar el mouse.
        $this->assertStringContainsString('aria-label="Editar cliente"', $html);
        $this->assertStringContainsString('aria-label="Pedir datos al cliente"', $html);
        $this->assertStringContainsString('aria-label="Desactivar cliente"', $html);

        // Y en la tarjeta del celular van con texto, incluida la de pedir datos.
        $this->assertStringContainsString('Pedir datos', $html);
    }

    #[Test]
    public function la_tabla_muestra_cuanto_le_falta_al_expediente(): void
    {
        $vacio = $this->cliente();

        $completo = Cliente::create([
            'nombres' => 'Ana', 'apellidos' => 'Completa',
            'nombre_legal' => 'ANA COMPLETA', 'curp' => 'COAA900101MCLXXX01',
            'rfc' => 'COAA900101RN9', 'telefono' => '8781234567',
            'correo' => 'ana@example.test', 'direccion' => 'Calle 1',
            'nacionalidad' => 'Mexicana', 'estado_civil' => 'Soltera',
            'ocupacion' => 'Enfermera', 'identificacion_tipo' => 'INE',
            'identificacion_numero' => '1234', 'ine_frente' => 'ine/f.jpg',
            'ine_reverso' => 'ine/r.jpg',
        ]);

        $html = Livewire::actingAs($this->usuario())->test(ClientesIndex::class)->html();

        // El que no trae nada arranca muy abajo y el completo llega al 100%.
        $this->assertStringContainsString('0/13', $html);
        $this->assertStringContainsString('13/13', $html);
        $this->assertStringContainsString('100%', $html);

        // Y se puede ver qué le falta sin entrar al cliente.
        $this->assertStringContainsString('Faltan: ', $html);

        $this->assertNotNull($vacio->id);
        $this->assertNotNull($completo->id);
    }

    #[Test]
    public function la_tabla_dice_en_que_va_la_liga_de_cada_cliente(): void
    {
        $servicio = app(SolicitudDatosClienteService::class);

        $sinLiga = $this->cliente();

        $conLiga = Cliente::create(['nombres' => 'Beto', 'apellidos' => 'Xilitla']);
        $servicio->crear($conLiga);

        $contesto = Cliente::create(['nombres' => 'Caro', 'apellidos' => 'Wadley']);
        $this->capturaDe($contesto, ['curp' => 'COAC900101MCLXXX01']);

        $html = Livewire::actingAs($this->usuario())->test(ClientesIndex::class)->html();

        $this->assertStringContainsString('Sin liga', $html);
        $this->assertStringContainsString('Enviada · sin contestar', $html);
        $this->assertStringContainsString('Contestada · revisar', $html);

        $this->assertNotNull($sinLiga->id);
    }

    #[Test]
    public function una_liga_vencida_se_distingue_de_una_viva(): void
    {
        $cliente = Cliente::create(['nombres' => 'Dani', 'apellidos' => 'Yecapixtla']);

        $resultado = app(SolicitudDatosClienteService::class)->crear($cliente);
        $resultado['solicitud']->forceFill(['expira_en' => now()->subDay()])->save();

        $html = Livewire::actingAs($this->usuario())->test(ClientesIndex::class)->html();

        $this->assertStringContainsString('Liga vencida', $html);
        $this->assertStringNotContainsString('Enviada · sin contestar', $html);
    }

    #[Test]
    public function el_filtro_acota_por_el_estado_de_la_liga(): void
    {
        $este = $this->clientesDeCadaEstado();

        $componente = Livewire::actingAs($this->usuario())->test(ClientesIndex::class);

        // Sin filtro salen todos.
        $componente->assertSee('Zumpango')->assertSee('Wadley')->assertSee('Yecapixtla');

        $componente->set('ligaFilter', 'contestada')
            ->assertSee('Wadley')
            ->assertDontSee('Zumpango')
            ->assertDontSee('Xilitla')
            ->assertDontSee('Yecapixtla');

        $componente->set('ligaFilter', 'enviada')
            ->assertSee('Xilitla')
            ->assertDontSee('Wadley')
            ->assertDontSee('Yecapixtla');

        $componente->set('ligaFilter', 'vencida')
            ->assertSee('Yecapixtla')
            ->assertDontSee('Xilitla')
            ->assertDontSee('Wadley');

        $componente->set('ligaFilter', 'sin')
            ->assertSee('Zumpango')
            ->assertDontSee('Wadley')
            ->assertDontSee('Xilitla');

        $this->assertCount(4, $este);
    }

    #[Test]
    public function un_cliente_cuya_liga_ya_se_aplico_vuelve_a_contar_como_sin_liga(): void
    {
        $cliente = Cliente::create(['nombres' => 'Eva', 'apellidos' => 'Aplicada']);

        $solicitud = $this->capturaDe($cliente, ['curp' => 'APEV900101MCLXXX01']);
        app(SolicitudDatosClienteService::class)->aplicar($solicitud);

        Livewire::actingAs($this->usuario())
            ->test(ClientesIndex::class)
            ->set('ligaFilter', 'sin')
            ->assertSee('Aplicada');
    }

    #[Test]
    public function el_filtro_regresa_a_la_primera_pagina(): void
    {
        $this->clientesDeCadaEstado();

        Livewire::actingAs($this->usuario())
            ->test(ClientesIndex::class)
            ->call('gotoPage', 3)
            ->set('ligaFilter', 'contestada')
            ->assertSet('paginators.page', 1);
    }

    /** Un cliente por cada estado posible de la liga. */
    private function clientesDeCadaEstado(): array
    {
        $servicio = app(SolicitudDatosClienteService::class);

        $sin = Cliente::create(['nombres' => 'Ana', 'apellidos' => 'Zumpango']);

        $enviada = Cliente::create(['nombres' => 'Beto', 'apellidos' => 'Xilitla']);
        $servicio->crear($enviada);

        $contesto = Cliente::create(['nombres' => 'Caro', 'apellidos' => 'Wadley']);
        $this->capturaDe($contesto, ['curp' => 'COAC900101MCLXXX01']);

        $vencida = Cliente::create(['nombres' => 'Dani', 'apellidos' => 'Yecapixtla']);
        $servicio->crear($vencida)['solicitud']->forceFill(['expira_en' => now()->subDay()])->save();

        return [$sin, $enviada, $contesto, $vencida];
    }

    #[Test]
    public function lo_capturado_aparece_comparado_contra_lo_que_habia(): void
    {
        $cliente = $this->cliente();
        $cliente->update(['rfc' => 'RFC-VIEJO']);

        $solicitud = $this->capturaDe($cliente, ['rfc' => 'RFC-NUEVO', 'curp' => 'CURP-NUEVA']);

        $componente = Livewire::actingAs($this->usuario())->test(RevisionDatos::class);

        $filas = collect($componente->instance()->comparacion($solicitud));

        $rfc = $filas->firstWhere('campo', 'rfc');

        $this->assertSame('RFC-VIEJO', $rfc['actual']);
        $this->assertSame('RFC-NUEVO', $rfc['nuevo']);
        $this->assertTrue($rfc['cambia']);
    }

    #[Test]
    public function lo_que_no_cambia_se_marca_para_no_revisarlo_de_mas(): void
    {
        $cliente = $this->cliente();
        $cliente->update(['telefono' => '8781234567']);

        $solicitud = $this->capturaDe($cliente, ['telefono' => '8781234567']);

        $componente = Livewire::actingAs($this->usuario())->test(RevisionDatos::class);

        $fila = collect($componente->instance()->comparacion($solicitud))->firstWhere('campo', 'telefono');

        $this->assertFalse($fila['cambia']);

        // Y no viene palomeado por defecto.
        $this->assertNotContains('telefono', $componente->instance()->camposPorDefecto($solicitud));
    }

    #[Test]
    public function aplicar_pasa_los_datos_al_cliente(): void
    {
        $cliente = $this->cliente();
        $solicitud = $this->capturaDe($cliente, ['curp' => 'PEJU800101HCLXXX01']);

        Livewire::actingAs($this->usuario())
            ->test(RevisionDatos::class)
            ->call('aplicar', $solicitud->id)
            ->assertDispatched('toast');

        $this->assertSame('PEJU800101HCLXXX01', $cliente->fresh()->curp);
        $this->assertSame(SolicitudDatosCliente::APLICADA, $solicitud->fresh()->estatus);
    }

    #[Test]
    public function el_revisor_puede_aplicar_solo_lo_que_palomeo(): void
    {
        $cliente = $this->cliente();

        $solicitud = $this->capturaDe($cliente, [
            'curp' => 'PEJU800101HCLXXX01',
            'ocupacion' => 'algo mal escrito',
        ]);

        Livewire::actingAs($this->usuario())
            ->test(RevisionDatos::class)
            ->set('seleccion.'.$solicitud->id, ['curp'])
            ->call('aplicar', $solicitud->id);

        $actualizado = $cliente->fresh();

        $this->assertSame('PEJU800101HCLXXX01', $actualizado->curp);
        $this->assertNull($actualizado->ocupacion);
    }

    #[Test]
    public function descartar_deja_la_solicitud_sin_efecto(): void
    {
        $cliente = $this->cliente();
        $solicitud = $this->capturaDe($cliente, ['curp' => 'PEJU800101HCLXXX01']);

        Livewire::actingAs($this->usuario())
            ->test(RevisionDatos::class)
            ->call('descartar', $solicitud->id);

        $this->assertSame(SolicitudDatosCliente::CANCELADA, $solicitud->fresh()->estatus);
        $this->assertNull($cliente->fresh()->curp);
    }

    #[Test]
    public function la_pantalla_solo_lista_lo_que_espera_revision(): void
    {
        $conCaptura = $this->cliente();
        $this->capturaDe($conCaptura, ['curp' => 'PEJU800101HCLXXX01']);

        // Una liga mandada que nadie ha contestado no debe aparecer.
        $sinCaptura = Cliente::create(['nombres' => 'Ana', 'apellidos' => 'Pendiente']);
        app(SolicitudDatosClienteService::class)->crear($sinCaptura);

        Livewire::actingAs($this->usuario())
            ->test(RevisionDatos::class)
            ->assertSee('Juan')
            ->assertDontSee('Ana');
    }

    private function capturaDe(Cliente $cliente, array $datos): SolicitudDatosCliente
    {
        $servicio = app(SolicitudDatosClienteService::class);
        $solicitud = $servicio->crear($cliente)['solicitud'];

        $servicio->guardarCaptura($solicitud, $datos);

        return $solicitud->fresh();
    }

    private function usuario(): User
    {
        $usuario = User::factory()->create();

        $rol = Role::findOrCreate('admin', 'web');

        foreach (['admin.ver', 'clientes.ver'] as $permiso) {
            $rol->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
        }

        $usuario->assignRole($rol);

        return $usuario;
    }

    private function cliente(): Cliente
    {
        return Cliente::create(['nombres' => 'Juan', 'apellidos' => 'Perez']);
    }
}
