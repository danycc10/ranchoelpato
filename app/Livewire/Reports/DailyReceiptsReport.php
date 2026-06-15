<?php

namespace App\Livewire\Reports;

use App\Exports\DailyReceiptsExport;
use App\Models\Propietario;
use App\Models\ReciboPago;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;

class DailyReceiptsReport extends Component
{
    use WithPagination;

    public string $fecha;
    public array $propietarioIds = [];

    public bool $showReciboModal = false;
    public ?int $selectedPagoId = null;
    public ?array $selectedReciboData = null;

    public function mount(): void
    {
        $this->fecha = now()->toDateString();
        
            $user = auth()->user();

    if ( $user->propietario_id) {
        $this->propietarioIds = [(int) $user->propietario_id];
    }
    
    }

    public function updatedFecha(): void
    {
        $this->resetPage();
    }

    public function updatedPropietarioIds(): void
    {
        $this->propietarioIds = collect($this->propietarioIds)
            ->filter()
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $this->resetPage();
    }

    public function getPropietariosProperty()
    {
        return Propietario::query()
            ->orderBy('nombre')
            ->get(['id', 'nombre']);
    }

    protected function baseJoinQuery()
    {
        return DB::table('recibos_pagos')
            ->join('recibos', 'recibos.id', '=', 'recibos_pagos.recibo_id')
            ->leftJoin('tipos_cobro', 'tipos_cobro.id', '=', 'recibos.tipos_cobro_id')
            ->leftJoin('formas_pago', 'formas_pago.id', '=', 'recibos_pagos.forma_pago_id')
            ->leftJoin('contratos', 'contratos.id', '=', 'recibos.contrato_id')
            ->leftJoin('lotes', 'lotes.id', '=', 'contratos.lote_id')
            ->leftJoin('fraccionamientos', 'fraccionamientos.id', '=', 'lotes.fraccionamiento_id')
            ->leftJoin('propietarios as propietarios_contables', 'propietarios_contables.id', '=', 'recibos.propietario_contable_id')
            ->whereDate('recibos_pagos.fecha_efectiva', $this->fecha)
            ->whereNull('recibos_pagos.deleted_at')
            ->where('recibos.afecta_reportes', true)
            ->whereNull('recibos.anulado_at')
            ->whereNull('recibos.deleted_at')
            ->where('recibos.folio', 'not like', 'REC%')
            ->when(
                !empty($this->propietarioIds),
                fn($q) => $q->whereIn('recibos.propietario_contable_id', $this->propietarioIds)
            );
    }

