<?php

namespace Tests\Unit;

use App\Models\Recibo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Mockery;
use PHPUnit\Framework\TestCase;

class ReciboFechaEfectivaTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_sincroniza_la_fecha_del_recibo_con_todos_sus_pagos_detalle(): void
    {
        $pagosDetalle = Mockery::mock(HasMany::class);
        $pagosDetalle
            ->shouldReceive('update')
            ->once()
            ->with(['fecha_efectiva' => '2026-07-24'])
            ->andReturn(2);

        $recibo = Mockery::mock(Recibo::class)->makePartial();
        $recibo
            ->shouldReceive('pagosDetalle')
            ->once()
            ->andReturn($pagosDetalle);

        $actualizados = $recibo->sincronizarFechaEfectivaPagos('2026-07-24');

        $this->assertSame(2, $actualizados);
    }
}
