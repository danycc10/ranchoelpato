<?php

namespace Tests\Feature;

use App\Livewire\Admin\Recibos\Crear;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Cuota;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * El recibo se guarda desde varios lados, y el modal que confirma el recargo
 * se cierra antes de que el error alcance a verse junto al campo. Sin un aviso
 * en pantalla, al usuario le parece que el botón no hace nada.
 */
class ReciboAvisaDatosFaltantesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function confirmar_el_recargo_sin_forma_de_pago_avisa_en_pantalla(): void
    {
        $datos = $this->contratoConCuotaVencida();

        $componente = $this->capturaConRecargo($datos)
            // Es el paso donde hoy parecía que no pasaba nada.
            ->call('confirmarGuardarConRecargo');

        $componente
            ->assertHasErrors('recargo_forma_pago_id')
            ->assertDispatched('toast');

        // Y el recibo no se generó a medias.
        $this->assertSame(0, DB::table('recibos')->count());
    }

    #[Test]
    public function el_aviso_nombra_el_campo_como_lo_ve_el_usuario(): void
    {
        $datos = $this->contratoConCuotaVencida();

        $this->capturaConRecargo($datos)
            ->call('confirmarGuardarConRecargo')
            ->assertDispatched(
                'toast',
                fn ($evento, $params) => str_contains(
                    mb_strtolower($params['message'] ?? ''),
                    'forma de pago del recargo'
                )
            );
    }

    #[Test]
    public function cobrar_recargos_sin_cuotas_tambien_avisa(): void
    {
        $datos = $this->contratoConCuotaVencida();

        $this->actingAs(User::factory()->create());

        Livewire::test(Crear::class)
            ->set('cliente_id', $datos['cliente_id'])
            ->set('contrato_id', $datos['contrato_id'])
            ->set('lote_id', $datos['lote_id'])
            ->set('tipos_cobro_id', $datos['tipo_recargo_id'])
            ->set('asociarACuota', true)
            ->set('cuotaIds', [])
            ->call('guardar')
            ->assertDispatched('toast');
    }

    private function capturaConRecargo(array $datos)
    {
        $this->actingAs(User::factory()->create());

        return Livewire::test(Crear::class)
            ->set('cliente_id', $datos['cliente_id'])
            ->set('contrato_id', $datos['contrato_id'])
            ->set('lote_id', $datos['lote_id'])
            ->set('tipos_cobro_id', $datos['tipos_cobro_id'])
            ->set('asociarACuota', true)
            ->set('cuotaIds', [$datos['cuota_id']])
            ->set('fecha', now()->toDateString())
            ->set('metodo', 'efectivo')
            ->set('recargoModo', 'cobrar')
            // El cobro del recargo va aparte y nadie capturó su forma de pago.
            ->set('recargo_forma_pago_id', null)
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

    private function contratoConCuotaVencida(): array
    {
        $ahora = now();

        $cliente = Cliente::create([
            'nombres' => 'Cliente',
            'apellidos' => 'Recargo',
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
            'clave' => 'LOTE-RECARGO',
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);

        $contrato = Contrato::create([
            'cliente_id' => $cliente->id,
            'lote_id' => $loteId,
            'folio_contrato' => 'CT-RECARGO',
            'fecha_inicio' => now()->subMonths(6)->toDateString(),
            'frecuencia' => 'mensual',
            'dia_mes' => 1,
            'precio_total' => 200,
            'enganche' => 0,
            'saldo_inicial' => 200,
            'saldo_actual' => 200,
            'monto_pago' => 200,
            'tipo_recargo' => 'fijo',
            'valor_recargo' => 50,
            'dias_gracia' => 0,
            'estatus' => 'activo',
            'tipo' => 'terreno',
        ]);

        // Vencida hace rato: al cobrarla arrastra recargo.
        $cuota = Cuota::create([
            'contrato_id' => $contrato->id,
            'numero' => 1,
            'fecha_vencimiento' => now()->subMonths(4)->toDateString(),
            'monto' => 200,
            'estatus' => 'pendiente',
        ]);

        return [
            'cliente_id' => $cliente->id,
            'contrato_id' => $contrato->id,
            'lote_id' => $loteId,
            'cuota_id' => $cuota->id,
            'tipos_cobro_id' => DB::table('tipos_cobro')->insertGetId([
                'nombre' => 'MENSUALIDAD', 'created_at' => $ahora, 'updated_at' => $ahora,
            ]),
            'tipo_recargo_id' => DB::table('tipos_cobro')->insertGetId([
                'nombre' => 'RECARGO', 'created_at' => $ahora, 'updated_at' => $ahora,
            ]),
            'forma_pago_id' => DB::table('formas_pago')->insertGetId([
                'nombre' => 'EFECTIVO', 'created_at' => $ahora, 'updated_at' => $ahora,
            ]),
        ];
    }
}
