<?php

namespace App\Livewire\Dashboard;

use App\Models\Cuota;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Reactive;
use Livewire\Component;
use Livewire\WithPagination;

class CobranzaTablaHoy extends Component
{
    use WithPagination;

    #[Reactive]
    public string $hoy;

    #[Reactive]
    public ?string $search = null;

    #[Reactive]
    public ?int $fraccionamientoId = null;
    
    #[Reactive]
    public string $tipoCuota = 'todos';

    protected function aplicarFiltrosBase(Builder $q): Builder
    {
        if ($this->fraccionamientoId) {
            $q->whereHas('contrato.lote.fraccionamiento', function ($qq) {
                $qq->where('id', $this->fraccionamientoId);
            });
        }

        if (filled($this->search)) {
            $search = trim($this->search);

            $q->where(function ($qq) use ($search) {
                $qq->whereHas('contrato.cliente', function ($c) use ($search) {
                    $c->where('nombres', 'like', "%{$search}%")
                        ->orWhere('apellidos', 'like', "%{$search}%")
                        ->orWhere('telefono', 'like', "%{$search}%")
                        ->orWhere('correo', 'like', "%{$search}%");
                })
                ->orWhereHas('contrato', function ($c) use ($search) {
                    $c->where('folio_contrato', 'like', "%{$search}%");
                })
                ->orWhereHas('contrato.lote', function ($l) use ($search) {
                    $l->where('lote', 'like', "%{$search}%");
                });
            });
        }
        
if ($this->tipoCuota === 'terreno') {
    $q->whereHas('contrato', function ($c) {
        $c->where('tipo', 'terreno');
    });
}

if ($this->tipoCuota === 'servicio') {
    $q->whereHas('contrato', function ($c) {
        $c->where('tipo', 'servicio');
    });
}

        return $q;
    }

    protected function query(): Builder
    {
        $q = Cuota::query()
            ->with([
                'contrato.cliente',
                'contrato.lote.fraccionamiento',
            ])
            ->whereDate('fecha_vencimiento', $this->hoy)
            ->where('estatus', 'pendiente');

        return $this->aplicarFiltrosBase($q);
    }

    public function abrirWhatsapp(int $cuotaId): void
    {
        $this->dispatch('abrir-whatsapp-desde-cuota', cuotaId: $cuotaId)
            ->to(CobranzaDashboard::class);
    }

    public function render()
    {
        return view('livewire.dashboard.cobranza-tabla-hoy', [
            'cuotasHoy' => $this->query()
                ->orderBy('fecha_vencimiento')
                ->paginate(15, pageName: 'hoyPage'),
        ]);
    }
}