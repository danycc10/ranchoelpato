<?php

namespace Tests\Feature;

use App\Livewire\Admin\Recibos\Crear;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Cuota;
use App\Models\PrintAgent;
use App\Models\PrintJob;
use App\Models\User;
use App\Services\Impresion\PrintAgentPairingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Al cobrar, el ticket sale directo en la impresora del cajero si tiene su
 * equipo emparejado. Si no, se abre la pestaña para imprimir por el navegador:
 * nadie se queda sin su ticket.
 */
class ReciboImpresionAlCobrarTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function con_el_agente_encendido_el_ticket_sale_solo(): void
    {
        $datos = $this->contratoConCuota();
        $usuario = $this->cajeroConAgente();

        $this->cobrar($usuario, $datos)
            ->assertNotDispatched('open-print-tab');

        $trabajo = PrintJob::sole();

        $this->assertSame($usuario->id, $trabajo->requested_by_id);
        $this->assertSame('sale_ticket', $trabajo->document_type->value);
    }

    #[Test]
    public function sin_equipo_emparejado_se_abre_la_pestana(): void
    {
        $datos = $this->contratoConCuota();

        $this->cobrar(User::factory()->create(), $datos)
            ->assertDispatched('open-print-tab');

        // Sin agente no hay nada que encolar.
        $this->assertSame(0, PrintJob::count());
    }

    #[Test]
    public function con_el_agente_apagado_se_avisa_y_se_cae_al_navegador(): void
    {
        $datos = $this->contratoConCuota();
        $usuario = $this->cajeroConAgente();

        // El agente lleva rato sin latir: se da por caído.
        PrintAgent::query()->where('user_id', $usuario->id)
            ->update(['last_seen_at' => now()->subHour()]);

        $this->cobrar($usuario, $datos)
            ->assertDispatched('open-print-tab')
            ->assertDispatched('toast');

        $this->assertSame(0, PrintJob::count());
    }

    private function cobrar(User $usuario, array $datos, bool $imprimir = true)
    {
        $this->actingAs($usuario);

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
            ]])
            ->call('guardar', true)
            ->assertHasNoErrors();
    }

    private function cajeroConAgente(): User
    {
        $usuario = User::factory()->create();

        $agente = app(PrintAgentPairingService::class)->pair($usuario, $usuario)['agent'];
        $agente->forceFill(['last_seen_at' => now()])->save();

        return $usuario;
    }

    private function contratoConCuota(): array
    {
        $ahora = now();

        $cliente = Cliente::create([
            'nombres' => 'Cliente',
            'apellidos' => 'Impresion',
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
            'clave' => 'LOTE-IMPRESION',
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);

        $contrato = Contrato::create([
            'cliente_id' => $cliente->id,
            'lote_id' => $loteId,
            'folio_contrato' => 'CT-IMPRESION',
            'fecha_inicio' => '2026-11-01',
            'frecuencia' => 'mensual',
            'dia_mes' => 1,
            'precio_total' => 200,
            'enganche' => 0,
            'saldo_inicial' => 200,
            'saldo_actual' => 200,
            'monto_pago' => 200,
            'tipo_recargo' => 'fijo',
            'valor_recargo' => 0,
            'dias_gracia' => 0,
            'estatus' => 'activo',
            'tipo' => 'terreno',
        ]);

        $cuota = Cuota::create([
            'contrato_id' => $contrato->id,
            'numero' => 1,
            'fecha_vencimiento' => '2026-11-01',
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
            'forma_pago_id' => DB::table('formas_pago')->insertGetId([
                'nombre' => 'EFECTIVO', 'created_at' => $ahora, 'updated_at' => $ahora,
            ]),
        ];
    }
}
