<?php

namespace App\Services\Reportes;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Cuántos recibos se esperan en los próximos días.
 *
 * 1. De las cuotas que vencieron hace 1 a 4 meses se ve qué parte se cobró el
 *    mismo día del vencimiento, cuál un día antes, cuál tres días después...
 * 2. Eso se aplica a las cuotas que vencen alrededor de cada día que viene.
 * 3. Se ajusta por día de la semana con lo que de verdad se hizo las últimas
 *    8 semanas: absorbe los recibos que no son de cuotas y los días en que no
 *    se abre.
 *
 * Cuenta igual que el reporte de carga: por hora de captura, sin históricos.
 * Con un canal, solo pronostica los recibos de oficina o los de WhatsApp.
 */
class PronosticoCargaRecibos
{
    /** Días antes y después del vencimiento en que se considera que se cobra una cuota. */
    private const ANTES = 14;

    private const DESPUES = 30;

    private const SEMANAS_AJUSTE = 8;

    private const DIAS_REFERENCIA = 90;

    /**
     * Recibos que las cuotas deben explicar de un día de la semana, en las
     * semanas de ajuste, para confiar en el ajuste. Con menos, dividir entre
     * casi nada lo dispara (2 recibos un lunes normal salían como 20).
     */
    private const MINIMO_EXPLICADO = 5;

    public function pronosticar(Carbon $hoy, int $dias = 7, ?string $canal = null): array
    {
        $hoy = $hoy->copy()->startOfDay();

        $tasas = $this->tasasPorDiaDeDiferencia($hoy, $canal);

        if ($tasas === null) {
            return ['disponible' => false, 'dias' => []];
        }

        $inicioAjuste = $hoy->copy()->subWeeks(self::SEMANAS_AJUSTE);
        $finPronostico = $hoy->copy()->addDays($dias - 1);

        $vencimientos = $this->vencimientosPorDia(
            $inicioAjuste->copy()->subDays(self::DESPUES),
            $finPronostico->copy()->addDays(self::ANTES)
        );

        $modelo = fn (Carbon $dia) => $this->esperadoPorCuotas($dia, $tasas, $vencimientos);

        $reales = $this->recibosPorDia($inicioAjuste, $hoy, $canal);

        // Ajuste por día de la semana con las últimas semanas.
        $sumas = [];
        foreach (CarbonPeriod::create($inicioAjuste, $hoy->copy()->subDay()) as $dia) {
            $w = $dia->dayOfWeekIso;
            $sumas[$w]['real'] = ($sumas[$w]['real'] ?? 0) + $reales->get($dia->toDateString(), 0);
            $sumas[$w]['modelo'] = ($sumas[$w]['modelo'] ?? 0) + $modelo($dia);
            $sumas[$w]['dias'] = ($sumas[$w]['dias'] ?? 0) + 1;
        }

        $estimar = function (Carbon $dia) use ($sumas, $modelo): float {
            $s = $sumas[$dia->dayOfWeekIso] ?? ['real' => 0, 'modelo' => 0, 'dias' => 1];

            // Si las cuotas casi no explican ese día (un domingo), queda su promedio.
            return $s['modelo'] >= self::MINIMO_EXPLICADO
                ? $modelo($dia) * $s['real'] / $s['modelo']
                : $s['real'] / max(1, $s['dias']);
        };

        $errores = [];
        foreach (CarbonPeriod::create($inicioAjuste, $hoy->copy()->subDay()) as $dia) {
            $errores[] = abs($estimar($dia) - $reales->get($dia->toDateString(), 0));
        }

        $pronostico = [];
        foreach (CarbonPeriod::create($hoy, $finPronostico) as $dia) {
            $s = $sumas[$dia->dayOfWeekIso] ?? ['real' => 0, 'dias' => 1];

            $pronostico[] = [
                'fecha' => $dia->toDateString(),
                'esperados' => $estimar($dia),
                'normal' => $s['real'] / max(1, $s['dias']),
                'vencen' => $vencimientos->get($dia->toDateString(), 0),
                'llevan' => $dia->isSameDay($hoy) ? $reales->get($dia->toDateString(), 0) : null,
            ];
        }

        return [
            'disponible' => true,
            'dias' => $pronostico,
            'error_promedio' => $errores ? array_sum($errores) / count($errores) : 0,
            'cobro_mismo_dia' => ($tasas[0] ?? 0) / max(array_sum($tasas), 0.000001) * 100,
        ];
    }

