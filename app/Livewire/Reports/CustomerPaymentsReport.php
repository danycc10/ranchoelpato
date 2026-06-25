<?php

namespace App\Livewire\Reports;

use App\Exports\CustomerPaymentsExport;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\ReciboPago;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;

class CustomerPaymentsReport extends Component
{
    use WithPagination;

    public ?int $clienteId = null;

    public ?int $contratoId = null;

    public string $searchCliente = '';

    public function mount(): void
    {
        $this->clienteId = Cliente::orderBy('nombres')->value('id');

        if ($this->clienteId) {
            $c = Cliente::find($this->clienteId);
            $this->searchCliente = $c ? trim("{$c->nombres} {$c->apellidos}") : '';
        }

        $this->contratoId = null;
    }

    public function updatedContratoId(): void
    {
        $this->resetPage();
    }

    public function selectCliente(int $id): void
    {
        $cliente = Cliente::find($id);

        $this->clienteId = $id;
        $this->searchCliente = $cliente ? trim("{$cliente->nombres} {$cliente->apellidos}") : '';

        $this->contratoId = null;
        $this->resetPage();
    }

    public function getClientesFiltradosProperty()
    {
        if (mb_strlen($this->searchCliente) < 3) {
            return collect();
        }

        return Cliente::query()
            ->where(function ($q) {
                $q->where('nombres', 'like', "%{$this->searchCliente}%")
                    ->orWhere('apellidos', 'like', "%{$this->searchCliente}%");
            })
            ->orderBy('nombres')
            ->limit(10)
            ->get(['id', 'nombres', 'apellidos']);
    }

    public function getClienteSeleccionadoNombreProperty(): ?string
    {
        if (! $this->clienteId) {
            return null;
        }

        $cliente = Cliente::find($this->clienteId);

        return $cliente ? trim("{$cliente->nombres} {$cliente->apellidos}") : null;
    }

    public function getContratosClienteProperty()
    {
        if (! $this->clienteId) {
            return collect();
        }

        return Contrato::query()
            ->where('cliente_id', $this->clienteId)
            ->with(['lote.fraccionamiento'])
            ->orderByDesc('id')
            ->get(['id', 'folio_contrato', 'estatus', 'precio_total', 'enganche', 'saldo_actual', 'lote_id'])
            ->map(function ($ct) {
                $lote = $ct->lote;
                $finca = $lote?->fraccionamiento?->nombre ?? '—';
                $manzana = $lote?->manzana ?? ($lote?->mz ?? '—');
                $loteNum = $lote?->lote ?? ($lote?->numero ?? ($lote?->num_lote ?? '—'));

                $ct->finca = $finca;
                $ct->manzana = $manzana;
                $ct->lote = $loteNum;

                return $ct;
            });
    }

    public function getContratoSeleccionadoProperty(): ?Contrato
    {
        if (! $this->contratoId) {
            return null;
        }

        return Contrato::query()
            ->with(['lote.fraccionamiento'])
            ->find($this->contratoId);
    }

    public function getLoteInfoProperty(): ?object
    {
        $ct = $this->contratoSeleccionado;
        if (! $ct) {
            return null;
        }

        $lote = $ct->lote;
        $finca = $lote?->fraccionamiento?->nombre ?? '—';
        $manzana = $lote?->manzana ?? ($lote?->mz ?? '—');
        $loteNum = $lote?->lote ?? ($lote?->numero ?? ($lote?->num_lote ?? '—'));

        return (object) [
            'finca' => $finca,
            'manzana' => $manzana,
            'lote' => $loteNum,
        ];
    }

    public function clearFilters(): void
    {
        $this->contratoId = null;
        $this->searchCliente = '';
        $this->clienteId = null;
        $this->resetPage();
    }

    public function clearCliente(): void
    {
        $this->clienteId = null;
        $this->searchCliente = '';
        $this->contratoId = null;
        $this->resetPage();
    }

    protected function pagosValidosQuery(): Builder
    {
        return ReciboPago::query()
            ->whereNull('recibos_pagos.deleted_at')
            ->whereHas('recibo', function ($r) {
                $r->where('afecta_reportes', true)
                    ->where(function ($x) {
                        $x->whereNull('es_historico')
                            ->orWhere('es_historico', false);
                    })
                    ->whereNull('anulado_at')
                    ->whereNull('deleted_at')
                    ->where('folio', 'not like', 'REC%');
            });
    }

