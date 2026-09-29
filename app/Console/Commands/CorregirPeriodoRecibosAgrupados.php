<?php

namespace App\Console\Commands;

use App\Services\Recibos\PeriodoResolver;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Deja a cada recibo ya guardado con el periodo que de verdad le toca.
 *
 * Los recibos que agrupan varias cuotas se guardaban con el periodo de la
 * primera: uno que cubría julio, agosto y septiembre decía "JULIO 2026" y el
 * reporte le cargaba a julio los tres meses. Un recibo así no es de ningún
 * mes, así que va sin periodo y el reparto sale del desglose por cuota.
 *
 * De paso corrige al revés: el recibo de un solo mes que quedó con el periodo
 * de otro (por ejemplo el del día en que se capturó).
 *
 * Los históricos importados sin periodo se quedan como están: no tienen un
 * dato equivocado, les falta. Rellenarlos es harina de otro costal y va
 * detrás de --rellenar-faltantes.
 */
class CorregirPeriodoRecibosAgrupados extends Command
{
    protected $signature = 'app:corregir-periodo-recibos
        {--dry-run : Muestra lo que cambiaría sin tocar la base}
        {--rellenar-faltantes : Incluye los recibos que se quedaron sin periodo}
        {--limit=30 : Cuántos recibos listar en el detalle}';

    protected $description = 'Corrige el periodo de los recibos que cubren varios meses o traen el mes equivocado.';

    public function handle(PeriodoResolver $periodos): int
    {
        $pendientes = $this->recibosPorCorregir($periodos);

        $sinPeriodo = $pendientes->filter(fn (array $r) => $r['periodo_actual'] === null);

        if (! $this->option('rellenar-faltantes')) {
            $pendientes = $pendientes->reject(fn (array $r) => $r['periodo_actual'] === null)->values();
        }

        if ($pendientes->isEmpty()) {
            $this->info('Ningún recibo trae un periodo equivocado.');
            $this->avisarDeLosFaltantes($sinPeriodo);

            return self::SUCCESS;
        }

        $this->mostrar($pendientes);
        $this->avisarDeLosFaltantes($sinPeriodo);

        if ($this->option('dry-run')) {
            $this->warn('Dry run: no se actualizó ningún recibo.');

            return self::SUCCESS;
        }

        $this->aplicar($pendientes);

        $this->info($pendientes->count().' recibo(s) actualizados.');

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, array{id: int, folio: ?string, meses: Collection<int, string>, periodo_actual: ?int, periodo_nuevo: ?int}>
     */
    private function recibosPorCorregir(PeriodoResolver $periodos): Collection
    {
        return $this->mesesPorRecibo()
            ->map(function (array $recibo) use ($periodos) {
                $meses = $recibo['meses'];

                // Varios meses: el recibo no es de ninguno. Uno solo: el suyo.
                $recibo['periodo_nuevo'] = $meses->count() === 1
                    ? $periodos->idParaFecha($meses->first().'-01')
                    : null;

                return $recibo;
            })
            ->filter(fn (array $recibo) => $recibo['periodo_nuevo'] !== $recibo['periodo_actual'])
            ->values();
    }

    /**
     * Los meses que cubre cada recibo, tomados de su desglose por cuota. Los
     * recibos sin cuota detrás (servicios sueltos, contado) no entran: su
     * periodo no sale de ninguna cuota.
     *
     * @return Collection<int, array{id: int, folio: ?string, meses: Collection<int, string>, periodo_actual: ?int}>
     */
    private function mesesPorRecibo(): Collection
    {
        return DB::table('recibos')
            ->leftJoin('pagos', function ($join) {
                $join->on('pagos.recibo_id', '=', 'recibos.id')
                    ->whereNull('pagos.deleted_at')
                    ->whereNull('pagos.anulado_at');
            })
            // El desglose vive en `pagos`; los recibos viejos solo traen la
            // cuota ancla en `recibos.cuota_id`.
            ->join('cuotas', function ($join) {
                $join->on('cuotas.id', '=', DB::raw('COALESCE(pagos.cuota_id, recibos.cuota_id)'));
            })
            ->whereNull('recibos.deleted_at')
            ->whereNull('recibos.anulado_at')
            ->whereNotNull('cuotas.fecha_vencimiento')
            ->orderBy('recibos.id')
            ->get([
                'recibos.id',
                'recibos.folio',
                'recibos.periodo_id',
                'cuotas.fecha_vencimiento',
            ])
            ->groupBy('id')
            ->map(fn (Collection $filas) => [
                'id' => (int) $filas->first()->id,
                'folio' => $filas->first()->folio,
                'periodo_actual' => $filas->first()->periodo_id === null
                    ? null
                    : (int) $filas->first()->periodo_id,
                'meses' => $filas
                    ->map(fn ($fila) => Carbon::parse($fila->fecha_vencimiento)->format('Y-m'))
                    ->unique()
                    ->sort()
                    ->values(),
            ])
            ->values();
    }

    /**
     * @param  Collection<int, array{id: int, periodo_nuevo: ?int}>  $pendientes
     */
    private function aplicar(Collection $pendientes): void
    {
        DB::transaction(function () use ($pendientes) {
            $pendientes
                ->groupBy(fn (array $recibo) => $recibo['periodo_nuevo'] ?? 0)
                ->each(function (Collection $grupo, $periodoNuevo) {
                    // De a mil para no armar un IN gigante con los históricos.
                    $grupo->pluck('id')->chunk(1000)->each(
                        fn (Collection $ids) => DB::table('recibos')
                            ->whereIn('id', $ids)
                            ->update([
                                'periodo_id' => $periodoNuevo ?: null,
                                'updated_at' => now(),
                            ])
                    );
                });
        });
    }

    /**
     * @param  Collection<int, array{folio: ?string, meses: Collection<int, string>, periodo_actual: ?int, periodo_nuevo: ?int}>  $pendientes
     */
    private function mostrar(Collection $pendientes): void
    {
        $nombres = DB::table('periodos')->pluck('nombre', 'id');

        $limite = max(1, (int) $this->option('limit'));

        $this->table(
            ['Folio', 'Meses que cubre', 'Periodo guardado', 'Periodo correcto'],
            $pendientes
                ->take($limite)
                ->map(fn (array $recibo) => [
                    $recibo['folio'] ?? '—',
                    $recibo['meses']->implode(', '),
                    $nombres[$recibo['periodo_actual']] ?? 'SIN PERIODO',
                    $nombres[$recibo['periodo_nuevo']] ?? 'SIN PERIODO (cubre varios meses)',
                ])
                ->all()
        );

        $this->info($pendientes->count().' recibo(s) por corregir.');

        if ($pendientes->count() > $limite) {
            $this->line('Se listaron los primeros '.$limite.'. Usa --limit para ver más.');
        }
    }

    /**
     * @param  Collection<int, mixed>  $sinPeriodo
     */
    private function avisarDeLosFaltantes(Collection $sinPeriodo): void
    {
        if ($sinPeriodo->isEmpty() || $this->option('rellenar-faltantes')) {
            return;
        }

        $this->line('');
        $this->comment(
            'Aparte hay '.$sinPeriodo->count().' recibo(s) que se quedaron sin periodo '
            .'(casi siempre históricos importados). No traen un dato equivocado, les falta: '
            .'córrelo con --rellenar-faltantes si quieres ponérselo.'
        );
    }
}
