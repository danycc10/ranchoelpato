<?php

namespace Tests\Feature;

use App\Livewire\Admin\Contratos\Create;
use App\Livewire\Admin\Contratos\Edit;
use App\Livewire\Admin\Promociones\Index as PromocionesIndex;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Cuota;
use App\Models\Fraccionamiento;
use App\Models\Lote;
use App\Models\Promocion;
use App\Models\Propietario;
use App\Models\User;
use App\Services\Contratos\ContratoPlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Promoción de terrenos: $250 por pago el primer año y $400 lo que resta.
 */
class PromocionPagoEscalonadoTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function semanal_son_52_pagos_de_250_y_luego_400(): void
    {
        $plan = ContratoPlanService::generarCuotas($this->datosPlan('semanal', 30000), $this->promocion());

        $montos = array_column($plan, 'monto');

        $this->assertSame(array_fill(0, 52, 250.0), array_slice($montos, 0, 52));
        $this->assertSame(400.0, $montos[52]);

        // 52 × 250 = 13,000; los 17,000 restantes son 42 pagos de 400 y uno de 200.
        $this->assertCount(95, $plan);
        $this->assertSame(200.0, end($montos));
        $this->assertSame(30000.0, round(array_sum($montos), 2));
    }

    #[Test]
    public function mensual_son_12_pagos_de_250_y_luego_400(): void
    {
        $plan = ContratoPlanService::generarCuotas($this->datosPlan('mensual', 10000), $this->promocion());

        $montos = array_column($plan, 'monto');

        $this->assertSame(array_fill(0, 12, 250.0), array_slice($montos, 0, 12));
        $this->assertSame(400.0, $montos[12]);
        $this->assertSame(10000.0, round(array_sum($montos), 2));
    }

    #[Test]
    public function la_anualidad_no_se_come_pagos_de_la_promocion(): void
    {
        $datos = $this->datosPlan('mensual', 20000) + [
            'tiene_anualidad' => true,
            'anualidad_fecha' => '2027-06-15',
            'anualidad_monto' => 1000,
        ];

        $plan = ContratoPlanService::generarCuotas($datos, $this->promocion());

        $regulares = array_values(array_filter($plan, fn ($c) => ! $c['es_anualidad']));

        $this->assertSame(array_fill(0, 12, 250.0), array_slice(array_column($regulares, 'monto'), 0, 12));
        $this->assertSame(400.0, $regulares[12]['monto']);
    }

    #[Test]
    public function sin_promocion_el_plan_no_cambia(): void
    {
        $montos = array_column(ContratoPlanService::generarCuotas($this->datosPlan('semanal', 2000), null), 'monto');

        $this->assertSame([400.0, 400.0, 400.0, 400.0, 400.0], $montos);
    }

    #[Test]
    public function se_da_de_alta_desde_promociones(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(PromocionesIndex::class)
            ->call('crear')
            ->set('form.nombre', 'Terrenos 250 / 400')
            ->set('form.tipo', 'pago_escalonado')
            ->set('form.monto_pago_inicial', 250)
            ->set('form.meses_pago_inicial', 12)
            ->set('form.monto_pago_posterior', 400)
            ->call('guardar')
            ->assertHasNoErrors();

        $promocion = Promocion::sole();

        $this->assertSame('pago_escalonado', $promocion->tipo);
        $this->assertSame('250.00', (string) $promocion->monto_pago_inicial);
        $this->assertSame(12, $promocion->meses_pago_inicial);
        $this->assertSame('400.00', (string) $promocion->monto_pago_posterior);
    }

    #[Test]
    public function la_promocion_pide_sus_tres_datos(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(PromocionesIndex::class)
            ->call('crear')
            ->set('form.nombre', 'Terrenos 250 / 400')
            ->set('form.tipo', 'pago_escalonado')
            ->call('guardar')
            ->assertHasErrors(['form.monto_pago_inicial', 'form.meses_pago_inicial', 'form.monto_pago_posterior']);

        $this->assertSame(0, Promocion::count());
    }

    #[Test]
    public function al_elegirla_en_el_contrato_el_pago_queda_en_400(): void
    {
        $promocion = $this->promocion();

        Livewire::actingAs(User::factory()->create())
            ->test(Create::class)
            ->set('promocion_id', $promocion->id)
            ->assertSet('monto_pago', 400.0)
            ->assertSee('$250.00 los primeros 12 meses, después $400.00');
    }

    #[Test]
    public function mover_la_fecha_de_un_contrato_con_promocion_respeta_los_pagos_de_250(): void
    {
        // La fecha nueva (lunes 9) tiene que quedar en el futuro: el día
        // que el reloj llegó a ella la prueba empezó a fallar.
        $this->travelTo('2026-10-27 10:00:00');

        $promocion = $this->promocion();
        $contrato = $this->contratoSemanal($promocion);

        // Ya pagó dos semanas de la promoción.
        foreach ([1, 2] as $numero) {
            Cuota::create([
                'contrato_id' => $contrato->id,
                'numero' => $numero,
                'fecha_vencimiento' => now()->subWeeks(3 - $numero)->toDateString(),
                'monto' => 250,
                'estatus' => 'pagada',
            ]);
        }

        Cuota::create([
            'contrato_id' => $contrato->id,
            'numero' => 3,
            'fecha_vencimiento' => '2026-11-02',
            'monto' => 250,
            'estatus' => 'pendiente',
        ]);

        Livewire::actingAs(User::factory()->create())
            ->test(Edit::class, ['uuid' => $contrato->uuid])
            ->set('nueva_fecha_primera_cuota', '2026-11-09')
            ->set('confirmar_reestructura', true)
            ->call('guardarCambios')
            ->assertHasNoErrors();

        $montos = Cuota::where('contrato_id', $contrato->id)
            ->where('estatus', '!=', 'pagada')
            ->orderBy('numero')
            ->pluck('monto')
            ->map(fn ($m) => (float) $m)
            ->all();

        // De las 52 de la promoción ya pagó 2: quedan 50 de 250 y luego 400.
        $this->assertSame(array_fill(0, 50, 250.0), array_slice($montos, 0, 50));
        $this->assertSame(400.0, $montos[50]);
        $this->assertSame(29500.0, round(array_sum($montos), 2));
    }

    private function promocion(): Promocion
    {
        return Promocion::create([
            'nombre' => 'Terrenos 250 / 400',
            'codigo' => 'terrenos-250-400',
            'tipo' => Promocion::PAGO_ESCALONADO,
            'monto_pago_inicial' => 250,
            'meses_pago_inicial' => 12,
            'monto_pago_posterior' => 400,
            'activa' => true,
        ]);
    }

    private function datosPlan(string $frecuencia, float $saldo): array
    {
        return [
            'fecha_inicio' => '2026-11-01',
            'frecuencia' => $frecuencia,
            'dia_semana' => $frecuencia === 'semanal' ? 1 : null,
            'dia_mes' => $frecuencia === 'mensual' ? 15 : null,
            'saldo_inicial' => $saldo,
            'monto_pago' => 400,
        ];
    }

    private function contratoSemanal(Promocion $promocion): Contrato
    {
        $propietario = Propietario::create(['nombre' => 'El Pato']);

        $fraccionamiento = Fraccionamiento::create([
            'propietario_id' => $propietario->id,
            'nombre' => 'Rancho El Pato',
        ]);

        $lote = Lote::create([
            'fraccionamiento_id' => $fraccionamiento->id,
            'manzana' => '3',
            'lote' => '4',
            'clave' => 'M3-L4',
        ]);

        $cliente = Cliente::create(['nombres' => 'Juan', 'apellidos' => 'Perez']);

        return Contrato::create([
            'cliente_id' => $cliente->id,
            'lote_id' => $lote->id,
            'promocion_id' => $promocion->id,
            'folio_contrato' => 'CT-ESCALONADO',
            'fecha_inicio' => '2026-10-12',
            'frecuencia' => 'semanal',
            'dia_semana' => 1,
            'precio_total' => 30000,
            'enganche' => 0,
            'saldo_inicial' => 30000,
            'saldo_actual' => 29500,
            'monto_pago' => 400,
            'tipo_recargo' => 'fijo',
            'valor_recargo' => 0,
            'dias_gracia' => 3,
            'estatus' => 'activo',
            'tipo' => 'terreno',
        ]);
    }
}
