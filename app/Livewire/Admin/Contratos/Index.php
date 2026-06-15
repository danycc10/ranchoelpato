<?php

namespace App\Livewire\Admin\Contratos;

use App\Exports\ContractsExport;
use App\Models\Contrato;
use App\Models\Fraccionamiento;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;


class Index extends Component
{
    use WithPagination;

    // Búsqueda + orden
    public string $q = '';
    public string $sortBy = 'ubicacion';
    public string $sortDir = 'asc';

    // Filtros
    public ?string $estatusFilter = null;
    public ?string $frecuenciaFilter = null;
    public ?int $fraccionamientoFilter = null;

    public ?string $inicioDesde = null;
    public ?string $inicioHasta = null;

    public $saldoMin = null;
    public $saldoMax = null;

    public bool $soloConSaldo = false;

    protected $queryString = [
        'q' => ['except' => ''],
        'sortBy' => ['except' => 'ubicacion'],
        'sortDir' => ['except' => 'asc'],
        'estatusFilter' => ['except' => null],
        'frecuenciaFilter' => ['except' => null],
        'fraccionamientoFilter' => ['except' => null],
        'inicioDesde' => ['except' => null],
        'inicioHasta' => ['except' => null],
        'saldoMin' => ['except' => null],
        'saldoMax' => ['except' => null],
        'soloConSaldo' => ['except' => false],
    ];

    public function updatingQ(): void { $this->resetPage(); }
    public function updatingEstatusFilter(): void { $this->resetPage(); }
    public function updatingFrecuenciaFilter(): void { $this->resetPage(); }
    public function updatingFraccionamientoFilter(): void { $this->resetPage(); }
    public function updatingInicioDesde(): void { $this->resetPage(); }
    public function updatingInicioHasta(): void { $this->resetPage(); }
    public function updatingSaldoMin(): void { $this->resetPage(); }
    public function updatingSaldoMax(): void { $this->resetPage(); }
    public function updatingSoloConSaldo(): void { $this->resetPage(); }

    public function limpiarFiltros(): void
    {
        $this->reset([
            'q',
            'estatusFilter',
            'frecuenciaFilter',
            'fraccionamientoFilter',
            'inicioDesde',
            'inicioHasta',
            'saldoMin',
            'saldoMax',
            'soloConSaldo',
        ]);

        $this->sortBy = 'ubicacion';
        $this->sortDir = 'asc';

        $this->resetPage();
    }

    public function sort(string $key): void
    {
        $allowed = [
            'folio_contrato',
            'fecha_inicio',
            'frecuencia',
            'estatus',
            'saldo_actual',
            'ubicacion',
        ];

        if (!in_array($key, $allowed, true)) {
            $key = 'ubicacion';
        }

        if ($this->sortBy === $key) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $key;
            $this->sortDir = 'asc';
        }