    /**
     * Por cada cuota que vence, cuántos recibos se capturan N días antes o
     * después (N negativo = antes). Null si todavía no hay historia.
     *
     * @return array<int, float>|null
     */
    private function tasasPorDiaDeDiferencia(Carbon $hoy, ?string $canal): ?array
    {
        // Cuotas cuyo periodo de cobro ya terminó de verse completo.
        $hasta = $hoy->copy()->subDays(self::DESPUES + 1);
        $desde = $hasta->copy()->subDays(self::DIAS_REFERENCIA - 1);

        $cuotas = $this->cuotas()
            ->whereBetween('cuotas.fecha_vencimiento', [$desde->toDateString(), $hasta->toDateString()])
            ->count();

        if ($cuotas === 0) {
            return null;
        }

        $tasas = [];
        $this->cuotas()
            ->join('recibos', 'recibos.cuota_id', '=', 'cuotas.id')
            ->whereBetween('cuotas.fecha_vencimiento', [$desde->toDateString(), $hasta->toDateString()])
            ->whereNull('recibos.deleted_at')
            ->where('recibos.es_historico', false)
            ->tap(fn ($q) => CanalRecibo::filtrar($q, $canal))
            ->get(['recibos.created_at', 'cuotas.fecha_vencimiento'])
            ->each(function ($r) use (&$tasas) {
                $diferencia = (int) Carbon::parse($r->fecha_vencimiento)->diffInDays(Carbon::parse($r->created_at)->startOfDay(), false);

                if ($diferencia >= -self::ANTES && $diferencia <= self::DESPUES) {
                    $tasas[$diferencia] = ($tasas[$diferencia] ?? 0) + 1;
                }
            });

        if ($tasas === []) {
            return null;
        }

        return array_map(fn ($n) => $n / $cuotas, $tasas);
    }

    private function esperadoPorCuotas(Carbon $dia, array $tasas, Collection $vencimientos): float
    {
        $total = 0.0;

        foreach ($tasas as $diferencia => $tasa) {
            $total += $tasa * $vencimientos->get($dia->copy()->subDays($diferencia)->toDateString(), 0);
        }

        return $total;
    }

    /** Las cuotas que se esperan cobrar: de contratos vigentes o ya liquidados, sin anuladas. */
    private function cuotas()
    {
        return DB::table('cuotas')
            ->join('contratos', 'contratos.id', '=', 'cuotas.contrato_id')
            ->whereNull('contratos.deleted_at')
            ->whereIn('contratos.estatus', ['activo', 'liquidado'])
            ->where('cuotas.estatus', '!=', 'anulada');
    }

    private function vencimientosPorDia(Carbon $desde, Carbon $hasta): Collection
    {
        return $this->cuotas()
            ->whereBetween('cuotas.fecha_vencimiento', [$desde->toDateString(), $hasta->toDateString()])
            ->groupBy('cuotas.fecha_vencimiento')
            ->selectRaw('cuotas.fecha_vencimiento as fecha, COUNT(*) as total')
            ->pluck('total', 'fecha')
            ->mapWithKeys(fn ($total, $fecha) => [substr((string) $fecha, 0, 10) => (int) $total]);
    }

    private function recibosPorDia(Carbon $desde, Carbon $hasta, ?string $canal): Collection
    {
        return DB::table('recibos')
            ->whereNull('deleted_at')
            ->where('es_historico', false)
            ->whereBetween('created_at', [$desde->copy()->startOfDay(), $hasta->copy()->endOfDay()])
            ->tap(fn ($q) => CanalRecibo::filtrar($q, $canal))
            ->pluck('created_at')
            ->countBy(fn ($momento) => Carbon::parse($momento)->toDateString());
    }
}
