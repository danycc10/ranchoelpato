<?php

namespace App\Services\Clientes;

use App\Models\Cliente;

class ClientePorTelefonoService
{
    /** Convierte números de México y EE. UU. al mismo formato usado en clientes. */
    public function normalizar(?string $telefono): ?string
    {
        $digitos = preg_replace('/\D+/', '', (string) $telefono) ?: '';

        if ($digitos === '') {
            return null;
        }

        if (strlen($digitos) === 13 && str_starts_with($digitos, '521')) {
            return substr($digitos, 3);
        }

        if (strlen($digitos) === 12 && str_starts_with($digitos, '52')) {
            return substr($digitos, 2);
        }

        return $digitos;
    }

    /**
     * Solo devuelve cliente cuando la coincidencia es única.
     *
     * Busca en el teléfono principal y en los adicionales: un cliente que
     * escribe desde su segundo número es el mismo cliente, y antes su
     * comprobante llegaba sin dueño.
     */
    public function buscarUnico(?string $telefono): ?Cliente
    {
        $variantes = $this->variantes($telefono);

        if ($variantes === []) {
            return null;
        }

        $clientes = Cliente::query()
            ->where(fn ($q) => $q
                ->whereIn('telefono', $variantes)
                ->orWhereHas('telefonosAdicionales', fn ($t) => $t->whereIn('telefono', $variantes))
            )
            ->limit(2)
            ->get();

        return $clientes->count() === 1 ? $clientes->first() : null;
    }

    /**
     * Las formas en que un mismo número puede estar guardado.
     *
     * @return array<int, string>
     */
    public function variantes(?string $telefono): array
    {
        $normalizado = $this->normalizar($telefono);

        if ($normalizado === null) {
            return [];
        }

        $variantes = [$normalizado];

        if (strlen($normalizado) === 10) {
            $variantes[] = '52'.$normalizado;
            $variantes[] = '521'.$normalizado;
        }

        return array_values(array_unique($variantes));
    }
}
