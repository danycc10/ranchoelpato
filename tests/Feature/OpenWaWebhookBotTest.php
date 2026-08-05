<?php

namespace Tests\Feature;

use App\Jobs\AtenderMensajeWhatsapp;
use App\Models\MensajeWhatsappBot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OpenWaWebhookBotTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function registra_y_encola_un_mensaje_de_texto_firmado(): void
    {
        Bus::fake();
        config()->set('services.openwa.webhook_secret', 'secreto-prueba');

        $json = json_encode([
            'event' => 'message.received',
            'sessionId' => 'sesion-1',
            'deliveryId' => 'entrega-1',
            'data' => [
                'id' => 'wa-texto-1',
                'from' => '5218781234567@c.us',
                'chatId' => '5218781234567@c.us',
                'type' => 'text',
                'body' => 'Hola, quiero mandar mi comprobante',
                'timestamp' => 1_786_000_000,
                'fromMe' => false,
                'isGroup' => false,
            ],
        ], JSON_THROW_ON_ERROR);

        $firma = 'sha256='.hash_hmac('sha256', $json, 'secreto-prueba');

        $respuesta = $this->call(
            'POST',
            '/api/whatsapp/webhook',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_OPENWA_SIGNATURE' => $firma,
            ],
            content: $json,
        );

        $respuesta->assertAccepted()
            ->assertJsonPath('estatus', MensajeWhatsappBot::ESTATUS_PENDIENTE);

        $mensaje = MensajeWhatsappBot::firstOrFail();

        $this->assertSame('wa-texto-1', $mensaje->wa_message_id);
        $this->assertSame('8781234567', $mensaje->telefono);
        $this->assertSame('texto', $mensaje->tipo);

        Bus::assertDispatchedAfterResponse(
            AtenderMensajeWhatsapp::class,
            fn (AtenderMensajeWhatsapp $job) => $job->mensajeId === $mensaje->id
        );
    }

    #[Test]
    public function no_duplica_ni_vuelve_a_encolar_el_mismo_mensaje(): void
    {
        Bus::fake();
        config()->set('services.openwa.webhook_secret', 'secreto-prueba');

        $payload = [
            'event' => 'message.received',
            'data' => [
                'id' => 'wa-texto-repetido',
                'from' => '19561234567@c.us',
                'type' => 'text',
                'body' => 'Hola',
                'fromMe' => false,
                'isGroup' => false,
            ],
        ];

        $this->enviarFirmado($payload)->assertAccepted();
        $this->enviarFirmado($payload)->assertOk()->assertJsonPath('duplicado', true);

        $this->assertDatabaseCount('mensajes_whatsapp_bot', 1);
        Bus::assertDispatchedAfterResponseTimes(AtenderMensajeWhatsapp::class, 1);
    }

    private function enviarFirmado(array $payload)
    {
        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        $firma = 'sha256='.hash_hmac('sha256', $json, 'secreto-prueba');

        return $this->call(
            'POST',
            '/api/whatsapp/webhook',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_OPENWA_SIGNATURE' => $firma,
            ],
            content: $json,
        );
    }
}
