<?php

namespace App\Services\Recibos;

use App\Models\FormaPago;
use Illuminate\Validation\ValidationException;

class ReciboPagoNormalizer
{
    public function normalizar(
        array $pagos,
        ?float $montoEsperado = null,
        ?callable $formaPagoRequiereCuenta = null,
    ): array {
        $formaPagoRequiereCuenta ??= fn (?int $formaPagoId): bool => $this->formaPagoRequiereCuenta($formaPagoId);

        $normalizados = collect($pagos)
            ->map(function ($pago, $index) use ($formaPagoRequiereCuenta) {
                $formaPagoId = isset($pago['forma_pago_id']) && $pago['forma_pago_id'] !== ''
                    ? (int) $pago['forma_pago_id']
                    : null;

                $requiereCuenta = $formaPagoRequiereCuenta($formaPagoId);
                $monto = round((float) ($pago['monto'] ?? 0), 2);

                return [
                    'forma_pago_id' => $formaPagoId,
                    'cuentas_bancarias_id' => $requiereCuenta
                        ? (isset($pago['cuentas_bancarias_id']) && $pago['cuentas_bancarias_id'] !== '' ? (int) $pago['cuentas_bancarias_id'] : null)
                        : null,
                    'monto' => $monto,
                    'referencia' => isset($pago['referencia']) && trim((string) $pago['referencia']) !== ''
                        ? trim((string) $pago['referencia'])
                        : null,
                    'evidencia' => $pago['evidencia'] ?? null,
                    'sin_evidencia' => (bool) ($pago['sin_evidencia'] ?? false),
                    'orden' => $index + 1,
                ];
            })
            ->filter(fn ($pago) => $pago['forma_pago_id'] && $pago['monto'] > 0)
            ->values()
            ->all();

        if (empty($normalizados)) {
            throw ValidationException::withMessages([
                'pagos' => 'Debes capturar al menos una forma de pago.',
            ]);
        }

        foreach ($normalizados as $index => $pago) {
            $requiereCuenta = $formaPagoRequiereCuenta($pago['forma_pago_id']);

            if ($requiereCuenta && empty($pago['cuentas_bancarias_id'])) {
                throw ValidationException::withMessages([
                    'pagos.'.$index.'.cuentas_bancarias_id' => 'La cuenta bancaria es obligatoria para esta forma de pago.',
                ]);
            }

            if ($requiereCuenta && empty($pago['evidencia']) && empty($pago['sin_evidencia'])) {
                throw ValidationException::withMessages([
                    'pagos.'.$index.'.evidencia' => 'Debes adjuntar la evidencia para esta forma de pago.',
                ]);
            }
        }

        $suma = round((float) collect($normalizados)->sum('monto'), 2);

        if ($montoEsperado !== null && round((float) $montoEsperado, 2) !== $suma) {
            throw ValidationException::withMessages([
                'pagos' => 'La suma de las formas de pago debe coincidir con el monto total del recibo.',
            ]);
        }

        return $normalizados;
    }

    public function metodoDesdeFormaPago(?int $formaPagoId): string
    {
        if (! $formaPagoId) {
            return 'efectivo';
        }

        $forma = FormaPago::find($formaPagoId);
        $nombre = mb_strtoupper(trim((string) ($forma?->nombre ?? '')));

        return match (true) {
            str_contains($nombre, 'TRANSFER') => 'transferencia',
            str_contains($nombre, 'OXXO') => 'oxxo',
            str_contains($nombre, 'STRIPE') || str_contains($nombre, 'TARJETA') || str_contains($nombre, 'TERMINAL') => 'stripe',
            default => 'efectivo',
        };
    }

    protected function formaPagoRequiereCuenta(?int $formaPagoId): bool
    {
        if (! $formaPagoId) {
            return false;
        }

        $formaPago = FormaPago::find($formaPagoId);

        return (bool) ($formaPago?->requiere_cuenta);
    }
}
