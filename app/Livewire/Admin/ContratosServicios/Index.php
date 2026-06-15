<?php

namespace App\Livewire\Admin\ContratosServicios;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Contrato;
use Illuminate\Database\Eloquent\Builder;

class Index extends Component
{
    use WithPagination;

    public string $q = '';
    public string $servicio = ''; // agua|electricidad|''

    protected $queryString = [
        'q' => ['except' => ''],
        'servicio' => ['except' => ''],
    ];

    public function updatingQ(): void { $this->resetPage(); }
    public function updatingServicio(): void { $this->resetPage(); }

    public function render()
    {
        $query = Contrato::query()
            ->with(['cliente','lote','contratoBase'])
            ->where('tipo', 'servicio')
            ->orderByDesc('id');

        $term = trim($this->q);
        if ($term !== '') {
            $query->where(function (Builder $qq) use ($term) {
                $qq->where('folio_contrato', 'like', "%{$term}%")
                    ->orWhereHas('cliente', function (Builder $c) use ($term) {
                        $c->where('nombres', 'like', "%{$term}%")
                          ->orWhere('apellidos', 'like', "%{$term}%");
                    })
                    ->orWhereHas('contratoBase', function (Builder $b) use ($term) {
                        $b->where('folio_contrato', 'like', "%{$term}%");
                    });
            });
        }

        if ($this->servicio !== '') {
            $query->where('servicio_tipo', $this->servicio);
        }

        return view('livewire.admin.contratos-servicios.index', [
            'items' => $query->paginate(12),
        ])->layout('layouts.app');
    }
}
