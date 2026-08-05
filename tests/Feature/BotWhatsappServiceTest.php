<?php

namespace Tests\Feature;

use App\Models\MensajeWhatsappBot;
use App\Services\Whatsapp\BotWhatsappService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BotWhatsappServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function genera_una_respuesta_breve_con_la_responses_api(): void
    {
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([
                'id' => 'resp_bot_prueba',
                'model' => 'gpt-5-nano-2025-08-07',
                'output' => [[
                    'type' => 'message',
                    'content' => [[
                        'type' => 'output_text',
                        'text' => 'Envíame una foto clara o el PDF completo de tu comprobante.',
                    ]],
                ]],
                'usage' => [
                    'input_tokens' => 120,
                    'input_tokens_details' => ['cached_tokens' => 0],
                    'output_tokens' => 20,
                    'output_tokens_details' => ['reasoning_tokens' => 5],
                    'total_tokens' => 140,
                ],
            ]),
        ]);

        $mensaje = MensajeWhatsappBot::create([
            'wa_message_id' => 'wa-1',
            'wa_chat_id' => '5218781234567@c.us',
            'telefono' => '8781234567',
            'tipo' => 'texto',
            'contenido' => 'Quiero enviar mi comprobante',
            'estatus' => MensajeWhatsappBot::ESTATUS_PENDIENTE,
            'recibido_en' => now(),
        ]);

        $servicio = new BotWhatsappService([
            'api_key' => 'clave-prueba',
            'base_url' => 'https://api.openai.com/v1',
            'bot_model' => 'gpt-5-nano',
            'bot_reasoning_effort' => 'minimal',
            'bot_max_output_tokens' => 140,
            'bot_history_limit' => 3,
            'bot_timeout' => 10,
        ]);

        $resultado = $servicio->responder($mensaje);

        $this->assertSame('Envíame una foto clara o el PDF completo de tu comprobante.', $resultado['texto']);
        $this->assertSame('gpt-5-nano-2025-08-07', $resultado['modelo']);
        $this->assertDatabaseHas('openai_usos', [
            'request_id' => 'resp_bot_prueba',
            'modelo' => 'gpt-5-nano-2025-08-07',
            'operacion' => 'bot_whatsapp',
            'tokens_total' => 140,
        ]);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.openai.com/v1/responses'
            && $request['model'] === 'gpt-5-nano'
            && $request['reasoning']['effort'] === 'minimal'
            && $request['max_output_tokens'] === 140
            && $request['store'] === false
            && $request['input'][0]['content'] === 'Quiero enviar mi comprobante');
    }
}
