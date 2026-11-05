<?php

namespace App\Services\Clientes;

use App\Models\Cliente;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Los cumpleaños de los clientes, sacados de su CURP.
 *
 * El CURP trae la fecha de nacimiento en las posiciones 5 a 10 (AAMMDD). El
 * carácter 17 dice el siglo: un dígito es de antes del 2000 y una letra del
 * 2000 en adelante. Quien no tiene CURP, o lo tiene mal capturado, no sale.
 */
class CumpleanosClientes
{
    private const FORMATO_CURP = '/^[A-Z]{4}\d{6}[HMX][A-Z]{5}[A-Z0-9]\d$/';

    public static function fechaNacimiento(?string $curp): ?Carbon
    {
        $curp = mb_strtoupper(preg_replace('/\s+/', '', (string) $curp));

        if (! preg_match(self::FORMATO_CURP, $curp)) {
            return null;
        }

        $anio = (int) substr($curp, 4, 2) + (ctype_digit($curp[16]) ? 1900 : 2000);
        $mes = (int) substr($curp, 6, 2);
        $dia = (int) substr($curp, 8, 2);

        return checkdate($mes, $dia, $anio) ? Carbon::create($anio, $mes, $dia)->startOfDay() : null;
    }

    /**
     * Los clientes que cumplen años ese día, con su edad.
     *
     * Los del 29 de febrero, en años que no son bisiestos, festejan el 28.
     *
     * @return Collection<int, array{cliente: Cliente, nacimiento: Carbon, edad: int}>
     */
    public function cumplenEl(Carbon $dia): Collection
    {
        $dia = $dia->copy()->startOfDay();
        $fechas = [$dia->format('md')];

        if ($dia->format('md') === '0228' && ! $dia->isLeapYear()) {
            $fechas[] = '0229';
        }

        return Cliente::query()
            ->where('estatus', 'activo')
            ->whereNotNull('curp')
            ->whereRaw('SUBSTR(UPPER(TRIM(curp)), 7, 4) IN ('.implode(',', array_fill(0, count($fechas), '?')).')', $fechas)
            ->orderBy('nombres')
            ->get()
            ->map(function (Cliente $cliente) use ($dia) {
                $nacimiento = self::fechaNacimiento($cliente->curp);

                return $nacimiento ? [
                    'cliente' => $cliente,
                    'nacimiento' => $nacimiento,
                    'edad' => $dia->year - $nacimiento->year,
                ] : null;
            })
            ->filter()
            ->values();
    }

    /** El nombre como se le dice: "Mario Sergio", no "MARIO SERGIO PÉREZ". */
    public static function nombreDePila(Cliente $cliente): string
    {
        $nombre = trim((string) ($cliente->nombres ?: $cliente->nombre_completo));

        return mb_convert_case(mb_strtolower($nombre), MB_CASE_TITLE);
    }

    public static function mensajeWhatsapp(Cliente $cliente): string
    {
        return '¡Feliz cumpleaños, '.self::nombreDePila($cliente).'! 🎂🎉'
            ."\n\n"
            .'De parte de todo el equipo de '.config('app.name').' te deseamos un día lleno de alegría junto a tus seres queridos y un año lleno de salud y logros.'
            ."\n\n"
            .'¡Gracias por tu confianza!';
    }

    /** Abre WhatsApp Web con el mensaje ya escrito; null si no tiene a dónde. */
    public static function enlaceWhatsapp(Cliente $cliente): ?string
    {
        $telefono = self::telefonoInternacional($cliente->telefonoParaWhatsapp());

        if (! $telefono) {
            return null;
        }

        return 'https://web.whatsapp.com/send?phone='.$telefono.'&text='.rawurlencode(self::mensajeWhatsapp($cliente));
    }

    private static function telefonoInternacional(?string $telefono): ?string
    {
        $digitos = preg_replace('/\D+/', '', (string) $telefono);

        return match (true) {
            strlen($digitos) === 10 => '52'.$digitos,
            strlen($digitos) === 11 && str_starts_with($digitos, '1') => $digitos,
            strlen($digitos) === 12 && str_starts_with($digitos, '52') => $digitos,
            strlen($digitos) === 13 && str_starts_with($digitos, '521') => '52'.substr($digitos, 3),
            default => null,
        };
    }
}
