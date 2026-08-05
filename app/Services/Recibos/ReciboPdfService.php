<?php

namespace App\Services\Recibos;

use App\Models\Recibo;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Genera el PDF de un recibo a partir de la vista de impresión.
 *
 * Existe para que correo y WhatsApp entreguen exactamente el mismo documento:
 * antes cada canal armaba el PDF por su cuenta, y un cambio en la vista o en las
 * relaciones a precargar solo llegaba a uno de los dos.
 */
class ReciboPdfService
{
    /** Relaciones que la vista de impresión necesita para no disparar consultas N+1. */
    protected const RELACIONES = [
        'cliente',
        'contrato',
        'lote.fraccionamiento',
        'tipoCobro',
        'formaPago',
        'cuentaBancaria',
        'periodo',
        'capturadoPor',
    ];

    /** Bytes crudos del PDF, listos para adjuntar o enviar. */
    public function generar(Recibo $recibo): string
    {
        $recibo->loadMissing(self::RELACIONES);

        return Pdf::loadView('admin.recibos.imprimir', [
            'recibo' => $recibo,
            'modo' => 'pdf',
        ])->output();
    }

    public function nombreArchivo(Recibo $recibo): string
    {
        return 'Recibo-'.($recibo->folio ?? $recibo->id).'.pdf';
    }
}
