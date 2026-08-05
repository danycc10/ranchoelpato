<?php

namespace App\Services\Whatsapp;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaWhatsappService
{
    protected string $baseUrl;

    protected string $version;

    protected ?string $accessToken;

    protected ?string $phoneNumberId;

    protected string $codigoPais;

    protected int $timeout;

    public function __construct(?array $config = null)
    {
        $config ??= config('services.meta_whatsapp', []);

        $this->baseUrl = rtrim((string) ($config['base_url'] ?? 'https://graph.facebook.com'), '/');
        $this->version = trim((string) ($config['version'] ?? 'v23.0'), '/');
        $this->accessToken = $config['access_token'] ?? null;
        $this->phoneNumberId = $config['phone_number_id'] ?? null;
        $this->codigoPais = preg_replace('/\D+/', '', (string) ($config['codigo_pais'] ?? '52')) ?: '52';
        $this->timeout = max(5, (int) ($config['timeout'] ?? 30));
    }

    public function estaConfigurado(): bool
    {
        return $this->baseUrl !== ''
            && $this->version !== ''
            && ! empty($this->accessToken)
            && ! empty($this->phoneNumberId);
    }

    public function enviarTexto(string $telefono, string $texto): array
    {
        $texto = trim($texto);

        if ($texto === '') {
            throw new WhatsappException('No se puede enviar un mensaje de WhatsApp vacío.');
        }

        return $this->enviarMensaje([
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->destinatario($telefono),
            'type' => 'text',
            'text' => [
                'preview_url' => false,
                'body' => $texto,
            ],
        ]);
    }

    public function enviarTextoAChat(string $chatId, string $texto): array
    {
        return $this->enviarTexto($chatId, $texto);
    }

    public function enviarDocumento(
        string $telefono,
        string $contenido,
        string $nombreArchivo,
        ?string $caption = null,
        string $mimetype = 'application/pdf'
    ): array {
        if ($contenido === '') {
            throw new WhatsappException("El archivo '{$nombreArchivo}' está vacío; no se envió.");
        }

        $mediaId = $this->subirMedia($contenido, $nombreArchivo, $mimetype);
        $documento = [
            'id' => $mediaId,
            'filename' => $nombreArchivo,
        ];

        if (filled($caption)) {
            $documento['caption'] = trim((string) $caption);
        }

        return $this->enviarMensaje([
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->destinatario($telefono),
            'type' => 'document',
            'document' => $documento,
        ]);
    }

    /** @return array{contenido:string,mime:string} */
    public function descargarMedia(string $mediaId): array
    {
        $metadata = $this->cliente()->get($this->graphUrl($mediaId));
        $this->validarRespuesta($metadata, 'consultar archivo');

        $url = $metadata->json('url');

        if (! is_string($url) || $url === '') {
            throw new WhatsappException('Meta no devolvió la URL temporal del archivo.');
        }

        $archivo = $this->cliente()->get($url);
        $this->validarRespuesta($archivo, 'descargar archivo');

        return [
            'contenido' => $archivo->body(),
            'mime' => (string) ($metadata->json('mime_type') ?: $archivo->header('Content-Type') ?: 'application/octet-stream'),
        ];
    }

    public function destinatario(string $telefono): string
    {
        $digitos = preg_replace('/\D+/', '', $telefono) ?: '';

        if ($digitos === '') {
            throw new WhatsappException('El cliente no tiene teléfono capturado.');
        }

        if (strlen($digitos) === 13 && str_starts_with($digitos, '521')) {
            return '52'.substr($digitos, 3);
        }

        if (strlen($digitos) === 10) {
            return $this->codigoPais.$digitos;
        }

        return $digitos;
    }

    protected function subirMedia(string $contenido, string $nombreArchivo, string $mimetype): string
    {
        $respuesta = $this->cliente()
            ->attach('file', $contenido, $nombreArchivo, ['Content-Type' => $mimetype])
            ->post($this->graphUrl($this->phoneNumberId.'/media'), [
                'messaging_product' => 'whatsapp',
                'type' => $mimetype,
            ]);

        $this->validarRespuesta($respuesta, 'subir documento');
        $mediaId = $respuesta->json('id');

        if (! is_string($mediaId) || $mediaId === '') {
            throw new WhatsappException('Meta aceptó el archivo pero no devolvió su identificador.');
        }

        return $mediaId;
    }

    protected function enviarMensaje(array $payload): array
    {
        $respuesta = $this->cliente()->post(
            $this->graphUrl($this->phoneNumberId.'/messages'),
            $payload,
        );

        $this->validarRespuesta($respuesta, 'enviar mensaje');

        return is_array($respuesta->json()) ? $respuesta->json() : [];
    }

    protected function cliente(): PendingRequest
    {
        if (! $this->estaConfigurado()) {
            throw new WhatsappException(
                'WhatsApp oficial no está configurado: revisa META_WHATSAPP_ACCESS_TOKEN y META_WHATSAPP_PHONE_NUMBER_ID.'
            );
        }

        return Http::withToken((string) $this->accessToken)
            ->timeout($this->timeout)
            ->acceptJson();
    }

    protected function graphUrl(string $ruta): string
    {
        return $this->baseUrl.'/'.$this->version.'/'.ltrim($ruta, '/');
    }

    protected function validarRespuesta($respuesta, string $operacion): void
    {
        if ($respuesta->successful()) {
            return;
        }

        $mensaje = $respuesta->json('error.message') ?: 'HTTP '.$respuesta->status();

        Log::error('WhatsApp Cloud API rechazó una operación', [
            'operacion' => $operacion,
            'status' => $respuesta->status(),
            'error' => $mensaje,
        ]);

        throw new WhatsappException("Meta no pudo {$operacion}: {$mensaje}");
    }
}
