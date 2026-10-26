<?php

namespace Tests\Feature;

use App\Livewire\Admin\Contratos\Edit;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Cuota;
use App\Models\Fraccionamiento;
use App\Models\Lote;
use App\Models\Propietario;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Si el contrato ya quedó en lunes y solo se quiere recorrer la primera cuota
 * (del 12 al 19), no cambia ni la frecuencia ni el día: la fecha sola tiene
 * que bastar para regenerar las cuotas pendientes.
 */
class ContratoEditMoverPrimeraCuotaTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function mover_solo_la_fecha_de_la_primera_cuota_regenera_las_pendientes(): void
    {
        $contrato = $this->contratoSemanalEnLunes();

        Livewire::actingAs(User::factory()->create())
            ->test(Edit::class, ['uuid' => $contrato->uuid])
            ->assertSet('nueva_fecha_primera_cuota', '2026-10-12')
            ->set('nueva_fecha_primera_cuota', '2026-10-19')
            ->set('confirmar_reestructura', true)
            ->call('guardarCambios')
            ->assertHasNoErrors();

        $fechas = Cuota::where('contrato_id', $contrato->id)
            ->orderBy('numero')
            ->pluck('fecha_vencimiento')
            ->map(fn ($f) => substr((string) $f, 0, 10))
            ->all();

        $this->assertSame(['2026-10-19', '2026-10-26', '2026-11-02'], $fechas);
    }

    #[Test]
    public function mover_la_fecha_pide_confirmar_la_reestructura(): void
    {
        $contrato = $this->contratoSemanalEnLunes();

        Livewire::actingAs(User::factory()->create())
            ->test(Edit::class, ['uuid' => $contrato->uuid])
            ->set('nueva_fecha_primera_cuota', '2026-10-19')
            ->call('guardarCambios')
            ->assertHasErrors('confirmar_reestructura');

        $this->assertSame(
            '2026-10-12',
            substr((string) Cuota::where('contrato_id', $contrato->id)->min('fecha_vencimiento'), 0, 10)
        );
    }

    #[Test]
    public function sin_mover_la_fecha_no_se_tocan_las_cuotas(): void
    {
        $contrato = $this->contratoSemanalEnLunes();
        $ids = Cuota::where('contrato_id', $contrato->id)->orderBy('id')->pluck('id')->all();

        Livewire::actingAs(User::factory()->create())
            ->test(Edit::class, ['uuid' => $contrato->uuid])
            ->set('dias_gracia', 5)
            ->call('guardarCambios')
            ->assertHasNoErrors();

        $this->assertSame($ids, Cuota::where('contrato_id', $contrato->id)->orderBy('id')->pluck('id')->all());
    }

    private function contratoSemanalEnLunes(): Contrato
    {
        $propietario = Propietario::firstOrCreate(
            ['nombre' => 'El Pato'],
            ['nombre_legal' => 'INMOBILIARIA EL PATO']
        );

        $fraccionamiento = Fraccionamiento::firstOrCreate(
            ['nombre' => 'Rancho El Pato'],
            ['propietario_id' => $propietario->id]
        );

        $lote = Lote::create([
            'fraccionamiento_id' => $fraccionamiento->id,
            'manzana' => '2',
            'lote' => '7',
            'clave' => 'M2-L7',
        ]);

        $cliente = Cliente::create(['nombres' => 'Juan', 'apellidos' => 'Perez']);

        $contrato = Contrato::create([
            'cliente_id' => $cliente->id,
            'lote_id' => $lote->id,
            'folio_contrato' => 'CT-MOVER-PRIMERA',
            'fecha_inicio' => '2026-10-05',
            'frecuencia' => 'semanal',
            'dia_semana' => 1,
            'precio_total' => 3000,
            'enganche' => 0,
            'saldo_inicial' => 3000,
            'saldo_actual' => 3000,
            'monto_pago' => 1000,
            'tipo_recargo' => 'fijo',
            'valor_recargo' => 0,
            'dias_gracia' => 3,
            'estatus' => 'activo',
            'tipo' => 'terreno',
        ]);

        foreach (['2026-10-12', '2026-10-19', '2026-10-26'] as $i => $fecha) {
            Cuota::create([
                'uuid' => (string) Str::uuid(),
                'contrato_id' => $contrato->id,
                'numero' => $i + 1,
                'fecha_vencimiento' => $fecha,
                'monto' => 1000,
                'estatus' => 'pendiente',
            ]);
        }

        return $contrato;
    }
}
