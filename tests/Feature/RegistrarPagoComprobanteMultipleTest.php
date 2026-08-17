<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\ComprobantePago;
use App\Models\Cuota;
use App\Models\User;
use App\Services\Comprobantes\RegistrarPagoComprobanteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RegistrarPagoComprobanteMultipleTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function genera_recibos_separados_para_principal_y_recargo_de_cada_cuota(): void
    {
        $ahora = now();
        $usuario = User::factory()->create();
        $cliente = Cliente::create([
            'nombres' => 'Cliente',
            'apellidos' => 'Varias Cuotas',
            'telefono' => '8781234567',
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
            'clave' => 'LOTE-PRUEBA-MULTIPLE',
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);
        $contratoId = DB::table('contratos')->insertGetId([
            'cliente_id' => $cliente->id,
            'lote_id' => $loteId,
            'folio_contrato' => 'CONTRATO-MULTIPLE',
            'fecha_inicio' => '2026-01-01',
            'frecuencia' => 'mensual',
            'dia_mes' => 1,
            'precio_total' => 1000,
            'enganche' => 0,
            'saldo_inicial' => 1000,
            'saldo_actual' => 1000,
            'monto_pago' => 200,
            'tipo_recargo' => 'fijo',
            'valor_recargo' => 0,
            'dias_gracia' => 0,
            'estatus' => 'activo',
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);
        $cuotaJulio = Cuota::create([
            'contrato_id' => $contratoId,
            'numero' => 7,
            'fecha_vencimiento' => '2026-07-01',
            'monto' => 200,
            'estatus' => 'vencida',
        ]);
        $cuotaAgosto = Cuota::create([
            'contrato_id' => $contratoId,
            'numero' => 8,
            'fecha_vencimiento' => '2026-08-01',
            'monto' => 200,
            'estatus' => 'pendiente',
        ]);
        Cuota::create([
            'contrato_id' => $contratoId,
            'numero' => 9,
            'fecha_vencimiento' => '2026-09-01',
            'monto' => 600,
            'estatus' => 'pendiente',
        ]);
        $tipoCobroId = DB::table('tipos_cobro')->insertGetId([
            'nombre' => 'MENSUALIDAD', 'created_at' => $ahora, 'updated_at' => $ahora,
        ]);
        $tipoRecargoId = DB::table('tipos_cobro')->insertGetId([
            'nombre' => 'RECARGO', 'created_at' => $ahora, 'updated_at' => $ahora,
        ]);
        $formaPagoId = DB::table('formas_pago')->insertGetId([
            'nombre' => 'TRANSFERENCIA',
            'requiere_cuenta' => false,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);
        $comprobante = ComprobantePago::create([
            'wa_message_id' => 'wa-pago-varias-cuotas',
            'wa_chat_id' => '5218781234567@c.us',
            'telefono' => '8781234567',
            'recibido_en' => $ahora,
            'estatus' => ComprobantePago::ESTATUS_POR_VALIDAR,
            'cliente_id' => $cliente->id,
            'contrato_id' => $contratoId,
            'monto_detectado' => 500,
        ]);

        $this->actingAs($usuario);

        $recibos = app(RegistrarPagoComprobanteService::class)->registrarVarios($comprobante, [
            'cuota_ids' => [$cuotaJulio->id, $cuotaAgosto->id],
            'distribucion' => [
                ['cuota_id' => $cuotaJulio->id, 'monto_principal' => 200, 'monto_recargo' => 50],
                ['cuota_id' => $cuotaAgosto->id, 'monto_principal' => 200, 'monto_recargo' => 50],
            ],
            'forma_pago_id' => $formaPagoId,
            'cuentas_bancarias_id' => null,
            'monto' => 500,
            'referencia' => 'PAGO-JULIO-AGOSTO',
            'tipos_cobro_id' => $tipoCobroId,
            'fecha' => '2026-08-04',
            'observaciones' => 'Pago de dos mensualidades.',
            'fuera_de_orden_autorizado' => false,
        ], $usuario->id);

        $this->assertCount(4, $recibos);
        $this->assertSame(500.0, round((float) $recibos->sum('monto'), 2));

        // Cada recibo (principal y recargo) lleva el periodo de su propia cuota,
        // aunque el mes no estuviera dado de alta en el catálogo.
        foreach ([$cuotaJulio->id => 7, $cuotaAgosto->id => 8] as $cuotaId => $mes) {
            $recibosCuota = $recibos->where('cuota_id', $cuotaId);

            $this->assertCount(2, $recibosCuota);

            foreach ($recibosCuota as $recibo) {
                $this->assertNotNull(
                    $recibo->periodo_id,
                    "El recibo {$recibo->folio} se guardó sin periodo."
                );
                $this->assertDatabaseHas('periodos', [
                    'id' => $recibo->periodo_id,
                    'tipo' => 'mensual',
                    'anio' => 2026,
                    'mes' => $mes,
                ]);
            }
        }
        $this->assertSame(2, $recibos->where('tipos_cobro_id', $tipoCobroId)->count());
        $this->assertSame(2, $recibos->where('tipos_cobro_id', $tipoRecargoId)->count());
        $this->assertDatabaseCount('comprobante_pago_recibos', 4);
        $this->assertDatabaseCount('notificaciones_salida', 4);
        $this->assertDatabaseHas('comprobante_pago_recibos', ['concepto' => 'principal']);
        $this->assertDatabaseHas('comprobante_pago_recibos', ['concepto' => 'recargo']);
        $this->assertDatabaseHas('cuotas', [
            'id' => $cuotaJulio->id,
            'estatus' => 'pagada',
            'recargo_aplicado' => 50,
        ]);
        $this->assertDatabaseHas('cuotas', [
            'id' => $cuotaAgosto->id,
            'estatus' => 'pagada',
            'recargo_aplicado' => 50,
        ]);
        $this->assertDatabaseHas('contratos', ['id' => $contratoId, 'saldo_actual' => 600]);
        $this->assertDatabaseHas('comprobantes_pago', [
            'id' => $comprobante->id,
            'estatus' => ComprobantePago::ESTATUS_VALIDADO,
            'recibo_id' => $recibos->first()->id,
        ]);
    }
}
