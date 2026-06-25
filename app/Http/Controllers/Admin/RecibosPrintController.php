<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Recibo;
use Barryvdh\DomPDF\Facade\Pdf;

class RecibosPrintController extends Controller
{
    public function show(Recibo $recibo)
    {
        $recibo->load($this->relations());

        $modo = 'web';

        return view('admin.recibos.imprimir', compact('recibo', 'modo'));
    }

    public function printData(Recibo $recibo)
    {
        $recibo->load($this->relations());

        return response()->json([
            'ok' => true,
            'folio' => $recibo->folio,
            'uuid' => $recibo->uuid,
            'cliente' => trim((string) (
                $recibo->cliente->nombre_completo
                ?? ($recibo->cliente->nombre ?? '')
            )),
            'monto' => (float) $recibo->monto,
        ]);
    }

    public function printToken(Recibo $recibo)
    {
        $secret = config('services.print_bridge.secret');

        abort_if(blank($secret), 500, 'Print bridge secret no configurado.');

        $payload = [
            'recibo_uuid' => $recibo->uuid,
            'user_id' => auth()->id(),
            'expires_at' => now()->addSeconds(30)->timestamp,
        ];

        $json = json_encode($payload);

        return response()->json([
            'ok' => true,
            'payload' => base64_encode($json),
            'signature' => hash_hmac('sha256', $json, $secret),
        ]);
    }

    public function pdf(Recibo $recibo)
    {
        $recibo->load($this->relations());

        $modo = 'pdf';

        $pdf = Pdf::loadView('admin.recibos.imprimir', compact('recibo', 'modo'));
        $pdf->setPaper([0, 0, 226.77, 700], 'portrait');

        return $pdf->download('recibo_'.$recibo->folio.'.pdf');
    }

    public function showLote(string $token)
    {
        $recibos = $this->obtenerRecibosDesdeToken($token);
        $modo = 'web';

        return view('admin.recibos.imprimir-lote', compact('recibos', 'modo', 'token'));
    }

    public function pdfLote(string $token)
    {
        $recibos = $this->obtenerRecibosDesdeToken($token);
        $modo = 'pdf';

        $pdf = Pdf::loadView('admin.recibos.imprimir-lote', compact('recibos', 'modo', 'token'));
        $pdf->setPaper([0, 0, 226.77, 3000], 'portrait');

        return $pdf->download('recibos_lote_'.now()->format('Ymd_His').'.pdf');
    }

    protected function obtenerRecibosDesdeToken(string $token)
    {
        $payload = cache()->get('print_batch:'.$token);

        abort_unless($payload, 404);
        abort_unless((int) ($payload['user_id'] ?? 0) === (int) auth()->id(), 403);

        $ids = collect($payload['recibo_ids'] ?? [])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        abort_if($ids->isEmpty(), 404);

        return Recibo::with($this->relations())
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn ($recibo) => $ids->search($recibo->id))
            ->values();
    }

    protected function relations(): array
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
        ];
    }
}
