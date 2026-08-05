<?php

namespace App\Jobs;

use App\Models\MensajeWhatsappBot;
use App\Services\Whatsapp\BotWhatsappLimiter;
use App\Services\Whatsapp\BotWhatsappService;
use App\Services\Whatsapp\WhatsappService;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

class AtenderMensajeWhatsapp
{
    use Dispatchable;

    public function __construct(public int $mensajeId) {}

    public function handle(
        BotWhatsappService $bot,
        BotWhatsappLimiter $limiter,
        WhatsappService $whatsapp
    ): void {
        $mensaje = MensajeWhatsappBot::with('comprobante')->find($this->mensajeId);

        if (! $mensaje || $mensaje->estatus === MensajeWhatsappBot::ESTATUS_RESPONDIDO) {
            return;
        }

        $mensaje->forceFill([
            'estatus' => MensajeWhatsappBot::ESTATUS_PROCESANDO,
            'error' => null,
        ])->save();

        try {
            $resultado = $mensaje->tipo === 'comprobante'
                ? $this->respuestaComprobante($mensaje)
                : $this->respuestaIa($mensaje, $bot, $limiter);

            $mensaje->forceFill([
                'respuesta' => $resultado['texto'],
                'modelo' => $resultado['modelo'],
                'cliente_id' => $resultado['cliente_id'] ?? $mensaje->cliente_id,
            ])->save();

            $whatsapp->enviarTextoAChat($mensaje->wa_chat_id, $resultado['texto']);

            $mensaje->forceFill([
                'estatus' => MensajeWhatsappBot::ESTATUS_RESPONDIDO,
                'respondido_en' => now(),
            ])->save();

            Log::info('Bot de WhatsApp respondió un mensaje', [
                'mensaje_bot_id' => $mensaje->id,
                'tipo' => $mensaje->tipo,
                'cliente_id' => $mensaje->cliente_id,
            ]);
        } catch (\Throwable $e) {
            $mensaje->forceFill([
                'estatus' => MensajeWhatsappBot::ESTATUS_ERROR,
                'error' => mb_substr($e->getMessage(), 0, 1000),
            ])->save();

            Log::error('Error atendiendo mensaje con el bot de WhatsApp', [
                'mensaje_bot_id' => $mensaje->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** @return array{texto:string,modelo:?string,cliente_id:?int} */
    protected function respuestaIa(
        MensajeWhatsappBot $mensaje,
        BotWhatsappService $bot,
        BotWhatsappLimiter $limiter
    ): array {
        $respuestaSinIa = $limiter->respuestaSinIa($mensaje);

        if ($respuestaSinIa !== null) {
            Log::info('Mensaje de WhatsApp respondido sin IA por límite de consumo', [
                'mensaje_bot_id' => $mensaje->id,
                'wa_chat_id' => $mensaje->wa_chat_id,
            ]);

            return [
                'texto' => $respuestaSinIa,
                'modelo' => null,
                'cliente_id' => $mensaje->cliente_id,
            ];
        }

        try {
            return $bot->responder($mensaje);
        } catch (\Throwable $e) {
            Log::warning('La IA del bot no respondió; se usará el mensaje de respaldo', [
                'mensaje_bot_id' => $mensaje->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'texto' => 'Hola. Puedes enviar por este chat una foto legible o un PDF completo de tu comprobante de pago. Un cobrador lo revisará y, cuando quede validado, recibirás aquí tu recibo oficial en PDF. Para atención especial comunícate a Oficina '.config('services.openai.bot_support_phone', '878 785 1461').'.',
                'modelo' => null,
                'cliente_id' => $mensaje->cliente_id,
            ];
        }
    }

    /** @return array{texto:string,modelo:null,cliente_id:?int} */
    protected function respuestaComprobante(MensajeWhatsappBot $mensaje): array
    {
        $comprobante = $mensaje->comprobante;

        if (! $comprobante || ! $comprobante->tieneArchivo()) {
            $texto = 'No pudimos descargar el archivo del comprobante. Por favor envíalo nuevamente como foto o PDF y verifica que se vea completo.';
        } else {
            $texto = 'Recibimos tu comprobante de pago con folio de seguimiento CP-'.$comprobante->id
                .'. Un cobrador lo revisará y, cuando quede validado, te enviaremos por aquí el recibo oficial en PDF. Este mensaje todavía no confirma que el pago haya sido aplicado.';
        }

        return [
            'texto' => $texto,
            'modelo' => null,
            'cliente_id' => $mensaje->cliente_id,
        ];
    }
}
