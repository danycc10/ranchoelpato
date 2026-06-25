<?php

namespace App\Mail;

use App\Models\Recibo;
use Barryvdh\DomPDF\Facade\Pdf;
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
        $this->recibo->load([
            'cliente',
            'contrato',
            'lote.fraccionamiento',
            'tipoCobro',
            'formaPago',
            'cuentaBancaria',
            'periodo',
            'capturadoPor',
        ]);

        // ✅ PDF con tu vista existente
        $pdf = Pdf::loadView('admin.recibos.imprimir', [
            'recibo' => $this->recibo,
            'modo' => 'pdf',
        ]);

        $filename = 'Recibo-'.($this->recibo->folio ?? $this->recibo->id).'.pdf';

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
            ->attachData($pdf->output(), $filename, [
                'mime' => 'application/pdf',
            ]);
    }
}
