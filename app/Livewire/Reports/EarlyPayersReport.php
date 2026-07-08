<?php

namespace App\Livewire\Reports;

use App\Models\Propietario;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\EarlyPayersExport;

class EarlyPayersReport extends Component
{
    public int $anio;
    public int $mes;
    public ?int $propietarioId = null;
    public string $frecuencia = 'ambas'; // mensual | semanal | ambas

    public function mount(): void
    {
        $this->anio = (int) now()->year;
        $this->mes  = (int) now()->month;

        $user = auth()->user();
        if ($user->propietario_id) {
            $this->propietarioId = (int) $user->propietario_id;
        }
    }

    public function getPropietariosProperty(): Collection
    {
        return Propietario::orderBy('nombre')->get(['id', 'nombre']);
    }

    public function getMesNombreProperty(): string
    {
        return mb_strtoupper(now()->setDate($this->anio, $this->mes, 1)->translatedFormat('F Y'));
    }

    public function getFilasProperty(): Collection
    {
        $rows = collect();

        if (in_array($this->frecuencia, ['mensual', 'ambas'])) {
            $rows = $rows->concat($this->queryMensuales());
        }

        if (in_array($this->frecuencia, ['semanal', 'ambas'])) {
            $rows = $rows->concat($this->querySemanales());
        }

        return $rows->sortBy('cliente')->values();
    }

    protected function queryMensuales(): Collection
    {
        $inicio = now()->setDate($this->anio, $this->mes, 1)->toDateString();
        $fin    = now()->setDate($this->anio, $this->mes, 1)->endOfMonth()->toDateString();

        $raw = DB::table('recibos_pagos as rp')
            ->join('recibos as r',   'r.id',  '=', 'rp.recibo_id')
            ->join('cuotas as cu',   'cu.id', '=', 'r.cuota_id')
            ->join('contratos as c', 'c.id',  '=', 'cu.contrato_id')
            ->join('clientes as cl', 'cl.id', '=', 'c.cliente_id')
            ->leftJoin('lotes as l',            'l.id', '=', 'c.lote_id')
            ->leftJoin('fraccionamientos as f', 'f.id', '=', 'l.fraccionamiento_id')
            ->whereNull('rp.deleted_at')
            ->whereNull('r.deleted_at')
            ->whereNull('r.anulado_at')
            ->where(fn ($q) => $q->whereNull('r.es_historico')->orWhere('r.es_historico', false))
            ->where('c.frecuencia', 'mensual')
            ->where('c.tipo', 'terreno')
            ->whereIn('c.estatus', ['activo', 'liquidado'])
            ->where('cu.es_anualidad', 0)
            ->whereBetween('cu.fecha_vencimiento', [$inicio, $fin])
            ->whereRaw("rp.fecha_efectiva < DATE_FORMAT(cu.fecha_vencimiento, '%Y-%m-10')")
            ->when($this->propietarioId, fn ($q) => $q->where('f.propietario_id', $this->propietarioId))
            ->select([
                DB::raw("'mensual' as frecuencia"),
                'cl.id as cliente_id',
                DB::raw("CONCAT_WS(' ', cl.nombres, cl.apellidos) as cliente"),
                DB::raw("COALESCE(f.nombre, 'Sin finca') as fraccionamiento"),
                'rp.fecha_efectiva as fecha_pago',
                DB::raw('DAY(rp.fecha_efectiva) as dia_pago'),
                'rp.monto',
                'c.id as contrato_id',
            ])
            ->get();

        // Un registro por cliente: suma montos, toma el día más temprano, lista fincas únicas
        return $raw->groupBy('cliente_id')->map(function ($filas) {
            $primero = $filas->sortBy('dia_pago')->first();
            return (object) [
                'frecuencia'          => 'mensual',
                'cliente_id'          => $primero->cliente_id,
                'cliente'             => $primero->cliente,
                'fraccionamiento'     => $filas->pluck('fraccionamiento')->unique()->sort()->join(', '),
                'contratos_count'     => $filas->pluck('contrato_id')->unique()->count(),
                'fecha_pago'          => $filas->min('fecha_pago'),
                'dia_pago'            => $filas->min('dia_pago'),
                'monto'               => $filas->sum('monto'),
                'cuotas_anticipadas'  => null,
            ];
        })->values();
    }

