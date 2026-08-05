<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\AtenderMensajeWhatsapp;
use App\Models\ComprobantePago;
use App\Models\MensajeWhatsappBot;
use App\Services\Clientes\ClientePorTelefonoService;
use App\Services\Whatsapp\MetaWhatsappService;
use App\Services\Whatsapp\WhatsappProviderSelector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class MetaWhatsappWebhookController extends Controller
{
    public function verificar(Request $request): Response
    {
        abort_unless(config('services.meta_whatsapp.enabled', false), 404);

        $modo = (string) $request->query('hub_mode', $request->query('hub.mode', ''));
        $token = (string) $request->query('hub_verify_token', $request->query('hub.verify_token', ''));
        $challenge = (string) $request->query('hub_challenge', $request->query('hub.challenge', ''));
        $esperado = (string) config('services.meta_whatsapp.verify_token', '');

        if ($modo !== 'subscribe' || $esperado === '' || ! hash_equals($esperado, $token)) {
            return response('', 403);
        }

        return response($challenge, 200)->header('Content-Type', 'text/plain');
    }

    public function recibir(
        Request $request,
        WhatsappProviderSelector $selector,
        MetaWhatsappService $meta,
        ClientePorTelefonoService $telefonos,
    ): JsonResponse {
        abort_unless(config('services.meta_whatsapp.enabled', false), 404);

        if (! $this->firmaValida($request)) {
            Log::warning('Webhook de Meta WhatsApp con firma inválida', ['ip' => $request->ip()]);

            return response()->json(['message' => 'Firma inválida'], 401);
        }

        if ($selector->activo() !== WhatsappProviderSelector::META) {
            return response()->json(['ignorado' => 'Meta no es el proveedor activo']);
        }

        $procesados = 0;

        foreach ($this->mensajesDelPayload($request->all()) as $entrada) {
            $mensaje = $entrada['mensaje'];
            $value = $entrada['value'];
            $tipo = (string) ($mensaje['type'] ?? '');

            if ($tipo === 'text') {
                $this->registrarTexto($mensaje, $value, $telefonos);
                $procesados++;

                continue;
            }

            if (in_array($tipo, ['image', 'document'], true)) {
                $this->registrarComprobante($mensaje, $value, $tipo, $meta, $telefonos);
                $procesados++;
            }
        }

        Log::info('Webhook oficial de Meta WhatsApp procesado', ['mensajes' => $procesados]);

        return response()->json(['recibido' => true, 'mensajes_procesados' => $procesados]);
    }

    /** @return array<int, array{mensaje:array,value:array}> */
    protected function mensajesDelPayload(array $payload): array
    {
        $resultado = [];

        foreach (($payload['entry'] ?? []) as $entry) {
            foreach (($entry['changes'] ?? []) as $change) {
                $value = is_array($change['value'] ?? null) ? $change['value'] : [];

                foreach (($value['messages'] ?? []) as $mensaje) {
                    if (is_array($mensaje) && ! empty($mensaje['id']) && ! empty($mensaje['from'])) {
                        $resultado[] = ['mensaje' => $mensaje, 'value' => $value];
                    }
                }
            }
        }

        return $resultado;
    }

    protected function registrarTexto(
        array $mensaje,
        array $value,
        ClientePorTelefonoService $telefonos,
    ): void {
        $registro = $this->registrarMensajeBot(
            mensaje: $mensaje,
            value: $value,
            tipo: 'texto',
            contenido: $mensaje['text']['body'] ?? null,
            telefonos: $telefonos,
        );

        if ($registro->wasRecentlyCreated) {
            AtenderMensajeWhatsapp::dispatchAfterResponse($registro->id);
        }
    }

    protected function registrarComprobante(
        array $mensaje,
        array $value,
        string $tipo,
        MetaWhatsappService $meta,
        ClientePorTelefonoService $telefonos,
    ): void {
        $waMessageId = (string) $mensaje['id'];
        $existente = ComprobantePago::where('wa_message_id', $waMessageId)->first();

        if ($existente) {
            $registro = $this->registrarMensajeBot(
                $mensaje,
                $value,
                'comprobante',
                $mensaje[$tipo]['caption'] ?? null,
                $telefonos,
                $existente,
            );

            if ($registro->wasRecentlyCreated) {
                AtenderMensajeWhatsapp::dispatchAfterResponse($registro->id);
            }

            return;
        }

        $mediaId = (string) ($mensaje[$tipo]['id'] ?? '');
        $archivo = $this->descargarYGuardar($meta, $mediaId, $waMessageId);
        $telefono = $telefonos->normalizar((string) $mensaje['from']) ?? (string) $mensaje['from'];
        $comprobante = ComprobantePago::create([
            'wa_message_id' => $waMessageId,
            'wa_idempotency_key' => 'meta:'.$waMessageId,
            'wa_chat_id' => (string) $mensaje['from'],
            'telefono' => $telefono,
            'nombre_whatsapp' => $this->nombreContacto($value, (string) $mensaje['from']),
            'texto_mensaje' => $mensaje[$tipo]['caption'] ?? null,
            'recibido_en' => $this->fechaDe($mensaje),
            'archivo_path' => $archivo['path'],
            'archivo_disk' => $archivo['disk'],
            'archivo_mime' => $archivo['mime'],
            'archivo_size' => $archivo['size'],
            'estatus' => $archivo['path'] !== null
                ? ComprobantePago::ESTATUS_RECIBIDO
                : ComprobantePago::ESTATUS_SIN_ARCHIVO,
            'error' => $archivo['error'],
        ]);
        $registro = $this->registrarMensajeBot(
            $mensaje,
            $value,
            'comprobante',
            $mensaje[$tipo]['caption'] ?? null,
            $telefonos,
            $comprobante,
        );

        if ($registro->wasRecentlyCreated) {
            AtenderMensajeWhatsapp::dispatchAfterResponse($registro->id);
        }
    }

    protected function registrarMensajeBot(
        array $mensaje,
        array $value,
        string $tipo,
        ?string $contenido,
        ClientePorTelefonoService $telefonos,
        ?ComprobantePago $comprobante = null,
    ): MensajeWhatsappBot {
        $telefono = $telefonos->normalizar((string) $mensaje['from']) ?? (string) $mensaje['from'];

        return MensajeWhatsappBot::firstOrCreate(
            ['wa_message_id' => (string) $mensaje['id']],
            [
                'wa_chat_id' => (string) $mensaje['from'],
                'telefono' => $telefono,
                'cliente_id' => $comprobante?->cliente_id,
                'comprobante_id' => $comprobante?->id,
                'tipo' => $tipo,
                'contenido' => $contenido,
                'estatus' => MensajeWhatsappBot::ESTATUS_PENDIENTE,
                'recibido_en' => $this->fechaDe($mensaje),
            ],
        );
    }

    /** @return array{path:?string,disk:?string,mime:?string,size:?int,error:?string} */
    protected function descargarYGuardar(
        MetaWhatsappService $meta,
        string $mediaId,
        string $waMessageId,
    ): array {
        $vacio = ['path' => null, 'disk' => null, 'mime' => null, 'size' => null, 'error' => null];

        if ($mediaId === '') {
            return [...$vacio, 'error' => 'Meta envió el comprobante sin identificador de archivo.'];
        }

        try {
            $media = $meta->descargarMedia($mediaId);
            $contenido = $media['contenido'];
            $mime = $media['mime'];
            $disk = 'private';
            $path = 'comprobantes/'.now()->format('Y/m').'/'.Str::slug(Str::limit($waMessageId, 40, ''))
                .'-'.Str::random(8).'.'.$this->extensionDe($mime);

            Storage::disk($disk)->put($path, $contenido);

            return [
                'path' => $path,
                'disk' => $disk,
                'mime' => $mime,
                'size' => strlen($contenido),
                'error' => null,
            ];
        } catch (\Throwable $e) {
            Log::error('No se pudo descargar un comprobante desde Meta', [
                'media_id' => $mediaId,
                'error' => $e->getMessage(),
            ]);

            return [...$vacio, 'error' => mb_substr($e->getMessage(), 0, 1000)];
        }
    }

    protected function firmaValida(Request $request): bool
    {
        $secreto = (string) config('services.meta_whatsapp.app_secret', '');
        $firma = (string) $request->header('X-Hub-Signature-256', '');

        if ($secreto === '' || $firma === '') {
            return false;
        }

        $esperada = 'sha256='.hash_hmac('sha256', $request->getContent(), $secreto);

        return hash_equals($esperada, $firma);
    }

    protected function nombreContacto(array $value, string $telefono): ?string
    {
        foreach (($value['contacts'] ?? []) as $contacto) {
            if (($contacto['wa_id'] ?? null) === $telefono) {
                return $contacto['profile']['name'] ?? null;
            }
        }

        return null;
    }

    protected function fechaDe(array $mensaje): Carbon
    {
        return is_numeric($mensaje['timestamp'] ?? null)
            ? Carbon::createFromTimestamp((int) $mensaje['timestamp'])
            : now();
    }

    protected function extensionDe(string $mime): string
    {
        return match (strtolower(Str::before($mime, ';'))) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
            default => 'bin',
        };
    }
}
