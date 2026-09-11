<?php

namespace App\Services\Impresion;

use App\Models\Recibo;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;

/**
 * Genera el PDF de un ticket con la misma hoja y el mismo blade que la
 * impresión del navegador, para que el agente imprima exactamente lo que el
 * usuario ve en pantalla.
 */
class ReciboTicketPdfService
{
    /** Hoja del ticket: 80 mm de ancho, la misma que usa RecibosPrintController. */
    public const HOJA_TICKET = [0, 0, 226.77, 700];

    /**
     * @param  array<int, int>  $adjuntoIds  recibos de recargo que van dentro
     *                                       del mismo ticket que el principal
     */
    public function generar(Recibo $recibo, array $adjuntoIds = []): string
    {
        $recibo->load($this->relaciones());

        $tickets = collect([[
            'recibo' => $recibo,
            'adjuntos' => $this->adjuntos($recibo, $adjuntoIds),
        ]]);

        $pdf = Pdf::loadView('admin.recibos.imprimir-lote', [
            'tickets' => $tickets,
            'modo' => 'pdf',
            'token' => null,
        ]);

        $pdf->setPaper(self::HOJA_TICKET, 'portrait');

        return $pdf->output();
    }

    public function nombreArchivo(Recibo $recibo): string
    {
        return 'recibo_'.$recibo->folio.'.pdf';
    }

    /**
     * @param  array<int, int>  $adjuntoIds
     * @return Collection<int, Recibo>
     */
    protected function adjuntos(Recibo $recibo, array $adjuntoIds): Collection
    {
        $ids = collect($adjuntoIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->reject(fn (int $id) => $id === (int) $recibo->id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return Recibo::with($this->relaciones())
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn (Recibo $r) => $ids->search($r->id))
            ->values();
    }

    /** Las mismas relaciones que carga la impresión del navegador. */
    protected function relaciones(): array
    {
        return [
            'cliente',
            'contrato',
            'lote.fraccionamiento',
            'tipoCobro',
            'formaPago',
            'cuentaBancaria',
            'periodo',
            'capturadoPor',
            'cuota',
            'pagos.cuota',
            'pagosDetalle.formaPago',
            'pagosDetalle.cuentaBancaria',
        ];
    }
}
