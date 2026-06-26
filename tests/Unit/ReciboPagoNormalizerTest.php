<?php

namespace Tests\Unit;

use App\Services\Recibos\ReciboPagoNormalizer;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReciboPagoNormalizerTest extends TestCase
{
    public function test_rechaza_lista_de_pagos_vacia(): void
    {
        $this->expectException(ValidationException::class);

        (new ReciboPagoNormalizer)->normalizar([]);
    }

    public function test_normaliza_pagos_validos_y_valida_monto_esperado(): void
    {
        $pagos = (new ReciboPagoNormalizer)->normalizar(
            pagos: [
                [
                    'forma_pago_id' => '5',
                    'cuentas_bancarias_id' => '99',
                    'monto' => '125.236',
                    'referencia' => '  ABC-123  ',
                    'sin_evidencia' => true,
                ],
                [
                    'forma_pago_id' => '',
                    'monto' => '20',
                ],
            ],
            montoEsperado: 125.24,
            formaPagoRequiereCuenta: fn (?int $formaPagoId): bool => $formaPagoId === 5,
        );

        $this->assertSame([
            [
                'forma_pago_id' => 5,
                'cuentas_bancarias_id' => 99,
                'monto' => 125.24,
                'referencia' => 'ABC-123',
                'evidencia' => null,
                'sin_evidencia' => true,
                'orden' => 1,
            ],
        ], $pagos);
    }

    public function test_requiere_cuenta_y_evidencia_si_la_forma_de_pago_lo_pide(): void
    {
        $this->expectException(ValidationException::class);

        (new ReciboPagoNormalizer)->normalizar(
            pagos: [
                [
                    'forma_pago_id' => 7,
                    'monto' => 50,
                ],
            ],
            formaPagoRequiereCuenta: fn (?int $formaPagoId): bool => $formaPagoId === 7,
        );
    }
}
