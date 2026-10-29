<?php

namespace App\Livewire\Reports;

use App\Services\Reportes\CanalRecibo;
use App\Services\Reportes\PronosticoCargaRecibos;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Cuántos recibos se generan y quién los hace: por hora, día y semana.
 *
 * Cuenta por la hora en que se capturó el recibo (created_at), no por la
 * fecha del pago, porque lo que se mide es el trabajo en el sistema. Los
 * históricos importados no cuentan: entraron miles de golpe en minutos.
 * Los anulados sí, porque también se capturaron; se muestran aparte.
 *
 * Separa la oficina (efectivo y tarjeta, el cliente vino) de WhatsApp
 * (transferencia y depósito) y cuenta clientes atendidos además de recibos:
 * un cliente que paga cuota y recargo genera dos recibos en una sola visita.
 */
class WorkloadReport extends Component
{
    public const DIAS = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'];

    /** Rampa azul de un solo tono para el mapa de calor, de poco a mucho. */
    public const RAMPA = ['#cde2fb', '#9ec5f4', '#6da7ec', '#3987e5', '#256abf', '#184f95', '#0d366b'];

    /** Siempre el mismo color para cada canal, en todas las gráficas. */
    public const COLORES = [CanalRecibo::OFICINA => '#2a78d6', CanalRecibo::WHATSAPP => '#eb6834'];

    /** Recibos del mismo cliente con el mismo usuario a menos de esto son una sola atención. */
    private const MISMA_ATENCION_MINUTOS = 10;

    /** Entre un cliente y el siguiente, más de esto ya no es fila sino tiempo sin gente. */
    private const SEGUIDOS_MINUTOS = 30;

    public string $desde;

    public string $hasta;

    public ?int $usuarioId = null;

    /** '' todos, o CanalRecibo::OFICINA / CanalRecibo::WHATSAPP. */
    public string $canal = '';

    /** El mapa de calor en promedio por día en vez del total del periodo. */
    public bool $promedio = false;

    public function mount(): void
    {
        $this->rango('30');
    }

    public function rango(string $clave): void
    {
        $hoy = now();

        [$desde, $hasta] = match ($clave) {
            'hoy' => [$hoy->copy(), $hoy->copy()],
            'semana' => [$hoy->copy()->startOfWeek(Carbon::MONDAY), $hoy->copy()],
            'mes' => [$hoy->copy()->startOfMonth(), $hoy->copy()],
            default => [$hoy->copy()->subDays(29), $hoy->copy()],
        };

        $this->desde = $desde->toDateString();
        $this->hasta = $hasta->toDateString();
    }

