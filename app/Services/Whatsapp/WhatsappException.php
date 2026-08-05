<?php

namespace App\Services\Whatsapp;

use RuntimeException;

/**
 * Falla al hablar con OpenWA o al resolver un destinatario.
 *
 * Se lanza en vez de devolver null/false a propósito: quien envía notificaciones
 * las marca 'fallido' al capturar la excepción, y un envío que falla en silencio
 * en cobranza es peor que uno que falla ruidosamente.
 */
class WhatsappException extends RuntimeException {}
