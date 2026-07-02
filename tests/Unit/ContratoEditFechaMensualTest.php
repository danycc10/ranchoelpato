<?php

namespace Tests\Unit;

use App\Livewire\Admin\Contratos\Edit;
use Carbon\Carbon;
use Tests\TestCase;

class ContratoEditFechaMensualTest extends TestCase
{
    public function test_fecha_mensual_despues_de_ultima_pagada_avanza_al_siguiente_mes(): void
    {
        $component = new class extends Edit
        {
            public function calcular(string $base, int $day, bool $estrictamenteDespues): string
            {
                return $this->fechaMensualDesdeBase(
                    Carbon::parse($base),
                    $day,
                    $estrictamenteDespues
                )->toDateString();
            }
        };

        $this->assertSame('2026-08-05', $component->calcular('2026-07-05', 5, true));
        $this->assertSame('2026-08-05', $component->calcular('2026-08-05', 5, false));
    }
}
