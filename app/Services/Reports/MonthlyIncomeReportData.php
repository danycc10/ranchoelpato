<?php

namespace App\Services\Reports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MonthlyIncomeReportData
{
    protected ?Collection $metodosPagoCache = null;

    protected ?Collection $filasCache = null;

    protected ?object $totalesConceptosHeaderCache = null;

    protected ?object $conceptosPorFincaCache = null;

    protected ?object $totalesCache = null;

    public function __construct(
        protected int $anio,
        protected int $mes,
        protected ?int $propietarioId = null,
        protected string $modoVista = 'flujo_real',
    ) {}

    public function modoVistaLabel(): string
    {
        return match ($this->modoVista) {
            'flujo_real' => 'FLUJO REAL MENSUAL',
            default => $this->comoVaMesLabel(),
        };
    }

    public function metodosPago(): Collection
    {
        if ($this->metodosPagoCache !== null) {
            return $this->metodosPagoCache;
        }

        return $this->metodosPagoCache = $this->baseJoinRecibos()
            ->selectRaw('COALESCE(formas_pago.nombre, "SIN FORMA") as metodo')
            ->distinct()
            ->orderBy('metodo')
            ->pluck('metodo')
            ->values();
    }

    public function filas(): Collection
    {
        if ($this->filasCache !== null) {
            return $this->filasCache;
        }

        $metodos = $this->metodosPago()->values();
        [$start, $end] = $this->monthRange();

        $esperado = DB::table('cuotas')
            ->leftJoin('contratos', 'contratos.id', '=', 'cuotas.contrato_id')
            ->leftJoin('lotes', 'lotes.id', '=', 'contratos.lote_id')
            ->leftJoin('fraccionamientos', 'fraccionamientos.id', '=', 'lotes.fraccionamiento_id')
            ->when(
                $this->propietarioId,
                fn ($q) => $q->where('fraccionamientos.propietario_id', $this->propietarioId)
            )
            ->whereBetween('cuotas.fecha_vencimiento', [$start, $end])
            ->where('contratos.tipo', 'terreno')
            ->whereIn('cuotas.estatus', ['pendiente', 'parcial', 'pagada', 'vencida'])
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('pagos')
                    ->whereColumn('pagos.cuota_id', 'cuotas.id')
                    ->whereNull('pagos.anulado_at')
                    ->whereRaw('UPPER(pagos.referencia) LIKE ?', ['%IMPORT%']);
            })
            ->groupBy('fraccionamientos.id', 'fraccionamientos.nombre')
            ->selectRaw('
                fraccionamientos.id as finca_id,
                COALESCE(fraccionamientos.nombre, "Sin finca") as finca,
                COALESCE(SUM(cuotas.monto), 0) as esperado
            ')
            ->get()
            ->keyBy('finca_id');

        $recibidoNormal = $this->baseJoinRecibosMensualidad()
            ->groupBy('fraccionamientos.id', 'fraccionamientos.nombre')
            ->selectRaw('
                fraccionamientos.id as finca_id,
                COALESCE(fraccionamientos.nombre, "Sin finca") as finca,
                COALESCE(SUM(recibos_pagos.monto), 0) as recibido
            ')
            ->get()
            ->keyBy('finca_id');

        $recibidoHistorico = collect();

        if ($this->modoVista !== 'flujo_real') {
            $recibidoHistorico = $this->baseJoinRecibosHistoricosComoVaMes()
                ->groupBy('fraccionamientos.id', 'fraccionamientos.nombre')
                ->selectRaw('
                    fraccionamientos.id as finca_id,
                    COALESCE(fraccionamientos.nombre, "Sin finca") as finca,
                    COALESCE(SUM(recibos.monto), 0) as recibido
                ')
                ->get()
                ->keyBy('finca_id');
        }

        $recibido = collect();

        foreach ($recibidoNormal as $id => $row) {
            $row->recibido = (float) $row->recibido;
            $recibido[$id] = $row;
        }

        foreach ($recibidoHistorico as $id => $row) {
            if (isset($recibido[$id])) {
                $recibido[$id]->recibido += (float) $row->recibido;
            } else {
                $row->recibido = (float) $row->recibido;
                $recibido[$id] = $row;
            }
        }

        $adelantado = $this->baseJoinRecibosAdelantado()
            ->groupBy('fraccionamientos.id', 'fraccionamientos.nombre')
            ->selectRaw('
                fraccionamientos.id as finca_id,
                COALESCE(fraccionamientos.nombre, "Sin finca") as finca,
                COALESCE(SUM(recibos_pagos.monto), 0) as adelantado
            ')
            ->get()
            ->keyBy('finca_id');

        $atrasado = $this->baseJoinRecibosAtrasado()
            ->groupBy('fraccionamientos.id', 'fraccionamientos.nombre')
            ->selectRaw('
                fraccionamientos.id as finca_id,
                COALESCE(fraccionamientos.nombre, "Sin finca") as finca,
                COALESCE(SUM(recibos_pagos.monto), 0) as atrasado
            ')
            ->get()
            ->keyBy('finca_id');

        $recPorMetodo = $this->baseJoinRecibosMensualidad()
            ->groupBy('fraccionamientos.id', 'formas_pago.nombre')
            ->selectRaw('
                fraccionamientos.id as finca_id,
                COALESCE(formas_pago.nombre, "SIN FORMA") as metodo,
                COALESCE(SUM(recibos_pagos.monto), 0) as total
            ')
            ->get();

        $mapMetodo = [];

        foreach ($recPorMetodo as $r) {
            $mapMetodo[$r->finca_id][$r->metodo] = (float) $r->total;
        }

        if ($this->modoVista !== 'flujo_real') {
            $historicoLabel = $this->historicoLabel();
            $historicoPorMetodo = $this->baseJoinRecibosHistoricosComoVaMes()
                ->groupBy('fraccionamientos.id')
                ->selectRaw('
                    fraccionamientos.id as finca_id,
                    COALESCE(SUM(recibos.monto), 0) as total
                ')
                ->get();

            foreach ($historicoPorMetodo as $r) {
                $mapMetodo[$r->finca_id][$historicoLabel] =
                    (float) ($mapMetodo[$r->finca_id][$historicoLabel] ?? 0) + (float) $r->total;
            }

            if ($historicoPorMetodo->isNotEmpty() && ! $metodos->contains($historicoLabel)) {
                $metodos->push($historicoLabel);
            }
        }

        $fincaIds = collect($esperado->keys())
            ->merge($recibido->keys())
            ->merge($adelantado->keys())
            ->merge($atrasado->keys())
            ->unique()
            ->values();

        $rows = $fincaIds->map(function ($id) use ($esperado, $recibido, $adelantado, $atrasado, $metodos, $mapMetodo) {
            $e = $esperado->get($id);
            $r = $recibido->get($id);
            $a = $adelantado->get($id);
            $d = $atrasado->get($id);

            $finca = $e->finca ?? $r->finca ?? $a->finca ?? $d->finca ?? 'Sin finca';

            $valEsperado = (float) ($e->esperado ?? 0);
            $valRecibido = (float) ($r->recibido ?? 0);
            $valAdelantado = (float) ($a->adelantado ?? 0);
            $valAtrasado = (float) ($d->atrasado ?? 0);

            $met = [];

            foreach ($metodos as $m) {
                $met[$m] = (float) ($mapMetodo[$id][$m] ?? 0);
            }

            return (object) [
                'finca' => $finca,
                'esperado' => $valEsperado,
                'recibido' => $valRecibido,
                'adelantado' => $valAdelantado,
                'atrasado' => $valAtrasado,
                'diferencia' => $valRecibido - $valEsperado,
                'metodos' => $met,
            ];
        });

        return $this->filasCache = $rows->sortBy('finca', SORT_NATURAL | SORT_FLAG_CASE)->values();
    }

    public function totalesConceptosHeader(): object
    {
        if ($this->totalesConceptosHeaderCache !== null) {
            return $this->totalesConceptosHeaderCache;
        }

        $targets = [
            'RECARGO' => 'Recargo',
            'CAMBIO DE CONTRATO' => 'Cambio de contrato',
            'ELECTRICIDAD' => 'Pago electricidad',
            'ENGANCHE' => 'Enganche',
        ];

        $rows = $this->baseJoinRecibos()
            ->whereNotNull('tipos_cobro.nombre')
            ->groupBy('tipos_cobro.nombre')
            ->selectRaw('tipos_cobro.nombre as concepto, SUM(recibos_pagos.monto) as total')
            ->get();

        $out = [];

        foreach ($targets as $label) {
            $out[$label] = 0.0;
        }

        foreach ($rows as $r) {
            $name = mb_strtoupper((string) $r->concepto);

            foreach ($targets as $needle => $label) {
                if (str_contains($name, $needle)) {
                    $out[$label] += (float) $r->total;
                }
            }
        }

        return $this->totalesConceptosHeaderCache = (object) $out;
    }

    public function conceptosPorFinca(): object
    {
        if ($this->conceptosPorFincaCache !== null) {
            return $this->conceptosPorFincaCache;
        }

        $conceptos = [
            'PAGO ELECTRICIDAD' => ['ELECTRICIDAD'],
            'ENGANCHE' => ['ENGANCHE'],
        ];

        $rows = $this->baseJoinRecibos()
            ->whereNotNull('tipos_cobro.nombre')
            ->groupBy('fraccionamientos.id', 'fraccionamientos.nombre', 'tipos_cobro.nombre')
            ->selectRaw('
                fraccionamientos.id as finca_id,
                COALESCE(fraccionamientos.nombre, "Sin finca") as finca,
                tipos_cobro.nombre as concepto,
                COALESCE(SUM(recibos_pagos.monto), 0) as total
            ')
            ->get();

        $catalogoNombres = $rows
            ->filter(fn ($r) => ! is_null($r->finca_id))
            ->map(fn ($r) => [
                'id' => $r->finca_id,
                'nombre' => $r->finca,
            ])
            ->unique('id')
            ->values();

        foreach ($this->filas() as $f) {
            $yaExiste = collect($catalogoNombres)->firstWhere('nombre', $f->finca);

            if (! $yaExiste) {
                $catalogoNombres->push([
                    'id' => 'name_'.md5($f->finca),
                    'nombre' => $f->finca,
                ]);
            }
        }

        $catalogoNombres = collect($catalogoNombres)
            ->sortBy('nombre', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        $out = [];

        foreach ($conceptos as $label => $needles) {
            $out[$label] = [
                'concepto' => $label,
                'fincas' => [],
                'total' => 0.0,
            ];

            foreach ($catalogoNombres as $finca) {
                $out[$label]['fincas'][$finca['nombre']] = 0.0;
            }
        }

        foreach ($rows as $r) {
            $nombreConcepto = mb_strtoupper((string) $r->concepto);
            $nombreFinca = (string) ($r->finca ?? 'Sin finca');

            foreach ($conceptos as $label => $needles) {
                foreach ($needles as $needle) {
                    if (str_contains($nombreConcepto, $needle)) {
                        $out[$label]['fincas'][$nombreFinca] =
                            (float) ($out[$label]['fincas'][$nombreFinca] ?? 0) + (float) $r->total;

                        $out[$label]['total'] += (float) $r->total;

                        break;
                    }
                }
            }
        }

        return $this->conceptosPorFincaCache = (object) [
            'fincas' => $catalogoNombres,
            'filas' => collect($out)->values(),
        ];
    }

    public function totales(): object
    {
        if ($this->totalesCache !== null) {
            return $this->totalesCache;
        }

        $metodos = $this->metodosPago();

        $sumEsperado = 0.0;
        $sumRecibido = 0.0;
        $sumAdelantado = 0.0;
        $sumAtrasado = 0.0;
        $sumMetodos = array_fill_keys($metodos->toArray(), 0.0);

        foreach ($this->filas() as $f) {
            $sumEsperado += (float) $f->esperado;
            $sumRecibido += (float) $f->recibido;
            $sumAdelantado += (float) ($f->adelantado ?? 0);
            $sumAtrasado += (float) ($f->atrasado ?? 0);

            foreach ($metodos as $m) {
                $sumMetodos[$m] += (float) ($f->metodos[$m] ?? 0);
            }
        }

        return $this->totalesCache = (object) [
            'esperado' => $sumEsperado,
            'recibido' => $sumRecibido,
            'adelantado' => $sumAdelantado,
            'atrasado' => $sumAtrasado,
            'diferencia' => $sumRecibido - $sumEsperado,
            'metodos' => $sumMetodos,
        ];
    }

    protected function monthRange(): array
    {
        $start = now()->setDate($this->anio, $this->mes, 1)->startOfDay();
        $end = (clone $start)->endOfMonth()->endOfDay();

        return [$start->toDateString(), $end->toDateString()];
    }

    protected function comoVaMesLabel(): string
    {
        return 'C'."\u{00D3}".'MO VA EL MES';
    }

    protected function historicoLabel(): string
    {
        return 'HIST'."\u{00D3}".'RICO';
    }

    protected function baseJoinRecibosFlujoReal()
    {
        [$start, $end] = $this->monthRange();

        return DB::table('recibos_pagos')
            ->join('recibos', 'recibos.id', '=', 'recibos_pagos.recibo_id')
            ->leftJoin('formas_pago', 'formas_pago.id', '=', 'recibos_pagos.forma_pago_id')
            ->leftJoin('tipos_cobro', 'tipos_cobro.id', '=', 'recibos.tipos_cobro_id')
            ->leftJoin('contratos', 'contratos.id', '=', 'recibos.contrato_id')
            ->leftJoin('cuotas', 'cuotas.id', '=', 'recibos.cuota_id')
            ->leftJoin('lotes', 'lotes.id', '=', 'contratos.lote_id')
            ->leftJoin('fraccionamientos', 'fraccionamientos.id', '=', 'lotes.fraccionamiento_id')
            ->whereNull('recibos_pagos.deleted_at')
            ->whereNull('recibos.deleted_at')
            ->whereNull('recibos.anulado_at')
            ->where(function ($q) {
                $q->whereNull('recibos.es_historico')
                    ->orWhere('recibos.es_historico', false);
            })
            ->where('recibos.afecta_reportes', true)
            ->where('recibos.folio', 'not like', 'REC%')
            ->whereIn('contratos.estatus', ['activo', 'liquidado'])
            ->whereBetween('recibos_pagos.fecha_efectiva', [$start, $end])
            ->where(function ($q) use ($start, $end) {
                $q->where(function ($sub) use ($start, $end) {
                    $sub->where('tipos_cobro.nombre', 'MENSUALIDAD')
                        ->whereBetween('cuotas.fecha_vencimiento', [$start, $end]);
                })
                    ->orWhere(function ($sub) {
                        $sub->where(function ($x) {
                            $x->whereNull('tipos_cobro.nombre')
                                ->orWhere('tipos_cobro.nombre', '!=', 'MENSUALIDAD');
                        });
                    });
            })
            ->when(
                $this->propietarioId,
                fn ($q) => $q->where('recibos.propietario_contable_id', $this->propietarioId)
            );
    }

    protected function baseJoinRecibos()
    {
        if ($this->modoVista === 'flujo_real') {
            return $this->baseJoinRecibosFlujoReal();
        }

        [$start, $end] = $this->monthRange();

        return DB::table('recibos_pagos')
            ->join('recibos', 'recibos.id', '=', 'recibos_pagos.recibo_id')
            ->leftJoin('formas_pago', 'formas_pago.id', '=', 'recibos_pagos.forma_pago_id')
            ->leftJoin('tipos_cobro', 'tipos_cobro.id', '=', 'recibos.tipos_cobro_id')
            ->leftJoin('contratos', 'contratos.id', '=', 'recibos.contrato_id')
            ->leftJoin('cuotas', 'cuotas.id', '=', 'recibos.cuota_id')
            ->leftJoin('lotes', 'lotes.id', '=', 'contratos.lote_id')
            ->leftJoin('fraccionamientos', 'fraccionamientos.id', '=', 'lotes.fraccionamiento_id')
            ->whereNull('recibos_pagos.deleted_at')
            ->whereNull('recibos.deleted_at')
            ->whereNull('recibos.anulado_at')
            ->whereIn('contratos.estatus', ['activo', 'liquidado'])
            ->where(function ($q) use ($start, $end) {
                $q->where(function ($sub) use ($start, $end) {
                    $sub->where('tipos_cobro.nombre', 'MENSUALIDAD')
                        ->whereBetween('cuotas.fecha_vencimiento', [$start, $end]);
                })
                    ->orWhere(function ($sub) use ($start, $end) {
                        $sub->where(function ($x) {
                            $x->whereNull('tipos_cobro.nombre')
                                ->orWhere('tipos_cobro.nombre', '!=', 'MENSUALIDAD');
                        })
                            ->whereBetween('recibos_pagos.fecha_efectiva', [$start, $end]);
                    });
            })
            ->when(
                $this->propietarioId,
                fn ($q) => $q->where('recibos.propietario_contable_id', $this->propietarioId)
            );
    }

    protected function baseJoinRecibosHistoricosComoVaMes()
    {
        [$start, $end] = $this->monthRange();

        return DB::table('recibos')
            ->leftJoin('tipos_cobro', 'tipos_cobro.id', '=', 'recibos.tipos_cobro_id')
            ->leftJoin('contratos', 'contratos.id', '=', 'recibos.contrato_id')
            ->leftJoin('cuotas', 'cuotas.id', '=', 'recibos.cuota_id')
            ->leftJoin('lotes', 'lotes.id', '=', 'contratos.lote_id')
            ->leftJoin('fraccionamientos', 'fraccionamientos.id', '=', 'lotes.fraccionamiento_id')
            ->whereNull('recibos.deleted_at')
            ->whereNull('recibos.anulado_at')
            ->whereIn('contratos.estatus', ['activo', 'liquidado'])
            ->where('recibos.es_historico', true)
            ->where('tipos_cobro.nombre', 'MENSUALIDAD')
            ->where('contratos.tipo', 'terreno')
            ->whereBetween('cuotas.fecha_vencimiento', [$start, $end])
            ->when(
                $this->propietarioId,
                fn ($q) => $q->where('recibos.propietario_contable_id', $this->propietarioId)
            );
    }

    protected function baseJoinRecibosMensualidad()
    {
        return $this->baseJoinRecibos()
            ->where('tipos_cobro.nombre', 'MENSUALIDAD')
            ->where('contratos.tipo', 'terreno');
    }

    protected function baseJoinRecibosMensualidadPorFechaPago()
    {
        [$start, $end] = $this->monthRange();

        return DB::table('recibos_pagos')
            ->join('recibos', 'recibos.id', '=', 'recibos_pagos.recibo_id')
            ->leftJoin('formas_pago', 'formas_pago.id', '=', 'recibos_pagos.forma_pago_id')
            ->leftJoin('tipos_cobro', 'tipos_cobro.id', '=', 'recibos.tipos_cobro_id')
            ->leftJoin('contratos', 'contratos.id', '=', 'recibos.contrato_id')
            ->leftJoin('cuotas', 'cuotas.id', '=', 'recibos.cuota_id')
            ->leftJoin('lotes', 'lotes.id', '=', 'contratos.lote_id')
            ->leftJoin('fraccionamientos', 'fraccionamientos.id', '=', 'lotes.fraccionamiento_id')
            ->whereNull('recibos_pagos.deleted_at')
            ->whereNull('recibos.deleted_at')
            ->whereNull('recibos.anulado_at')
            ->where(function ($q) {
                $q->whereNull('recibos.es_historico')
                    ->orWhere('recibos.es_historico', false);
            })
            ->where('recibos.afecta_reportes', true)
            ->where('recibos.folio', 'not like', 'REC%')
            ->whereIn('contratos.estatus', ['activo', 'liquidado'])
            ->where('tipos_cobro.nombre', 'MENSUALIDAD')
            ->where('contratos.tipo', 'terreno')
            ->whereBetween('recibos_pagos.fecha_efectiva', [$start, $end])
            ->when(
                $this->propietarioId,
                fn ($q) => $q->where('recibos.propietario_contable_id', $this->propietarioId)
            );
    }

    protected function baseJoinRecibosAdelantado()
    {
        [, $end] = $this->monthRange();

        return $this->baseJoinRecibosMensualidadPorFechaPago()
            ->where('cuotas.fecha_vencimiento', '>', $end);
    }

    protected function baseJoinRecibosAtrasado()
    {
        [$start] = $this->monthRange();

        return $this->baseJoinRecibosMensualidadPorFechaPago()
            ->where('cuotas.fecha_vencimiento', '<', $start);
    }
}