    protected function aggregatedRows()
    {
        return $this->baseJoinQuery()
            ->groupBy(
                'formas_pago.nombre',
                'fraccionamientos.id',
                'fraccionamientos.nombre',
                'tipos_cobro.nombre'
            )
            ->selectRaw('
                COALESCE(formas_pago.nombre, "Sin forma") as metodo,
                fraccionamientos.id as finca_id,
                COALESCE(fraccionamientos.nombre, "Sin finca") as finca,
                COALESCE(tipos_cobro.nombre, "Sin concepto") as concepto,
                SUM(recibos_pagos.monto) as total,
                MAX(recibos.afecta_reportes) as afecta_reportes
            ')
            ->get();
    }

    public function getRecibosProperty()
    {
        $ids = $this->baseJoinQuery()
            ->select('recibos_pagos.id')
            ->pluck('id');

        return ReciboPago::query()
            ->with([
                'recibo.cliente',
                'recibo.contrato.lote.fraccionamiento',
                'recibo.tipoCobro',
                'recibo.propietarioContable',
                'formaPago',
                'cuentaBancaria',
            ])
            ->leftJoin('formas_pago', 'formas_pago.id', '=', 'recibos_pagos.forma_pago_id')
            ->whereIn('recibos_pagos.id', $ids)
            ->select('recibos_pagos.*')
            ->orderByRaw("
                CASE
                    WHEN LOWER(COALESCE(formas_pago.nombre, '')) LIKE '%efect%' THEN 0
                    ELSE 1
                END ASC
            ")
            ->orderByDesc('recibos_pagos.id')
            ->paginate(25);
    }

    public function getConceptosProperty()
    {
        return $this->baseJoinQuery()
            ->whereNotNull('tipos_cobro.nombre')
            ->distinct()
            ->orderBy('tipos_cobro.nombre')
            ->pluck('tipos_cobro.nombre')
            ->values();
    }

    public function getMetodosPagoProperty()
    {
        return DB::table('formas_pago')
            ->pluck('nombre')
            ->map(fn($m) => strtoupper(trim($m)))
            ->filter()
            ->unique()
            ->sortBy(function ($metodo) {
                $m = mb_strtolower($metodo);

                return match (true) {
                    str_contains($m, 'efect')    => 1,
                    str_contains($m, 'transfer') => 2,
                    str_contains($m, 'tarje')    => 3,
                    str_contains($m, 'oxxo')     => 4,
                    default                      => 99,
                };
            })
            ->values();
    }

    public function clearFilters(): void
    {
        $this->propietarioIds = [];
        $this->resetPage();
    }

    public function getResumenPorMetodoProperty()
    {
        $conceptos = $this->conceptos;
        $metodos = $this->metodosPago;

        $expr = 'UPPER(TRIM(COALESCE(formas_pago.nombre, "Sin forma")))';

        $rows = $this->baseJoinQuery()
            ->groupByRaw("$expr, fraccionamientos.id, fraccionamientos.nombre, tipos_cobro.nombre")
            ->selectRaw("
                $expr as metodo,
                fraccionamientos.id as finca_id,
                COALESCE(fraccionamientos.nombre, 'Sin finca') as finca,
                COALESCE(tipos_cobro.nombre, 'Sin concepto') as concepto,
                SUM(recibos_pagos.monto) as total
            ")
            ->get();

        return collect($metodos)->mapWithKeys(function ($metodo) use ($rows, $conceptos) {
            $itemsMetodo = $rows->where('metodo', $metodo);

            $filas = $itemsMetodo->groupBy('finca_id')->map(function ($itemsFinca) use ($conceptos) {
                $first = $itemsFinca->first();

                $fila = [
                    'finca' => $first->finca,
                    'totales' => [],
                    'total_general' => 0.0,
                ];

                foreach ($conceptos as $c) {
                    $fila['totales'][$c] = 0.0;
                }

                foreach ($itemsFinca as $it) {
                    $fila['totales'][$it->concepto] += (float) $it->total;
                    $fila['total_general'] += (float) $it->total;
                }

                return (object) $fila;
            })->values();

            $totPorConcepto = array_fill_keys($conceptos->toArray(), 0.0);
            $totalMetodo = 0.0;

            foreach ($filas as $fila) {
                foreach ($conceptos as $c) {
                    $totPorConcepto[$c] += (float) $fila->totales[$c];
                }
                $totalMetodo += (float) $fila->total_general;
            }

            return [
                $metodo => (object) [
                    'filas' => $filas,
                    'totales' => (object) [
                        'por_concepto' => $totPorConcepto,
                        'total_general' => $totalMetodo,
                    ],
                ],
            ];
        });
    }

    public function getTotalGeneralProperty()
    {
        $conceptos = $this->conceptos;

        $tot = array_fill_keys($conceptos->toArray(), 0.0);
        $total = 0.0;

        foreach ($this->resumenPorMetodo as $metodoData) {
            foreach ($conceptos as $c) {
                $tot[$c] += (float) ($metodoData->totales->por_concepto[$c] ?? 0);
            }
            $total += (float) $metodoData->totales->total_general;
        }

        return (object) [
            'por_concepto' => $tot,
            'total_general' => $total,
        ];
    }

    protected function detailRows()
    {
        return $this->baseJoinQuery()
            ->leftJoin('clientes', 'clientes.id', '=', 'recibos.cliente_id')
            ->leftJoin('cuentas_bancarias', 'cuentas_bancarias.id', '=', 'recibos_pagos.cuenta_bancaria_id')
            ->selectRaw('
                recibos_pagos.id as pago_id,
                recibos.folio as folio,
                COALESCE(NULLIF(CONCAT_WS(" ", clientes.nombres, clientes.apellidos), ""), "—") as persona,
                COALESCE(lotes.lote, lotes.clave, "—") as lote,
                COALESCE(fraccionamientos.nombre, "Sin finca") as finca,
                COALESCE(tipos_cobro.nombre, "Sin concepto") as concepto,
                COALESCE(formas_pago.nombre, "Sin forma") as forma,
                COALESCE(cuentas_bancarias.alias, "—") as cuenta_bancaria,
                recibos_pagos.monto as monto,
                recibos_pagos.fecha_efectiva as fecha_recibo
            ')
            ->orderByRaw("
                CASE
                    WHEN LOWER(COALESCE(formas_pago.nombre, '')) LIKE '%efect%' THEN 0
                    ELSE 1
                END ASC
            ")
            ->orderByDesc('recibos_pagos.id')
            ->get();
    }

    public function openReciboModal(int $pagoId): void
    {
        $pago = ReciboPago::query()
            ->with([
                'recibo.cliente',
                'recibo.contrato.lote.fraccionamiento',
                'recibo.tipoCobro',
                'recibo.propietarioContable',
                'formaPago',
                'cuentaBancaria',
            ])
            ->findOrFail($pagoId);

        $recibo = $pago->recibo;

        $persona = $recibo?->cliente?->nombre_completo
            ?? trim(($recibo?->cliente?->nombres ?? '') . ' ' . ($recibo?->cliente?->apellidos ?? ''));

        if (blank($persona)) {
            $persona = '—';
        }

        $lote = $recibo?->contrato?->lote?->lote
            ?? $recibo?->contrato?->lote?->clave
            ?? $recibo?->contrato?->lote?->nombre
            ?? '—';

        $finca = $recibo?->contrato?->lote?->fraccionamiento?->nombre ?? '—';

        $cuenta = '—';
        if ($pago->cuentaBancaria) {
            $cuenta = trim(
                ($pago->cuentaBancaria->alias ?? '')

            );

            if ($cuenta === '') {
                $cuenta = '—';
            }
        }

        $this->selectedPagoId = $pago->id;
        $this->selectedReciboData = [
            'folio' => $recibo?->folio ?? '—',
            'persona' => $persona,
            'lote' => $lote,
            'finca' => $finca,
            'concepto' => $recibo?->tipoCobro?->nombre ?? '—',
            'forma_pago' => $pago->formaPago?->nombre ?? '—',
            'cuenta_bancaria' => $cuenta,
            'monto' => (float) ($pago->monto ?? 0),
            'fecha' => $pago->created_at,
            'referencia' => $pago->referencia ?? null,
            'observaciones' => $pago->observaciones ?? $recibo?->observaciones ?? null,
            'evidencia_url' => $pago->evidencia_path
                ? route('admin.recibo-pagos.evidencia.show', $pago->id)
                : null,
            'evidencia_path' => $pago->evidencia_path,
            'evidencia_mime' => $pago->evidencia_mime,
            'propietario_contable' => $recibo?->propietarioContable?->nombre ?? '—',
        ];

        $this->showReciboModal = true;
    }

    public function closeReciboModal(): void
    {
        $this->showReciboModal = false;
        $this->selectedPagoId = null;
        $this->selectedReciboData = null;
    }

    public function exportExcel()
    {
        $propietariosNombres = collect();

        if (!empty($this->propietarioIds)) {
            $propietariosNombres = Propietario::query()
                ->whereIn('id', $this->propietarioIds)
                ->orderBy('nombre')
                ->pluck('nombre');
        }

        $propNombre = $propietariosNombres->isNotEmpty()
            ? $propietariosNombres->join(', ')
            : null;

        $timestamp = now()->format('H-i-s');

        $file = "reporte_diario_{$this->fecha}_{$timestamp}";

        if ($propietariosNombres->isNotEmpty()) {
            $file .= '_' . Str::slug($propietariosNombres->join('_'), '_');
        }

        $file .= '.xlsx';

        return Excel::download(
            new DailyReceiptsExport(
                fecha: $this->fecha,
                propietarioNombre: $propNombre,
                rows: $this->aggregatedRows(),
                detailRows: $this->detailRows(),
            ),
            $file
        );
    }

    public function render()
    {
        return view('livewire.reports.daily-receipts-report', [
            'propietarios' => $this->propietarios,
            'conceptos' => $this->conceptos,
            'metodosPago' => $this->metodosPago,
            'resumenPorMetodo' => $this->resumenPorMetodo,
            'totalGeneral' => $this->totalGeneral,
            'recibos' => $this->recibos,
        ])->layout('layouts.app');
    }
}
