<?php

namespace App\Console\Commands;

use App\Mail\CumpleanosMail;
use App\Models\FelicitacionCumpleanos;
use App\Services\Clientes\CumpleanosClientes;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Manda el correo de cumpleaños a los clientes que cumplen hoy.
 *
 * Cada cliente recibe uno al año: si el comando vuelve a correr, a quien ya
 * se le mandó se salta.
 */
class FelicitarCumpleanos extends Command
{
    protected $signature = 'clientes:felicitar-cumpleanos
                            {--fecha= : Día a revisar (AAAA-MM-DD); por defecto hoy}';

    protected $description = 'Envía el correo de felicitación a los clientes que cumplen años';

    public function handle(CumpleanosClientes $cumpleanos): int
    {
        $dia = $this->option('fecha') ? Carbon::parse($this->option('fecha')) : now();
        $enviados = 0;

        foreach ($cumpleanos->cumplenEl($dia) as ['cliente' => $cliente]) {
            $correo = trim((string) $cliente->correo);

            if (! filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $yaSeMando = FelicitacionCumpleanos::query()
                ->where('cliente_id', $cliente->id)
                ->where('anio', $dia->year)
                ->where('canal', FelicitacionCumpleanos::CORREO)
                ->exists();

            if ($yaSeMando) {
                continue;
            }

            try {
                Mail::to($correo)->send(new CumpleanosMail($cliente));
            } catch (\Throwable $e) {
                Log::warning('No se pudo mandar el correo de cumpleaños', [
                    'cliente_id' => $cliente->id,
                    'correo' => $correo,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            FelicitacionCumpleanos::create([
                'cliente_id' => $cliente->id,
                'anio' => $dia->year,
                'canal' => FelicitacionCumpleanos::CORREO,
            ]);

            $enviados++;
        }

        $this->info("Correos de cumpleaños enviados: {$enviados}");

        return self::SUCCESS;
    }
}