    public function getContratosResumenProperty()
    {
        if (! $this->clienteId) {
            return collect();
        }

        $contratos = Contrato::query()
            ->where('cliente_id', $this->clienteId)
            ->with(['lote.fraccionamiento'])
            ->get();

        $ids = $contratos->pluck('id');

        $ultimos = $this->pagosValidosQuery()
            ->join('recibos', 'recibos.id', '=', 'recibos_pagos.recibo_id')
            ->whereIn('recibos.contrato_id', $ids)
            ->selectRaw('recibos.contrato_id, MAX(recibos_pagos.fecha_efectiva) as ultimo_pago')
            ->groupBy('recibos.contrato_id')
            ->get()
            ->keyBy('contrato_id');

        $totalesSinRecargo = $this->pagosValidosQuery()
            ->join('recibos', 'recibos.id', '=', 'recibos_pagos.recibo_id')
            ->leftJoin('tipos_cobro', 'tipos_cobro.id', '=', 'recibos.tipos_cobro_id')
            ->whereIn('recibos.contrato_id', $ids)
            ->where(function ($q) {
                $q->whereNull('tipos_cobro.nombre')
                    ->orWhereRaw('UPPER(TRIM(tipos_cobro.nombre)) <> "RECARGO"');
            })
            ->selectRaw('recibos.contrato_id, SUM(recibos_pagos.monto) as total_pagado')
            ->groupBy('recibos.contrato_id')
            ->get()
            ->keyBy('contrato_id');

        return $contratos->map(function ($c) use ($ultimos, $totalesSinRecargo) {
            $precio = (float) ($c->precio_total ?? 0);
            $enganche = (float) ($c->enganche ?? 0);

            $pagado = (float) ($totalesSinRecargo[$c->id]->total_pagado ?? 0);
            $ultimoPago = $ultimos[$c->id]->ultimo_pago ?? null;

            $lote = $c->lote;
            $finca = $lote?->fraccionamiento?->nombre ?? '—';
            $manzana = $lote?->manzana ?? ($lote?->mz ?? '—');
            $loteNum = $lote?->lote ?? ($lote?->numero ?? ($lote?->num_lote ?? '—'));

            return (object) [
                'folio' => $c->folio_contrato,
                'finca' => $finca,
                'manzana' => $manzana,
                'lote' => $loteNum,
                'estatus' => $c->estatus,
                'precio_total' => $precio,
                'enganche' => $enganche,
                'total_pagado' => $pagado,
                'saldo_restante_calc' => max(($precio - $enganche) - $pagado, 0),
                'saldo_actual' => (float) ($c->saldo_actual ?? 0),
                'ultimo_pago' => $ultimoPago,
            ];
        });
    }

    public function getPagosProperty()
    {
        if (! $this->clienteId) {
            return ReciboPago::query()->whereRaw('1=0')->paginate(25);
        }

        return $this->pagosValidosQuery()
            ->with([
                'recibo.contrato.lote.fraccionamiento',
                'recibo.cuota',
                'recibo.tipoCobro',
                'formaPago',
                'cuentaBancaria',
            ])
            ->whereHas('recibo.contrato', fn (Builder $q) => $q->where('cliente_id', $this->clienteId)
                ->when($this->contratoId, fn ($qq) => $qq->whereKey($this->contratoId))
            )
            ->orderByDesc('fecha_efectiva')
            ->paginate(25);
    }

    public function exportExcel()
    {
        if (! $this->clienteId) {
            return;
        }

        $cliente = Cliente::find($this->clienteId);
        $clienteNombre = $cliente ? trim("{$cliente->nombres} {$cliente->apellidos}") : "cliente_{$this->clienteId}";

        $pagos = $this->pagosValidosQuery()
            ->with([
                'recibo.contrato.lote.fraccionamiento',
                'recibo.cuota',
                'recibo.tipoCobro',
                'formaPago',
                'cuentaBancaria',
            ])
            ->whereHas('recibo.contrato', fn (Builder $q) => $q->where('cliente_id', $this->clienteId)
                ->when($this->contratoId, fn ($qq) => $qq->whereKey($this->contratoId))
            )
            ->orderByDesc('fecha_efectiva')
            ->get();

        $resumen = $this->contratosResumen;

        $ct = $this->contratoSeleccionado;
        $loteInfo = $this->loteInfo;

        $timestamp = now()->format('Y-m-d_H-i-s');

        $file = "pagos_cliente_{$this->clienteId}"
            .($this->contratoId ? "_contrato_{$this->contratoId}" : '')
            ."_{$timestamp}.xlsx";

        return Excel::download(
            new CustomerPaymentsExport(
                clienteNombre: $clienteNombre,
                pagos: $pagos,
                resumen: $resumen,
                contratoFolio: $ct?->folio_contrato,
                finca: $loteInfo?->finca,
                manzana: $loteInfo?->manzana,
                lote: $loteInfo?->lote,
            ),
            $file
        );
    }

    public function render()
    {
        return view('livewire.reports.customer-payments-report', [
            'clientesFiltrados' => $this->clientesFiltrados,
            'clienteSeleccionadoNombre' => $this->clienteSeleccionadoNombre,
            'contratosResumen' => $this->contratosResumen,
            'contratosCliente' => $this->contratosCliente,
            'pagos' => $this->pagos,
            'loteInfo' => $this->loteInfo,
        ])->layout('layouts.app');
    }
}
