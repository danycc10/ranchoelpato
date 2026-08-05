<?php

namespace App\Services\Notificaciones;

use App\Mail\CuotaAtrasadaMail;
use App\Models\Notificacion;
use App\Models\Recibo;
use App\Services\Recibos\ReciboPdfService;
use App\Services\Whatsapp\WhatsappException;
use App\Services\Whatsapp\WhatsappService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ProcesarNotificacionService
{
    public function __construct(
        protected WhatsappService $whatsapp,
        protected ReciboPdfService $reciboPdf,
    ) {}

    /**
     * Envía y actualiza el estatus. Cuando se solicita respaldo, una falla deja
     * la notificación en cola para que el programador la reintente después.
     */
    public function procesar(Notificacion $notificacion, bool $mantenerEnColaSiFalla = false): bool
    {
        try {
            $payload = is_array($notificacion->payload)
                ? $notificacion->payload
                : (array) $notificacion->payload;

            match ($notificacion->canal) {
                'correo' => $this->enviarCorreo($notificacion, $payload),
                'whatsapp' => $this->enviarWhatsapp($notificacion, $payload),
                default => throw new \RuntimeException(
                    "Canal de notificación no soportado: '{$notificacion->canal}'."
                ),
            };

            $notificacion->forceFill([
                'estatus' => 'enviado',
                'enviado_en' => now(),
                'error' => null,
            ])->save();

            return true;
        } catch (\Throwable $e) {
            $notificacion->forceFill([
                'estatus' => $mantenerEnColaSiFalla ? 'en_cola' : 'fallido',
                'error' => mb_substr($e->getMessage(), 0, 255),
            ])->save();

            Log::log(
                $mantenerEnColaSiFalla ? 'warning' : 'error',
                $mantenerEnColaSiFalla
                    ? 'Falló el envío inmediato; la notificación permanece en cola'
                    : 'Error procesando notificación',
                [
                    'id' => $notificacion->id,
                    'canal' => $notificacion->canal,
                    'destino' => $notificacion->destino,
                    'error' => $e->getMessage(),
                ]
            );

            if (! $mantenerEnColaSiFalla) {
                throw $e;
            }

            return false;
        }
    }

    protected function enviarCorreo(Notificacion $notificacion, array $payload): void
    {
        Mail::to($notificacion->destino)->send(new CuotaAtrasadaMail($payload));
    }

    protected function enviarWhatsapp(Notificacion $notificacion, array $payload): void
    {
        $destino = trim((string) $notificacion->destino);

        if ($destino === '') {
            throw new WhatsappException('La notificación no tiene teléfono destino.');
        }

        $reciboId = $payload['recibo_id'] ?? null;

        if (! empty($reciboId)) {
            $recibo = Recibo::find($reciboId);

            if (! $recibo) {
                throw new \RuntimeException("El recibo #{$reciboId} ya no existe; no se envió el comprobante.");
            }

            $this->whatsapp->enviarDocumento(
                telefono: $destino,
                contenido: $this->reciboPdf->generar($recibo),
                nombreArchivo: $this->reciboPdf->nombreArchivo($recibo),
                caption: $this->textoDe($payload),
            );

            return;
        }

        $texto = $this->textoDe($payload);

        if ($texto === null) {
            throw new \RuntimeException('La notificación de WhatsApp no trae mensaje ni recibo que enviar.');
        }

        $this->whatsapp->enviarTexto($destino, $texto);
    }

    protected function textoDe(array $payload): ?string
    {
        $mensaje = $payload['mensaje'] ?? null;

        if (! is_string($mensaje)) {
            return null;
        }

        $mensaje = trim($mensaje);

        return $mensaje !== '' ? $mensaje : null;
    }
}
