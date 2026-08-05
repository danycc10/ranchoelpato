<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Simula que un cliente mandó un comprobante por WhatsApp.
 *
 * Arma el mismo sobre que envía OpenWA, lo firma con el secreto real y lo
 * entrega al webhook de esta aplicación. Sirve para probar todo el circuito
 * (firma, deduplicación, guardado, lectura con IA, bandeja de validación) sin
 * WhatsApp, sin celular y sin la sesión conectada.
 *
 * Solo para desarrollo: se niega a correr en producción.
 */
class SimularComprobanteWhatsapp extends Command
{
    protected $signature = 'comprobantes:simular
                            {archivo : Ruta a la imagen del comprobante (jpg, png o pdf)}
                            {--telefono=5218781102902 : Teléfono del remitente, con lada}
                            {--nombre=Cliente de Prueba : Nombre que muestra WhatsApp}
                            {--texto=Aqui esta mi comprobante : Mensaje que acompaña la foto}
                            {--url= : URL del webhook (default: APP_URL + /api/whatsapp/webhook)}
                            {--omitir-archivo : Simula un adjunto demasiado grande que OpenWA no entregó}';

    protected $description = '[DEV] Simula la llegada de un comprobante de pago por WhatsApp';

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('Este comando es solo para desarrollo.');

            return self::FAILURE;
        }

        $secreto = (string) config('services.openwa.webhook_secret', '');

        if ($secreto === '') {
            $this->error('Falta OPENWA_WEBHOOK_SECRET en el .env.');

            return self::FAILURE;
        }

        $media = $this->construirMedia();

        if ($media === null) {
            return self::FAILURE;
        }

        $telefono = preg_replace('/\D+/', '', (string) $this->option('telefono'));

        $cuerpo = json_encode([
            'event' => 'message.received',
            'timestamp' => now()->toIso8601String(),
            'sessionId' => config('services.openwa.session_id') ?: 'sesion-de-prueba',
            'idempotencyKey' => (string) Str::uuid(),
            'deliveryId' => (string) Str::uuid(),
            'data' => [
                // Id único por corrida: dos simulaciones seguidas crean dos
                // comprobantes. Para probar la deduplicación, repite el mismo id.
                'id' => 'SIM_'.strtoupper(Str::random(16)),
                'from' => $telefono.'@c.us',
                'to' => '5218787851461@c.us',
                'body' => (string) $this->option('texto'),
                'type' => 'image',
                'timestamp' => now()->timestamp,
                'isGroup' => false,
                'fromMe' => false,
                'hasMedia' => true,
                'contact' => [
                    'name' => (string) $this->option('nombre'),
                    'pushName' => (string) $this->option('nombre'),
                ],
                'media' => $media,
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $url = $this->option('url')
            ?: rtrim((string) config('app.url'), '/').'/api/whatsapp/webhook';

        $this->line("Entregando a: <fg=cyan>{$url}</>");

        try {
            $respuesta = Http::withHeaders([
                'Content-Type' => 'application/json',
                // Misma firma que calcula OpenWA: HMAC-SHA256 del cuerpo crudo.
                'X-OpenWA-Signature' => 'sha256='.hash_hmac('sha256', $cuerpo, $secreto),
            ])->timeout(30)->withBody($cuerpo, 'application/json')->post($url);
        } catch (\Throwable $e) {
            $this->error('No se pudo alcanzar el webhook: '.$e->getMessage());
            $this->line('¿Está corriendo el servidor? Prueba: php artisan serve');

            return self::FAILURE;
        }

        $this->line('Respuesta HTTP '.$respuesta->status().': '.$respuesta->body());

        if ($respuesta->failed()) {
            return self::FAILURE;
        }

        $id = $respuesta->json('comprobante_id');

        if ($id) {
            $this->info("Comprobante #{$id} creado.");
            $this->line('Ahora córrelo por la IA:  php artisan comprobantes:extraer --id='.$id);
            $this->line('Y revísalo en:            /admin/comprobantes');
        }

        return self::SUCCESS;
    }

    /** @return array<string,mixed>|null */
    protected function construirMedia(): ?array
    {
        if ($this->option('omitir-archivo')) {
            $this->warn('Simulando adjunto omitido (excedió el límite de OpenWA).');

            return ['mimetype' => 'image/jpeg', 'omitted' => true, 'sizeBytes' => 4_500_000];
        }

        $ruta = (string) $this->argument('archivo');

        if (! is_file($ruta)) {
            $this->error("No existe el archivo: {$ruta}");

            return null;
        }

        $contenido = file_get_contents($ruta);

        if ($contenido === false || $contenido === '') {
            $this->error("No se pudo leer: {$ruta}");

            return null;
        }

        $mime = match (strtolower(pathinfo($ruta, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf',
            default => 'image/jpeg',
        };

        $this->line('Archivo: '.basename($ruta).' ('.number_format(strlen($contenido) / 1024, 1).' KB, '.$mime.')');

        return [
            'mimetype' => $mime,
            'filename' => basename($ruta),
            'data' => base64_encode($contenido),
        ];
    }
}
