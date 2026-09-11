<?php

namespace Tests\Feature;

use App\Livewire\Dashboard\CobranzaDashboard;
use App\Livewire\Dashboard\CobranzaTablaAtrasadas;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\ContratoHistorial;
use App\Models\Cuota;
use App\Models\Notificacion;
use App\Models\User;
use App\Services\Contratos\CasoEspecialService;
use App\Services\Reports\MonthlyIncomeReportData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Un contrato en caso especial existe y admite pagos, pero mientras su situación
 * no se resuelve sus cuotas no cuentan para cobranza, atrasos ni reportes.
 */
class ContratoCasoEspecialTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function sus_cuotas_atrasadas_desaparecen_de_cobranza(): void
    {
        $contrato = $this->contratoConCuotaVencida();

        $this->actingAs(User::factory()->create());

        // Antes de marcarlo, la cuota vencida sí aparece y cuenta en el KPI.
        Livewire::test(CobranzaTablaAtrasadas::class, ['hoy' => '2026-12-01'])
            ->assertSee('CONTRATO-ESPECIAL');

        $this->assertSame(1, $this->atrasadasEnKpi());

        app(CasoEspecialService::class)->marcar($contrato, 'Predio en litigio');

        Livewire::test(CobranzaTablaAtrasadas::class, ['hoy' => '2026-12-01'])
            ->assertDontSee('CONTRATO-ESPECIAL');

        $this->assertSame(0, $this->atrasadasEnKpi());
    }

    #[Test]
    public function no_se_le_generan_notificaciones_masivas(): void
    {
        $contrato = $this->contratoConCuotaVencida();

        app(CasoEspecialService::class)->marcar($contrato);

        $this->actingAs(User::factory()->create());

        Livewire::test(CobranzaDashboard::class)
            ->set('hoy', '2026-12-01')
            ->call('notificarAtrasadasMasivo');

        $this->assertSame(0, Notificacion::query()->count());
    }

    #[Test]
    public function queda_fuera_de_los_estatus_reportables(): void
    {
        $this->assertNotContains(
            Contrato::ESTATUS_ESPECIAL,
            MonthlyIncomeReportData::REPORTABLE_CONTRACT_STATUSES
        );
    }

    #[Test]
    public function al_resolverlo_regresa_al_estatus_que_tenia_antes(): void
    {
        $contrato = $this->contratoConCuotaVencida();

        $contrato->update(['estatus' => 'moroso']);

        $casos = app(CasoEspecialService::class);

        $casos->marcar($contrato, 'Documentos extraviados');

        $this->assertSame(Contrato::ESTATUS_ESPECIAL, $contrato->fresh()->estatus);

        $casos->resolver($contrato->fresh(), 'Ya se localizaron');

        $this->assertSame('moroso', $contrato->fresh()->estatus);

        // Y la cuota vuelve a contar para cobranza.
        $this->actingAs(User::factory()->create());
        $this->assertSame(1, $this->atrasadasEnKpi());
    }

    #[Test]
    public function ambos_movimientos_quedan_en_el_historial_del_contrato(): void
    {
        $contrato = $this->contratoConCuotaVencida();

        $usuario = User::factory()->create();
        $casos = app(CasoEspecialService::class);

        $casos->marcar($contrato, 'Predio en litigio', $usuario->id);
        $casos->resolver($contrato->fresh(), 'Litigio cerrado', $usuario->id);

        $tipos = ContratoHistorial::query()
            ->where('contrato_id', $contrato->id)
            ->orderBy('id')
            ->pluck('tipo')
            ->all();

        $this->assertSame(
            [CasoEspecialService::TIPO_MARCAR, CasoEspecialService::TIPO_RESOLVER],
            $tipos
        );

        $this->assertStringContainsString(
            'Predio en litigio',
            (string) ContratoHistorial::query()->where('contrato_id', $contrato->id)->first()->nota
        );
    }

    #[Test]
    public function un_contrato_cancelado_no_puede_marcarse_como_caso_especial(): void
    {
        $contrato = $this->contratoConCuotaVencida();

        $contrato->update(['estatus' => 'cancelado']);

        $this->expectException(\RuntimeException::class);

        app(CasoEspecialService::class)->marcar($contrato->fresh());
    }

    private function atrasadasEnKpi(): int
    {
        return (int) Livewire::test(CobranzaDashboard::class)
            ->set('hoy', '2026-12-01')
            ->viewData('kpis')
            ->atr_count;
    }

    private function contratoConCuotaVencida(): Contrato
    {
        $ahora = now();

        $cliente = Cliente::create([
            'nombres' => 'Cliente',
            'apellidos' => 'Especial',
            'telefono' => '8781112233',
            'correo' => 'especial@example.test',
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
            'clave' => 'LOTE-ESPECIAL',
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);

        $contrato = Contrato::create([
            'cliente_id' => $cliente->id,
            'lote_id' => $loteId,
            'folio_contrato' => 'CONTRATO-ESPECIAL',
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
        ]);

        Cuota::create([
            'contrato_id' => $contrato->id,
            'numero' => 1,
            'fecha_vencimiento' => '2026-11-01',
            'monto' => 200,
            'estatus' => 'pendiente',
        ]);

        return $contrato;
    }
}
