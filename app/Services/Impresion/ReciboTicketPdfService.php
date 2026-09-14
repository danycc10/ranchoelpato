<?php

namespace App\Services\Impresion;

use App\Models\Recibo;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Collection;

/**
 * Genera el PDF de un ticket con la misma hoja y el mismo blade que la
 * impresión del navegador, para que el agente imprima exactamente lo que el
 * usuario ve en pantalla.
 */
class ReciboTicketPdfService
{
    /** 80 mm, el mismo ancho de hoja que el `@page` del ticket en el navegador. */
    public const ANCHO_HOJA = 226.77;

    /** Hoja de sondeo: alta de sobra para que el ticket quepa entero y medirlo. */
    private const ALTO_SONDEO = 3000.0;

    /**
     * Aire bajo la última línea: el descendente de la letra más el relleno
     * inferior del ticket, que no dejan rastro en el PDF.
     */
    private const AIRE_INFERIOR = 12.0;

    /** Alto mínimo razonable de un ticket, por si la medición sale corta. */
    private const ALTO_MINIMO = 180.0;

    /**
     * @param  array<int, int>  $adjuntoIds  recibos de recargo que van dentro
     *                                       del mismo ticket que el principal
     */
    public function generar(Recibo $recibo, array $adjuntoIds = []): string
    {
        $html = $this->html($recibo, $adjuntoIds);

        return $this->renderizar($html, $this->altoDelContenido($html));
    }

    /**
     * Alto que ocupa el ticket, para que la hoja termine donde termina el
     * recibo igual que el `size: 80mm auto` del navegador.
     *
     * Se mide sobre una hoja alta de sobra: el PDF dice en qué coordenada
     * quedó la tinta más baja, y de ahí sale el alto real.
     */
    public function altoDelContenido(string $html): float
    {
        $sondeo = $this->dompdf($html, self::ALTO_SONDEO);

        $tinta = $this->tintaMasBaja($sondeo->output(['compress' => 0]));

        if ($tinta === null) {
            return self::ALTO_SONDEO;
        }

        return max(
            self::ALTO_MINIMO,
            min(self::ALTO_SONDEO, self::ALTO_SONDEO - $tinta + self::AIRE_INFERIOR)
        );
    }

    protected function renderizar(string $html, float $alto): string
    {
        $pdf = $this->dompdf($html, $alto);

        // Si por un pelo no cupo, la hoja crece: partir el ticket en dos
        // páginas saldría peor que dejarle un poco de cola.
        if ($pdf->getCanvas()->get_page_count() > 1) {
            $pdf = $this->dompdf($html, min(self::ALTO_SONDEO, $alto * 1.15));
        }

        return $pdf->output();
    }

    protected function dompdf(string $html, float $alto): Dompdf
    {
        $opciones = new Options;
        $opciones->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($opciones);
        $dompdf->setPaper([0, 0, self::ANCHO_HOJA, $alto], 'portrait');
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->render();

        return $dompdf;
    }

    /**
     * Coordenada Y más baja con tinta. En un PDF la Y crece hacia arriba, así
     * que el mínimo es el pie del contenido.
     *
     * Solo se miran los flujos de contenido de la página (los que traen
     * operadores de texto): los de las imágenes son binarios comprimidos y
     * darían números al azar.
     */
    protected function tintaMasBaja(string $pdf): ?float
    {
        preg_match_all('#stream\r?\n(.*?)\r?\nendstream#s', $pdf, $flujos);

        $minimo = null;

        foreach ($flujos[1] as $flujo) {
            if (! str_contains($flujo, 'BT ')) {
                continue;
            }

            foreach ($this->coordenadasY($flujo) as $y) {
                if ($y < 0) {
                    continue;
                }

                $minimo = $minimo === null ? $y : min($minimo, $y);
            }
        }

        return $minimo;
    }

    /**
     * @return array<int, float>
     */
    protected function coordenadasY(string $flujo): array
    {
        $numero = '(-?[\d.]+)';
        $ys = [];

        // Texto ("x y Td"), trazos ("x y m" / "x y l") e imágenes
        // ("a b c d x y cm"). Es todo lo que dibuja el ticket.
        $patrones = [
            "#{$numero}\\s+{$numero}\\s+(?:Td|TD)\\b#" => 2,
            "#{$numero}\\s+{$numero}\\s+[ml]\\s#" => 2,
            "#{$numero}\\s+{$numero}\\s+{$numero}\\s+{$numero}\\s+{$numero}\\s+{$numero}\\s+cm\\b#" => 6,
        ];

        foreach ($patrones as $patron => $grupo) {
            if (preg_match_all($patron, $flujo, $coincidencias)) {
                foreach ($coincidencias[$grupo] as $y) {
                    $ys[] = (float) $y;
                }
            }
        }

        // Los rectángulos se saltan a propósito: el único del ticket es el
        // fondo blanco de la hoja, que llega hasta abajo y taparía la medición.

        return $ys;
    }

    protected function html(Recibo $recibo, array $adjuntoIds): string
    {
        $recibo->load($this->relaciones());

        return view('admin.recibos.imprimir-lote', [
            'tickets' => collect([[
                'recibo' => $recibo,
                'adjuntos' => $this->adjuntos($recibo, $adjuntoIds),
            ]]),
            'modo' => 'pdf',
            'token' => null,
        ])->render();
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
