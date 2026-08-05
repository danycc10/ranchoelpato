<?php

namespace Tests\Feature;

use App\Services\Comprobantes\ComprobanteExtractorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ComprobanteExtractorUsageTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function registra_los_tokens_al_leer_un_comprobante(): void
    {
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'id' => 'chatcmpl_comprobante_prueba',
                'model' => 'gpt-4o-mini-2024-07-18',
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'es_comprobante' => true,
                            'monto' => 850,
                            'fecha' => '2026-08-04',
                            'banco' => 'Banco de prueba',
                            'referencia' => 'ABC123',
                            'ultimos_digitos' => '4567',
                            'beneficiario' => 'Rancho El Pato',
                            'confianza' => 0.95,
                            'notas' => null,
                        ], JSON_THROW_ON_ERROR),
                    ],
                ]],
                'usage' => [
                    'prompt_tokens' => 1800,
                    'prompt_tokens_details' => ['cached_tokens' => 0],
                    'completion_tokens' => 120,
                    'completion_tokens_details' => ['reasoning_tokens' => 0],
                    'total_tokens' => 1920,
                ],
            ]),
        ]);

        $servicio = new ComprobanteExtractorService([
            'api_key' => 'clave-prueba',
            'base_url' => 'https://api.openai.com/v1',
            'model' => 'gpt-4o-mini',
            'max_lado_px' => 1568,
            'timeout' => 10,
        ]);

        $resultado = $servicio->extraer('%PDF-archivo-prueba', 'application/pdf');

        $this->assertTrue($resultado['es_comprobante']);
        $this->assertSame(850.0, $resultado['monto']);
        $this->assertDatabaseHas('openai_usos', [
            'request_id' => 'chatcmpl_comprobante_prueba',
            'modelo' => 'gpt-4o-mini-2024-07-18',
            'operacion' => 'comprobante_extraccion',
            'tokens_total' => 1920,
        ]);
    }
}
