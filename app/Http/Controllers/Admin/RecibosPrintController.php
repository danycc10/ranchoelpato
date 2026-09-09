<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Recibo;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;

class RecibosPrintController extends Controller
{
    /** Hoja del ticket: 80 mm de ancho por 247 mm, la misma para uno o varios recibos. */
    private const HOJA_TICKET = [0, 0, 226.77, 700];

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
        $pdf->setPaper(self::HOJA_TICKET, 'portrait');

        return $pdf->download('recibo_'.$recibo->folio.'.pdf');
    }

    public function showLote(string $token)
    {
        [$recibos, $agrupar] = $this->obtenerRecibosDesdeToken($token);
        $tickets = $this->agruparTickets($recibos, $agrupar);
        $modo = 'web';

        return view('admin.recibos.imprimir-lote', compact('tickets', 'modo', 'token'));
    }

    public function pdfLote(string $token)
    {
        [$recibos, $agrupar] = $this->obtenerRecibosDesdeToken($token);
        $tickets = $this->agruparTickets($recibos, $agrupar);
        $modo = 'pdf';

        // Misma hoja que el recibo individual: un recibo por página, igual de largo.
        $pdf = Pdf::loadView('admin.recibos.imprimir-lote', compact('tickets', 'modo', 'token'));
        $pdf->setPaper(self::HOJA_TICKET, 'portrait');

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

        $recibos = Recibo::with($this->relations())
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn ($recibo) => $ids->search($recibo->id))
            ->values();

        return [$recibos, (bool) ($payload['agrupar'] ?? false)];
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
            // Desglose por cuota cuando un recibo agrupa varias.
            'pagos.cuota',
            'pagosDetalle.formaPago',
            'pagosDetalle.cuentaBancaria',
        ];
    }

    /**
     * Arma los tickets a imprimir. Cuando el lote viene marcado como agrupado,
     * los recibos de recargo se pegan al ticket del recibo principal en vez de
     * salir en su propia hoja.
     *
     * @return Collection<int, array{recibo: Recibo, adjuntos: Collection<int, Recibo>}>
     */
    protected function agruparTickets($recibos, bool $agrupar)
    {
        $esRecargo = fn (Recibo $r): bool => str_contains(
            mb_strtoupper((string) ($r->tipoCobro?->nombre ?? '')),
            'RECARGO'
        );

        if (! $agrupar || $recibos->count() < 2 || $recibos->every($esRecargo)) {
            return $recibos->map(fn (Recibo $r) => [
                'recibo' => $r,
                'adjuntos' => collect(),
            ])->values();
        }

        $principales = $recibos->reject($esRecargo)->values();
        $recargos = $recibos->filter($esRecargo)->values();

        return $principales->map(fn (Recibo $r, int $i) => [
            'recibo' => $r,
            // Los recargos del lote cuelgan del primer recibo principal.
            'adjuntos' => $i === 0 ? $recargos : collect(),
        ])->values();
    }
}
