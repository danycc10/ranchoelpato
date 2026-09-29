<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Cuota;
use App\Models\FormaPago;
use App\Models\Fraccionamiento;
use App\Models\Lote;
use App\Models\Pago;
use App\Models\Periodo;
use App\Models\Propietario;
use App\Models\Recibo;
use App\Models\TipoCobro;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Los recibos que ya se guardaron con el periodo de su primera cuota.
 */
class CorregirPeriodoRecibosAgrupadosTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function al_recibo_que_cubre_varios_meses_le_quita_el_periodo(): void
    {
        $julio = $this->periodo(7, 'JULIO');

        $recibo = $this->recibo('R-AGRUPADO', $julio, ['2026-07-18', '2026-08-18', '2026-09-18']);

        $this->artisan('app:corregir-periodo-recibos')->assertSuccessful();

        $this->assertNull($recibo->fresh()->periodo_id);
    }

    #[Test]
    public function al_recibo_de_un_solo_mes_le_pone_el_mes_de_su_cuota(): void
    {
        $julio = $this->periodo(7, 'JULIO');

        // Se capturó en septiembre y se quedó con el periodo del día, no con
        // el de la cuota que pagó.
        $septiembre = $this->periodo(9, 'SEPTIEMBRE');

        $recibo = $this->recibo('R-MES-EQUIVOCADO', $septiembre, ['2026-07-18']);

        $this->artisan('app:corregir-periodo-recibos')->assertSuccessful();

        $this->assertSame($julio->id, (int) $recibo->fresh()->periodo_id);
    }

    #[Test]
    public function el_recibo_que_ya_estaba_bien_no_se_toca(): void
    {
        $julio = $this->periodo(7, 'JULIO');

        $recibo = $this->recibo('R-CORRECTO', $julio, ['2026-07-18']);

        $antes = $recibo->fresh()->updated_at;

        $this->artisan('app:corregir-periodo-recibos')
            ->expectsOutputToContain('Ningún recibo trae un periodo equivocado')
            ->assertSuccessful();

        $this->assertSame($julio->id, (int) $recibo->fresh()->periodo_id);
        $this->assertEquals($antes, $recibo->fresh()->updated_at);
    }

    #[Test]
    public function el_dry_run_solo_avisa(): void
    {
        $julio = $this->periodo(7, 'JULIO');

        $recibo = $this->recibo('R-AGRUPADO', $julio, ['2026-07-18', '2026-08-18']);

        $this->artisan('app:corregir-periodo-recibos', ['--dry-run' => true])
            ->expectsOutputToContain('no se actualizó')
            ->assertSuccessful();

        $this->assertSame($julio->id, (int) $recibo->fresh()->periodo_id);
    }

    #[Test]
    public function el_recibo_sin_cuota_detras_se_queda_como_esta(): void
    {
        // Un cobro de servicio suelto: su periodo no sale de ninguna cuota.
        $septiembre = $this->periodo(9, 'SEPTIEMBRE');

        $recibo = $this->recibo('R-SERVICIO', $septiembre, []);

        $this->artisan('app:corregir-periodo-recibos')->assertSuccessful();

        $this->assertSame($septiembre->id, (int) $recibo->fresh()->periodo_id);
    }

    #[Test]
    public function al_historico_sin_periodo_no_lo_toca_si_no_se_lo_piden(): void
    {
        $recibo = $this->recibo('R-HISTORICO', null, ['2023-05-18']);

        $this->artisan('app:corregir-periodo-recibos')
            ->expectsOutputToContain('--rellenar-faltantes')
            ->assertSuccessful();

        $this->assertNull($recibo->fresh()->periodo_id);
    }

    #[Test]
    public function con_la_bandera_si_le_pone_el_periodo_al_historico(): void
    {
        $recibo = $this->recibo('R-HISTORICO', null, ['2023-05-18']);

        $this->artisan('app:corregir-periodo-recibos', ['--rellenar-faltantes' => true])
            ->assertSuccessful();

        $periodo = Periodo::sole();

        $this->assertSame(2023, (int) $periodo->anio);
        $this->assertSame(5, (int) $periodo->mes);
        $this->assertSame($periodo->id, (int) $recibo->fresh()->periodo_id);
    }

    private function periodo(int $mes, string $nombre): Periodo
    {
        return Periodo::create([
            'tipo' => 'mensual',
            'anio' => 2026,
            'mes' => $mes,
            'nombre' => $nombre.' 2026',
        ]);
    }

    /**
     * @param  array<int, string>  $vencimientos  una cuota por cada mes cubierto
     */
    private function recibo(string $folio, ?Periodo $periodo, array $vencimientos): Recibo
    {
        $propietario = Propietario::firstOrCreate(['nombre' => 'El Pato']);

        $fraccionamiento = Fraccionamiento::firstOrCreate(
            ['nombre' => 'Rancho El Pato'],
            ['propietario_id' => $propietario->id]
        );

        $lote = Lote::create([
            'fraccionamiento_id' => $fraccionamiento->id,
            'manzana' => '1',
            'lote' => $folio,
            'clave' => 'M1-L'.$folio,
        ]);

        $cliente = Cliente::firstOrCreate(['nombres' => 'Dora', 'apellidos' => 'Cardona']);

        $contrato = Contrato::create([
            'cliente_id' => $cliente->id,
            'lote_id' => $lote->id,
            'folio_contrato' => 'CT-'.$folio,
            'fecha_inicio' => '2026-07-18',
            'frecuencia' => 'mensual',
            'dia_mes' => 18,
            'precio_total' => 4500,
            'enganche' => 0,
            'saldo_inicial' => 4500,
            'saldo_actual' => 4500,
            'monto_pago' => 1500,
            'tipo_recargo' => 'fijo',
            'valor_recargo' => 0,
            'dias_gracia' => 0,
            'estatus' => 'activo',
            'tipo' => 'terreno',
        ]);

        $cuotas = collect($vencimientos)->map(fn (string $vence, int $i) => Cuota::create([
            'contrato_id' => $contrato->id,
            'numero' => $i + 1,
            'fecha_vencimiento' => $vence,
            'monto' => 1500,
            'estatus' => 'pagada',
        ]));

        $recibo = Recibo::create([
            'folio' => $folio,
            'fecha' => '2026-09-20',
            'anio' => 2026,
            'mes_del_anio' => 9,
            'cliente_id' => $cliente->id,
            'contrato_id' => $contrato->id,
            'lote_id' => $lote->id,
            'cuota_id' => $cuotas->first()?->id,
            'tipos_cobro_id' => TipoCobro::firstOrCreate(['nombre' => 'MENSUALIDAD'])->id,
            'forma_pago_id' => FormaPago::firstOrCreate(['nombre' => 'EFECTIVO'])->id,
            'periodo_id' => $periodo?->id,
            'monto' => 1500 * max(1, $cuotas->count()),
        ]);

        foreach ($cuotas as $cuota) {
            Pago::create([
                'recibo_id' => $recibo->id,
                'contrato_id' => $contrato->id,
                'cuota_id' => $cuota->id,
                'fecha_pago' => '2026-09-20',
                'monto' => 1500,
                'metodo' => 'efectivo',
                'estatus' => 'confirmado',
            ]);
        }

        return $recibo;
    }
}
