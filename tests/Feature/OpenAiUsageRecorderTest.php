<?php

namespace Tests\Feature;

use App\Services\OpenAi\OpenAiUsageRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OpenAiUsageRecorderTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function registra_responses_api_y_calcula_el_costo_sin_duplicar_razonamiento(): void
    {
        Log::spy();

        $respuesta = [
            'id' => 'resp_uso_1',
            'model' => 'gpt-5-nano-2025-08-07',
            'usage' => [
                'input_tokens' => 1000,
                'input_tokens_details' => ['cached_tokens' => 200],
                'output_tokens' => 100,
                'output_tokens_details' => ['reasoning_tokens' => 40],
                'total_tokens' => 1100,
            ],
        ];

        $registro = app(OpenAiUsageRecorder::class)
            ->registrar($respuesta, 'bot_whatsapp', 'responses', 'gpt-5-nano');

        $this->assertNotNull($registro);
        $this->assertSame(1000, $registro->tokens_entrada);
        $this->assertSame(200, $registro->tokens_entrada_cache);
        $this->assertSame(100, $registro->tokens_salida);
        $this->assertSame(40, $registro->tokens_razonamiento);
        $this->assertSame(1100, $registro->tokens_total);
        $this->assertSame('0.0000810000', $registro->costo_estimado_usd);

        Log::shouldHaveReceived('info')
            ->once()
            ->with('Consumo de tokens de OpenAI registrado.', \Mockery::on(
                fn (array $contexto) => $contexto['request_id'] === 'resp_uso_1'
                    && $contexto['tokens_total'] === 1100
            ));
    }

    #[Test]
    public function registra_chat_completions_y_evitar_duplicar_un_request_id(): void
    {
        $respuesta = [
            'id' => 'chatcmpl_uso_1',
            'model' => 'gpt-4o-mini-2024-07-18',
            'usage' => [
                'prompt_tokens' => 2000,
                'prompt_tokens_details' => ['cached_tokens' => 500],
                'completion_tokens' => 300,
                'completion_tokens_details' => ['reasoning_tokens' => 0],
                'total_tokens' => 2300,
            ],
        ];

        $servicio = app(OpenAiUsageRecorder::class);
        $primero = $servicio->registrar($respuesta, 'comprobante_extraccion', 'chat_completions');
        $segundo = $servicio->registrar($respuesta, 'comprobante_extraccion', 'chat_completions');

        $this->assertSame($primero?->id, $segundo?->id);
        $this->assertDatabaseCount('openai_usos', 1);
        $this->assertSame('0.0004425000', $primero?->costo_estimado_usd);
    }
}
