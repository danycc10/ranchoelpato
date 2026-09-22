<?php

namespace Tests\Feature;

use App\Livewire\Publico\CapturaDatosCliente;
use App\Models\Cliente;
use App\Models\SolicitudDatosCliente;
use App\Services\Clientes\SolicitudDatosClienteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * El formulario que abre el cliente con su liga.
 *
 * Es la única pantalla sin sesión del sistema: el control de acceso es el
 * token, así que conviene fijar por prueba qué deja y qué no deja hacer.
 */
class CapturaDatosClientePublicaTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function la_liga_abre_el_formulario_sin_iniciar_sesion(): void
    {
        $token = $this->ligaDe($this->cliente());

        $this->get(route('publico.datos-cliente', $token))
            ->assertOk()
            ->assertSee('Completa tus datos');
    }

    #[Test]
    public function una_liga_invalida_no_revela_nada(): void
    {
        $this->ligaDe($this->cliente());

        $html = $this->get(route('publico.datos-cliente', str_repeat('b', 48)))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('ya no está disponible', $html);

        // Ni el nombre del cliente ni el formulario.
        $this->assertStringNotContainsString('Juan', $html);
        $this->assertStringNotContainsString('Enviar mis datos', $html);
    }

    #[Test]
    public function una_liga_vencida_tampoco_abre(): void
    {
        $cliente = $this->cliente();
        $resultado = app(SolicitudDatosClienteService::class)->crear($cliente);

        $resultado['solicitud']->forceFill(['expira_en' => now()->subDay()])->save();

        $this->get(route('publico.datos-cliente', $resultado['token']))
            ->assertOk()
            ->assertSee('ya no está disponible');
    }

    #[Test]
    public function el_cliente_captura_sus_datos_y_quedan_por_revisar(): void
    {
        $cliente = $this->cliente();
        $token = $this->ligaDe($cliente);

        Livewire::test(CapturaDatosCliente::class, ['token' => $token])
            ->set('datos.nombre_legal', 'JUAN PEREZ LOPEZ')
            ->set('datos.telefono', '8781234567')
            ->set('datos.curp', 'PEJU800101HCLXXX01')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSet('enviado', true);

        // Su registro sigue intacto: lo capturado espera revisión.
        $this->assertNull($cliente->fresh()->curp);

        $solicitud = SolicitudDatosCliente::sole();

        $this->assertSame(SolicitudDatosCliente::CAPTURADA, $solicitud->estatus);
        $this->assertSame('PEJU800101HCLXXX01', $solicitud->datos['curp']);
    }

    #[Test]
    public function el_nombre_y_el_telefono_son_obligatorios(): void
    {
        $token = $this->ligaDe($this->cliente());

        Livewire::test(CapturaDatosCliente::class, ['token' => $token])
            ->set('datos.nombre_legal', '')
            ->set('datos.telefono', '')
            ->call('guardar')
            ->assertHasErrors(['datos.nombre_legal', 'datos.telefono']);
    }

    #[Test]
    public function la_foto_de_la_identificacion_va_al_disco_privado(): void
    {
        Storage::fake('private');

        $cliente = $this->cliente();
        $token = $this->ligaDe($cliente);

        Livewire::test(CapturaDatosCliente::class, ['token' => $token])
            ->set('datos.nombre_legal', 'JUAN PEREZ LOPEZ')
            ->set('datos.telefono', '8781234567')
            ->set('ine_frente', UploadedFile::fake()->image('ine.jpg'))
            ->call('guardar')
            ->assertHasNoErrors();

        $solicitud = SolicitudDatosCliente::sole();

        $this->assertNotNull($solicitud->ine_frente);
        Storage::disk('private')->assertExists($solicitud->ine_frente);

        // Y no acabó en la carpeta pública.
        $this->assertStringStartsWith('clientes/'.$cliente->id, $solicitud->ine_frente);
    }

    #[Test]
    public function un_archivo_que_no_es_imagen_se_rechaza(): void
    {
        Storage::fake('private');

        $token = $this->ligaDe($this->cliente());

        Livewire::test(CapturaDatosCliente::class, ['token' => $token])
            ->set('datos.nombre_legal', 'JUAN PEREZ LOPEZ')
            ->set('datos.telefono', '8781234567')
            ->set('ine_frente', UploadedFile::fake()->create('virus.php', 40))
            ->call('guardar')
            ->assertHasErrors('ine_frente');
    }

    #[Test]
    public function con_la_liga_de_un_cliente_no_se_tocan_los_datos_de_otro(): void
    {
        $mio = $this->cliente();
        $ajeno = Cliente::create(['nombres' => 'Ana', 'apellidos' => 'Ajena']);

        $token = $this->ligaDe($mio);

        Livewire::test(CapturaDatosCliente::class, ['token' => $token])
            ->set('datos.nombre_legal', 'JUAN PEREZ LOPEZ')
            ->set('datos.telefono', '8781234567')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame($mio->id, SolicitudDatosCliente::sole()->cliente_id);
        $this->assertNull($ajeno->fresh()->nombre_legal);
    }

    #[Test]
    public function la_liga_deja_de_servir_despues_de_aplicarse(): void
    {
        $cliente = $this->cliente();
        $servicio = app(SolicitudDatosClienteService::class);
        $token = $this->ligaDe($cliente);

        Livewire::test(CapturaDatosCliente::class, ['token' => $token])
            ->set('datos.nombre_legal', 'JUAN PEREZ LOPEZ')
            ->set('datos.telefono', '8781234567')
            ->call('guardar');

        $servicio->aplicar(SolicitudDatosCliente::sole(), null);

        // Quien conserve la liga ya no puede volver a entrar.
        $this->get(route('publico.datos-cliente', $token))
            ->assertOk()
            ->assertSee('ya no está disponible');
    }

    private function ligaDe(Cliente $cliente): string
    {
        return app(SolicitudDatosClienteService::class)->crear($cliente)['token'];
    }

    private function cliente(): Cliente
    {
        return Cliente::create(['nombres' => 'Juan', 'apellidos' => 'Perez']);
    }
}
