<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\AtenderMensajeWhatsapp;
use App\Models\ComprobantePago;
use App\Models\MensajeWhatsappBot;
use App\Services\Whatsapp\WhatsappProviderSelector;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Recibe los eventos de OpenWA.
 *
 * Es una ruta pública: la firma HMAC es lo único que la protege, así que se
 * verifica ANTES de mirar el contenido y contra el cuerpo crudo (una firma
 * calculada sobre JSON re-serializado no coincide).
 *
 * Responde 200 en cuanto guarda. El trabajo caro (IA, vinculación con cliente)
 * lo hace después el comando `comprobantes:extraer`, porque OpenWA corta la
 * entrega por timeout y reintenta, y no queremos que un webhook lento provoque
 * reintentos y trabajo duplicado.
 */
class OpenWaWebhookController extends Controller
{
    /** Tipos de mensaje que aceptamos como comprobante. */
    protected const TIPOS_ACEPTADOS = ['image', 'document'];

    public function __invoke(Request $request, WhatsappProviderSelector $selector): JsonResponse
    {
        if (! $this->firmaValida($request)) {
            Log::warning('Webhook de OpenWA con firma inválida', [
                'ip' => $request->ip(),
            ]);

            return response()->json(['message' => 'Firma inválida'], 401);
        }

        if ($selector->activo() !== WhatsappProviderSelector::OPENWA) {
            return response()->json(['ignorado' => 'OpenWA no es el proveedor activo']);
        }

        $evento = (string) $request->input('event', '');
        $data = $request->input('data');

        Log::info('Webhook de OpenWA recibido', [
            'evento' => $evento,
            'session_id' => $request->input('sessionId'),
            'delivery_id' => $request->input('deliveryId'),
            'mensaje_id' => is_array($data) ? ($data['id'] ?? null) : null,
            'tipo' => is_array($data) ? ($data['type'] ?? null) : null,
            'tiene_adjunto' => is_array($data) && $this->tieneAdjunto($data),
        ]);

        // Los eventos que no nos interesan se aceptan en silencio: devolver error
        // haría que OpenWA los reintente para siempre.
        if ($evento !== 'message.received') {
            return response()->json(['ignorado' => $evento]);
        }

        if (! is_array($data)) {
            return response()->json(['ignorado' => 'sin data']);
        }

        $motivo = $this->motivoParaIgnorar($data);

        if ($motivo !== null) {
            Log::info('Webhook de OpenWA ignorado', [
                'motivo' => $motivo,
                'mensaje_id' => $data['id'] ?? null,
                'tipo' => $data['type'] ?? null,
            ]);

            return response()->json(['ignorado' => $motivo]);
        }

        if (($data['type'] ?? null) === 'text') {
            return $this->registrarMensajeDeTexto($data);
        }

        return $this->registrarComprobante(
            data: $data,
            idempotencyKey: $request->input('idempotencyKey')
        );
    }

    /**
     * Compara la firma en tiempo constante contra el cuerpo crudo.
     */
    protected function firmaValida(Request $request): bool
    {
        $secreto = (string) config('services.openwa.webhook_secret', '');

        if ($secreto === '') {
            // Sin secreto configurado no hay forma de distinguir a OpenWA de
            // cualquiera que conozca la URL: se rechaza todo, no se acepta todo.
            Log::error('OPENWA_WEBHOOK_SECRET no está configurado; se rechazan los webhooks.');

            return false;
        }

        $firma = (string) $request->header('X-OpenWA-Signature', '');

        if ($firma === '') {
            return false;
        }

        $esperada = 'sha256='.hash_hmac('sha256', $request->getContent(), $secreto);

        return hash_equals($esperada, $firma);
    }

    /** Devuelve el motivo por el que el mensaje no puede atenderse, o null si sí puede. */
    protected function motivoParaIgnorar(array $data): ?string
    {
        if (! empty($data['fromMe'])) {
            return 'mensaje propio';
        }

        if (! empty($data['isGroup'])) {
            return 'mensaje de grupo';
        }

        if (empty($data['id'])) {
            return 'sin id de mensaje';
        }

        $tipo = (string) ($data['type'] ?? '');

        if ($tipo === 'text') {
            return trim((string) ($data['body'] ?? '')) === '' ? 'texto vacío' : null;
        }

        if (in_array($tipo, self::TIPOS_ACEPTADOS, true)) {
            return $this->tieneAdjunto($data) ? null : 'sin adjunto';
        }

        return "tipo no aceptado: {$tipo}";
    }