    protected function querySemanales(): Collection
    {
        $raw = DB::table('recibos_pagos as rp')
            ->join('recibos as r',   'r.id',  '=', 'rp.recibo_id')
            ->join('cuotas as cu',   'cu.id', '=', 'r.cuota_id')
            ->join('contratos as c', 'c.id',  '=', 'cu.contrato_id')
            ->join('clientes as cl', 'cl.id', '=', 'c.cliente_id')
            ->leftJoin('lotes as l',            'l.id', '=', 'c.lote_id')
            ->leftJoin('fraccionamientos as f', 'f.id', '=', 'l.fraccionamiento_id')
            ->whereNull('rp.deleted_at')
            ->whereNull('r.deleted_at')
            ->whereNull('r.anulado_at')
            ->where(fn ($q) => $q->whereNull('r.es_historico')->orWhere('r.es_historico', false))
            ->where('c.frecuencia', 'semanal')
            ->where('c.tipo', 'terreno')
            ->whereIn('c.estatus', ['activo', 'liquidado'])
            ->where('cu.es_anualidad', 0)
            ->whereRaw("DATE_FORMAT(rp.fecha_efectiva, '%Y-%m') = ?", [
                now()->setDate($this->anio, $this->mes, 1)->format('Y-m'),
            ])
            ->whereRaw('DAY(rp.fecha_efectiva) < 10')
            ->when($this->propietarioId, fn ($q) => $q->where('f.propietario_id', $this->propietarioId))
            ->select([
                DB::raw("'semanal' as frecuencia"),
                'cl.id as cliente_id',
                DB::raw("CONCAT_WS(' ', cl.nombres, cl.apellidos) as cliente"),
                DB::raw("COALESCE(f.nombre, 'Sin finca') as fraccionamiento"),
                DB::raw('MIN(rp.fecha_efectiva) as fecha_pago'),
                DB::raw('MIN(DAY(rp.fecha_efectiva)) as dia_pago'),
                DB::raw('SUM(rp.monto) as monto'),
                DB::raw('COUNT(DISTINCT cu.id) as cuotas_anticipadas'),
                'c.id as contrato_id',
            ])
            ->groupBy('c.id', 'cl.id', 'f.id', 'cl.nombres', 'cl.apellidos', 'f.nombre')
            ->havingRaw('COUNT(DISTINCT cu.id) >= 2')
            ->get();

        // Un registro por cliente: suma cuotas y montos de todos sus contratos que califican
        return $raw->groupBy('cliente_id')->map(function ($filas) {
            $primero = $filas->sortBy('dia_pago')->first();
            return (object) [
                'frecuencia'         => 'semanal',
                'cliente_id'         => $primero->cliente_id,
                'cliente'            => $primero->cliente,
                'fraccionamiento'    => $filas->pluck('fraccionamiento')->unique()->sort()->join(', '),
                'contratos_count'    => $filas->pluck('contrato_id')->unique()->count(),
                'fecha_pago'         => $filas->min('fecha_pago'),
                'dia_pago'           => $filas->min('dia_pago'),
                'monto'              => $filas->sum('monto'),
                'cuotas_anticipadas' => $filas->sum('cuotas_anticipadas'),
            ];
        })->values();
    }

    public function exportExcel()
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        $file = "pagadores_adelantados_{$this->anio}_" . str_pad($this->mes, 2, '0', STR_PAD_LEFT) . "_{$timestamp}.xlsx";

        return Excel::download(
            new EarlyPayersExport(
                filas: $this->filas,
                mesNombre: $this->mesNombre,
            ),
            $file
        );
    }

    public function render()
    {
        return view('livewire.reports.early-payers-report', [
            'propietarios' => $this->propietarios,
            'filas'        => $this->filas,
            'mesNombre'    => $this->mesNombre,
        ])->layout('layouts.app');
    }
}
