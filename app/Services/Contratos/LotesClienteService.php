<?php

namespace App\Services\Contratos;

use App\Models\Contrato;

/**
 * Los lotes de un cliente vistos desde uno de sus contratos.
 *
 * El contrato de luz y su hoja de amortización no amparan solo el lote con el
 * que se dio de alta, sino todos los lotes del cliente.
 */
class LotesClienteService
{
    /**
     * Todos los lotes del cliente, no solo el del contrato indicado.
     *
     * Se toman de los contratos no cancelados del cliente y, para cada lote, se
     * anota el folio de su contrato de terreno.
     *
     * @return list<array<string, string>>
     */
    public static function lotes(Contrato $contrato): array
    {
        $porLote = [];

        if ($contrato->cliente_id) {
            $contratos = Contrato::query()
                ->where('cliente_id', $contrato->cliente_id)
                ->whereNotNull('lote_id')
                ->where('estatus', '!=', 'cancelado')
                ->with('lote.fraccionamiento')
                ->orderByRaw("CASE WHEN COALESCE(tipo, 'terreno') = 'terreno' THEN 0 ELSE 1 END")
                ->orderBy('id')
                ->get();

            foreach ($contratos as $otro) {
                if (! $otro->lote) {
                    continue;
                }

                $porLote[$otro->lote_id] ??= ['lote' => $otro->lote, 'folio' => null];

                if (! $porLote[$otro->lote_id]['folio'] && ($otro->tipo ?: 'terreno') === 'terreno') {
                    $porLote[$otro->lote_id]['folio'] = $otro->folio_contrato;
                }
            }
        }

        // El lote del propio contrato aparece siempre, aunque el contrato esté cancelado.
        if ($contrato->lote) {
            $porLote[$contrato->lote_id] ??= ['lote' => $contrato->lote, 'folio' => null];

            $porLote[$contrato->lote_id]['folio'] = $porLote[$contrato->lote_id]['folio']
                ?: ($contrato->contratoBase?->folio_contrato ?: $contrato->folio_contrato);
        }

        $filas = [];

        foreach ($porLote as $datos) {
            $lote = $datos['lote'];

            $filas[] = [
                'fraccionamiento' => trim((string) $lote->fraccionamiento?->nombre),
                'lote' => trim((string) ($lote->lote ?: $lote->clave)),
                'manzana' => trim((string) $lote->manzana),
                'clave' => trim((string) ($lote->clave ?: $lote->lote)),
                'contrato' => trim((string) $datos['folio']),
            ];
        }

        usort($filas, fn (array $a, array $b) => strnatcasecmp($a['fraccionamiento'], $b['fraccionamiento'])
            ?: strnatcasecmp($a['manzana'], $b['manzana'])
            ?: strnatcasecmp($a['lote'], $b['lote']));

        return $filas;
    }

    /**
     * Los mismos lotes, ya unidos para imprimirse en un documento.
     *
     * @return array<string, string>
     */
    public static function resumen(Contrato $contrato): array
    {
        $lotes = self::lotes($contrato);

        $numeros = self::unir(array_column($lotes, 'lote'));
        $manzanas = self::unir(array_column($lotes, 'manzana'));
        $fraccionamientos = self::unir(array_column($lotes, 'fraccionamiento'));

        $identificacion = array_filter([
            $numeros !== '' ? self::etiqueta('Lote', 'Lotes', $numeros).' '.$numeros : '',
            $manzanas !== '' ? self::etiqueta('Manzana', 'Manzanas', $manzanas).' '.$manzanas : '',
            $fraccionamientos,
        ]);

        return [
            'numeros' => $numeros,
            'manzanas' => $manzanas,
            'fraccionamientos' => $fraccionamientos,
            'claves' => self::unir(array_column($lotes, 'clave')),
            'contratos' => self::unir(array_column($lotes, 'contrato')),
            'identificacion' => implode(', ', $identificacion),
        ];
    }

    /**
     * «19», «19 y 20», «19, 20 y 21»: valores únicos, sin vacíos.
     *
     * @param  array<int, ?string>  $valores
     */
    public static function unir(array $valores): string
    {
        $valores = array_values(array_unique(array_filter(
            array_map(fn ($valor) => trim((string) $valor), $valores),
            fn (string $valor) => $valor !== ''
        )));

        if (count($valores) <= 1) {
            return $valores[0] ?? '';
        }

        $ultimo = array_pop($valores);

        return implode(', ', $valores).' y '.$ultimo;
    }

    /**
     * Singular o plural según si la lista unida trae más de un valor.
     */
    private static function etiqueta(string $singular, string $plural, string $valores): string
    {
        return str_contains($valores, ' y ') ? $plural : $singular;
    }
}
