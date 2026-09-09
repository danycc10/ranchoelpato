<?php

namespace Tests\Feature;

use App\Livewire\Admin\Recibos\Crear;
use App\Models\Cliente;
use App\Models\Cuota;
use App\Models\Pago;
use App\Models\Recibo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Los pagos de luz atrasados generan recargo, y el cajero puede condonarlo o
 * ajustarlo igual que en las mensualidades del terreno.
 */
class RecargoServicioLuzTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function el_recargo_de_luz_se_cobra_en_el_tipo_de_cobro_del_servicio(): void
    {
        $datos = $this->contratoDeLuzConDosCuotasVencidas();

        $this->cobrar($datos);

        // Un recibo por el consumo y otro por el recargo.
        $this->assertSame(2, Recibo::query()->count());

        $principal = Recibo::query()->where('monto', 400)->sole();
        $recargo = Recibo::query()->where('monto', 100)->sole();

        // El recargo se queda bajo SERVICIO - ELECTRICIDAD, no bajo RECARGO,
        // para que herede el propietario contable de la luz.
        $this->assertSame($datos['tipos_cobro_id'], (int) $recargo->tipos_cobro_id);
        $this->assertSame($datos['tipos_cobro_id'], (int) $principal->tipos_cobro_id);
        $this->assertStringContainsString('RECARGO', (string) $recargo->observaciones);

        // Y trae su desglose por cuota, igual que el principal.
        $this->assertSame(
            [50.0, 50.0],
            Pago::query()->where('recibo_id', $recargo->id)->pluck('monto')
                ->map(fn ($m) => (float) $m)->all()
        );
    }

    #[Test]
    public function condonar_el_recargo_de_luz_no_genera_recibo_de_recargo(): void
    {
        $datos = $this->contratoDeLuzConDosCuotasVencidas();

        $this->cobrar($datos, modo: 'condonar');

        // Solo queda el recibo del consumo.
        $recibo = Recibo::query()->sole();

        $this->assertSame(400.0, (float) $recibo->monto);

        // Y a las cuotas no se les quedó recargo pegado.
        $this->assertSame(
            [0.0, 0.0],
            Cuota::query()->whereIn('id', $datos['cuota_ids'])->pluck('recargo_aplicado')
                ->map(fn ($m) => (float) $m)->all()
        );
    }

    #[Test]
    public function editar_el_recargo_reparte_el_total_entre_las_cuotas(): void
    {
        $datos = $this->contratoDeLuzConDosCuotasVencidas();

        // De $100 automáticos se negocia dejarlo en $30 por las dos cuotas.
        $this->cobrar($datos, modo: 'manual', montoManual: 30);

        $recargo = Recibo::query()->where('monto', 30)->sole();

        $this->assertSame(
            [15.0, 15.0],
            Pago::query()->where('recibo_id', $recargo->id)->pluck('monto')
                ->map(fn ($m) => (float) $m)->all()
        );
    }

    private function cobrar(array $datos, string $modo = 'auto', ?float $montoManual = null): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(Crear::class)
            ->set('cliente_id', $datos['cliente_id'])
            ->set('contrato_id', $datos['contrato_id'])
            ->set('lote_id', $datos['lote_id'])
            ->set('tipos_cobro_id', $datos['tipos_cobro_id'])
            ->set('asociarACuota', true)
            ->set('cuotaIds', $datos['cuota_ids'])
            ->set('fecha', now()->toDateString())
            ->set('metodo', 'efectivo')
            ->set('pagos', [[
                'forma_pago_id' => $datos['forma_pago_id'],
                'cuentas_bancarias_id' => null,
                'monto' => 400,
                'referencia' => null,
                'evidencia' => null,
                'sin_evidencia' => true,
                'orden' => 1,
            ]])
            ->set('recargo_forma_pago_id', $datos['forma_pago_id'])
            ->set('recargoModo', $modo)
            ->set('recargoMontoManual', $montoManual)
            ->set('guardarConRecargoConfirmado', true)
            ->call('guardar')
            ->assertHasNoErrors();
    }

    private function contratoDeLuzConDosCuotasVencidas(): array
    {
        $ahora = now();

        $cliente = Cliente::create([
            'nombres' => 'Cliente',
            'apellidos' => 'Luz',
            'telefono' => '8781112233',
        ]);

        $propietarioId = DB::table('propietarios')->insertGetId([
            'nombre' => 'Propietario luz', 'created_at' => $ahora, 'updated_at' => $ahora,
        ]);
        $fraccionamientoId = DB::table('fraccionamientos')->insertGetId([
            'propietario_id' => $propietarioId,
            'nombre' => 'Fraccionamiento luz',
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);
        $loteId = DB::table('lotes')->insertGetId([
            'fraccionamiento_id' => $fraccionamientoId,
            'lote' => '1',
            'clave' => 'LOTE-LUZ',
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);

        $contratoId = DB::table('contratos')->insertGetId([
            'cliente_id' => $cliente->id,
            'lote_id' => $loteId,
            'folio_contrato' => 'CONTRATO-LUZ',
            'fecha_inicio' => $ahora->copy()->subMonths(3)->toDateString(),
            'frecuencia' => 'mensual',
            'dia_mes' => 1,
            'precio_total' => 400,
            'enganche' => 0,
            'saldo_inicial' => 400,
            'saldo_actual' => 400,
            'monto_pago' => 200,
            // $50 fijos de recargo por cuota vencida, sin días de gracia. El
            // recargo se cobra una vez cada 30 días de atraso, así que las dos
            // cuotas de la prueba llevan un solo cargo de $50 cada una.
            'tipo_recargo' => 'fijo',
            'valor_recargo' => 50,
            'frecuencia_recargo_dias' => 30,
            'dias_gracia' => 0,
            'estatus' => 'activo',
            'tipo' => 'servicio',
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);

        $cuotaIds = [];

        foreach ([
            ['numero' => 1, 'dias' => 20],
            ['numero' => 2, 'dias' => 10],
        ] as $fila) {
            $cuotaIds[] = Cuota::create([
                'contrato_id' => $contratoId,
                'numero' => $fila['numero'],
                'fecha_vencimiento' => $ahora->copy()->subDays($fila['dias'])->toDateString(),
                'monto' => 200,
                'estatus' => 'pendiente',
            ])->id;
        }

        $tipoCobroId = DB::table('tipos_cobro')->insertGetId([
            'nombre' => 'SERVICIO - ELECTRICIDAD',
            'categoria' => 'servicio',
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);

        // El tipo RECARGO existe en el catálogo: el de luz no debe usarlo.
        DB::table('tipos_cobro')->insert([
            'nombre' => 'RECARGO',
            'categoria' => 'recargo',
            'created_at' => $ahora,
            'updated_at' => $ahora,
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