        $this->resetPage();
    }

    protected function applySearch(Builder $query): Builder
    {
        $term = trim($this->q);

        if ($term === '') {
            return $query;
        }

        $tokens = preg_split('/\s+/', $term, -1, PREG_SPLIT_NO_EMPTY);

        return $query->where(function (Builder $qq) use ($term, $tokens) {
            $qq->where('contratos.folio_contrato', 'like', "%{$term}%")
                ->orWhere('contratos.frecuencia', 'like', "%{$term}%")
                ->orWhere('contratos.estatus', 'like', "%{$term}%")

                ->orWhereHas('cliente', function (Builder $c) use ($term, $tokens) {
                    $c->where(function (Builder $nameQ) use ($term, $tokens) {
                        $nameQ->whereRaw("CONCAT(nombres,' ',apellidos) LIKE ?", ["%{$term}%"])
                            ->orWhereRaw("CONCAT(apellidos,' ',nombres) LIKE ?", ["%{$term}%"]);

                        if (count($tokens) > 1) {
                            $nameQ->orWhere(function (Builder $and) use ($tokens) {
                                foreach ($tokens as $t) {
                                    $and->where(function (Builder $w) use ($t) {
                                        $w->where('nombres', 'like', "%{$t}%")
                                            ->orWhere('apellidos', 'like', "%{$t}%");
                                    });
                                }
                            });
                        } else {
                            $nameQ->orWhere('nombres', 'like', "%{$term}%")
                                  ->orWhere('apellidos', 'like', "%{$term}%");
                        }
                    });
                })

                ->orWhereHas('lote', function (Builder $l) use ($term) {
                    $l->where('clave', 'like', "%{$term}%")
                        ->orWhere('manzana', 'like', "%{$term}%")
                        ->orWhere('lote', 'like', "%{$term}%");
                })

                ->orWhereHas('lote.fraccionamiento', function (Builder $f) use ($term) {
                    $f->where('nombre', 'like', "%{$term}%");
                });
        });
    }

    protected function applyFilters(Builder $query): Builder
    {
        if (filled($this->estatusFilter)) {
            $query->where('contratos.estatus', $this->estatusFilter);
        }

        if (filled($this->frecuenciaFilter)) {
            $query->where('contratos.frecuencia', $this->frecuenciaFilter);
        }

        if (filled($this->fraccionamientoFilter)) {
            $query->where('lotes.fraccionamiento_id', (int) $this->fraccionamientoFilter);
        }

        if (filled($this->inicioDesde)) {
            $query->whereDate('contratos.fecha_inicio', '>=', $this->inicioDesde);
        }

        if (filled($this->inicioHasta)) {
            $query->whereDate('contratos.fecha_inicio', '<=', $this->inicioHasta);
        }

        if ($this->saldoMin !== null && $this->saldoMin !== '') {
            $query->where('contratos.saldo_actual', '>=', (float) $this->saldoMin);
        }

        if ($this->saldoMax !== null && $this->saldoMax !== '') {
            $query->where('contratos.saldo_actual', '<=', (float) $this->saldoMax);
        }

        if ($this->soloConSaldo) {
            $query->where('contratos.saldo_actual', '>', 0);
        }

        return $query;
    }

protected function buildQuery(): Builder
{
    $query = Contrato::withTrashed()
        ->with(['cliente', 'lote.fraccionamiento'])
        ->leftJoin('lotes', 'lotes.id', '=', 'contratos.lote_id')
        ->leftJoin('fraccionamientos', 'fraccionamientos.id', '=', 'lotes.fraccionamiento_id')
        ->select('contratos.*')
        ->where('contratos.tipo', 'terreno')
        ->where(function (Builder $q) {
            $q->whereNull('contratos.es_financiamiento_instalacion')
                ->orWhere('contratos.es_financiamiento_instalacion', 0);
        });

    $query = $this->applySearch($query);
    $query = $this->applyFilters($query);

    $dir = strtolower($this->sortDir) === 'desc' ? 'desc' : 'asc';

    $allowed = [
        'folio_contrato',
        'fecha_inicio',
        'frecuencia',
        'estatus',
        'saldo_actual',
        'ubicacion',
    ];

    $sortKey = in_array($this->sortBy, $allowed, true)
        ? $this->sortBy
        : 'ubicacion';

    if ($sortKey === 'ubicacion') {
        $query->orderBy('fraccionamientos.nombre', $dir)
            ->orderBy('lotes.manzana', $dir)
            ->orderByRaw("CAST(COALESCE(lotes.lote, '0') AS UNSIGNED) {$dir}");
    } else {
        $query->orderBy("contratos.{$sortKey}", $dir);
    }

    return $query;
}

    public function exportarExcel()
    {
        $rows = $this->buildQuery()->get();

        $filename = 'contratos_' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(new ContractsExport($rows), $filename);
    }

    public function render()
    {
        $fraccionamientos = Fraccionamiento::query()
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        return view('livewire.admin.contratos.index', [
            'items' => $this->buildQuery()->paginate(10),
            'fraccionamientos' => $fraccionamientos,
        ])->layout('layouts.app');
    }
}