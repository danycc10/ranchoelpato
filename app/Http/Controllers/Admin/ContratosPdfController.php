<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Contrato;
use App\Services\Contratos\ContratoPlanService;
use App\Services\Contratos\LotesClienteService;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Dompdf\Canvas;
use Dompdf\FontMetrics;

class ContratosPdfController extends Controller
{
    public function download(string $uuid)
    {
        $contrato = Contrato::withTrashed()
            ->where('uuid', $uuid)
            ->firstOrFail();

        $contrato->load([
            'cliente',
            'lote.fraccionamiento.propietario',
            'promocion',
            'cuotas' => fn ($q) => $q->orderBy('numero'),
        ]);

        $diasSemana = ContratoPlanService::diasSemana();

        // El financiamiento de luz ampara todos los lotes del cliente, no solo el del contrato.
        $esFinanciamientoElectrico = $contrato->tipo === 'servicio'
            && $contrato->servicio_tipo === 'electricidad';

        $lotesCliente = $esFinanciamientoElectrico
            ? LotesClienteService::resumen($contrato)
            : null;

        $pdf = Pdf::loadView('admin.contratos.pdf', compact('contrato', 'diasSemana', 'lotesCliente'))
            ->setPaper('letter', 'portrait'); // o 'landscape' si tu tabla sale apretada

        // Las hojas sueltas no dicen de quien son: de la segunda en adelante
        // llevan arriba el nombre y el lote.
        $this->identificarHojasSiguientes($pdf, self::identificadorDeHoja($contrato, $lotesCliente));

        $filename = 'Contrato-'.$contrato->folio_contrato.'.pdf';

        // stream: abre en navegador
        return $pdf->stream($filename);

        // download: descarga directo
        // return $pdf->download($filename);
    }

    /** "NOMBRE  ·  Lote 12", con el nombre como sale en la primera hoja. */
    public static function identificadorDeHoja(Contrato $contrato, ?array $lotesCliente = null): string
    {
        $nombre = mb_strtoupper(trim((string) $contrato->cliente?->nombre_completo));

        $lotes = trim((string) ($lotesCliente['numeros'] ?? $contrato->lote?->lote));
        $etiqueta = str_contains($lotes, ' y ') ? 'Lotes ' : 'Lote ';

        return collect([$nombre, $lotes !== '' ? $etiqueta.$lotes : null])
            ->filter()
            ->implode('  ·  ');
    }

    private function identificarHojasSiguientes(DomPdf $pdf, string $texto): void
    {
        if ($texto === '') {
            return;
        }

        // El texto se escribe sobre las hojas ya armadas.
        $pdf->render();

        $pdf->getDomPDF()->getCanvas()->page_script(function (int $hoja, int $total, Canvas $canvas, FontMetrics $metricas) use ($texto) {
            if ($hoja === 1) {
                return;
            }

            $fuente = $metricas->getFont('DejaVu Sans');
            $ancho = $metricas->getTextWidth($texto, $fuente, 12);

            $canvas->text(($canvas->get_width() - $ancho) / 2, 8, $texto, $fuente, 12, [0.3, 0.3, 0.3]);
        });
    }
}
