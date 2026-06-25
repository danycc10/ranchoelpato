<?php

namespace Tests\Unit;

use App\Models\Contrato;
use PHPUnit\Framework\TestCase;

class ContratoFrecuenciaRecargoTest extends TestCase
{
    public function test_calcula_frecuencia_de_recargo_desde_dias_de_gracia(): void
    {
        $this->assertSame(1, Contrato::frecuenciaRecargoDiasPorGracia(0));
        $this->assertSame(4, Contrato::frecuenciaRecargoDiasPorGracia(3));
        $this->assertSame(7, Contrato::frecuenciaRecargoDiasPorGracia(7));
        $this->assertSame(5, Contrato::frecuenciaRecargoDiasPorGracia(5));
        $this->assertSame(1, Contrato::frecuenciaRecargoDiasPorGracia(-2));
    }
}
