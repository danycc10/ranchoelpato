<?php

namespace App\Livewire\Reports;

use App\Exports\BankMovementsExport;
use App\Models\CuentaBancaria;
use App\Models\Propietario;
use App\Models\ReciboPago;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;

class BankMovementsReport extends Component
{
    use WithPagination;

    public ?int $propietarioId = null;

    public array $cuentaBancariaIds = [];

    public string $desde;

    public string $hasta;

    public function mount(): void
    {
        $this->desde = now()->startOfMonth()->toDateString();
        $this->hasta = now()->toDateString();

        $user = auth()->user();

        if ($user->propietario_id) {
            $this->propietarioId = $user->propietario_id;
        }
        $this->cuentaBancariaIds = [];
    }

    public function updatedPropietarioId(): void
    {
        $this->cuentaBancariaIds = [];
        $this->resetPage();
    }

    public function updatedCuentaBancariaIds(): void
    {
        $this->resetPage();
    }

    public function updatedDesde(): void
    {
        $this->resetPage();
    }

    public function updatedHasta(): void
    {
        $this->resetPage();
    }

    public function getPropietariosProperty()
    {
        return Propietario::query()
            ->orderBy('nombre')
            ->get(['id', 'nombre']);
    }

    public function getCuentasProperty()
    {
        return CuentaBancaria::query()
            ->when(
                $this->propietarioId,
                fn ($q) => $q->where('propietario_id', $this->propietarioId)
            )
            ->orderBy('alias')
            ->get(['id', 'propietario_id', 'alias', 'banco', 'tipo', 'numero', 'activa']);
    }

    public function getCuentasSeleccionadasProperty()
    {
        if (empty($this->cuentaBancariaIds)) {
            return collect();
        }

        return CuentaBancaria::query()
            ->whereIn('id', $this->cuentaBancariaIds)
            ->orderBy('alias')
            ->get(['id', 'alias', 'banco', 'numero', 'activa']);
    }

    protected function baseQuery()
    {

        return ReciboPago::query()
            ->with([
                'recibo.cliente:id,nombres,apellidos',
                'recibo.contrato:id,folio_contrato,lote_id',
                'recibo.contrato.lote:id,fraccionamiento_id,manzana,lote',
                'recibo.contrato.lote.fraccionamiento:id,nombre,propietario_id',
                'recibo.tipoCobro:id,nombre',
                'recibo.cuota:id,numero,fecha_vencimiento',
                'formaPago:id,nombre,requiere_cuenta',
                'cuentaBancaria:id,propietario_id,alias,banco,tipo,numero',
                'capturadoPor:id,name',
            ])

            ->whereNotNull('cuenta_bancaria_id')
            ->whereHas('formaPago', fn ($q) => $q->where('requiere_cuenta', true))
            ->whereNull('deleted_at')

            ->whereHas('recibo', function ($q) {
                $q->where('afecta_reportes', true)
                    ->where(function ($x) {
                        $x->whereNull('es_historico')
                            ->orWhere('es_historico', false);
                    })
                    ->whereNull('anulado_at')
                    ->whereNull('deleted_at')
                    ->where('folio', 'not like', 'REC%');
            })
            ->when(
                $this->propietarioId,
                fn ($q) => $q->whereHas('recibo', function ($sub) {
                    $sub->where('propietario_contable_id', $this->propietarioId);
                })
            )

            ->when(
                ! empty($this->cuentaBancariaIds),
                fn ($q) => $q->whereIn('cuenta_bancaria_id', $this->cuentaBancariaIds)
            )

            ->whereDate('fecha_efectiva', '>=', $this->desde)
            ->whereDate('fecha_efectiva', '<=', $this->hasta);
    }

    public function getMovimientosProperty()
    {
        return $this->baseQuery()
            ->orderByDesc('fecha_efectiva')
            ->orderByDesc('id')
            ->paginate(25);
    }

    public function getTotalProperty(): float
    {
        return (float) $this->baseQuery()->sum('monto');
    }

    public function clearFilters(): void
    {
        $this->propietarioId = null;
        $this->cuentaBancariaIds = [];
        $this->desde = now()->startOfMonth()->toDateString();
        $this->hasta = now()->toDateString();

        $this->resetPage();
    }

    public function clearCuentas(): void
    {
        $this->cuentaBancariaIds = [];
        $this->resetPage();
    }

    public function exportExcel()
    {
        $cuentaNombre = null;

        if (! empty($this->cuentaBancariaIds)) {
            $cuentaNombre = CuentaBancaria::query()
                ->whereIn('id', $this->cuentaBancariaIds)
                ->pluck('alias')
                ->filter()
                ->implode('_');
        }

        $rows = $this->baseQuery()
            ->orderBy('fecha_efectiva')
            ->orderBy('id')
            ->get();

        $fileCuenta = $cuentaNombre
            ? preg_replace('/[^A-Za-z0-9_-]+/', '_', $cuentaNombre)
            : 'todas';

        $timestamp = now()->format('Y-m-d_H-i-s');

        $file = "movimientos_bancarios_{$fileCuenta}_{$this->desde}_a_{$this->hasta}_{$timestamp}.xlsx";

        return Excel::download(
            new BankMovementsExport(
                desde: $this->desde,
                hasta: $this->hasta,
                cuentaNombre: $cuentaNombre,
                rows: $rows
            ),
            $file
        );
    }

    public function render()
    {
        return view('livewire.reports.bank-movements-report')
            ->layout('layouts.app');
    }
}
