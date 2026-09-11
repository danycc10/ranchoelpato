<?php

namespace Tests\Feature;

use App\Livewire\Admin\Recibos\Crear;
use App\Livewire\Admin\Recibos\Index as RecibosIndex;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Cuota;
use App\Models\Recibo;
use App\Models\User;
use App\Services\Contratos\CuotaPagoRollbackService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Cuando el cliente viene a abonar varias cuotas se le entrega un solo ticket,
 * con un renglón por cuota, en vez de un ticket por cuota.
 */
class ReciboAgrupadoVariasCuotasTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function el_ticket_lleva_un_renglon_por_cada_cuota_pagada(): void
    {
        $datos = $this->contratoConTresCuotas();

        $this->cobrarLasTresCuotas($datos);

        $recibo = Recibo::query()->sole();

        $this->actingAs($this->usuarioConPermisos());

        $html = $this->get(route('admin.recibos.imprimir', $recibo))
            ->assertOk()
            ->getContent();

        // Un renglón de concepto por cuota, cada uno con su vencimiento.
        $this->assertStringContainsString('Mes #1 · 01/11/2026', $html);
        $this->assertStringContainsString('Mes #2 · 01/12/2026', $html);
        $this->assertStringContainsString('Mes #3 · 01/01/2027', $html);

        // Y un solo total por los tres.
        $this->assertStringContainsString('$600.00', $html);
        $this->assertSame(3, substr_count($html, '$200.00'));

        // Sin recargos, el pago sí fue puntual.
        $this->assertStringContainsString('Gracias por su pago puntual.', $html);
    }

    #[Test]
    public function anular_un_recibo_agrupado_revierte_todas_sus_cuotas(): void
    {
        $datos = $this->contratoConTresCuotas();

        $this->cobrarLasTresCuotas($datos);

        $contrato = Contrato::query()->findOrFail($datos['contrato_id']);

        $this->assertSame(0.0, (float) $contrato->saldo_actual);

        // Se anula desde una cuota intermedia: el recibo cubre las tres, así que
        // las tres tienen que regresar a pendiente.
        CuotaPagoRollbackService::anularPagoReciboDeCuota(
            $contrato,
            $datos['cuota_ids'][1],
            User::factory()->create()->id,
            'Prueba de anulación'
        );

        $cuotas = Cuota::query()->whereIn('id', $datos['cuota_ids'])->get();

        $this->assertCount(3, $cuotas);

        foreach ($cuotas as $cuota) {
            $this->assertSame('pendiente', $cuota->estatus);
            $this->assertSame(0.0, (float) $cuota->pagado_total);
        }

        $this->assertSame(600.0, (float) $contrato->fresh()->saldo_actual);
        $this->assertSame(0, Recibo::query()->whereNull('anulado_at')->count());
    }

    #[Test]
    public function el_recibo_muestra_el_rango_de_cuotas_que_cubre(): void
    {
        $datos = $this->contratoConTresCuotas();

        $this->cobrarLasTresCuotas($datos);

        $recibo = Recibo::query()->with('pagos.cuota')->sole();

        $this->assertSame([1, 2, 3], $recibo->numerosDeCuota());
        $this->assertSame('1 - 3', $recibo->rangoCuotas());

        // En la pestaña de recibos sale el rango, no solo la cuota ancla.
        $this->actingAs($this->usuarioConPermisos());

        Livewire::test(RecibosIndex::class)
            ->set('mes', '2026-11')
            ->assertSee($recibo->folio)
            ->assertSee('#1 - 3');
    }

    #[Test]
    public function un_recibo_de_una_sola_cuota_muestra_solo_su_numero(): void
    {
        $datos = $this->contratoConTresCuotas();

        // Solo la primera cuota: el rango no debe inventar un "1 - 1".
        $this->cobrarLasTresCuotas($datos, [$datos['cuota_ids'][0]], 200);

        $recibo = Recibo::query()->with('pagos.cuota')->sole();

        $this->assertSame('1', $recibo->rangoCuotas());
    }

    #[Test]
    public function los_recibos_de_una_cuota_se_encuentran_aunque_sea_intermedia(): void
    {
        $datos = $this->contratoConTresCuotas();

        $this->cobrarLasTresCuotas($datos);

        $recibo = Recibo::query()->sole();

        // La cuota de en medio no es la cuota ancla del recibo, pero el recibo
        // sí la cubre: si no se encuentra, la anulación desde el contrato diría
        // que no hay recibos para esa cuota.
        $encontrados = Recibo::query()
            ->queCubrenCuota($datos['cuota_ids'][1])
            ->pluck('id')
            ->all();

        $this->assertSame([$recibo->id], $encontrados);
    }

    #[Test]
    public function el_recargo_se_imprime_dentro_del_mismo_ticket(): void
    {
        $datos = $this->contratoConTresCuotas();

        $this->cobrarLasTresCuotas($datos);

        $principal = Recibo::query()->sole();

        // El recargo conserva folio propio (puede ir a otro propietario
        // contable), pero comparte el ticket con el recibo principal.
        $recargo = Recibo::create([
            'folio' => 'REC-RECARGO',
            'fecha' => '2026-11-05',
            'anio' => 2026,
            'cliente_id' => $datos['cliente_id'],
            'contrato_id' => $datos['contrato_id'],
            'lote_id' => $datos['lote_id'],
            'cuota_id' => $datos['cuota_ids'][0],
            'tipos_cobro_id' => DB::table('tipos_cobro')->insertGetId([
                'nombre' => 'RECARGO', 'created_at' => now(), 'updated_at' => now(),
            ]),
            'forma_pago_id' => $datos['forma_pago_id'],
            'monto' => 50,
        ]);

        $this->actingAs($this->usuarioConPermisos());

        $token = 'token-agrupado';

        Cache::put('print_batch:'.$token, [
            'user_id' => auth()->id(),
            'recibo_ids' => [$principal->id, $recargo->id],
            'adjunto_ids' => [$recargo->id],
        ], now()->addMinutes(15));

        $html = $this->get(route('admin.recibos.print-lote', ['token' => $token]))
            ->assertOk()
            ->getContent();

        // Un solo ticket, no dos.
        $this->assertSame(1, substr_count($html, 'class="ticket"'));

        // Con los dos folios y el recargo como renglón aparte.
        $this->assertStringContainsString($principal->folio, $html);
        $this->assertStringContainsString('REC-RECARGO', $html);
        $this->assertStringContainsString('Recargo Mes #1', $html);

        // Y el total suma las cuotas más el recargo.
        $this->assertStringContainsString('$650.00', $html);

        // Si el ticket trae recargo el pago no fue puntual: cierra sin mensaje.
        $this->assertStringNotContainsString('Gracias por su pago puntual.', $html);
        $this->assertStringNotContainsString('Pagando a tiempo evita recargos.', $html);
    }

    private function cobrarLasTresCuotas(array $datos, ?array $cuotaIds = null, float $monto = 600): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(Crear::class)
            ->set('cliente_id', $datos['cliente_id'])
            ->set('contrato_id', $datos['contrato_id'])
            ->set('lote_id', $datos['lote_id'])
            ->set('tipos_cobro_id', $datos['tipos_cobro_id'])
            ->set('asociarACuota', true)
            ->set('cuotaIds', $cuotaIds ?? $datos['cuota_ids'])
            ->set('fecha', '2026-11-05')
            ->set('metodo', 'efectivo')
            ->set('pagos', [[
                'forma_pago_id' => $datos['forma_pago_id'],
                'cuentas_bancarias_id' => null,
                'monto' => $monto,
                'referencia' => null,
                'evidencia' => null,
                'sin_evidencia' => true,
                'orden' => 1,
            ]])
            ->call('guardar')
            ->assertHasNoErrors();
    }

    private function usuarioConPermisos(): User
    {
        $usuario = User::factory()->create();

        $rol = Role::findOrCreate('admin', 'web');

        foreach (['admin.ver', 'recibos.imprimir', 'recibos.crear'] as $permiso) {
            $rol->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
        }

        $usuario->assignRole($rol);

        return $usuario;
    }

    private function contratoConTresCuotas(): array
    {
        $ahora = now();

        $cliente = Cliente::create([
            'nombres' => 'Cliente',
            'apellidos' => 'Agrupado',
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
            'clave' => 'LOTE-AGRUPADO',
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);
        $contratoId = DB::table('contratos')->insertGetId([
            'cliente_id' => $cliente->id,
            'lote_id' => $loteId,
            'folio_contrato' => 'CONTRATO-AGRUPADO',
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
            'tipo' => 'terreno',
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
