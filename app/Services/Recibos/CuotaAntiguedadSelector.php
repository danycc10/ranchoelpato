<?php

namespace App\Services\Recibos;

class CuotaAntiguedadSelector
{
    /**
     * @param  array<int, int|string>  $idsOrdenados
     * @param  array<int, int|string>  $idsSeleccionados
     */
    public function seleccionPermitida(
        array $idsOrdenados,
        array $idsSeleccionados,
        bool $excepcionAutorizada
    ): bool {
        return $excepcionAutorizada
            || $this->seleccionEsPrefijo($idsOrdenados, $idsSeleccionados);
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
