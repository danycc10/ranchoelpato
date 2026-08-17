<?php

namespace Tests\Feature;

use App\Livewire\Admin\Recibos\Crear;
use App\Models\Cliente;
use App\Models\Cuota;
use App\Models\Periodo;
use App\Models\Recibo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RecibosCrearPeriodoPorCuotaTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function cada_recibo_de_un_pago_de_varias_cuotas_lleva_el_periodo_de_su_cuota(): void
    {
        $datos = $this->contratoConCuotas();

        // El catálogo solo tiene el mes de la primera cuota: los demás se crean solos.
        Periodo::create([
            'tipo' => 'mensual',
            'anio' => 2026,
            'mes' => 11,
            'nombre' => 'NOVIEMBRE 2026',
        ]);

        $this->actingAs(User::factory()->create());

        Livewire::test(Crear::class)
            ->set('cliente_id', $datos['cliente_id'])
            ->set('contrato_id', $datos['contrato_id'])
            ->set('lote_id', $datos['lote_id'])
            ->set('tipos_cobro_id', $datos['tipos_cobro_id'])
            ->set('asociarACuota', true)
            ->set('cuotaIds', $datos['cuota_ids'])
            ->set('fecha', '2026-11-05')
            ->set('metodo', 'efectivo')
            ->set('pagos', [[
                'forma_pago_id' => $datos['forma_pago_id'],
                'cuentas_bancarias_id' => null,
                'monto' => 600,
                'referencia' => null,
                'evidencia' => null,
                'sin_evidencia' => true,
                'orden' => 1,
            ]])
            ->call('guardar')
            ->assertHasNoErrors();

        $recibos = Recibo::query()
            ->whereIn('cuota_id', $datos['cuota_ids'])
            ->get()
            ->keyBy('cuota_id');

        $this->assertCount(3, $recibos);

        $mesesEsperados = [
            $datos['cuota_ids'][0] => [2026, 11],
            $datos['cuota_ids'][1] => [2026, 12],
            $datos['cuota_ids'][2] => [2027, 1],
        ];

        foreach ($mesesEsperados as $cuotaId => [$anio, $mes]) {
            $recibo = $recibos[$cuotaId];

            $this->assertNotNull(
                $recibo->periodo_id,
                "El recibo {$recibo->folio} se guardó sin periodo."
            );
            $this->assertDatabaseHas('periodos', [
                'id' => $recibo->periodo_id,
                'tipo' => 'mensual',
                'anio' => $anio,
                'mes' => $mes,
            ]);
        }

        // Tres cuotas de meses distintos, tres periodos distintos.
        $this->assertSame(3, $recibos->pluck('periodo_id')->unique()->count());
    }

    private function contratoConCuotas(): array
    {
        $ahora = now();

        $cliente = Cliente::create([
            'nombres' => 'Cliente',
            'apellidos' => 'Periodos',
            'telefono' => '8781112233',
        ]);

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
            'clave' => 'LOTE-PERIODOS',
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);
        $contratoId = DB::table('contratos')->insertGetId([
            'cliente_id' => $cliente->id,
            'lote_id' => $loteId,
            'folio_contrato' => 'CONTRATO-PERIODOS',
            'fecha_inicio' => '2026-11-01',
            'frecuencia' => 'mensual',
            'dia_mes' => 1,
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

        $cuotaIds = [];

        foreach ([
            ['numero' => 1, 'fecha' => '2026-11-01'],
            ['numero' => 2, 'fecha' => '2026-12-01'],
            ['numero' => 3, 'fecha' => '2027-01-01'],
        ] as $fila) {
            $cuotaIds[] = Cuota::create([
                'contrato_id' => $contratoId,
                'numero' => $fila['numero'],
                'fecha_vencimiento' => $fila['fecha'],
                'monto' => 200,
                'estatus' => 'pendiente',
            ])->id;
        }

        $tipoCobroId = DB::table('tipos_cobro')->insertGetId([
            'nombre' => 'MENSUALIDAD', 'created_at' => $ahora, 'updated_at' => $ahora,
        ]);
        $formaPagoId = DB::table('formas_pago')->insertGetId([
            'nombre' => 'EFECTIVO',
            'requiere_cuenta' => false,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);

        return [
            'cliente_id' => $cliente->id,
            'contrato_id' => $contratoId,
            'lote_id' => $loteId,
            'cuota_ids' => $cuotaIds,
            'tipos_cobro_id' => $tipoCobroId,
            'forma_pago_id' => $formaPagoId,
        ];
    }
}
