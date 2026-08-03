<?php

namespace Tests\Unit;

use App\Services\Recibos\CuotaAntiguedadSelector;
use PHPUnit\Framework\TestCase;

class CuotaAntiguedadSelectorTest extends TestCase
{
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

    public function test_permite_una_cuota_fuera_de_orden_solo_como_excepcion_autorizada(): void
    {
        $selector = new CuotaAntiguedadSelector;

        $this->assertFalse($selector->seleccionPermitida([10, 20, 30], [30], false));
        $this->assertTrue($selector->seleccionPermitida([10, 20, 30], [30], true));
    }
}
