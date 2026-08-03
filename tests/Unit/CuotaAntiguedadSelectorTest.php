<?php

namespace Tests\Unit;

use App\Services\Recibos\CuotaAntiguedadSelector;
use PHPUnit\Framework\TestCase;

class CuotaAntiguedadSelectorTest extends TestCase
{
    public function test_incluye_las_cuotas_anteriores_al_elegir_una_posterior(): void
    {
        $selector = new CuotaAntiguedadSelector;

        $this->assertSame(
            [10, 20, 30],
            $selector->prefijoHastaLaUltimaSeleccionada([10, 20, 30, 40], [10, 30])
        );
    }

    public function test_rechaza_una_seleccion_que_omite_la_cuota_mas_antigua(): void
    {
        $selector = new CuotaAntiguedadSelector;

        $this->assertFalse($selector->seleccionEsPrefijo([10, 20, 30], [20]));
        $this->assertFalse($selector->seleccionEsPrefijo([10, 20, 30], [10, 30]));
    }

    public function test_acepta_una_o_varias_cuotas_consecutivas_desde_la_mas_antigua(): void
    {
        $selector = new CuotaAntiguedadSelector;

        $this->assertTrue($selector->seleccionEsPrefijo([10, 20, 30], [10]));
        $this->assertTrue($selector->seleccionEsPrefijo([10, 20, 30], [20, 10]));
    }
}
