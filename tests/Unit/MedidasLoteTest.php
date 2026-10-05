<?php

namespace Tests\Unit;

use App\Services\MedidasLoteService;
use PHPUnit\Framework\TestCase;

class MedidasLoteTest extends TestCase
{
    public function test_une_segmentos_colineales_y_descarta_vertices_repetidos(): void
    {
        $lados = (new MedidasLoteService)->calcular([[0, 0], [5, 0], [10, 0], [10, 0], [10, 20], [0, 20], [0, 0]]);
        $this->assertSame([10.0, 20.0, 10.0, 20.0], array_column($lados, 'metros'));
        $this->assertSame([1, 2, 3, 4], array_column($lados, 'numero'));
        $this->assertSame($lados[0]['inicio'], $lados[3]['fin']);
    }

    public function test_mide_diagonales_y_cierra_poligonos_sin_repetir_el_primer_vertice(): void
    {
        $lados = (new MedidasLoteService)->calcular([[100, 100], [103, 100], [103, 104]]);
        $this->assertSame([3.0, 4.0, 5.0], array_column($lados, 'metros'));
    }

    public function test_conserva_los_lados_de_lotes_concavos(): void
    {
        $lados = (new MedidasLoteService)->calcular([[0, 0], [10, 0], [10, 5], [5, 5], [5, 10], [0, 10]]);
        $this->assertSame([10.0, 5.0, 5.0, 5.0, 5.0, 10.0], array_column($lados, 'metros'));
        $this->assertSame([], (new MedidasLoteService)->calcular([]));
    }
}
