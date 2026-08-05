<?php

namespace Tests\Feature;

use App\Jobs\AtenderMensajeWhatsapp;
use App\Models\ComprobantePago;
use App\Models\MensajeWhatsappBot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MetaWhatsappWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.whatsapp.provider', 'meta');
        config()->set('services.meta_whatsapp.enabled', true);
        config()->set('services.meta_whatsapp', [
            'enabled' => true,
            'base_url' => 'https://graph.facebook.com',
            'version' => 'v23.0',
            'access_token' => 'token-meta',
            'phone_number_id' => 'phone-123',
            'verify_token' => 'verificar-meta',
            'app_secret' => 'app-secret-meta',
            'codigo_pais' => '52',
            'timeout' => 10,
        ]);
    }

    #[Test]
    public function responde_el_reto_de_verificacion_de_meta(): void
    {
        $this->get('/api/whatsapp/meta/webhook?hub.mode=subscribe&hub.verify_token=verificar-meta&hub.challenge=123456')
            ->assertOk()
            ->assertSeeText('123456');
    }

    #[Test]
    public function el_webhook_no_esta_disponible_mientras_la_integracion_esta_desactivada(): void
    {
        config()->set('services.meta_whatsapp.enabled', false);

        $this->get('/api/whatsapp/meta/webhook?hub.mode=subscribe&hub.verify_token=verificar-meta&hub.challenge=123456')
            ->assertNotFound();
    }

    #[Test]
    public function registra_y_encola_un_texto_firmado_por_meta(): void
    {
        Bus::fake();
        $payload = $this->payload([
            'from' => '5218781234567',
            'id' => 'wamid.texto-meta',
            'timestamp' => '1786000000',
            'type' => 'text',
            'text' => ['body' => 'Quiero enviar mi comprobante'],
        ]);

        $this->postFirmado($payload)
            ->assertOk()
            ->assertJsonPath('mensajes_procesados', 1);

        $mensaje = MensajeWhatsappBot::firstOrFail();
        $this->assertSame('wamid.texto-meta', $mensaje->wa_message_id);
        $this->assertSame('8781234567', $mensaje->telefono);
        $this->assertSame('5218781234567', $mensaje->wa_chat_id);
        Bus::assertDispatchedAfterResponse(
            AtenderMensajeWhatsapp::class,
            fn (AtenderMensajeWhatsapp $job) => $job->mensajeId === $mensaje->id,
        );
    }

    #[Test]
    public function descarga_una_imagen_y_crea_el_mismo_comprobante_manual(): void
    {
        Bus::fake();
        Storage::fake('private');
        Http::fake([
            'https://graph.facebook.com/v23.0/media-meta-1' => Http::response([
                'url' => 'https://lookaside.fbsbx.com/media-meta-1',
                'mime_type' => 'image/jpeg',
            ]),
            'https://lookaside.fbsbx.com/media-meta-1' => Http::response('imagen-binaria', 200, [
                'Content-Type' => 'image/jpeg',
            ]),
        ]);
        $payload = $this->payload([
            'from' => '5218781234567',
            'id' => 'wamid.imagen-meta',
            'timestamp' => '1786000000',
            'type' => 'image',
            'image' => [
                'id' => 'media-meta-1',
                'mime_type' => 'image/jpeg',
                'caption' => 'Pago de agosto',
            ],
        ]);

        $this->postFirmado($payload)->assertOk()->assertJsonPath('mensajes_procesados', 1);

        $comprobante = ComprobantePago::firstOrFail();
        $this->assertSame(ComprobantePago::ESTATUS_RECIBIDO, $comprobante->estatus);
        $this->assertSame('8781234567', $comprobante->telefono);
        $this->assertSame('Cliente Meta', $comprobante->nombre_whatsapp);
        Storage::disk('private')->assertExists($comprobante->archivo_path);
        $this->assertDatabaseHas('mensajes_whatsapp_bot', [
            'wa_message_id' => 'wamid.imagen-meta',
            'comprobante_id' => $comprobante->id,
            'tipo' => 'comprobante',
        ]);
    }

    protected function payload(array $mensaje): array
    {
        return [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => 'waba-123',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'metadata' => ['phone_number_id' => 'phone-123'],
                        'contacts' => [[
                            'wa_id' => '5218781234567',
                            'profile' => ['name' => 'Cliente Meta'],
                        ]],
                        'messages' => [$mensaje],
                    ],
                ]],
            ]],
        ];
    }

    protected function postFirmado(array $payload)
    {
        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        $firma = 'sha256='.hash_hmac('sha256', $json, 'app-secret-meta');

        return $this->call(
            'POST',
            '/api/whatsapp/meta/webhook',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_HUB_SIGNATURE_256' => $firma,
            ],
            content: $json,
        );
    }
}
