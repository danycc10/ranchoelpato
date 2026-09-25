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
    public function el_alta_de_luz_deja_listas_las_condiciones_del_recargo(): void
    {
        $this->actingAs(User::factory()->create());

        // Lo pactado en la luz: $100 desde el primer día, sin gracia.
        Livewire::test(Create::class)
            ->assertSet('valor_recargo', 0.0)
            ->set('dias_gracia', 7)
            ->set('servicio_tipo', 'electricidad')
            ->assertSet('valor_recargo', 100.0)
            ->assertSet('dias_gracia', 0);
    }

    #[Test]
    public function un_servicio_de_luz_nace_sin_gracia_y_con_recargo_diario(): void
    {
        $base = $this->contratoDeTerrenoBase();

        $this->actingAs(User::factory()->create());

        Livewire::test(Create::class)
            ->set('contrato_base_id', $base->id)
            ->set('servicio_tipo', 'electricidad')
            ->set('precio_total', 15000)
            ->set('enganche', 0)
            ->set('monto_pago', 250)
            ->call('guardar');

        $servicio = Contrato::where('tipo', 'servicio')->latest('id')->first();

        $this->assertSame(0, (int) $servicio->dias_gracia);
        $this->assertSame(1, (int) $servicio->frecuencia_recargo_dias);
        $this->assertSame(100.0, (float) $servicio->valor_recargo);
    }

    #[Test]
    public function el_servicio_cobra_por_dia_aunque_se_capturen_dias_de_gracia(): void
    {
        // En los contratos de terreno los días de gracia deciden cada cuánto
        // se cobra el recargo: con 7 de gracia, cada 7 días. En un servicio esa
        // regla dejaría el recargo semanal, que no es lo pactado.
        $this->assertSame(7, Contrato::frecuenciaRecargoDiasPorGracia(7));

        $base = $this->contratoDeTerrenoBase();

        $this->actingAs(User::factory()->create());

        Livewire::test(Create::class)
            ->set('contrato_base_id', $base->id)
            ->set('servicio_tipo', 'electricidad')
            ->set('precio_total', 15000)
            ->set('enganche', 0)
            ->set('monto_pago', 250)
            // La gracia solo retrasa el primer recargo, no lo vuelve semanal.
            ->set('dias_gracia', 7)
            ->call('guardar');

        $servicio = Contrato::where('tipo', 'servicio')->latest('id')->first();

        $this->assertSame(1, (int) $servicio->frecuencia_recargo_dias);
        $this->assertSame(7, (int) $servicio->dias_gracia);
        $this->assertSame(100.0, (float) $servicio->valor_recargo);
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

    /** Un contrato de terreno al que colgarle el servicio. */
    private function contratoDeTerrenoBase(): Contrato
    {
        $propietario = Propietario::create(['nombre' => 'El Pato']);

        $fraccionamiento = Fraccionamiento::create([
            'propietario_id' => $propietario->id,
            'nombre' => 'Rancho El Pato',
        ]);

        $lote = Lote::create([
            'fraccionamiento_id' => $fraccionamiento->id,
            'manzana' => '1',
            'lote' => '9',
            'clave' => 'M1-L9',
        ]);

        $cliente = Cliente::create(['nombres' => 'Ana', 'apellidos' => 'Base']);

        return Contrato::create([
            'cliente_id' => $cliente->id,
            'lote_id' => $lote->id,
            'folio_contrato' => 'CT-BASE-LUZ',
            'fecha_inicio' => now()->subMonths(6)->toDateString(),
            'frecuencia' => 'mensual',
            'dia_mes' => 1,
            'precio_total' => 100000,
            'enganche' => 0,
            'saldo_inicial' => 100000,
            'saldo_actual' => 100000,
            'monto_pago' => 3000,
            'tipo_recargo' => 'fijo',
            'valor_recargo' => 100,
            'dias_gracia' => 7,
            'estatus' => 'activo',
            'tipo' => 'terreno',
        ]);
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
