<?php

namespace App\Services\Whatsapp;

use App\Models\MensajeWhatsappBot;
use Illuminate\Support\Facades\Cache;

class BotWhatsappLimiter
{
    protected int $limiteHora;

    protected int $limiteDia;

    protected int $maximoCaracteres;

    protected string $telefonoOficina;

    public function __construct(?array $config = null)
    {
        $config ??= config('services.openai', []);

        $this->limiteHora = max(1, (int) ($config['bot_hourly_limit'] ?? 8));
        $this->limiteDia = max($this->limiteHora, (int) ($config['bot_daily_limit'] ?? 20));
        $this->maximoCaracteres = max(100, (int) ($config['bot_max_message_chars'] ?? 500));
        $this->telefonoOficina = trim((string) ($config['bot_support_phone'] ?? '878 785 1461'));
    }

    /**
     * Devuelve una respuesta fija cuando no se debe llamar a OpenAI. Un null
     * significa que el mensaje todavía puede consumir una respuesta de IA.
     */
    public function respuestaSinIa(MensajeWhatsappBot $mensaje): ?string
    {
        if (mb_strlen(trim((string) $mensaje->contenido)) > $this->maximoCaracteres) {
            return 'Tu mensaje es demasiado largo para este chat. Envíalo en partes más cortas o manda directamente una foto o PDF legible de tu comprobante.'.$this->contactoOficina();
        }

        $consulta = MensajeWhatsappBot::query()
            ->where('wa_chat_id', $mensaje->wa_chat_id)
            ->where('tipo', 'texto')
            ->whereNotNull('modelo')
            ->where('id', '!=', $mensaje->id);

        $usosUltimaHora = (clone $consulta)
            ->where('created_at', '>=', now()->subHour())
            ->count();
        $claveHora = 'bot_whatsapp:hora:'.hash('sha256', $mensaje->wa_chat_id).':'.now()->format('YmdH');

        Cache::add($claveHora, $usosUltimaHora, now()->addHour()->startOfHour()->addMinute());

        if ((int) Cache::increment($claveHora) > $this->limiteHora) {
            return 'Alcanzaste temporalmente el límite de respuestas automáticas. Intenta nuevamente más tarde o envía directamente una foto o PDF legible de tu comprobante.'.$this->contactoOficina();
        }

        $usosHoy = (clone $consulta)
            ->where('created_at', '>=', now()->startOfDay())
            ->count();
        $claveDia = 'bot_whatsapp:dia:'.hash('sha256', $mensaje->wa_chat_id).':'.now()->format('Ymd');

        Cache::add($claveDia, $usosHoy, now()->addDay()->startOfDay()->addMinute());

        if ((int) Cache::increment($claveDia) > $this->limiteDia) {
            return 'Por hoy alcanzaste el límite de respuestas automáticas. Puedes enviar directamente una foto o PDF legible de tu comprobante.'.$this->contactoOficina();
        }

        return null;
    }

    protected function contactoOficina(): string
    {
        return $this->telefonoOficina !== ''
            ? ' Para atención humana comunícate a Oficina '.$this->telefonoOficina.'.'
            : ' Un integrante del equipo de cobranza revisará tu caso.';
    }
}
