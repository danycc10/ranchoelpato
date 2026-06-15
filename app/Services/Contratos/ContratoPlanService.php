<?php

namespace App\Services\Contratos;

use Carbon\Carbon;
use App\Models\Promocion;

class ContratoPlanService
{
    public static function diasSemana(): array
    {
        return [
            1 => 'Lunes',
            2 => 'Martes',
            3 => 'Miércoles',
            4 => 'Jueves',
            5 => 'Viernes',
            6 => 'Sábado',
            7 => 'Domingo',
        ];
    }

    public static function aplicarPromocionEconomica(array &$data, ?Promocion $promo): void
    {
        if (! $promo) return;

        if ($promo->tipo === 'descuento_saldo' && $promo->porcentaje) {
            $data['saldo_inicial'] = round($data['saldo_inicial'] * (1 - ($promo->porcentaje / 100)), 2);
            $data['saldo_actual']  = $data['saldo_inicial'];
        }

        if ($promo->tipo === 'descuento_monto_pago' && $promo->monto_fijo) {
            $data['monto_pago'] = max(0.01, round($data['monto_pago'] - (float) $promo->monto_fijo, 2));
        }
    }

    /**
     * ✅ Anualidad ahora REDUCE saldo.
     * La suma de (semanas + anualidades) = saldo_inicial.
     */
    public static function generarCuotas(array $data, ?Promocion $promo): array
    {
        $saldo      = (float) $data['saldo_inicial'];
        $montoPago  = (float) $data['monto_pago'];
        $frecuencia = (string) $data['frecuencia'];
        $fechaInicio = Carbon::parse($data['fecha_inicio'])->startOfDay();

        if ($saldo <= 0) return [];
        if ($montoPago <= 0) throw new \RuntimeException('El monto de pago debe ser mayor a 0.');

        $primera = self::primeraFechaVencimiento(
            $fechaInicio,
            $frecuencia,
            $data['dia_semana'] ?? null,
            $data['dia_mes'] ?? null
        );

        // Promo: diferir primer pago
        if ($promo && $promo->tipo === 'diferir_primer_pago' && $promo->dias_diferidos) {
            $primera = $primera->copy()->addDays((int) $promo->dias_diferidos);
        }

        // Promo: cuotas fijas (⚠️ recomiendo NO mezclar con anualidad que reduce saldo)
        if ($promo && $promo->tipo === 'cuotas_fijas' && $promo->numero_cuotas) {
            $n = max(1, (int) $promo->numero_cuotas);

            // ✅ Genera cuotas fijas normal (sin anualidad para no sobrepagar)
            return self::planCuotasFijas($saldo, $n, $primera, $frecuencia);
        }

        // ✅ Plan dinámico: intercalar pagos regulares + anualidad y ambos reducen saldo
        return self::planConAnualidadReduciendoSaldo($data, $primera, $saldo, $montoPago);
    }

