<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PrintAgent;
use App\Models\Recibo;
use App\Services\Impresion\PrintJobService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Throwable;

class RecibosPrintController extends Controller
{
    /** Hoja del ticket: 80 mm de ancho por 247 mm, la misma para uno o varios recibos. */
    private const HOJA_TICKET = [0, 0, 226.77, 700];

    public function show(Recibo $recibo)
    {
        $recibo->load($this->relations());

        $modo = 'web';
        $tieneAgente = $this->tieneAgente();

        return view('admin.recibos.imprimir', compact('recibo', 'modo', 'tieneAgente'));
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
        [$recibos, $adjuntoIds] = $this->obtenerRecibosDesdeToken($token);
        $tickets = $this->agruparTickets($recibos, $adjuntoIds);
        $modo = 'web';
        $tieneAgente = $this->tieneAgente();

        return view('admin.recibos.imprimir-lote', compact('tickets', 'modo', 'token', 'tieneAgente'));
    }

    public function pdfLote(string $token)
    {
        [$recibos, $adjuntoIds] = $this->obtenerRecibosDesdeToken($token);
        $tickets = $this->agruparTickets($recibos, $adjuntoIds);
        $modo = 'pdf';

        // Misma hoja que el recibo individual: un recibo por página, igual de largo.
        $pdf = Pdf::loadView('admin.recibos.imprimir-lote', compact('tickets', 'modo', 'token'));
        $pdf->setPaper(self::HOJA_TICKET, 'portrait');

        return $pdf->download('recibos_lote_'.now()->format('Ymd_His').'.pdf');
    }

    /**
     * Manda el ticket a la impresora del cajero por su agente local. El botón
     * solo se pinta si tiene equipo emparejado, pero el agente puede estar
     * apagado: en ese caso el mensaje se muestra junto al botón y le queda la
     * impresión por navegador.
     */
    public function agente(Recibo $recibo, PrintJobService $cola): JsonResponse
    {
        return $this->encolar(fn () => $cola->queueRecibo(auth()->user(), $recibo), 1);
    }

    public function agenteLote(string $token, PrintJobService $cola): JsonResponse
    {
        [$recibos, $adjuntoIds] = $this->obtenerRecibosDesdeToken($token);
        $tickets = $this->agruparTickets($recibos, $adjuntoIds);

        return $this->encolar(function () use ($tickets, $cola): void {
            foreach ($tickets as $ticket) {
                $cola->queueRecibo(
                    auth()->user(),
                    $ticket['recibo'],
                    $ticket['adjuntos']->pluck('id')->all(),
                );
            }
        }, $tickets->count());
    }

    private function encolar(callable $encolar, int $cantidad): JsonResponse
    {
        if (! $this->tieneAgente()) {
            return response()->json([
                'message' => 'No tienes un equipo de impresión emparejado.',
            ], 422);
        }

        try {
            $encolar();
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => $cantidad === 1
                ? 'Ticket enviado a tu impresora.'
                : $cantidad.' tickets enviados a tu impresora.',
        ]);
    }

    private function tieneAgente(): bool
    {
        return PrintAgent::deUsuario(auth()->user()) !== null;
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

        $adjuntoIds = collect($payload['adjunto_ids'] ?? [])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        return [$recibos, $adjuntoIds];
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
     * Arma los tickets a imprimir. Los recibos que el lote marca como adjuntos
     * (los de recargo) se pegan al ticket del recibo principal en vez de salir
     * en su propia hoja.
     *
     * @param  array<int, int>  $adjuntoIds
     * @return Collection<int, array{recibo: Recibo, adjuntos: Collection<int, Recibo>}>
     */
    protected function agruparTickets($recibos, array $adjuntoIds)
    {
        $esAdjunto = fn (Recibo $r): bool => in_array((int) $r->id, $adjuntoIds, true);

        $principales = $recibos->reject($esAdjunto)->values();
        $adjuntos = $recibos->filter($esAdjunto)->values();

        // Sin principal no hay a quién pegarlos: se imprimen solos.
        if ($principales->isEmpty()) {
            return $adjuntos->map(fn (Recibo $r) => [
                'recibo' => $r,
                'adjuntos' => collect(),
            ])->values();
        }

        return $principales->map(fn (Recibo $r, int $i) => [
            'recibo' => $r,
            // Los adjuntos del lote cuelgan del primer recibo principal.
            'adjuntos' => $i === 0 ? $adjuntos : collect(),
        ])->values();
    }
}
