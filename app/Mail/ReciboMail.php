<?php

namespace App\Mail;

use App\Models\Recibo;
use App\Services\Recibos\ReciboPdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ReciboMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Recibo $recibo
    ) {}

    public function build()
    {
        // Mismo PDF que se manda por WhatsApp: una sola fuente de verdad.
        $pdfService = app(ReciboPdfService::class);

        $pdfBytes = $pdfService->generar($this->recibo);
        $filename = $pdfService->nombreArchivo($this->recibo);

        // ✅ Link (si quieres mostrar botón "Ver recibo")
        $urlVerRecibo = null;
        try {
            $urlVerRecibo = route('admin.recibos.imprimir', $this->recibo->uuid ?? $this->recibo->id);
        } catch (\Throwable $e) {
            $urlVerRecibo = null;
        }

        return $this->subject('Recibo '.($this->recibo->folio ?? ''))
            ->markdown('emails.recibos.recibo', [
                'recibo' => $this->recibo,
                'urlVerRecibo' => $urlVerRecibo, // opcional
            ])
            ->attachData($pdfBytes, $filename, [
                'mime' => 'application/pdf',
            ]);
    }
}
