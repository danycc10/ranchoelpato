<?php

namespace App\Livewire\Admin\ClientesExcelentes;

use App\Models\TipoCobro;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public int $meses = 12;

    public int $minScore = 10;

    public bool $soloElegibles = true;

    public function updatingMeses(): void
    {
        $this->resetPage();
    }

    public function updatingMinScore(): void
    {
        $this->resetPage();
    }

    public function updatingSoloElegibles(): void
    {
        $this->resetPage();
    }

    protected function obtenerTipoCobroRecargoId(): ?int
    {
        $id = TipoCobro::query()
            ->whereRaw('UPPER(nombre) LIKE ?', ['%RECARGO%'])
            ->value('id');

        return $id ? (int) $id : null;
    }

    public function render()
    {
        $desde = Carbon::now()->startOfDay()->subMonths($this->meses)->toDateString();
        $recargoTipoCobroId = $this->obtenerTipoCobroRecargoId();

        /**
         * Subquery:
         *  - por cuota: fecha primer pago confirmado (min fecha_pago)
         *  - detecta si ese pago está ligado a un recibo "recargo"
         */
        $pagosPorCuota = DB::table('pagos')
            ->select([
                'pagos.cuota_id',
                DB::raw('MIN(pagos.fecha_pago) as fecha_pago'),
                DB::raw('MAX(CASE 
                    WHEN recibos.id IS NOT NULL AND (
                        '.($recargoTipoCobroId ? "recibos.tipos_cobro_id = {$recargoTipoCobroId}" : '0=1').'
                        OR UPPER(COALESCE(recibos.observaciones, "")) LIKE "%RECARGO%"
                    )
                    THEN 1 ELSE 0 END) as es_recargo'
                ),
            ])
            ->leftJoin('recibos', 'recibos.id', '=', 'pagos.recibo_id')
            ->whereNotNull('pagos.cuota_id')
            ->where('pagos.estatus', 'confirmado')
            ->whereDate('pagos.fecha_pago', '>=', $desde)
            ->groupBy('pagos.cuota_id');

        /**
         * Score por contrato/cliente:
         *  - antes de vencer: +2
         *  - en gracia: +1
         *  - tarde: -3
         *  - recargo pagado: -2
         * Nota: gracia se toma desde contrato.dias_gracia (si no existe, 0).
         */
        $rows = DB::table('cuotas')
            ->join('contratos', 'contratos.id', '=', 'cuotas.contrato_id')
            ->join('clientes', 'clientes.id', '=', 'contratos.cliente_id')
            ->joinSub($pagosPorCuota, 'pp', function ($join) {
                $join->on('pp.cuota_id', '=', 'cuotas.id');
            })
            ->select([
                'clientes.id as cliente_id',
                DB::raw("TRIM(CONCAT(clientes.nombres,' ',clientes.apellidos)) as cliente_nombre"),
                'contratos.id as contrato_id',
                'contratos.folio_contrato',
                'contratos.uuid as contrato_uuid',   // ✅ AQUI
                DB::raw('COUNT(cuotas.id) as cuotas_pagadas'),
                DB::raw('SUM(CASE WHEN DATEDIFF(cuotas.fecha_vencimiento, pp.fecha_pago) >= 1 THEN 1 ELSE 0 END) as pagadas_adelantadas'),
                DB::raw('AVG(CASE WHEN DATEDIFF(cuotas.fecha_vencimiento, pp.fecha_pago) >= 1 THEN DATEDIFF(cuotas.fecha_vencimiento, pp.fecha_pago) ELSE NULL END) as adelanto_promedio_dias'),
                DB::raw('SUM(CASE 
                    WHEN DATEDIFF(pp.fecha_pago, cuotas.fecha_vencimiento) BETWEEN 0 AND COALESCE(contratos.dias_gracia,0)
                    THEN 1 ELSE 0 END) as pagadas_en_gracia'),
                DB::raw('SUM(CASE 
                    WHEN DATEDIFF(pp.fecha_pago, cuotas.fecha_vencimiento) > COALESCE(contratos.dias_gracia,0)
                    THEN 1 ELSE 0 END) as pagadas_tarde'),
                DB::raw('SUM(pp.es_recargo) as recargos_pagados'),
                DB::raw('(
                    (SUM(CASE WHEN DATEDIFF(cuotas.fecha_vencimiento, pp.fecha_pago) >= 1 THEN 1 ELSE 0 END) * 2)
                    + (SUM(CASE WHEN DATEDIFF(pp.fecha_pago, cuotas.fecha_vencimiento) BETWEEN 0 AND COALESCE(contratos.dias_gracia,0) THEN 1 ELSE 0 END) * 1)
                    - (SUM(CASE WHEN DATEDIFF(pp.fecha_pago, cuotas.fecha_vencimiento) > COALESCE(contratos.dias_gracia,0) THEN 1 ELSE 0 END) * 3)
                    - (SUM(pp.es_recargo) * 2)
                ) as score'),
            ])
            ->whereDate('cuotas.fecha_vencimiento', '>=', $desde)
            ->groupBy('clientes.id', 'clientes.nombres', 'clientes.apellidos', 'contratos.id', 'contratos.uuid', 'contratos.folio_contrato', 'contratos.dias_gracia')
            ->having('score', '>=', $this->minScore)
            ->when($this->soloElegibles, function ($q) {
                // elegible: sin pagos tarde y recargos 0
                $q->havingRaw('pagadas_tarde = 0')
                    ->havingRaw('recargos_pagados = 0');
            })
            ->orderByDesc('score')
            ->paginate(15);

        return view('livewire.admin.clientes-excelentes.index', [
            'rows' => $rows,
        ])->layout('layouts.app');
    }
}
