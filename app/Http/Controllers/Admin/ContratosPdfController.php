<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Contrato;
use App\Services\Contratos\ContratoPlanService;
use Barryvdh\DomPDF\Facade\Pdf;

class ContratosPdfController extends Controller
{
    public function download(Contrato $contrato)
    {
        $contrato->load([
            'cliente',
            'lote.fraccionamiento.propietario',
            'promocion',
            'cuotas' => fn ($q) => $q->orderBy('numero'),
        ]);

        $diasSemana = ContratoPlanService::diasSemana();

        $pdf = Pdf::loadView('admin.contratos.pdf', compact('contrato', 'diasSemana'))
            ->setPaper('letter', 'portrait'); // o 'landscape' si tu tabla sale apretada

        $filename = 'Contrato-'.$contrato->folio_contrato.'.pdf';

        // stream: abre en navegador
        return $pdf->stream($filename);

        // download: descarga directo
        // return $pdf->download($filename);
    }
}
