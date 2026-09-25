<?php

namespace Tests\Feature;

use App\Livewire\Admin\ContratosServicios\Create;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Cuota;
use App\Models\Fraccionamiento;
use App\Models\Lote;
use App\Models\Propietario;
use App\Models\User;
use App\Services\Recibos\RecargoCuotaCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * El financiamiento de luz cobra $100 por cada día de atraso.
 *
 * El monto sale de valor_recargo y el ritmo de frecuencia_recargo_dias: si
 * alguno se queda en su valor de arranque, el recargo no se cobra o se cobra
 * por semana, que es justo lo que pasaba.
 */
class RecargoDiarioLuzTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function cobra_cien_pesos_por_cada_dia_de_atraso(): void
    {
        // Tres días vencida: el día del primer recargo más dos.
        $cuota = $this->cuotaDeLuzVencida(diasDeAtraso: 3);

        $estado = app(RecargoCuotaCalculator::class)->estado($cuota);

        $this->assertTrue($estado['cuota_vencida']);
        $this->assertSame(3, (int) $estado['dias_atraso']);
        $this->assertSame(300.0, $estado['recargo_monto']);
    }

    #[Test]
    public function el_primer_dia_de_atraso_ya_cobra_cien(): void
    {
        $cuota = $this->cuotaDeLuzVencida(diasDeAtraso: 1);

        $this->assertSame(100.0, app(RecargoCuotaCalculator::class)->estado($cuota)['recargo_monto']);
    }

    #[Test]
    public function el_dia_del_vencimiento_todavia_no_cobra(): void
    {
        $cuota = $this->cuotaDeLuzVencida(diasDeAtraso: 0);

        $estado = app(RecargoCuotaCalculator::class)->estado($cuota);

        $this->assertFalse($estado['cuota_vencida']);
        $this->assertSame(0.0, $estado['recargo_monto']);
    }

    #[Test]
    public function sin_valor_de_recargo_no_se_cobra_nada(): void
    {
        // Es el estado en el que quedaron los contratos de luz existentes.
        $cuota = $this->cuotaDeLuzVencida(diasDeAtraso: 5, valorRecargo: 0);

        $this->assertSame(0.0, app(RecargoCuotaCalculator::class)->estado($cuota)['recargo_monto']);
    }

    #[Test]
    public function cobrado_por_semana_el_atraso_de_tres_dias_solo_daria_cien(): void
    {
        // Deja ver la diferencia: con frecuencia semanal, tres días de atraso
        // cobran una sola vez en vez de tres.
        $cuota = $this->cuotaDeLuzVencida(diasDeAtraso: 3, frecuenciaDias: 7);

        $this->assertSame(100.0, app(RecargoCuotaCalculator::class)->estado($cuota)['recargo_monto']);
    }

    #[Test]
    public function el_alta_de_luz_propone_los_cien_por_dia(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(Create::class)
            ->assertSet('valor_recargo', 0.0)
            ->set('servicio_tipo', 'electricidad')
            ->assertSet('valor_recargo', 100.0);
    }

    #[Test]
    public function la_propuesta_no_pisa_lo_que_ya_capturaron(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(Create::class)
            ->set('valor_recargo', 250)
            ->set('servicio_tipo', 'electricidad')
            ->assertSet('valor_recargo', 250.0);
    }

    private function cuotaDeLuzVencida(
        int $diasDeAtraso,
        float $valorRecargo = 100,
        int $frecuenciaDias = 1,
    ): Cuota {
        $propietario = Propietario::create(['nombre' => 'El Pato']);

        $fraccionamiento = Fraccionamiento::create([
            'propietario_id' => $propietario->id,
            'nombre' => 'Rancho El Pato',
        ]);

        $lote = Lote::create([
            'fraccionamiento_id' => $fraccionamiento->id,
            'manzana' => '1',
            'lote' => '1',
            'clave' => 'M1-L1',
        ]);

        $cliente = Cliente::create(['nombres' => 'Juan', 'apellidos' => 'Perez']);

        $contrato = Contrato::create([
            'cliente_id' => $cliente->id,
            'lote_id' => $lote->id,
            'folio_contrato' => 'CT-LUZ-'.$diasDeAtraso.'-'.$frecuenciaDias,
            'fecha_inicio' => now()->subMonths(3)->toDateString(),
            'frecuencia' => 'mensual',
            'dia_mes' => 1,
            'precio_total' => 15000,
            'enganche' => 0,
            'saldo_inicial' => 15000,
            'saldo_actual' => 15000,
            'monto_pago' => 250,
            'tipo_recargo' => 'fijo',
            'valor_recargo' => $valorRecargo,
            'dias_gracia' => 0,
            'frecuencia_recargo_dias' => $frecuenciaDias,
            'estatus' => 'activo',
            'tipo' => 'servicio',
            'servicio_tipo' => 'electricidad',
        ]);

        // Sin días de gracia el recargo corre desde el día siguiente al vencer.
        return Cuota::create([
            'contrato_id' => $contrato->id,
            'numero' => 1,
            'fecha_vencimiento' => now()->subDays($diasDeAtraso)->toDateString(),
            'monto' => 250,
            'estatus' => 'pendiente',
        ]);
    }
}
