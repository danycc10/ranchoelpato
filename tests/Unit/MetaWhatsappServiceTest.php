<?php

namespace Tests\Unit;

use App\Services\Whatsapp\MetaWhatsappService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MetaWhatsappServiceTest extends TestCase
{
    #[Test]
    public function envia_texto_con_el_formato_de_whatsapp_cloud_api(): void
    {
        Http::fake([
            'https://graph.facebook.com/v23.0/phone-123/messages' => Http::response([
                'messages' => [['id' => 'wamid.salida']],
            ]),
        ]);

        $servicio = $this->servicio();
        $respuesta = $servicio->enviarTexto('878 123 4567', 'Pago recibido');

        $this->assertSame('wamid.salida', $respuesta['messages'][0]['id']);
        Http::assertSent(fn (Request $request) => $request->url() === 'https://graph.facebook.com/v23.0/phone-123/messages'
            && $request->hasHeader('Authorization', 'Bearer token-prueba')
            && $request['messaging_product'] === 'whatsapp'
            && $request['to'] === '528781234567'
            && $request['type'] === 'text'
            && $request['text']['body'] === 'Pago recibido');
    }

    #[Test]
    public function sube_el_pdf_y_luego_envia_el_documento_por_id(): void
    {
        Http::fake([
            'https://graph.facebook.com/v23.0/phone-123/media' => Http::response(['id' => 'media-456']),
            'https://graph.facebook.com/v23.0/phone-123/messages' => Http::response([
                'messages' => [['id' => 'wamid.documento']],
            ]),
        ]);

        $this->servicio()->enviarDocumento(
            '19561234567',
            '%PDF-contenido',
            'recibo-100.pdf',
            'Tu recibo',
        );

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/media'));
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/messages')
            && $request['to'] === '19561234567'
            && $request['type'] === 'document'
            && $request['document']['id'] === 'media-456'
            && $request['document']['filename'] === 'recibo-100.pdf');
    }

    protected function servicio(): MetaWhatsappService
    {
        return new MetaWhatsappService([
            'base_url' => 'https://graph.facebook.com',
            'version' => 'v23.0',
            'access_token' => 'token-prueba',
            'phone_number_id' => 'phone-123',
            'codigo_pais' => '52',
            'timeout' => 10,
        ]);
    }
}
