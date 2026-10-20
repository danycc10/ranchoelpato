<?php

namespace Tests\Feature;

use App\Jobs\SendReciboMail;
use App\Livewire\Admin\Clientes\Index as ClientesIndex;
use App\Mail\ReciboMail;
use App\Models\Cliente;
use App\Models\FormaPago;
use App\Models\Fraccionamiento;
use App\Models\Lote;
use App\Models\Propietario;
use App\Models\Recibo;
use App\Models\TipoCobro;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Correos con acentos o ñ antes de la arroba.
 *
 * El correo electrónico no los admite, pero la validación los dejaba pasar y
 * el envío tronaba hasta el final, ya con el recibo hecho. Se atajan al
 * capturar, que es donde se puede corregir.
 */
class CorreoConAcentosTest extends TestCase
{
    use RefreshDatabase;

    /** El que apareció en los logs de producción. */
    private const CORREO_MALO = 'simentalaguiñagaabelhumberto@gmail.com';

    #[Test]
    public function no_deja_guardar_un_correo_con_eñe(): void
    {
        Livewire::actingAs($this->capturista())
            ->test(ClientesIndex::class)
            ->call('crear')
            ->set('form.nombres', 'Abel')
            ->set('form.apellidos', 'Simental')
            ->set('form.correo', self::CORREO_MALO)
            ->call('guardar')
            ->assertHasErrors('form.correo');

        $this->assertSame(0, Cliente::count());
    }

    #[Test]
    public function un_correo_normal_sigue_pasando(): void
    {
        Livewire::actingAs($this->capturista())
            ->test(ClientesIndex::class)
            ->call('crear')
            ->set('form.nombres', 'Abel')
            ->set('form.apellidos', 'Simental')
            ->set('form.correo', 'abel.simental@gmail.com')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame('abel.simental@gmail.com', Cliente::sole()->correo);
    }

    #[Test]
    public function el_envio_omite_el_correo_mal_escrito_en_vez_de_tronar(): void
    {
        Mail::fake();

        $recibo = $this->reciboDeClienteCon(self::CORREO_MALO);

        // Los que ya estaban guardados antes de apretar la validación.
        (new SendReciboMail($recibo->id))->handle();

        Mail::assertNothingSent();
    }

    #[Test]
    public function con_un_correo_bueno_si_lo_manda(): void
    {
        Mail::fake();

        $recibo = $this->reciboDeClienteCon('abel.simental@gmail.com');

        (new SendReciboMail($recibo->id))->handle();

        Mail::assertSent(ReciboMail::class);
    }

    private function reciboDeClienteCon(string $correo): Recibo
    {
        $propietario = Propietario::create(['nombre' => 'El Pato']);

        $fraccionamiento = Fraccionamiento::create([
            'propietario_id' => $propietario->id,
            'nombre' => 'Rancho El Pato',
        ]);

        $lote = Lote::create([
            'fraccionamiento_id' => $fraccionamiento->id,
            'manzana' => '1',
            'lote' => '7',
            'clave' => 'M1-L7',
        ]);

        // Se guarda por debajo: lo que se prueba aquí es el envío, no el alta.
        $cliente = Cliente::create(['nombres' => 'Abel', 'apellidos' => 'Simental']);
        $cliente->forceFill(['correo' => $correo])->save();

        return Recibo::create([
            'folio' => 'R-001',
            'fecha' => '2026-10-20',
            'anio' => 2026,
            'mes_del_anio' => 10,
            'cliente_id' => $cliente->id,
            'lote_id' => $lote->id,
            'tipos_cobro_id' => TipoCobro::firstOrCreate(['nombre' => 'MENSUALIDAD'])->id,
            'forma_pago_id' => FormaPago::firstOrCreate(['nombre' => 'EFECTIVO'])->id,
            'monto' => 1500,
        ]);
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
