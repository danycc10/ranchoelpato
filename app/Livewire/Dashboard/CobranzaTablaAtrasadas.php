<?php

namespace App\Livewire\Dashboard;

use App\Models\Cuota;
use App\Models\Notificacion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Reactive;
use Livewire\Component;
use Livewire\WithPagination;

class CobranzaTablaAtrasadas extends Component
{
    use WithPagination;

    #[Reactive]
    public string $hoy;

    #[Reactive]
    public int $diasTolerancia = 0;

    #[Reactive]
    public bool $soloConContacto = true;

    #[Reactive]
    public ?string $search = null;

    #[Reactive]
    public ?int $fraccionamientoId = null;
    
    #[Reactive]
public string $tipoCuota = 'todos';

    public array $selectedAtrasadas = [];

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
        $limite = Carbon::parse($this->hoy)
            ->subDays($this->diasTolerancia)
            ->toDateString();

        $q = Cuota::query()
            ->with([
                'contrato.cliente',
                'contrato.lote.fraccionamiento',
            ])
            ->whereDate('fecha_vencimiento', '<', $limite)
            ->where('estatus', 'pendiente');

        if ($this->soloConContacto) {
            $q->whereHas('contrato.cliente', function ($qq) {
                $qq->whereNotNull('correo')
                    ->orWhereNotNull('telefono');
            });
        }

        return $this->aplicarFiltrosBase($q);
    }

    public function getNotificadasHoyMapProperty(): array
    {
        $ids = (clone $this->query())
            ->limit(500)
            ->pluck('id')
            ->all();

        if (empty($ids)) {
            return [];
        }

        $rows = Notificacion::query()
            ->select(
                'cuota_id',
                DB::raw("MAX(CASE WHEN estatus='enviado' THEN 2 WHEN estatus='en_cola' THEN 1 ELSE 0 END) as lvl")
            )
            ->where('tipo', 'cuota_atrasada')
            ->whereIn('estatus', ['enviado', 'en_cola'])
            ->whereIn('cuota_id', $ids)
            ->where(function ($q) {
                $q->whereDate('enviado_en', $this->hoy)
                    ->orWhereDate('created_at', $this->hoy);
            })
            ->groupBy('cuota_id')
            ->get();

        $map = [];

        foreach ($rows as $r) {
            $map[(int) $r->cuota_id] = ((int) $r->lvl === 2) ? 'enviado' : 'en_cola';
        }

        return $map;
    }

    public function abrirWhatsapp(int $cuotaId): void
    {
        $this->dispatch('abrir-whatsapp-desde-cuota', cuotaId: $cuotaId)
            ->to(CobranzaDashboard::class);
    }

    public function notificarCuota(int $cuotaId): void
    {
        $this->dispatch('notificar-cuota-desde-tabla', cuotaId: $cuotaId)
            ->to(CobranzaDashboard::class);
    }

    public function notificarSeleccionadasAtrasadas(): void
    {
        $ids = array_map('intval', $this->selectedAtrasadas);

        if (empty($ids)) {
            $this->dispatch('toast', type: 'warning', message: 'Selecciona al menos una cuota atrasada.');
            return;
        }

        $this->dispatch('notificar-seleccionadas-desde-tabla', ids: $ids)
            ->to(CobranzaDashboard::class);

        $this->selectedAtrasadas = [];
    }

    public function render()
    {
        return view('livewire.dashboard.cobranza-tabla-atrasadas', [
            'cuotasAtrasadas'   => $this->query()
                ->orderBy('fecha_vencimiento')
                ->paginate(15, pageName: 'atrPage'),
            'notificadasHoyMap' => $this->notificadasHoyMap,
        ]);
    }
}