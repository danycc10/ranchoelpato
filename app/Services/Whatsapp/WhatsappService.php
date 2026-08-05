<?php

namespace App\Services\Whatsapp;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Punto único de salida de WhatsApp.
 *
 * Mantiene compatibilidad con OpenWA y delega a la Cloud API oficial cuando
 * ese proveedor está seleccionado en Sistema. Los endpoints de OpenWA responden
 * 201 aunque el número NO exista en
 * WhatsApp, así que enviar a ciegas produce cobranzas "enviadas" que nunca
 * llegaron. Por eso todo envío resuelve primero el destinatario contra
 * GET /contacts/check/{number}, que devuelve el chat id canónico del engine.
 * Ese id se usa tal cual como chatId: no armamos el JID a mano, porque el
 * formato ('@c.us', '@lid') depende del engine y de si WhatsApp conserva el
 * '1' de México (52 vs 521).
 */
class WhatsappService
{
    /** Días que se recuerda el chat id resuelto de un teléfono. */
    protected const CACHE_DIAS = 30;

    protected string $baseUrl;

    protected ?string $apiKey;

    protected ?string $sessionId;

    protected string $codigoPais;

    protected int $timeout;

    protected bool $configuracionOpenWaManual;

    protected ?MetaWhatsappService $meta = null;

    public function __construct(?array $config = null)
    {
        $this->configuracionOpenWaManual = $config !== null;
        $config ??= config('services.openwa', []);

        $this->baseUrl = rtrim((string) ($config['base_url'] ?? ''), '/');
        $this->apiKey = $config['api_key'] ?? null;
        $this->sessionId = $config['session_id'] ?? null;
        $this->codigoPais = preg_replace('/\D+/', '', (string) ($config['codigo_pais'] ?? '52')) ?: '52';
        $this->timeout = max(1, (int) ($config['timeout'] ?? 20));
    }

    public function estaConfigurado(): bool
    {
        if ($this->usaMeta()) {
            return $this->meta()->estaConfigurado();
        }

        return $this->baseUrl !== ''
            && ! empty($this->apiKey)
            && ! empty($this->sessionId);
    }

    /**
     * Envía un mensaje de texto.
     *
     * @param  string  $telefono  Teléfono del cliente (se acepta con o sin lada/símbolos).
     */
    public function enviarTexto(string $telefono, string $texto): array
    {
        if ($this->usaMeta()) {
            return $this->meta()->enviarTexto($telefono, $texto);
        }

        $texto = trim($texto);

        if ($texto === '') {
            throw new WhatsappException('No se puede enviar un mensaje de WhatsApp vacío.');
        }

        return $this->post('messages/send-text', [
            'chatId' => $this->resolverChatId($telefono),
            'text' => $texto,
        ]);
    }

    /** Responde al chat exacto que originó un webhook, incluso si usa un identificador @lid. */
    public function enviarTextoAChat(string $chatId, string $texto): array
    {
        if ($this->usaMeta()) {
            return $this->meta()->enviarTextoAChat($chatId, $texto);
        }

        $chatId = trim($chatId);
        $texto = trim($texto);

        if ($chatId === '' || ! str_contains($chatId, '@')) {
            throw new WhatsappException('El mensaje entrante no tiene un chat de WhatsApp válido.');
        }

        if ($texto === '') {
            throw new WhatsappException('No se puede enviar una respuesta de WhatsApp vacía.');
        }

        // Un mensaje pudo entrar por Meta justo antes de cambiar a OpenWA. En
        // ese caso el chat es un teléfono sin sufijo y se resuelve normalmente.
        if (! str_contains($chatId, '@')) {
            return $this->enviarTexto($chatId, $texto);
        }

        return $this->post('messages/send-text', [
            'chatId' => $chatId,
            'text' => $texto,
        ]);
    }

    /**
     * Envía un documento ya generado en memoria (típicamente el PDF de un recibo).
     *
     * Se manda como base64 y no como URL a propósito: el hosting del sistema no
     * necesita exponer el recibo públicamente, y OpenWA rechaza por SSRF las URLs
     * que resuelven a direcciones privadas.
     *
     * @param  string  $contenido  Bytes crudos del archivo (NO base64: se codifica aquí).
     */
    public function enviarDocumento(
        string $telefono,
        string $contenido,
        string $nombreArchivo,
        ?string $caption = null,
        string $mimetype = 'application/pdf'
    ): array {
        if ($this->usaMeta()) {
            return $this->meta()->enviarDocumento($telefono, $contenido, $nombreArchivo, $caption, $mimetype);
        }

        if ($contenido === '') {
            throw new WhatsappException("El archivo '{$nombreArchivo}' está vacío; no se envió.");
        }

        $payload = [
            'chatId' => $this->resolverChatId($telefono),
            'base64' => base64_encode($contenido),
            'mimetype' => $mimetype,
            'filename' => $nombreArchivo,
        ];

        $caption = $caption !== null ? trim($caption) : null;

        if (! empty($caption)) {
            $payload['caption'] = $caption;
        }

        return $this->post('messages/send-document', $payload);
    }

