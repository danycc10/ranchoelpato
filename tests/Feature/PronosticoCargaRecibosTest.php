<?php

namespace Tests\Feature;

use App\Livewire\Reports\WorkloadReport;
use App\Models\User;
use App\Services\Reportes\PronosticoCargaRecibos;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PronosticoCargaRecibosTest extends TestCase
{
    use RefreshDatabase;

    private array $ids;

    private int $folio = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-28 09:00:00');
        $this->ids = $this->catalogos();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    #[Test]
    public function sin_historia_de_cobros_no_inventa_numeros(): void
    {
        $resultado = app(PronosticoCargaRecibos::class)->pronosticar(now());

        $this->assertFalse($resultado['disponible']);
    }

    #[Test]
    public function un_dia_con_muchos_vencimientos_se_marca_con_mas_carga(): void
    {
        $contratoId = $this->contrato();
        $hoy = now()->startOfDay();

        // Cada día vencen dos cuotas y una se cobra el mismo día.
        foreach (CarbonPeriod::create($hoy->copy()->subDays(130), $hoy->copy()->addDays(20)) as $dia) {
            $cobrada = $this->cuota($contratoId, $dia);
            $this->cuota($contratoId, $dia);

            if ($dia->lt($hoy)) {
                $this->recibo($cobrada, $dia->copy()->setTime(11, 0));
            }
        }

        // El sábado 31 vencen ocho más: diez en total.
        foreach (range(1, 8) as $i) {
            $this->cuota($contratoId, Carbon::parse('2026-10-31'));
        }

        $resultado = app(PronosticoCargaRecibos::class)->pronosticar(now());

        $this->assertTrue($resultado['disponible']);
        $this->assertEqualsWithDelta(0.0, $resultado['error_promedio'], 0.001);
        $this->assertEqualsWithDelta(100.0, $resultado['cobro_mismo_dia'], 0.001);

        $dias = collect($resultado['dias'])->keyBy('fecha');
        $this->assertCount(7, $dias);

        $this->assertEqualsWithDelta(1.0, $dias['2026-10-29']['esperados'], 0.001);
        $this->assertEqualsWithDelta(1.0, $dias['2026-10-29']['normal'], 0.001);
        $this->assertEqualsWithDelta(5.0, $dias['2026-10-31']['esperados'], 0.001);
        $this->assertSame(10, $dias['2026-10-31']['vencen']);
        $this->assertSame(0, $dias['2026-10-28']['llevan']);
        $this->assertNull($dias['2026-10-29']['llevan']);

        $usuario = User::factory()->create();
        Livewire::actingAs($usuario)
            ->test(WorkloadReport::class)
            ->assertSee('Pronóstico de los próximos 7 días')
            ->assertSee('Más carga de lo normal')
            ->set('promedio', true)
            ->assertSee('Recibos en promedio');
    }

    private function contrato(): int
    {
        $ahora = now();

        return DB::table('contratos')->insertGetId([
            'cliente_id' => $this->ids['cliente_id'],
            'lote_id' => $this->ids['lote_id'],
            'folio_contrato' => 'CT-PRONOSTICO',
            'fecha_inicio' => '2026-01-01',
            'frecuencia' => 'semanal',
            'dia_semana' => 1,
            'precio_total' => 100000,
            'enganche' => 0,
            'saldo_inicial' => 100000,
            'saldo_actual' => 100000,
            'monto_pago' => 400,
            'tipo_recargo' => 'fijo',
            'valor_recargo' => 0,
            'dias_gracia' => 0,
            'estatus' => 'activo',
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);
    }

    private function cuota(int $contratoId, Carbon $vence): int
    {
        return DB::table('cuotas')->insertGetId([
            'contrato_id' => $contratoId,
            'numero' => ++$this->folio,
            'fecha_vencimiento' => $vence->toDateString(),
            'monto' => 400,
            'estatus' => 'pendiente',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function recibo(int $cuotaId, Carbon $momento): void
    {
        DB::table('recibos')->insert([
            'cliente_id' => $this->ids['cliente_id'],
            'lote_id' => $this->ids['lote_id'],
            'tipos_cobro_id' => $this->ids['tipos_cobro_id'],
            'forma_pago_id' => $this->ids['forma_pago_id'],
            'cuota_id' => $cuotaId,
            'folio' => 'R-'.(++$this->folio),
            'fecha' => $momento->toDateString(),
            'anio' => 2026,
            'monto' => 400,
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
}
