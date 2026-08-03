<?php

namespace App\Services\Recibos;

class CuotaAntiguedadSelector
{
    /**
     * Devuelve todas las cuotas desde la más antigua hasta la última seleccionada.
     *
     * @param  array<int, int|string>  $idsOrdenados
     * @param  array<int, int|string>  $idsSeleccionados
     * @return array<int, int>
     */
    public function prefijoHastaLaUltimaSeleccionada(array $idsOrdenados, array $idsSeleccionados): array
    {
        $ordenados = $this->normalizarIds($idsOrdenados);
        $seleccionados = $this->normalizarIds($idsSeleccionados);

        if ($ordenados === [] || $seleccionados === []) {
            return [];
        }

        $posiciones = array_flip($ordenados);
        $ultimaPosicion = null;

        foreach ($seleccionados as $id) {
            if (! array_key_exists($id, $posiciones)) {
                continue;
            }

            $ultimaPosicion = max($ultimaPosicion ?? 0, (int) $posiciones[$id]);
        }

        if ($ultimaPosicion === null) {
            return [];
        }

        return array_slice($ordenados, 0, $ultimaPosicion + 1);
    }

    /**
     * @param  array<int, int|string>  $idsOrdenados
     * @param  array<int, int|string>  $idsSeleccionados
     */
    public function seleccionEsPrefijo(array $idsOrdenados, array $idsSeleccionados): bool
    {
        $ordenados = $this->normalizarIds($idsOrdenados);
        $seleccionados = $this->normalizarIds($idsSeleccionados);

        if ($seleccionados === []) {
            return false;
        }

        $esperados = array_slice($ordenados, 0, count($seleccionados));

        sort($esperados);
        sort($seleccionados);

        return $esperados === $seleccionados;
    }

    /**
     * @param  array<int, int|string>  $ids
     * @return array<int, int>
     */
    private function normalizarIds(array $ids): array
    {
        return collect($ids)
            ->filter(fn ($id) => is_numeric($id) && (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
