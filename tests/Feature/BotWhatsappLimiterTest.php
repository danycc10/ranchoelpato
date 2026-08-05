<?php

namespace Tests\Feature;

use App\Models\MensajeWhatsappBot;
use App\Services\Whatsapp\BotWhatsappLimiter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BotWhatsappLimiterTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function bloquea_solo_al_chat_que_alcanzo_el_limite_por_hora(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            $this->mensaje("anterior-{$i}", 'chat-limitado@c.us', 'gpt-5-nano');
        }

        $actual = $this->mensaje('actual-limitado', 'chat-limitado@c.us');
        $otroChat = $this->mensaje('actual-otro', 'otro-chat@c.us');
        $limiter = new BotWhatsappLimiter([
            'bot_hourly_limit' => 3,
            'bot_daily_limit' => 10,
            'bot_max_message_chars' => 500,
            'bot_support_phone' => '878 785 1461',
        ]);

        $respuesta = (string) $limiter->respuestaSinIa($actual);

        $this->assertStringContainsString('temporalmente', $respuesta);
        $this->assertStringContainsString('Oficina 878 785 1461', $respuesta);
        $this->assertNull($limiter->respuestaSinIa($otroChat));
    }

    #[Test]
    public function rechaza_un_texto_excesivo_sin_necesidad_de_llamar_a_ia(): void
    {
        $mensaje = $this->mensaje('texto-largo', 'chat-largo@c.us', contenido: str_repeat('a', 501));
        $limiter = new BotWhatsappLimiter([
            'bot_hourly_limit' => 8,
            'bot_daily_limit' => 20,
            'bot_max_message_chars' => 500,
        ]);

        $this->assertStringContainsString('demasiado largo', (string) $limiter->respuestaSinIa($mensaje));
    }

    protected function mensaje(
        string $waMessageId,
        string $chatId,
        ?string $modelo = null,
        string $contenido = 'Hola'
    ): MensajeWhatsappBot {
        return MensajeWhatsappBot::create([
            'wa_message_id' => $waMessageId,
            'wa_chat_id' => $chatId,
            'telefono' => '8781234567',
            'tipo' => 'texto',
            'contenido' => $contenido,
            'respuesta' => $modelo ? 'Respuesta previa' : null,
            'modelo' => $modelo,
            'estatus' => $modelo
                ? MensajeWhatsappBot::ESTATUS_RESPONDIDO
                : MensajeWhatsappBot::ESTATUS_PENDIENTE,
            'recibido_en' => now(),
            'respondido_en' => $modelo ? now() : null,
        ]);
    }
}
