<?php

namespace Tests\Unit;

use App\Http\Controllers\Webhooks\OpenWaWebhookController;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OpenWaWebhookControllerTest extends TestCase
{
    #[Test]
    public function acepta_un_adjunto_en_el_formato_de_openwa_013_sin_has_media(): void
    {
        $motivo = $this->controller()->motivo([
            'id' => 'mensaje-1',
            'type' => 'image',
            'fromMe' => false,
            'isGroup' => false,
            'media' => [
                'mimetype' => 'image/jpeg',
                'data' => base64_encode('imagen'),
            ],
        ]);

        $this->assertNull($motivo);
    }

    #[Test]
    public function sigue_ignorando_un_mensaje_sin_adjunto(): void
    {
        $motivo = $this->controller()->motivo([
            'id' => 'mensaje-2',
            'type' => 'image',
            'fromMe' => false,
            'isGroup' => false,
        ]);

        $this->assertSame('sin adjunto', $motivo);
    }

    #[Test]
    public function acepta_un_mensaje_de_texto_para_el_bot(): void
    {
        $motivo = $this->controller()->motivo([
            'id' => 'mensaje-texto-1',
            'type' => 'text',
            'body' => 'Quiero enviar mi comprobante',
            'fromMe' => false,
            'isGroup' => false,
        ]);

        $this->assertNull($motivo);
    }

    #[Test]
    public function normaliza_el_numero_de_whatsapp_al_formato_local_del_cliente(): void
    {
        $controller = $this->controller();

        $this->assertSame('8781234567', $controller->telefono(['senderPhone' => '+52 878 123 4567']));
        $this->assertSame('8781234567', $controller->telefono(['senderPhone' => '5218781234567']));
        $this->assertSame('8781234567', $controller->telefono([], '8781234567@c.us'));
        $this->assertSame('19561234567', $controller->telefono(['senderPhone' => '+1 956 123 4567']));
    }

    private function controller(): object
    {
        return new class extends OpenWaWebhookController
        {
            public function motivo(array $data): ?string
            {
                return $this->motivoParaIgnorar($data);
            }

            public function telefono(array $data, string $chatId = ''): string
            {
                return $this->telefonoDe($data, $chatId);
            }
        };
    }
}
