<?php

namespace Tests\Feature;

use App\Livewire\Admin\Promociones\Index as PromocionesIndex;
use App\Livewire\Admin\Recibos\Crear;
use App\Livewire\Admin\Recibos\Edit;
use App\Models\AbonoCapital;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Cuota;
use App\Models\Promocion;
use App\Models\Recibo;
use App\Models\TipoCobro;
use App\Models\User;
use App\Services\Recibos\AdelantaYGana;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * «Adelanta y gana»: por cada $2,000 de abono extraordinario en efectivo se
 * bonifican $500 al saldo, si el cliente está al corriente.
 */
class AdelantaYGanaTest extends TestCase
{
    use RefreshDatabase;

    private array $datos;

    protected function setUp(): void
    {
        parent::setUp();

        // Miércoles: la cuota de esta semana venció el lunes 9.
        Carbon::setTestNow('2026-11-11 10:00:00');

        $this->datos = $this->contratoSemanal();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    #[Test]
    public function por_cada_bloque_de_2000_en_efectivo_bonifica_500(): void
    {
        $this->promocion();

        $this->abonar(2500)
            ->assertSee('Bonificación')
            ->assertSee('$500.00')
            ->assertSee('$4,200.00')
            ->call('guardar')
            ->assertHasNoErrors();

        $recibo = Recibo::query()->sole();
        $abono = AbonoCapital::query()->sole();

        // El recibo es solo por el efectivo; la bonificación no es ingreso.
        $this->assertSame(2500.0, (float) $recibo->monto);
        $this->assertSame(500.0, (float) $abono->monto_bonificacion);

        // Un solo bloque: los $500 que sobran se abonan sin bonificación.
        $this->assertSame(7200.0, (float) $abono->saldo_anterior);
        $this->assertSame(4200.0, (float) $recibo->saldo_posterior);
        $this->assertSame(4200.0, (float) Contrato::find($this->datos['contrato_id'])->saldo_actual);

        // $3,000 son 7 semanas de $400 y $200 de la octava, quitadas del final.
        $pendientes = Cuota::query()
            ->where('contrato_id', $this->datos['contrato_id'])
            ->where('estatus', '!=', 'pagada')
            ->orderBy('numero')
            ->get();

        $this->assertCount(11, $pendientes);
        $this->assertSame(13, $pendientes->last()->numero);
        $this->assertSame(200.0, (float) $pendientes->last()->monto);
        $this->assertSame(4200.0, (float) $pendientes->sum('monto'));

        $this->actingAs($this->usuarioCon(['admin.ver', 'recibos.imprimir']));

        $ticket = $this->get(route('admin.recibos.imprimir', $recibo))->assertOk()->getContent();

        $this->assertStringContainsString('Abono a capital', $ticket);
        $this->assertStringContainsString('Bonificación Adelanta y gana', $ticket);
        $this->assertStringContainsString('$3,000.00', $ticket);
        $this->assertStringContainsString('$4,200.00', $ticket);
    }

    #[Test]
    public function sin_estar_al_corriente_el_abono_entra_sin_bonificacion(): void
    {
        $this->promocion();

        Cuota::query()->where('contrato_id', $this->datos['contrato_id'])->where('numero', 2)
            ->update(['estatus' => 'pendiente', 'pagado_total' => 0]);
        Contrato::query()->whereKey($this->datos['contrato_id'])->update(['saldo_actual' => 7600]);

        $this->abonar(2000)
            ->assertSee('Debe estar al corriente: falta el pago #2 del 09/11/2026')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame(0.0, (float) AbonoCapital::query()->sole()->monto_bonificacion);
        $this->assertSame(5600.0, (float) Contrato::find($this->datos['contrato_id'])->saldo_actual);
    }

    #[Test]
    public function solo_aplica_en_efectivo_en_la_vigencia_y_en_los_fraccionamientos_marcados(): void
    {
        $promocion = $this->promocion();
        $contrato = Contrato::with('lote')->find($this->datos['contrato_id']);
        $servicio = app(AdelantaYGana::class);
        $hoy = now();

        $transferencia = DB::table('formas_pago')->insertGetId([
            'nombre' => 'TRANSFERENCIA', 'requiere_cuenta' => true, 'created_at' => $hoy, 'updated_at' => $hoy,
        ]);

        $this->assertSame(1000.0, $servicio->evaluar($contrato, 4000, [$this->datos['efectivo']], $hoy)['bonificacion']);

        $conTransferencia = $servicio->evaluar($contrato, 4000, [$this->datos['efectivo'], $transferencia], $hoy);
        $this->assertSame(0.0, $conTransferencia['bonificacion']);
        $this->assertStringContainsString('solo en efectivo', $conTransferencia['aviso']);

        $this->assertSame(0.0, $servicio->evaluar($contrato, 4000, [$this->datos['efectivo']], Carbon::parse('2026-12-24'))['bonificacion']);

        $faltaBloque = $servicio->evaluar($contrato, 1500, [$this->datos['efectivo']], $hoy);
        $this->assertSame(0.0, $faltaBloque['bonificacion']);
        $this->assertStringContainsString('Le faltan $500.00', $faltaBloque['aviso']);

        $promocion->fraccionamientos()->sync([]);

        $otroFraccionamiento = $servicio->evaluar($contrato, 4000, [$this->datos['efectivo']], $hoy);
        $this->assertSame(0.0, $otroFraccionamiento['bonificacion']);
        $this->assertStringContainsString('no participa', $otroFraccionamiento['aviso']);
    }

    #[Test]
    public function la_bonificacion_no_pasa_de_lo_que_queda_por_pagar(): void
    {
        $this->promocion();

        // Quedan 6 semanas: $2,400.
        Cuota::query()->where('contrato_id', $this->datos['contrato_id'])->where('numero', '>', 8)->delete();
        Contrato::query()->whereKey($this->datos['contrato_id'])->update(['saldo_actual' => 2400]);

        $this->abonar(2000)
            ->assertSee('La bonificación se limita a $400.00')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame(400.0, (float) AbonoCapital::query()->sole()->monto_bonificacion);

        $contrato = Contrato::find($this->datos['contrato_id']);
        $this->assertSame(0.0, (float) $contrato->saldo_actual);
        $this->assertSame('liquidado', $contrato->estatus);
    }

    #[Test]
    public function con_la_promocion_se_elige_la_cantidad_y_va_en_efectivo(): void
    {
        $this->promocion();

        $oxxo = DB::table('formas_pago')->insertGetId([
            'nombre' => 'PAGO EN OXXO', 'requiere_cuenta' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $componente = $this->abonar(0)
            // Aunque traiga otra forma capturada, con la promoción queda efectivo.
            ->set('pagos.0.forma_pago_id', $oxxo)
            ->assertSee('¿Cuánto va a abonar?')
            ->assertSee('solo acepta efectivo')
            ->assertDontSee('+ Agregar forma')
            ->assertSet('pagos.0.forma_pago_id', $this->datos['efectivo'])
            // Le quedan $7,200: de $10,000 para arriba no alcanza.
            ->assertSeeHtml('wire:click="elegirAbono(10000)"
                disabled');

        $componente->call('elegirAbono', 4000)
            ->assertSet('monto', 4000.0)
            ->assertSet('pagos.0.monto', 4000.0)
            ->assertSee('$1,000.00')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame(1000.0, (float) AbonoCapital::query()->sole()->monto_bonificacion);
    }

    #[Test]
    public function si_paga_con_otra_forma_abona_sin_bonificacion(): void
    {
        $this->promocion();

        $oxxo = DB::table('formas_pago')->insertGetId([
            'nombre' => 'PAGO EN OXXO', 'requiere_cuenta' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->abonar(2000)
            ->call('usarOtraFormaDePago')
            ->assertSee('no recibe bonificación')
            ->assertSee('+ Agregar forma')
            ->set('pagos.0.forma_pago_id', $oxxo)
            ->assertSet('pagos.0.forma_pago_id', $oxxo)
            ->call('guardar')
            ->assertHasNoErrors();

        $abono = AbonoCapital::query()->sole();

        $this->assertSame(0.0, (float) $abono->monto_bonificacion);
        $this->assertSame(5200.0, (float) $abono->saldo_nuevo);
    }

    #[Test]
    public function borrar_el_monto_mientras_se_escribe_no_truena(): void
    {
        $this->promocion();

        // Al borrar el campo Livewire manda vacío; el pago se ajusta al escribir.
        $this->abonar(2000)
            ->set('monto', '')
            ->assertSet('monto', null)
            ->set('monto', 4000)
            ->assertSet('pagos.0.monto', 4000)
            ->assertSee('$1,000.00')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame(4000.0, (float) Recibo::query()->sole()->monto);
    }

    #[Test]
    public function con_el_monto_vacio_avisa_en_vez_de_tronar(): void
    {
        $this->abonar(2000)
            ->set('monto', '')
            ->call('guardar')
            ->assertHasErrors('monto');

        $this->assertSame(0, Recibo::query()->count());
    }

    #[Test]
    public function no_se_puede_abonar_mas_de_lo_que_queda(): void
    {
        $this->abonar(7300)
            ->call('guardar')
            ->assertHasErrors('monto');

        $this->assertSame(0, Recibo::query()->count());
    }

    #[Test]
    public function anular_el_recibo_regresa_el_saldo_y_las_cuotas(): void
    {
        $this->promocion();

        $antes = Cuota::query()->where('contrato_id', $this->datos['contrato_id'])
            ->orderBy('numero')->get(['id', 'numero', 'monto', 'fecha_vencimiento'])->toArray();

        $this->abonar(2500)->call('guardar')->assertHasNoErrors();

        $recibo = Recibo::query()->sole();

        Livewire::actingAs($this->usuarioCon(['recibos.eliminar', 'recibos.editar']))
            ->test(Edit::class, ['uuid' => $recibo->uuid])
            ->call('confirmarAnularRecibo')
            ->assertSee('Se deshace el abono a capital')
            ->set('motivoAnulacion', 'Se capturó en el contrato equivocado')
            ->call('anularReciboConfirmado');

        $this->assertSame(7200.0, (float) Contrato::find($this->datos['contrato_id'])->saldo_actual);
        $this->assertNotNull(AbonoCapital::query()->sole()->anulado_at);
        $this->assertNotNull(Recibo::withTrashed()->find($recibo->id)->anulado_at);

        $despues = Cuota::query()->where('contrato_id', $this->datos['contrato_id'])
            ->orderBy('numero')->get(['id', 'numero', 'monto', 'fecha_vencimiento'])->toArray();

        $this->assertEquals($antes, $despues);
    }

    #[Test]
    public function se_configura_desde_promociones_con_sus_fraccionamientos(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(PromocionesIndex::class)
            ->call('crear')
            ->set('form.nombre', 'Adelanta y gana 2026')
            ->set('form.tipo', Promocion::ADELANTA_Y_GANA)
            ->assertSee('FINCA DEL RIO')
            ->call('guardar')
            ->assertHasErrors(['form.abono_por_bloque', 'form.fraccionamiento_ids'])
            ->set('form.abono_por_bloque', 2000)
            ->set('form.bonificacion_por_bloque', 500)
            ->set('form.fraccionamiento_ids', [(string) $this->datos['fraccionamiento_id']])
            ->set('form.fecha_inicio', '2026-11-09')
            ->set('form.fecha_fin', '2026-12-23')
            ->call('guardar')
            ->assertHasNoErrors();

        $promocion = Promocion::query()->sole();

        $this->assertSame(500.0, (float) $promocion->bonificacion_por_bloque);
        $this->assertSame([$this->datos['fraccionamiento_id']], $promocion->fraccionamientos()->pluck('fraccionamientos.id')->all());
    }

    #[Test]
    public function la_promocion_no_se_ofrece_al_crear_contratos(): void
    {
        $promocion = $this->promocion();

        $this->assertFalse(Promocion::query()->deContrato()->whereKey($promocion->id)->exists());
    }

    private function abonar(float $monto)
    {
        return Livewire::actingAs(User::factory()->create())
            ->test(Crear::class)
            ->set('cliente_id', $this->datos['cliente_id'])
            ->set('contrato_id', $this->datos['contrato_id'])
            ->set('lote_id', $this->datos['lote_id'])
            ->set('tipos_cobro_id', TipoCobro::where('nombre', 'ABONO A CAPITAL')->value('id'))
            ->set('fecha', '2026-11-11')
            ->set('metodo', 'efectivo')
            ->set('pagos', [[
                'forma_pago_id' => $this->datos['efectivo'],
                'cuentas_bancarias_id' => null,
                'monto' => $monto,
                'referencia' => null,
                'evidencia' => null,
                'sin_evidencia' => true,
                'orden' => 1,
            ]])
            ->set('monto', $monto);
    }

    private function promocion(): Promocion
    {
        $promocion = Promocion::create([
            'nombre' => 'Adelanta y gana',
            'codigo' => 'adelanta-y-gana-2026',
            'tipo' => Promocion::ADELANTA_Y_GANA,
            'abono_por_bloque' => 2000,
            'bonificacion_por_bloque' => 500,
            'activa' => true,
            'fecha_inicio' => '2026-11-09',
            'fecha_fin' => '2026-12-23',
        ]);

        $promocion->fraccionamientos()->sync([$this->datos['fraccionamiento_id']]);

        return $promocion;
    }

    private function usuarioCon(array $permisos): User
    {
        $usuario = User::factory()->create();
        $rol = Role::findOrCreate('admin', 'web');

        foreach ($permisos as $permiso) {
            $rol->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
        }

        $usuario->assignRole($rol);

        return $usuario;
    }

    /** 20 semanas de $400 desde el lunes 2 de noviembre; las dos primeras ya pagadas. */
    private function contratoSemanal(): array
    {
        $ahora = now();

        $cliente = Cliente::create([
            'nombres' => 'Cliente',
            'apellidos' => 'Adelanta',
            'telefono' => '8781112233',
        ]);

        $propietarioId = DB::table('propietarios')->insertGetId([
            'nombre' => 'Propietario prueba', 'created_at' => $ahora, 'updated_at' => $ahora,
        ]);
        $fraccionamientoId = DB::table('fraccionamientos')->insertGetId([
            'propietario_id' => $propietarioId,
            'nombre' => 'FINCA DEL RIO',
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);
        $loteId = DB::table('lotes')->insertGetId([
            'fraccionamiento_id' => $fraccionamientoId,
            'lote' => '1',
            'clave' => 'LOTE-ADELANTA',
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);
        $contratoId = DB::table('contratos')->insertGetId([
            'cliente_id' => $cliente->id,
            'lote_id' => $loteId,
            'folio_contrato' => 'CONTRATO-ADELANTA',
            'fecha_inicio' => '2026-11-02',
            'frecuencia' => 'semanal',
            'dia_semana' => 1,
            'precio_total' => 8000,
            'enganche' => 0,
            'saldo_inicial' => 8000,
            'saldo_actual' => 7200,
            'monto_pago' => 400,
            'tipo_recargo' => 'fijo',
            'valor_recargo' => 0,
            'dias_gracia' => 0,
            'estatus' => 'activo',
            'tipo' => 'terreno',
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);

        $fecha = Carbon::parse('2026-11-02');

        for ($numero = 1; $numero <= 20; $numero++) {
            $pagada = $numero <= 2;

            Cuota::create([
                'contrato_id' => $contratoId,
                'numero' => $numero,
                'fecha_vencimiento' => $fecha->toDateString(),
                'monto' => 400,
                'pagado_total' => $pagada ? 400 : 0,
                'estatus' => $pagada ? 'pagada' : 'pendiente',
            ]);

            $fecha->addWeek();
        }

        $efectivo = DB::table('formas_pago')->insertGetId([
            'nombre' => 'EFECTIVO', 'requiere_cuenta' => false, 'created_at' => $ahora, 'updated_at' => $ahora,
        ]);

        return [
            'cliente_id' => $cliente->id,
            'contrato_id' => $contratoId,
            'lote_id' => $loteId,
            'fraccionamiento_id' => $fraccionamientoId,
            'efectivo' => $efectivo,
        ];
    }
}
