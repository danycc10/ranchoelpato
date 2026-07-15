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

        return $rows->sortBy(['fraccionamiento', 'lote', 'cliente'])->values();
    }

    protected function queryMensuales(): Collection
    {
        $inicio = now()->setDate($this->anio, $this->mes, 1)->toDateString();
        $fin    = now()->setDate($this->anio, $this->mes, 1)->endOfMonth()->toDateString();

        // Un registro por lote/contrato: la cuota debe vencer en el mes Y el pago debe ser antes del día 10
        return DB::table('recibos_pagos as rp')
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
                'c.id as contrato_id',
                'c.folio_contrato',
                DB::raw("CONCAT_WS(' ', cl.nombres, cl.apellidos) as cliente"),
                DB::raw("COALESCE(f.nombre, 'Sin finca') as fraccionamiento"),
                DB::raw("COALESCE(l.lote, '—') as lote"),
                DB::raw('MIN(rp.fecha_efectiva) as fecha_pago'),
                DB::raw('MIN(DAY(rp.fecha_efectiva)) as dia_pago'),
                DB::raw('SUM(rp.monto) as monto'),
                DB::raw('NULL as cuotas_anticipadas'),
            ])
            ->groupBy('c.id', 'cl.id', 'f.id', 'l.id',
                      'c.folio_contrato', 'cl.nombres', 'cl.apellidos', 'f.nombre', 'l.lote')
            ->get();
    }

    protected function querySemanales(): Collection
    {
        $yearMonth = now()->setDate($this->anio, $this->mes, 1)->format('Y-m');

        // La cuota debe vencer en el mes seleccionado Y el pago debe ser antes del día 10
        // Se evalúa por lote/contrato: cada contrato necesita ≥2 cuotas del mes pagadas antes del día 10
        return DB::table('recibos_pagos as rp')
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
            ->whereRaw("DATE_FORMAT(cu.fecha_vencimiento, '%Y-%m') = ?", [$yearMonth])
            ->whereRaw('DAY(rp.fecha_efectiva) < 10')
            ->when($this->propietarioId, fn ($q) => $q->where('f.propietario_id', $this->propietarioId))
            ->select([
                DB::raw("'semanal' as frecuencia"),
                'c.id as contrato_id',
                'c.folio_contrato',
                DB::raw("CONCAT_WS(' ', cl.nombres, cl.apellidos) as cliente"),
                DB::raw("COALESCE(f.nombre, 'Sin finca') as fraccionamiento"),
                DB::raw("COALESCE(l.lote, '—') as lote"),
                DB::raw('MIN(rp.fecha_efectiva) as fecha_pago'),
                DB::raw('MIN(DAY(rp.fecha_efectiva)) as dia_pago'),
                DB::raw('SUM(rp.monto) as monto'),
                DB::raw('COUNT(DISTINCT cu.id) as cuotas_anticipadas'),
            ])
            ->groupBy('c.id', 'cl.id', 'f.id', 'l.id',
                      'c.folio_contrato', 'cl.nombres', 'cl.apellidos', 'f.nombre', 'l.lote')
            ->havingRaw('COUNT(DISTINCT cu.id) >= 2')
            ->get();
    }

    public function exportExcel()
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        $file = "participantes_rifa_{$this->anio}_" . str_pad($this->mes, 2, '0', STR_PAD_LEFT) . "_{$timestamp}.xlsx";

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
