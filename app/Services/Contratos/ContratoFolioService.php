<?php

namespace App\Services\Contratos;

use App\Models\Contrato;
use RuntimeException;

/**
 * Folio de los contratos: CT-AAAAMMDDHHMMSS-XXXXXX.
 *
 * Vive aquí para que el alta normal y el alta de contratos históricos usen el
 * mismo esquema y no se puedan separar con el tiempo.
 */
class ContratoFolioService
{
    public function generar(string $prefijo = 'CT', int $maxIntentos = 10): string
    {
        for ($intento = 0; $intento < $maxIntentos; $intento++) {
            $folio = $prefijo
                .'-'.now()->format('YmdHis')
                .'-'.strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

            $existe = Contrato::withTrashed()
                ->where('folio_contrato', $folio)
                ->exists();

            if (! $existe) {
                return $folio;
            }
        }

        throw new RuntimeException('No fue posible generar un folio único para el contrato.');
    }
}
