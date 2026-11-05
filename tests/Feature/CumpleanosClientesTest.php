<?php

namespace Tests\Feature;

use App\Livewire\Dashboard\Cumpleaneros;
use App\Mail\CumpleanosMail;
use App\Models\Cliente;
use App\Models\FelicitacionCumpleanos;
use App\Models\User;
use App\Services\Clientes\CumpleanosClientes;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CumpleanosClientesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-09 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    #[Test]
    public function la_fecha_de_nacimiento_sale_del_curp(): void
    {
        $this->assertSame('1973-11-09', CumpleanosClientes::fechaNacimiento('NIBS731109HCLXRR06')?->toDateString());
        $this->assertSame('1992-09-19', CumpleanosClientes::fechaNacimiento(' mapl920919hclrss02 ')?->toDateString());
        // Una letra en la posición 17: nació del 2000 en adelante.
        $this->assertSame('2005-03-15', CumpleanosClientes::fechaNacimiento('GOMA050315MCLRRNA1')?->toDateString());

        $this->assertNull(CumpleanosClientes::fechaNacimiento('CUDASD213JO123'));
        $this->assertNull(CumpleanosClientes::fechaNacimiento('XXXX991340HCLRSS02'));
        $this->assertNull(CumpleanosClientes::fechaNacimiento(null));
    }

    #[Test]
    public function el_correo_de_cumpleanos_sale_una_sola_vez_al_anio(): void
    {
        Mail::fake();

        $sergio = $this->cliente('SERGIO', 'NIBS731109HCLXRR06', correo: 'sergio@example.com');
        $this->cliente('SIN CORREO', 'MAPL731109HCLRSS02');
        $this->cliente('OTRO DIA', 'GOMA050315MCLRRNA1', correo: 'otro@example.com');

        $this->artisan('clientes:felicitar-cumpleanos')->assertSuccessful();
        $this->artisan('clientes:felicitar-cumpleanos')->assertSuccessful();

        Mail::assertSent(CumpleanosMail::class, 1);
        Mail::assertSent(CumpleanosMail::class, fn (CumpleanosMail $mail) => $mail->hasTo('sergio@example.com'));

        $this->assertDatabaseHas('felicitaciones_cumpleanos', [
            'cliente_id' => $sergio->id,
            'anio' => 2026,
            'canal' => FelicitacionCumpleanos::CORREO,
        ]);

        $mensaje = (new CumpleanosMail($sergio))->render();
        $this->assertStringContainsString('Feliz cumpleaños, Sergio', $mensaje);
    }

    #[Test]
    public function los_del_29_de_febrero_festejan_el_28_si_no_es_bisiesto(): void
    {
        $this->cliente('BISIESTA', 'PEXA000229MCLRRNA3');

        $this->assertCount(1, app(CumpleanosClientes::class)->cumplenEl(Carbon::parse('2027-02-28')));
        $this->assertCount(0, app(CumpleanosClientes::class)->cumplenEl(Carbon::parse('2028-02-28')));
        $this->assertCount(1, app(CumpleanosClientes::class)->cumplenEl(Carbon::parse('2028-02-29')));
    }

    #[Test]
    public function la_ventanita_muestra_a_los_de_hoy_y_se_felicitan_por_whatsapp(): void
    {
        $ana = User::factory()->create(['name' => 'Ana']);
        $sergio = $this->cliente('SERGIO', 'NIBS731109HCLXRR06', telefono: '878 111 8424');
        $this->cliente('SIN TELEFONO', 'MAPL731109HCLRSS02');
        $this->cliente('LUIS', 'MAPL921112HCLRSS02');

        $componente = Livewire::actingAs($ana)
            ->test(Cumpleaneros::class)
            ->assertSee('Cumpleañeros de hoy')
            ->assertSee('Cumple 53 años')
            ->assertSee('https://web.whatsapp.com/send?phone=528781118424&amp;text='.rawurlencode('¡Feliz cumpleaños, Sergio! 🎂🎉'), false)
            ->assertSee('Sin WhatsApp registrado')
            ->assertSee('Próximos 7 días')
            ->assertSee('Jue 12/11');

        $componente->call('marcarFelicitado', $sergio->id)
            ->call('marcarFelicitado', $sergio->id)
            ->assertSee('Felicitado por WhatsApp por Ana')
            ->assertSee('Abrir de nuevo');

        $this->assertSame(1, FelicitacionCumpleanos::where('cliente_id', $sergio->id)->where('canal', 'whatsapp')->count());
        $this->assertSame($ana->id, FelicitacionCumpleanos::where('cliente_id', $sergio->id)->value('user_id'));
    }

    #[Test]
    public function sin_cumpleaneros_la_ventanita_no_estorba(): void
    {
        $this->cliente('OTRO DIA', 'GOMA050315MCLRRNA1');

        Livewire::actingAs(User::factory()->create())
            ->test(Cumpleaneros::class)
            ->assertDontSee('Cumpleañeros de hoy');
    }

    private function cliente(string $nombres, string $curp, ?string $correo = null, ?string $telefono = null): Cliente
    {
        return Cliente::create([
            'nombres' => $nombres,
            'apellidos' => 'PRUEBA',
            'curp' => $curp,
            'correo' => $correo,
            'telefono' => $telefono,
            'estatus' => 'activo',
        ]);
    }
}
