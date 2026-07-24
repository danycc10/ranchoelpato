<?php

namespace Tests\Unit;

use App\Services\Contratos\ContratoPlanService;
use PHPUnit\Framework\TestCase;

class ContratoPlanServiceTest extends TestCase
{
    public function test_el_saldo_planeado_termina_en_cero_con_una_ultima_cuota_distinta(): void
    {
        $saldos = ContratoPlanService::calcularSaldosPlaneados([
            350,
            350,
            350,
            350,
            350,
            350,
            100,
        ]);

        $this->assertSame([
            1850.0,
            1500.0,
            1150.0,
            800.0,
            450.0,
            100.0,
            0.0,
        ], $saldos);
    }

    public function test_calcula_desde_el_plan_vigente_y_redondea_centavos(): void
    {
        $this->assertSame(
            [200.15, 100.1, 0.0],
            ContratoPlanService::calcularSaldosPlaneados(['100.10', '100.05', '100.10'])
        );
    }
}