    /**
     * OpenWA 0.13 envía `media` pero no siempre el indicador `hasMedia`.
     * Se aceptan ambas formas para mantener compatibilidad entre versiones.
     */
    protected function tieneAdjunto(array $data): bool
    {
        return ! empty($data['hasMedia']) || is_array($data['media'] ?? null);
    }

    protected function registrarComprobante(array $data, mixed $idempotencyKey): JsonResponse
    {
        $waMessageId = (string) $data['id'];

        // OpenWA reintenta las entregas que fallan, así que la misma foto puede
        // llegar varias veces. El índice único sobre wa_message_id es la garantía
        // dura; esta consulta solo evita guardar el archivo de nuevo.
        $existente = ComprobantePago::where('wa_message_id', $waMessageId)->first();

        if ($existente) {
            $this->encolarAtencion($data, 'comprobante', $existente);

            return response()->json([
                'duplicado' => true,
                'comprobante_id' => $existente->id,
            ]);
        }

        $chatId = (string) ($data['from'] ?? '');
        $media = is_array($data['media'] ?? null) ? $data['media'] : [];

        $archivo = $this->guardarArchivo($media, $waMessageId);

        try {
            $comprobante = ComprobantePago::create([
                'wa_message_id' => $waMessageId,
                'wa_idempotency_key' => is_string($idempotencyKey) ? $idempotencyKey : null,
                'wa_chat_id' => $chatId,
                'telefono' => $this->telefonoDe($data, $chatId),
                'nombre_whatsapp' => $data['contact']['pushName'] ?? $data['contact']['name'] ?? null,
                'texto_mensaje' => $data['body'] ?? null,
                'recibido_en' => $this->fechaDe($data),

                'archivo_path' => $archivo['path'],
                'archivo_disk' => $archivo['disk'],
                'archivo_mime' => $archivo['mime'],
                'archivo_size' => $archivo['size'],

                'estatus' => $archivo['path'] !== null
                    ? ComprobantePago::ESTATUS_RECIBIDO
                    : ComprobantePago::ESTATUS_SIN_ARCHIVO,

                'error' => $archivo['error'],
            ]);
        } catch (QueryException $e) {
            // Carrera entre dos entregas simultáneas del mismo mensaje: el índice
            // único hizo su trabajo. No es un error que OpenWA deba reintentar.
            if ($this->esViolacionDeUnicidad($e)) {
                return response()->json(['duplicado' => true]);
            }

            throw $e;
        }

        Log::info('Comprobante de pago recibido por WhatsApp', [
            'comprobante_id' => $comprobante->id,
            'telefono' => $comprobante->telefono,
            'estatus' => $comprobante->estatus,
        ]);

        $this->encolarAtencion($data, 'comprobante', $comprobante);

        return response()->json([
            'comprobante_id' => $comprobante->id,
            'estatus' => $comprobante->estatus,
        ], 201);
    }

    protected function registrarMensajeDeTexto(array $data): JsonResponse
    {
        $mensaje = $this->encolarAtencion($data, 'texto');

        return response()->json([
            'mensaje_bot_id' => $mensaje->id,
            'duplicado' => ! $mensaje->wasRecentlyCreated,
            'estatus' => $mensaje->estatus,
        ], $mensaje->wasRecentlyCreated ? 202 : 200);
    }

    protected function encolarAtencion(
        array $data,
        string $tipo,
        ?ComprobantePago $comprobante = null
    ): MensajeWhatsappBot {
        $chatId = (string) ($data['from'] ?? '');

        $mensaje = MensajeWhatsappBot::firstOrCreate(
            ['wa_message_id' => (string) $data['id']],
            [
                'wa_chat_id' => $chatId,
                'telefono' => $this->telefonoDe($data, $chatId) ?: null,
                'cliente_id' => $comprobante?->cliente_id,
                'comprobante_id' => $comprobante?->id,
                'tipo' => $tipo,
                'contenido' => $data['body'] ?? null,
                'estatus' => MensajeWhatsappBot::ESTATUS_PENDIENTE,
                'recibido_en' => $this->fechaDe($data),
            ]
        );

        if ($mensaje->wasRecentlyCreated) {
            AtenderMensajeWhatsapp::dispatchAfterResponse($mensaje->id);
        }

        return $mensaje;
    }

