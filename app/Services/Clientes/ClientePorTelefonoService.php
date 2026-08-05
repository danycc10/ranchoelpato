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

    /** Solo devuelve cliente cuando la coincidencia es única. */
    public function buscarUnico(?string $telefono): ?Cliente
    {
        $normalizado = $this->normalizar($telefono);

        if ($normalizado === null) {
            return null;
        }

        $variantes = [$normalizado];

        if (strlen($normalizado) === 10) {
            $variantes[] = '52'.$normalizado;
            $variantes[] = '521'.$normalizado;
        }

        $clientes = Cliente::query()
            ->whereIn('telefono', array_unique($variantes))
            ->limit(2)
            ->get();

        return $clientes->count() === 1 ? $clientes->first() : null;
    }
}
