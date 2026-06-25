<?php

namespace App\Services\Recibos;

use App\Models\Recibo;
use Illuminate\Validation\ValidationException;

class ReciboFolioService
{
    public function generar(?int $anio = null): string
    {
        $anio = $anio ?: now()->year;

        $ultimo = Recibo::withTrashed()
            ->where('anio', $anio)
            ->where('folio', 'regexp', '^R-'.$anio.'-[0-9]{6}$')
            ->orderByDesc('folio')
            ->value('folio');

        $numero = $ultimo ? ((int) substr($ultimo, -6)) + 1 : 1;

        return 'R-'.$anio.'-'.str_pad($numero, 6, '0', STR_PAD_LEFT);
    }

    public function crearRecibo(array $data, int $maxIntentos = 10): Recibo
    {
        $anio = (int) $data['anio'];

        for ($intento = 0; $intento < $maxIntentos; $intento++) {
            $data['folio'] = $this->generar($anio);

            try {
                return Recibo::create($data);
            } catch (\Throwable $e) {
                if (! $this->isDuplicateKeyException($e)) {
                    throw $e;
                }

                usleep(50000);
            }
        }

        throw ValidationException::withMessages([
            'folio' => "No fue posible asignar un folio \u{00FA}nico. Intenta nuevamente.",
        ]);
    }

    protected function isDuplicateKeyException(\Throwable $e): bool
    {
        $message = mb_strtolower($e->getMessage());

        return str_contains($message, 'duplicate')
            || str_contains($message, 'duplicada')
            || str_contains($message, 'unique')
            || str_contains($message, '1062');
    }
}
