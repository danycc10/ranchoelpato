<?php

namespace Tests\Feature;

use App\Livewire\Reports\WorkloadReport;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CargaTrabajoReportTest extends TestCase
{
    use RefreshDatabase;

    private array $ids;

    private int $folio = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-28 18:00:00');
        $this->ids = $this->catalogos();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    #[Test]
    public function la_pantalla_pide_su_propio_permiso(): void
    {
        $usuario = $this->usuario(['reportes.ver']);

        $this->actingAs($usuario)->get(route('reportes.index'))->assertOk()->assertDontSee('Carga de trabajo');
        $this->actingAs($usuario)->get(route('reportes.carga.trabajo'))->assertForbidden();

        $usuario->givePermissionTo(Permission::findOrCreate('reportes.carga_trabajo', 'web'));

        $this->actingAs($usuario)->get(route('reportes.index'))->assertOk()->assertSee('Carga de trabajo');
        $this->actingAs($usuario)->get(route('reportes.carga.trabajo'))->assertOk()->assertSee('Desempeño por usuario');
    }

    #[Test]
    public function cuenta_por_hora_dia_y_usuario_sin_historicos(): void
    {
        $ana = User::factory()->create(['name' => 'Ana']);
        $beto = User::factory()->create(['name' => 'Beto']);

        // Martes 27: Ana hace tres a las 10 y uno a las 11; Beto uno a las 10.
        $this->recibo($ana, '2026-10-27 10:05:00', 100);
        $this->recibo($ana, '2026-10-27 10:20:00', 100);
        $this->recibo($ana, '2026-10-27 10:40:00', 100, anulado: true);
        $this->recibo($ana, '2026-10-27 11:15:00', 100);
        $this->recibo($beto, '2026-10-27 10:30:00', 300);
        // Miércoles 28: Beto uno a las 16.
        $this->recibo($beto, '2026-10-28 16:00:00', 300);

        // No cuentan: importado y fuera del rango (este cae en el periodo anterior).
        $this->recibo($ana, '2026-10-27 12:00:00', 100, historico: true);
        $this->recibo($ana, '2026-10-20 09:00:00', 100);

        $e = Livewire::actingAs($ana)
            ->test(WorkloadReport::class)
            ->call('rango', 'semana')
            ->assertSet('desde', '2026-10-26')
            ->get('estadisticas');

        $this->assertSame(6, $e['total']);
        $this->assertSame(1, $e['anteriores']);
        $this->assertSame(1, $e['anulados']);
        $this->assertEquals(900.0, $e['monto']);
        $this->assertSame(2, $e['dias_trabajados']);
        $this->assertEqualsWithDelta(3.0, $e['promedio_dia'], 0.001);
        $this->assertSame(10, $e['hora_pico']);
        $this->assertSame(4, $e['por_hora'][10]);
        $this->assertSame(1, $e['por_hora'][16]);
        $this->assertSame(4, $e['calor'][2][10]);
        $this->assertSame(1, $e['calor'][3][16]);

        $dias = array_column($e['por_dia'], 'total', 'fecha');
        $this->assertSame(['2026-10-26' => 0, '2026-10-27' => 5, '2026-10-28' => 1], $dias);

        $this->assertCount(12, $e['por_semana']);
        $this->assertSame(6, end($e['por_semana'])['total']);
        $this->assertSame(1, $e['por_semana'][10]['total']);

        [$primero, $segundo] = $e['por_usuario'];
        $this->assertSame('Ana', $primero['nombre']);
        $this->assertSame(4, $primero['total']);
        $this->assertSame(1, $primero['anulados']);
        // 4 recibos en dos horas distintas (10 y 11).
        $this->assertEqualsWithDelta(2.0, $primero['por_hora_activa'], 0.001);
        $this->assertSame(10, $primero['hora_pico']);
        $this->assertSame('Beto', $segundo['nombre']);
        $this->assertSame(2, $segundo['dias']);
    }

    #[Test]
    public function se_puede_ver_un_solo_usuario(): void
    {
        $ana = User::factory()->create(['name' => 'Ana']);
        $beto = User::factory()->create(['name' => 'Beto']);

        $this->recibo($ana, '2026-10-27 10:05:00', 100);
        $this->recibo($beto, '2026-10-27 10:30:00', 300);

        $e = Livewire::actingAs($ana)
            ->test(WorkloadReport::class)
            ->set('usuarioId', $beto->id)
            ->get('estadisticas');

        $this->assertSame(1, $e['total']);
        $this->assertSame(['Beto'], array_column($e['por_usuario'], 'nombre'));
    }

    #[Test]
    public function el_mapa_de_calor_oscurece_con_la_cantidad(): void
    {
        $this->assertNull(WorkloadReport::colorCalor(0, 10));
        $this->assertSame(WorkloadReport::RAMPA[0], WorkloadReport::colorCalor(1, 10));
        $this->assertSame(WorkloadReport::RAMPA[6], WorkloadReport::colorCalor(10, 10));
    }

    private function recibo(User $usuario, string $momento, float $monto, bool $anulado = false, bool $historico = false): void
    {
        DB::table('recibos')->insert($this->ids + [
            'folio' => 'R-'.(++$this->folio),
            'fecha' => substr($momento, 0, 10),
            'anio' => 2026,
            'monto' => $monto,
            'capturado_por_user_id' => $usuario->id,
            'anulado_at' => $anulado ? $momento : null,
            'es_historico' => $historico,
            'created_at' => $momento,
            'updated_at' => $momento,
        ]);
    }

    private function catalogos(): array
    {
        $ahora = now();

        $clienteId = DB::table('clientes')->insertGetId(['nombres' => 'Cliente', 'apellidos' => 'Prueba', 'created_at' => $ahora, 'updated_at' => $ahora]);
        $propietarioId = DB::table('propietarios')->insertGetId(['nombre' => 'Propietario', 'created_at' => $ahora, 'updated_at' => $ahora]);
        $fraccionamientoId = DB::table('fraccionamientos')->insertGetId(['propietario_id' => $propietarioId, 'nombre' => 'Finca', 'created_at' => $ahora, 'updated_at' => $ahora]);

        return [
            'cliente_id' => $clienteId,
            'lote_id' => DB::table('lotes')->insertGetId(['fraccionamiento_id' => $fraccionamientoId, 'lote' => '1', 'clave' => 'L1', 'created_at' => $ahora, 'updated_at' => $ahora]),
            'tipos_cobro_id' => DB::table('tipos_cobro')->insertGetId(['nombre' => 'MENSUALIDAD', 'created_at' => $ahora, 'updated_at' => $ahora]),
            'forma_pago_id' => DB::table('formas_pago')->insertGetId(['nombre' => 'EFECTIVO', 'requiere_cuenta' => false, 'created_at' => $ahora, 'updated_at' => $ahora]),
        ];
    }

    private function usuario(array $permisos): User
    {
        $usuario = User::factory()->create();

        foreach ($permisos as $permiso) {
            $usuario->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
        }

        return $usuario;
    }
}