    protected static function planConAnualidadReduciendoSaldo(array $data, Carbon $primeraRegular, float $saldo, float $montoPago): array
    {
        $frecuencia = (string) $data['frecuencia'];

        $tieneAnualidad = (bool)($data['tiene_anualidad'] ?? false);
        $anualMonto     = (float)($data['anualidad_monto'] ?? 0);
        $anualFechaRaw  = $data['anualidad_fecha'] ?? null;

        $tieneAnualidad = $tieneAnualidad && $anualMonto > 0 && !empty($anualFechaRaw);

        $inicio = Carbon::parse($data['fecha_inicio'])->startOfDay();

        // Próxima fecha anual (primer vencimiento ajustado a >= inicio)
        $nextAnual = null;
        if ($tieneAnualidad) {
            $nextAnual = Carbon::parse($anualFechaRaw)->startOfDay();
            while ($nextAnual->lt($inicio)) {
                $nextAnual->addYear();
            }
        }

        $nextRegular = $primeraRegular->copy();
        $plan = [];
        $restante = round($saldo, 2);
        $i = 1;

        // Guard para evitar loops
        $guard = 0;

        while ($restante > 0) {
            $guard++;
            if ($guard > 20000) {
                throw new \RuntimeException('Loop guard: plan demasiado largo. Revisa datos.');
            }

            // Decide qué evento ocurre primero (regular vs anual)
            $useAnual = false;

            if ($tieneAnualidad && $nextAnual) {
                if ($nextAnual->lt($nextRegular)) {
                    $useAnual = true;
                } elseif ($nextAnual->eq($nextRegular)) {
                    // mismo día: primero anualidad y luego regular (quedan dos cuotas mismo día)
                    $useAnual = true;
                }
            }

            if ($useAnual) {
                $monto = min($restante, round($anualMonto, 2));

                $plan[] = [
                    'numero' => $i++,
                    'fecha_vencimiento' => $nextAnual->toDateString(),
                    'monto' => $monto,
                    'es_anualidad' => true,
                    'concepto' => 'ANUALIDAD',
                ];

                $restante = round($restante - $monto, 2);

                // siguiente anualidad
                $nextAnual = $nextAnual->copy()->addYear();

                // Si fue el mismo día que la regular, el loop siguiente generará la regular (porque nextRegular sigue igual)
                continue;
            }

            // cuota regular
            $monto = min($restante, round($montoPago, 2));

            $plan[] = [
                'numero' => $i++,
                'fecha_vencimiento' => $nextRegular->toDateString(),
                'monto' => $monto,
                'es_anualidad' => false,
                'concepto' => null,
            ];

            $restante = round($restante - $monto, 2);

            // siguiente regular
            $nextRegular = $nextRegular->copy();
            $frecuencia === 'semanal'
                ? $nextRegular->addWeek()
                : $nextRegular->addMonthNoOverflow();
        }

        return $plan;
    }

    protected static function planCuotasFijas(float $saldo, int $n, Carbon $primera, string $frecuencia): array
    {
        $plan = [];
        $base = round($saldo / $n, 2);
        $acum = 0;

        for ($i = 1; $i <= $n; $i++) {
            $monto = ($i < $n) ? $base : round($saldo - $acum, 2);
            $acum = round($acum + $monto, 2);

            $fecha = $primera->copy();
            if ($i > 1) {
                $frecuencia === 'semanal'
                    ? $fecha->addWeeks($i - 1)
                    : $fecha->addMonthsNoOverflow($i - 1);
            }

            $plan[] = [
                'numero' => $i,
                'fecha_vencimiento' => $fecha->toDateString(),
                'monto' => $monto,
                'es_anualidad' => false,
                'concepto' => null,
            ];
        }

        return $plan;
    }

    /**
     * ✅ CORRECCIÓN:
     * - Semanal: "siguiente" día de pago (si ya cae en el mismo día, brinca 7 días).
     * - Mensual: si inicio es el mismo día o posterior al día de pago, brinca al siguiente mes.
     */
    protected static function primeraFechaVencimiento(Carbon $inicio, string $frecuencia, ?int $diaSemana, ?int $diaMes): Carbon
    {
        $inicio = $inicio->copy()->startOfDay();

        if ($frecuencia === 'semanal') {
            $dow = (int) ($diaSemana ?: $inicio->isoWeekday()); // 1..7 ISO

            // Diferencia ISO (puede ser negativa)
            $diff = $dow - $inicio->isoWeekday();

            // 👇 clave: si hoy es el día (diff=0) o ya pasó (diff<0), brincar a la próxima semana
            if ($diff <= 0) $diff += 7;

            return $inicio->addDays($diff)->startOfDay();
        }

        // mensual
        $day = (int) ($diaMes ?: $inicio->day);

        $d = $inicio->copy();

        // 👇 clave: si es el mismo día o ya pasó el día de pago, brincar al siguiente mes
        if ($d->day >= $day) {
            $d->addMonthNoOverflow();
        }

        $max = $d->daysInMonth;
        $d->day = min($day, $max);

        return $d->startOfDay();
    }
}