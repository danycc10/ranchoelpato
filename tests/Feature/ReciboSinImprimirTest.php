<?php

namespace Tests\Feature;

use App\Livewire\Admin\Clientes\Index as ClientesIndex;
use App\Livewire\Admin\Recibos\Crear;
use App\Models\Cliente;
use App\Models\Cuota;
use App\Models\Notificacion;
use App\Models\Recibo;
use App\Models\User;
use App\Services\Whatsapp\WhatsappService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Hay clientes que no quieren su recibo en papel. A ellos no se les imprime el
 * ticket: se genera el PDF y se les manda por WhatsApp.
 */
class ReciboSinImprimirTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_quien_no_quiere_papel_se_le_manda_el_pdf_en_vez_de_imprimir(): void
    {
        $whatsapp = Mockery::mock(WhatsappService::class);
        $whatsapp->shouldReceive('enviarDocumento')
            ->once()
            ->withArgs(fn ($telefono, $contenido, $nombreArchivo) => $telefono === '8781112233'
                && str_starts_with($contenido, '%PDF')
                && str_ends_with($nombreArchivo, '.pdf'))
            ->andReturn(['id' => 'wa-1']);
        $this->app->instance(WhatsappService::class, $whatsapp);

        $datos = $this->contratoConCuota(['recibo_sin_imprimir' => true]);

        $componente = $this->cobrar($datos)
            ->call('guardar', true)
            ->assertHasNoErrors()
            ->assertNotDispatched('open-print-tab');

        // En pantalla solo se ve el último aviso.
        $this->assertSame(
            'Recibo generado. No se imprime: se envió por WhatsApp al 8781112233.',
            $this->ultimoAviso($componente)
        );

        $recibo = Recibo::sole();
        $notificacion = Notificacion::sole();

        $this->assertSame('whatsapp', $notificacion->canal);
        $this->assertSame('recibo_pago', $notificacion->tipo);
        $this->assertSame('8781112233', $notificacion->destino);
        $this->assertSame($recibo->id, (int) $notificacion->payload['recibo_id']);
        $this->assertSame('enviado', $notificacion->estatus);
    }

    #[Test]
    public function si_whatsapp_falla_el_recibo_queda_en_cola_y_se_avisa(): void
    {
        $whatsapp = Mockery::mock(WhatsappService::class);
        $whatsapp->shouldReceive('enviarDocumento')->andThrow(new \RuntimeException('OpenWA no disponible'));
        $this->app->instance(WhatsappService::class, $whatsapp);

        $datos = $this->contratoConCuota(['recibo_sin_imprimir' => true]);

        $componente = $this->cobrar($datos)
            ->call('guardar', true)
            ->assertHasNoErrors()
            ->assertNotDispatched('open-print-tab');

        $this->assertStringContainsString('se reintentará', $this->ultimoAviso($componente));

        // El cobro no se pierde y el programador lo vuelve a intentar.
        $this->assertSame(1, Recibo::count());
        $this->assertSame('en_cola', Notificacion::sole()->estatus);
    }

    #[Test]
    public function se_avisa_en_pantalla_y_no_se_ofrece_imprimir(): void
    {
        $datos = $this->contratoConCuota(['recibo_sin_imprimir' => true]);

        $this->cobrar($datos)
            ->assertSee('No imprimir físicamente')
            ->assertSee('Guardar y enviar por WhatsApp')
            ->assertDontSee('Guardar e imprimir');
    }

    #[Test]
    public function a_los_demas_se_les_imprime_como_siempre(): void
    {
        $datos = $this->contratoConCuota();

        $this->cobrar($datos)
            ->assertDontSee('No imprimir físicamente')
            ->call('guardar', true)
            ->assertHasNoErrors()
            ->assertDispatched('open-print-tab');

        $this->assertSame(0, Notificacion::count());
    }

    #[Test]
    public function sin_numero_de_whatsapp_se_imprime_para_que_no_se_quede_sin_recibo(): void
    {
        $datos = $this->contratoConCuota([
            'recibo_sin_imprimir' => true,
            'telefono' => '8781112233',
            'telefono_uso' => 'llamadas',
        ]);

        $this->cobrar($datos)
            ->assertSee('no tiene WhatsApp')
            ->assertSee('Guardar e imprimir')
            ->call('guardar', true)
            ->assertHasNoErrors()
            ->assertDispatched('open-print-tab');

        $this->assertSame(0, Notificacion::count());
    }

    #[Test]
    public function la_preferencia_se_guarda_desde_el_cliente(): void
    {
        $cliente = Cliente::create([
            'nombres' => 'Dora',
            'apellidos' => 'Cardona',
            'telefono' => '8781112233',
        ]);

        Livewire::actingAs($this->capturista())
            ->test(ClientesIndex::class)
            ->call('editar', $cliente->id)
            ->assertSet('form.recibo_sin_imprimir', false)
            ->set('form.recibo_sin_imprimir', true)
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertTrue($cliente->fresh()->recibo_sin_imprimir);
    }

    private function ultimoAviso($componente): ?string
    {
        return collect(data_get($componente->effects, 'dispatches'))
            ->where('name', 'toast')
            ->last()['params']['message'] ?? null;
    }

    private function cobrar(array $datos)
    {
        $this->actingAs(User::factory()->create());

        return Livewire::test(Crear::class)
            ->set('cliente_id', $datos['cliente_id'])
            ->set('contrato_id', $datos['contrato_id'])
            ->set('lote_id', $datos['lote_id'])
            ->set('tipos_cobro_id', $datos['tipos_cobro_id'])
            ->set('asociarACuota', true)
            ->set('cuotaIds', [$datos['cuota_id']])
            ->set('fecha', '2026-11-05')
            ->set('metodo', 'efectivo')
            ->set('pagos', [[
                'forma_pago_id' => $datos['forma_pago_id'],
                'cuentas_bancarias_id' => null,
                'monto' => 200,
                'referencia' => null,
                'evidencia' => null,
                'sin_evidencia' => true,
                'orden' => 1,
            ]]);
    }

    private function contratoConCuota(array $cliente = []): array
    {
        $ahora = now();

        $cliente = Cliente::create(array_merge([
            'nombres' => 'Cliente',
            'apellidos' => 'Sin Papel',
            'telefono' => '8781112233',
        ], $cliente));

        $propietarioId = DB::table('propietarios')->insertGetId([
            'nombre' => 'Propietario prueba', 'created_at' => $ahora, 'updated_at' => $ahora,
        ]);
        $fraccionamientoId = DB::table('fraccionamientos')->insertGetId([
            'propietario_id' => $propietarioId,
            'nombre' => 'Fraccionamiento prueba',
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);
        $loteId = DB::table('lotes')->insertGetId([
            'fraccionamiento_id' => $fraccionamientoId,
            'lote' => '1',
            'clave' => 'LOTE-SIN-PAPEL',
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);
        $contratoId = DB::table('contratos')->insertGetId([
            'cliente_id' => $cliente->id,
            'lote_id' => $loteId,
            'folio_contrato' => 'CONTRATO-SIN-PAPEL',
            'fecha_inicio' => '2026-11-01',
            'frecuencia' => 'mensual',
            'dia_mes' => 10,
            'precio_total' => 600,
            'enganche' => 0,
            'saldo_inicial' => 600,
            'saldo_actual' => 600,
            'monto_pago' => 200,
            'tipo_recargo' => 'fijo',
            'valor_recargo' => 0,
            'dias_gracia' => 0,
            'estatus' => 'activo',
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);

        $cuota = Cuota::create([
            'contrato_id' => $contratoId,
            'numero' => 1,
            'fecha_vencimiento' => '2026-11-10',
            'monto' => 200,
            'estatus' => 'pendiente',
        ]);

        return [
            'cliente_id' => $cliente->id,
            'contrato_id' => $contratoId,
            'lote_id' => $loteId,
            'cuota_id' => $cuota->id,
            'tipos_cobro_id' => DB::table('tipos_cobro')->insertGetId([
                'nombre' => 'MENSUALIDAD', 'created_at' => $ahora, 'updated_at' => $ahora,
            ]),
            'forma_pago_id' => DB::table('formas_pago')->insertGetId([
                'nombre' => 'EFECTIVO',
                'requiere_cuenta' => false,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]),
        ];
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