    /**
     * Devuelve el chat id canónico de un teléfono, o lanza si no está en WhatsApp.
     *
     * El resultado se cachea porque un número no entra y sale de WhatsApp seguido,
     * y sin caché cada envío costaría una consulta extra a la pasarela.
     */
    public function resolverChatId(string $telefono): string
    {
        if ($this->usaMeta()) {
            return $this->meta()->destinatario($telefono);
        }

        $digitos = $this->soloDigitos($telefono);

        if ($digitos === '') {
            throw new WhatsappException('El cliente no tiene teléfono capturado.');
        }

        $cacheKey = "whatsapp:chatid:{$this->sessionId}:{$digitos}";

        $cacheado = Cache::get($cacheKey);

        if (is_string($cacheado) && $cacheado !== '') {
            return $cacheado;
        }

        $intentados = [];

        foreach ($this->candidatos($digitos) as $candidato) {
            $intentados[] = $candidato;
            $chatId = $this->consultarNumero($candidato);

            if ($chatId !== null) {
                Cache::put($cacheKey, $chatId, now()->addDays(self::CACHE_DIAS));

                return $chatId;
            }
        }

        throw new WhatsappException(
            'El número '.$digitos.' no está registrado en WhatsApp (se probó: '.implode(', ', $intentados).').'
        );
    }

    /** Olvida el chat id memorizado de un teléfono (útil si el cliente cambia de número). */
    public function olvidarChatId(string $telefono): void
    {
        if ($this->usaMeta()) {
            return;
        }

        $digitos = $this->soloDigitos($telefono);

        if ($digitos !== '') {
            Cache::forget("whatsapp:chatid:{$this->sessionId}:{$digitos}");
        }
    }

    /**
     * Consulta un candidato concreto. Devuelve el chat id, o null si no existe.
     *
     * Un error de red aquí NO se traga: si la pasarela está caída queremos que el
     * envío falle, no que se reporte "el número no existe", que mandaría a corregir
     * un teléfono que estaba bien.
     */
    protected function consultarNumero(string $numero): ?string
    {
        $respuesta = $this->cliente()->get($this->url("contacts/check/{$numero}"));

        if ($respuesta->failed()) {
            throw new WhatsappException(
                'OpenWA no pudo verificar el número '.$numero.': '.$this->mensajeDeError($respuesta->json(), $respuesta->status())
            );
        }

        $datos = $respuesta->json();

        if (! is_array($datos) || ! ($datos['exists'] ?? false)) {
            return null;
        }

        $chatId = $datos['whatsappId'] ?? null;

        return is_string($chatId) && $chatId !== '' ? $chatId : null;
    }

    /**
     * Formatos a probar, del más probable al menos.
     *
     * México es el caso incómodo: WhatsApp migró de '521' + 10 dígitos a '52' + 10,
     * y conviven cuentas viejas con el '1'. Probamos ambos en vez de fijar uno.
     */
    protected function candidatos(string $digitos): array
    {
        $largo = strlen($digitos);
        $lada = $this->codigoPais;

        $candidatos = [];

        if ($largo === 10) {
            $candidatos[] = $lada.$digitos;

            if ($lada === '52') {
                $candidatos[] = '521'.$digitos;
                // Compatibilidad con clientes de EE. UU. que previamente se
                // hayan capturado sin el prefijo internacional 1.
                $candidatos[] = '1'.$digitos;
            }
        } elseif ($lada === '52' && $largo === 12 && str_starts_with($digitos, '52')) {
            $candidatos[] = $digitos;
            $candidatos[] = '521'.substr($digitos, 2);
        } elseif ($lada === '52' && $largo === 13 && str_starts_with($digitos, '521')) {
            $candidatos[] = $digitos;
            $candidatos[] = '52'.substr($digitos, 3);
        } else {
            // Número internacional ya normalizado: se usa tal cual.
            $candidatos[] = $digitos;
        }

        return array_values(array_unique($candidatos));
    }

    protected function post(string $ruta, array $payload): array
    {
        $respuesta = $this->cliente()->post($this->url($ruta), $payload);

        if ($respuesta->failed()) {
            $mensaje = $this->mensajeDeError($respuesta->json(), $respuesta->status());

            Log::error('Fallo al enviar por WhatsApp', [
                'ruta' => $ruta,
                'status' => $respuesta->status(),
                'chat_id' => $payload['chatId'] ?? null,
                'error' => $mensaje,
            ]);

            throw new WhatsappException("OpenWA rechazó el envío ({$ruta}): {$mensaje}");
        }

        $datos = $respuesta->json();

        return is_array($datos) ? $datos : [];
    }

    protected function cliente(): PendingRequest
    {
        if (! $this->estaConfigurado()) {
            throw new WhatsappException(
                'WhatsApp no está configurado: revisa OPENWA_BASE_URL, OPENWA_API_KEY y OPENWA_SESSION_ID en el .env.'
            );
        }

        return Http::withHeaders(['X-API-Key' => $this->apiKey])
            ->timeout($this->timeout)
            ->acceptJson()
            ->asJson();
    }

    protected function url(string $ruta): string
    {
        return $this->baseUrl.'/api/sessions/'.$this->sessionId.'/'.ltrim($ruta, '/');
    }

    protected function mensajeDeError(mixed $cuerpo, int $status): string
    {
        if (is_array($cuerpo)) {
            $mensaje = $cuerpo['message'] ?? $cuerpo['error'] ?? null;

            if (is_array($mensaje)) {
                $mensaje = implode('; ', array_map('strval', $mensaje));
            }

            if (is_string($mensaje) && $mensaje !== '') {
                return "HTTP {$status}: {$mensaje}";
            }
        }

        return "HTTP {$status}";
    }

    protected function soloDigitos(string $telefono): string
    {
        return preg_replace('/\D+/', '', $telefono) ?? '';
    }

    protected function usaMeta(): bool
    {
        if ($this->configuracionOpenWaManual) {
            return false;
        }

        return app(WhatsappProviderSelector::class)->activo() === WhatsappProviderSelector::META;
    }

    protected function meta(): MetaWhatsappService
    {
        return $this->meta ??= app(MetaWhatsappService::class);
    }
}