    /**
     * Baja el adjunto al disco privado.
     *
     * OpenWA solo incrusta media hasta WEBHOOK_MEDIA_INLINE_MAX_BYTES; por encima
     * manda un marcador `omitted` y los bytes se pierden (no hay endpoint para
     * recuperarlos después). En ese caso se registra el comprobante sin archivo
     * para que el cobrador le pida al cliente que reenvíe.
     *
     * @return array{path: ?string, disk: ?string, mime: ?string, size: ?int, error: ?string}
     */
    protected function guardarArchivo(array $media, string $waMessageId): array
    {
        $vacio = ['path' => null, 'disk' => null, 'mime' => null, 'size' => null, 'error' => null];

        if ($media === []) {
            return [...$vacio, 'error' => 'El mensaje traía adjunto pero OpenWA no envió el archivo.'];
        }

        if (! empty($media['omitted'])) {
            $tam = (int) ($media['sizeBytes'] ?? 0);

            return [...$vacio, 'error' => 'El archivo ('.$tam.' bytes) excedió el límite de incrustación de OpenWA; pídele al cliente que lo reenvíe.'];
        }

        $base64 = $media['data'] ?? null;

        if (! is_string($base64) || $base64 === '') {
            return [...$vacio, 'error' => 'El adjunto llegó sin contenido.'];
        }

        $contenido = base64_decode($base64, true);

        if ($contenido === false || $contenido === '') {
            return [...$vacio, 'error' => 'El adjunto no pudo decodificarse.'];
        }

        $mime = (string) ($media['mimetype'] ?? 'application/octet-stream');
        $disk = 'private';

        $ruta = 'comprobantes/'.now()->format('Y/m').'/'
            .Str::slug(Str::limit($waMessageId, 40, '')).'-'.Str::random(8)
            .'.'.$this->extensionDe($mime);

        Storage::disk($disk)->put($ruta, $contenido);

        return [
            'path' => $ruta,
            'disk' => $disk,
            'mime' => $mime,
            'size' => strlen($contenido),
            'error' => null,
        ];
    }

    protected function extensionDe(string $mime): string
    {
        return match (strtolower($mime)) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'application/pdf' => 'pdf',
            default => 'bin',
        };
    }

    /**
     * Teléfono del remitente en dígitos.
     *
     * `from` puede venir como '<numero>@c.us' o, cuando el contacto usa el id de
     * privacidad de WhatsApp, como '<id>@lid' — que NO es un teléfono. Para ese
     * caso OpenWA adjunta `senderPhone` si está habilitado RESOLVE_LID_TO_PHONE.
     */
    protected function telefonoDe(array $data, string $chatId): string
    {
        $senderPhone = $data['senderPhone'] ?? $data['contact']['senderPhone'] ?? null;

        if (is_string($senderPhone) && $senderPhone !== '') {
            return $this->normalizarTelefono($senderPhone);
        }

        if (str_contains($chatId, '@lid')) {
            return '';
        }

        return $this->normalizarTelefono(Str::before($chatId, '@'));
    }

    /** Quita la lada mexicana; conserva el prefijo 1 de Estados Unidos. */
    protected function normalizarTelefono(string $telefono): string
    {
        $digitos = preg_replace('/\D+/', '', $telefono) ?: '';

        if (strlen($digitos) === 13 && str_starts_with($digitos, '521')) {
            return substr($digitos, 3);
        }

        if (strlen($digitos) === 12 && str_starts_with($digitos, '52')) {
            return substr($digitos, 2);
        }

        return $digitos;
    }

    protected function fechaDe(array $data): Carbon
    {
        $ts = $data['timestamp'] ?? null;

        // El evento trae epoch en SEGUNDOS.
        if (is_numeric($ts) && (int) $ts > 0) {
            return Carbon::createFromTimestamp((int) $ts);
        }

        return now();
    }

    protected function esViolacionDeUnicidad(QueryException $e): bool
    {
        return in_array((string) ($e->errorInfo[1] ?? ''), ['1062'], true)
            || str_contains($e->getMessage(), 'Duplicate entry');
    }
}