    public function getUsuariosProperty(): Collection
    {
        return DB::table('users')
            ->whereIn('id', DB::table('recibos')->whereNotNull('capturado_por_user_id')->select('capturado_por_user_id'))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /** De todos los usuarios y desde hoy: solo sigue al filtro de canal. */
    public function getPronosticoProperty(): array
    {
        return app(PronosticoCargaRecibos::class)->pronosticar(now(), canal: $this->canalElegido());
    }

    public function getEstadisticasProperty(): array
    {
        [$desde, $hasta] = $this->periodo();

        $recibos = $this->recibos($desde, $hasta);

        // Se compara contra los mismos días de la semana, recorridos semanas
        // completas: lunes a miércoles contra lunes a miércoles, no contra un
        // fin de semana.
        $dias = (int) round($hasta->diffInDays($desde, true)) + 1;
        $semanas = (int) ceil($dias / 7);
        $antesDesde = $desde->copy()->subWeeks($semanas);
        $antesHasta = $hasta->copy()->subWeeks($semanas);
        $anteriores = $this->recibos($antesDesde, $antesHasta)->count();

        $porHora = array_fill(0, 24, 0);
        $porHoraCanal = array_fill_keys(array_keys(self::COLORES), array_fill(0, 24, 0));
        $calor = [];
        foreach ($recibos as $r) {
            $porHora[$r->hora]++;
            $porHoraCanal[$r->canal][$r->hora]++;
            $calor[$r->dia_semana][$r->hora] = ($calor[$r->dia_semana][$r->hora] ?? 0) + 1;
        }

        $atenciones = $this->atenciones($recibos);

        // Cuántos lunes, martes... tiene el periodo, para el promedio por día.
        $veces = collect(CarbonPeriod::create($desde, $hasta))->countBy(fn (Carbon $d) => $d->dayOfWeekIso)->all();

        $diasTrabajados = $recibos->pluck('fecha')->unique()->count();
        $horaPico = $recibos->isEmpty() ? null : array_search(max($porHora), $porHora, true);

        return [
            'total' => $recibos->count(),
            'clientes' => $atenciones->count(),
            'anteriores' => $anteriores,
            'anterior_rango' => $antesDesde->format('d/m').' al '.$antesHasta->format('d/m'),
            'anulados' => $recibos->where('anulado', true)->count(),
            'monto' => $recibos->where('anulado', false)->sum('monto'),
            'dias_trabajados' => $diasTrabajados,
            'promedio_dia' => $diasTrabajados ? $recibos->count() / $diasTrabajados : 0,
            'hora_pico' => $horaPico,
            'hora_pico_total' => $horaPico === null ? 0 : $porHora[$horaPico],
            'horas' => $this->horasVisibles($porHora),
            'por_hora' => $porHora,
            'por_hora_canal' => $porHoraCanal,
            'por_canal' => $this->porCanal($recibos, $atenciones),
            'calor' => $calor,
            'calor_max' => max([1, ...array_map(fn ($fila) => max($fila), $calor ?: [[0]])]),
            'veces_dia_semana' => $veces,
            'por_dia' => $this->porDia($recibos, $desde, $hasta),
            'por_semana' => $this->porSemana($hasta),
            'por_usuario' => $this->porUsuario($recibos, $atenciones),
        ];
    }

    /** Color de una celda del mapa de calor; null cuando no hubo recibos. */
    public static function colorCalor(float $valor, float $maximo): ?string
    {
        if ($valor <= 0) {
            return null;
        }

        $paso = (int) ceil($valor / max(0.000001, $maximo) * count(self::RAMPA)) - 1;

        return self::RAMPA[max(0, min(count(self::RAMPA) - 1, $paso))];
    }

    private function canalElegido(): ?string
    {
        return array_key_exists($this->canal, self::COLORES) ? $this->canal : null;
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function periodo(): array
    {
        try {
            $desde = Carbon::parse($this->desde)->startOfDay();
            $hasta = Carbon::parse($this->hasta)->startOfDay();
        } catch (\Throwable) {
            $desde = now()->subDays(29)->startOfDay();
            $hasta = now()->startOfDay();
        }

        if ($desde->gt($hasta)) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        // Más de un año no cabe en la gráfica por día.
        if ($hasta->diffInDays($desde, true) > 365) {
            $desde = $hasta->copy()->subDays(365);
        }

        return [$desde, $hasta];
    }

    private function recibos(Carbon $desde, Carbon $hasta): Collection
    {
        return DB::table('recibos')
            ->whereNull('deleted_at')
            ->where('es_historico', false)
            ->whereBetween('created_at', [$desde->copy()->startOfDay(), $hasta->copy()->endOfDay()])
            ->when($this->usuarioId, fn ($q) => $q->where('capturado_por_user_id', $this->usuarioId))
            ->tap(fn ($q) => CanalRecibo::filtrar($q, $this->canalElegido()))
            ->select(['id', 'cliente_id', 'created_at', 'capturado_por_user_id', 'anulado_at', 'monto'])
            ->selectRaw(CanalRecibo::sqlEsOficina().' as en_oficina')
            ->get()
            ->map(function ($r) {
                $momento = Carbon::parse($r->created_at);

                return (object) [
                    'id' => $r->id,
                    'cliente_id' => $r->cliente_id,
                    'canal' => (int) $r->en_oficina === 1 ? CanalRecibo::OFICINA : CanalRecibo::WHATSAPP,
                    'momento' => $momento->getTimestamp(),
                    'fecha' => $momento->toDateString(),
                    'hora' => $momento->hour,
                    'dia_semana' => $momento->dayOfWeekIso,
                    'usuario_id' => $r->capturado_por_user_id,
                    'anulado' => $r->anulado_at !== null,
                    'monto' => (float) $r->monto,
                ];
            });
    }

    /**
     * Recibos seguidos del mismo cliente, con el mismo usuario y por el mismo
     * canal, son una sola atención (cuota y recargo, o dos lotes).
     */
    private function atenciones(Collection $recibos): Collection
    {
        return $recibos
            ->sortBy('momento')
            ->groupBy(fn ($r) => $r->usuario_id.'|'.($r->cliente_id ?? 'r'.$r->id).'|'.$r->canal)
            ->flatMap(function (Collection $delCliente) {
                $atenciones = [];

                foreach ($delCliente as $r) {
                    $ultima = array_key_last($atenciones);

                    if ($ultima !== null && $r->momento - $atenciones[$ultima]['fin'] <= self::MISMA_ATENCION_MINUTOS * 60) {
                        $atenciones[$ultima]['fin'] = $r->momento;
                        $atenciones[$ultima]['recibos']++;

                        continue;
                    }

                    $atenciones[] = [
                        'inicio' => $r->momento,
                        'fin' => $r->momento,
                        'recibos' => 1,
                        'fecha' => $r->fecha,
                        'usuario_id' => $r->usuario_id,
                        'canal' => $r->canal,
                    ];
                }

                return $atenciones;
            })
            ->values();
    }

    /**
     * Minutos típicos (mediana) entre que un usuario empieza con un cliente y
     * empieza con el siguiente, solo cuando venían seguidos. El sistema no
     * sabe cuándo llegó el cliente: con gente en fila, esto es lo que tarda
     * cada atención.
     */
    private function minutosEntreClientes(Collection $atenciones): ?float
    {
        $huecos = $atenciones
            ->groupBy(fn ($a) => $a['usuario_id'].'|'.$a['fecha'].'|'.$a['canal'])
            ->flatMap(fn (Collection $delDia) => $delDia->pluck('inicio')->sort()->values()
                ->sliding(2)
                ->map(fn (Collection $par) => ($par->last() - $par->first()) / 60))
            ->filter(fn ($minutos) => $minutos <= self::SEGUIDOS_MINUTOS);

        return $huecos->isEmpty() ? null : (float) $huecos->median();
    }

    private function porCanal(Collection $recibos, Collection $atenciones): array
    {
        return collect(array_keys(self::COLORES))
            ->mapWithKeys(function (string $canal) use ($recibos, $atenciones) {
                $suyos = $recibos->where('canal', $canal);
                $suyas = $atenciones->where('canal', $canal);

                return [$canal => [
                    'recibos' => $suyos->count(),
                    'porcentaje' => $suyos->count() / max(1, $recibos->count()) * 100,
                    'clientes' => $suyas->count(),
                    'recibos_por_cliente' => $suyos->count() / max(1, $suyas->count()),
                    'minutos_entre' => $this->minutosEntreClientes($suyas),
                    'hora_pico' => $suyos->countBy('hora')->sortDesc()->keys()->first(),
                    'monto' => $suyos->where('anulado', false)->sum('monto'),
                ]];
            })
            ->all();
    }

    /** El horario de oficina, ampliado si hubo recibos antes o después. */
    private function horasVisibles(array $porHora): array
    {
        $conRecibos = array_keys(array_filter($porHora));

        return range(min([8, ...$conRecibos]), max([18, ...$conRecibos]));
    }

    private function porDia(Collection $recibos, Carbon $desde, Carbon $hasta): array
    {
        $conteo = $recibos->countBy('fecha');
        $oficina = $recibos->where('canal', CanalRecibo::OFICINA)->countBy('fecha');

        return collect(CarbonPeriod::create($desde, $hasta))
            ->map(fn (Carbon $dia) => [
                'fecha' => $dia->toDateString(),
                'etiqueta' => $dia->format('d/m'),
                'dia_semana' => self::DIAS[$dia->dayOfWeekIso],
                'total' => $conteo->get($dia->toDateString(), 0),
                CanalRecibo::OFICINA => $oficina->get($dia->toDateString(), 0),
                CanalRecibo::WHATSAPP => $conteo->get($dia->toDateString(), 0) - $oficina->get($dia->toDateString(), 0),
            ])
            ->all();
    }

    /** Las 12 semanas que terminan en la del "hasta", sin importar el rango. */
    private function porSemana(Carbon $hasta): array
    {
        $fin = $hasta->copy()->endOfWeek(Carbon::SUNDAY);
        $inicio = $fin->copy()->subWeeks(12)->addDay()->startOfDay();

        $recibos = $this->recibos($inicio, $fin);
        $semana = fn ($r) => Carbon::parse($r->fecha)->startOfWeek(Carbon::MONDAY)->toDateString();
        $conteo = $recibos->countBy($semana);
        $oficina = $recibos->where('canal', CanalRecibo::OFICINA)->countBy($semana);

        return collect(range(0, 11))
            ->map(function (int $i) use ($inicio, $conteo, $oficina) {
                $lunes = $inicio->copy()->addWeeks($i);
                $clave = $lunes->toDateString();

                return [
                    'etiqueta' => $lunes->format('d/m'),
                    'rango' => $lunes->format('d/m').' al '.$lunes->copy()->addDays(6)->format('d/m/Y'),
                    'total' => $conteo->get($clave, 0),
                    CanalRecibo::OFICINA => $oficina->get($clave, 0),
                    CanalRecibo::WHATSAPP => $conteo->get($clave, 0) - $oficina->get($clave, 0),
                ];
            })
            ->all();
    }

    private function porUsuario(Collection $recibos, Collection $atenciones): array
    {
        $nombres = DB::table('users')
            ->whereIn('id', $recibos->pluck('usuario_id')->filter()->unique())
            ->pluck('name', 'id');

        return $recibos
            ->groupBy(fn ($r) => $r->usuario_id ?? 0)
            ->map(function (Collection $suyos, $id) use ($nombres, $recibos, $atenciones) {
                $suyas = $atenciones->filter(fn ($a) => (int) ($a['usuario_id'] ?? 0) === (int) $id);
                $porHora = $suyos->countBy('hora');
                $dias = $suyos->pluck('fecha')->unique()->count();
                // Horas en las que hizo al menos un recibo: su ritmo cuando está cobrando.
                $horasActivas = $suyos->map(fn ($r) => $r->fecha.' '.$r->hora)->unique()->count();

                return [
                    'nombre' => $nombres[$id] ?? 'Sin usuario',
                    'total' => $suyos->count(),
                    CanalRecibo::OFICINA => $suyos->where('canal', CanalRecibo::OFICINA)->count(),
                    CanalRecibo::WHATSAPP => $suyos->where('canal', CanalRecibo::WHATSAPP)->count(),
                    'clientes' => $suyas->count(),
                    // En la oficina es donde se forma la fila.
                    'minutos_entre' => $this->minutosEntreClientes($suyas->where('canal', CanalRecibo::OFICINA)),
                    'porcentaje' => $suyos->count() / max(1, $recibos->count()) * 100,
                    'anulados' => $suyos->where('anulado', true)->count(),
                    'monto' => $suyos->where('anulado', false)->sum('monto'),
                    'dias' => $dias,
                    'promedio_dia' => $suyos->count() / max(1, $dias),
                    'por_hora_activa' => $suyos->count() / max(1, $horasActivas),
                    'hora_pico' => $porHora->sortDesc()->keys()->first(),
                ];
            })
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    public function render()
    {
        return view('livewire.reports.workload-report')
            ->layout('layouts.app');
    }
}
