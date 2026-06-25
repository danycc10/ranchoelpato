<?php

namespace Tests\Unit;

use App\Models\Contrato;
use App\Models\Cuota;
use App\Services\Recibos\RecargoCuotaCalculator;
use Carbon\Carbon;
use Tests\TestCase;

class RecargoCuotaCalculatorTest extends TestCase
{
    public function test_calcula_recargo_fijo_por_frecuencia_configurada(): void
    {
        Carbon::setTestNow('2026-06-20');

        try {
            $contrato = new Contrato([
                'tipo_recargo' => 'fijo',
                'valor_recargo' => 100,
                'dias_gracia' => 3,
                'frecuencia_recargo_dias' => 4,
            ]);
            $contrato->setRelation('lote', null);

            $cuota = new Cuota([
                'fecha_vencimiento' => '2026-06-08',
                'monto' => 1000,
                'recargo_aplicado' => 0,
            ]);
            $cuota->setRelation('contrato', $contrato);

            $estado = (new RecargoCuotaCalculator)->estado($cuota);

            $this->assertTrue($estado['cuota_vencida']);
            $this->assertSame(9.0, $estado['dias_atraso']);
            $this->assertSame(300.0, $estado['recargo_monto']);
            $this->assertSame(300.0, $estado['recargo_monto_original']);
        } finally {
            Carbon::setTestNow();
        }
    }
}
