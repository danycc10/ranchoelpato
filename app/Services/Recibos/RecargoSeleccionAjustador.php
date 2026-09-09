<?php

namespace App\Services\Recibos;

/**
 * Aplica el modo de recargo elegido por el cajero (automático, condonar o un
 * monto manual) sobre TODA la selección de cuotas, no cuota por cuota.
 *
 * Cuando alguien llega a pagar varios meses de luz atrasada y se le condona o
 * se le negocia el recargo, el trato es sobre el total; aquí se reparte ese
 * total entre las cuotas para que cada una guarde la parte que le toca.
 */
class RecargoSeleccionAjustador
{
    /**
     * @param  array<int|string, float>  $recargosCalculados  recargo automático por cuota
     * @return array<int|string, float> recargo final por cuota, con las mismas claves
     */
    public function ajustar(array $recargosCalculados, string $modo, ?float $montoManual): array
    {
        if ($recargosCalculados === []) {
            return [];
        }

        if ($modo === 'condonar') {
            return array_map(fn () => 0.0, $recargosCalculados);
        }

        if ($modo !== 'manual') {
            return array_map(fn ($monto) => round(max(0, (float) $monto), 2), $recargosCalculados);
        }

        $objetivo = round(max(0, (float) ($montoManual ?? 0)), 2);

        if ($objetivo <= 0) {
            return array_map(fn () => 0.0, $recargosCalculados);
        }

        return $this->repartir($recargosCalculados, $objetivo);
    }

    /**
     * Reparte el monto objetivo en proporción al recargo automático de cada
     * cuota. Si ninguna traía recargo, se reparte en partes iguales. El
     * sobrante de los centavos se va en la última cuota para que la suma
     * cuadre exactamente con lo que se le cobró al cliente.
     *
     * @param  array<int|string, float>  $recargosCalculados
     * @return array<int|string, float>
     */
    protected function repartir(array $recargosCalculados, float $objetivo): array
    {
        $totalCalculado = round(array_sum(array_map(
            fn ($monto) => max(0, (float) $monto),
            $recargosCalculados
        )), 2);

        $claves = array_keys($recargosCalculados);
        $ultima = end($claves);

        $repartido = [];
        $acumulado = 0.0;

        foreach ($recargosCalculados as $clave => $monto) {
            if ($clave === $ultima) {
                $repartido[$clave] = round(max(0, $objetivo - $acumulado), 2);

                continue;
            }

            $parte = $totalCalculado > 0
                ? $objetivo * (max(0, (float) $monto) / $totalCalculado)
                : $objetivo / count($recargosCalculados);

            $parte = round($parte, 2);

            $repartido[$clave] = $parte;
            $acumulado = round($acumulado + $parte, 2);
        }

        return $repartido;
    }
}
