<?php

namespace Tests\Feature;

use App\Services\Recibos\RecargoSeleccionAjustador;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RecargoSeleccionAjustadorTest extends TestCase
{
    #[Test]
    public function condonar_deja_en_cero_todas_las_cuotas(): void
    {
        $ajustado = $this->ajustador()->ajustar([1 => 150.0, 2 => 100.0], 'condonar', null);

        $this->assertSame([1 => 0.0, 2 => 0.0], $ajustado);
    }

    #[Test]
    public function automatico_respeta_lo_calculado_por_el_contrato(): void
    {
        $ajustado = $this->ajustador()->ajustar([1 => 150.0, 2 => 100.0], 'auto', 999.0);

        $this->assertSame([1 => 150.0, 2 => 100.0], $ajustado);
    }

    #[Test]
    public function el_monto_manual_se_reparte_en_proporcion_al_recargo_de_cada_cuota(): void
    {
        // $150 y $50 automáticos: de los $100 negociados, tres cuartas partes
        // le tocan a la cuota que traía más recargo.
        $ajustado = $this->ajustador()->ajustar([1 => 150.0, 2 => 50.0], 'manual', 100.0);

        $this->assertSame([1 => 75.0, 2 => 25.0], $ajustado);
    }

    #[Test]
    public function el_reparto_suma_exactamente_lo_que_se_cobro(): void
    {
        // Un total que no se divide parejo entre tres: los centavos sobrantes
        // se van en la última cuota para que la suma cuadre con el cobro.
        $ajustado = $this->ajustador()->ajustar([1 => 10.0, 2 => 10.0, 3 => 10.0], 'manual', 100.0);

        $this->assertSame(100.0, round(array_sum($ajustado), 2));
        $this->assertSame([1 => 33.33, 2 => 33.33, 3 => 33.34], $ajustado);
    }

    #[Test]
    public function sin_recargo_automatico_el_monto_manual_se_reparte_parejo(): void
    {
        $ajustado = $this->ajustador()->ajustar([1 => 0.0, 2 => 0.0], 'manual', 50.0);

        $this->assertSame([1 => 25.0, 2 => 25.0], $ajustado);
    }

    #[Test]
    public function un_monto_manual_en_cero_equivale_a_condonar(): void
    {
        $ajustado = $this->ajustador()->ajustar([1 => 150.0, 2 => 100.0], 'manual', 0.0);

        $this->assertSame([1 => 0.0, 2 => 0.0], $ajustado);
    }

    private function ajustador(): RecargoSeleccionAjustador
    {
        return new RecargoSeleccionAjustador;
    }
}
