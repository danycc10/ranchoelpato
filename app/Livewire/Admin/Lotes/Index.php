<?php

namespace App\Livewire\Admin\Lotes;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Lote;
use App\Models\Fraccionamiento;
use App\Models\Propietario;
use App\Exports\LotesExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class Index extends Component
{
    use WithPagination;

    // Tabla
    public string $q = '';
    public string $sortBy = 'lote';
    public string $sortDir = 'asc';

    // Filtros
    public ?int $fraccionamientoFilter = null;
    public ?int $propietarioFilter = null;
    public ?string $estatusFilter = null;
    public ?string $manzanaFilter = null;
    public ?string $loteFilter = null;

    public ?float $areaMin = null;
    public ?float $areaMax = null;
    public ?float $precioMin = null;
    public ?float $precioMax = null;

    // Modal / edición
    public bool $modal = false;
    public ?int $editId = null;

    // Campos base
    public ?int $fraccionamiento_id = null;
    public ?string $manzana = null;
    public string $lote = '';
    public string $clave = '';
    public ?float $area_m2 = null;
    public ?float $precio_lista = null;
    public string $estatus = 'disponible';
    public ?string $notas = null;

    // Nuevos campos
    public ?string $medida_norte = null;
    public ?string $medida_sur = null;
    public ?string $medida_este = null;
    public ?string $medida_oeste = null;

    public ?string $colindancia_norte = null;
    public ?string $colindancia_sur = null;
    public ?string $colindancia_este = null;
    public ?string $colindancia_oeste = null;

    // Control de autogeneración de clave
    public bool $claveManual = false;

    protected $queryString = [
        'q' => ['except' => ''],
        'sortBy' => ['except' => 'lote'],
        'sortDir' => ['except' => 'asc'],

        'fraccionamientoFilter' => ['except' => null],
        'propietarioFilter' => ['except' => null],
        'estatusFilter' => ['except' => null],
        'manzanaFilter' => ['except' => null],
        'loteFilter' => ['except' => null],
        'areaMin' => ['except' => null],
        'areaMax' => ['except' => null],
        'precioMin' => ['except' => null],
        'precioMax' => ['except' => null],
    ];

    public function updatingQ(): void { $this->resetPage(); }
    public function updatingFraccionamientoFilter(): void { $this->resetPage(); }
    public function updatingPropietarioFilter(): void { $this->resetPage(); }
    public function updatingEstatusFilter(): void { $this->resetPage(); }
    public function updatingManzanaFilter(): void { $this->resetPage(); }
    public function updatingLoteFilter(): void { $this->resetPage(); }
    public function updatingAreaMin(): void { $this->resetPage(); }
    public function updatingAreaMax(): void { $this->resetPage(); }
    public function updatingPrecioMin(): void { $this->resetPage(); }
    public function updatingPrecioMax(): void { $this->resetPage(); }

    public function updatedManzana($value): void
    {
        $this->autogenerarClave();
    }

    public function updatedLote($value): void
    {
        $this->autogenerarClave();
    }

    public function updatedClave($value): void
    {
        $this->claveManual = true;
    }

    protected function autogenerarClave(): void
    {
        if ($this->claveManual) {
            return;
        }

        $m = $this->normalizarParte($this->manzana, 'M');
        $l = $this->normalizarParte($this->lote, 'L');

        if ($m === null || $l === null) {
            $this->clave = '';
            return;
        }

        $this->clave = "{$m} - {$l}";
    }

    protected function normalizarParte($raw, string $prefix): ?string
    {
        $raw = is_string($raw) ? trim($raw) : '';
        if ($raw === '') {
            return null;
        }

        $raw = preg_replace('/^[mMlL]\s*/', '', $raw);
        $raw = preg_replace('/\s+/', '', $raw);

        return $prefix . $raw;
    }

    public function limpiarFiltros(): void
    {
        $this->reset([
            'q',
            'fraccionamientoFilter',
            'propietarioFilter',
            'estatusFilter',
            'manzanaFilter',
            'loteFilter',
            'areaMin',
            'areaMax',
            'precioMin',
            'precioMax',
        ]);

        $this->sortBy = 'lote';
        $this->sortDir = 'asc';

        $this->resetPage();
    }

    public function sort(string $key): void
    {
        $allowed = ['fraccionamiento', 'propietario', 'manzana', 'lote', 'clave', 'estatus', 'area_m2', 'precio_lista', 'id'];

        if (! in_array($key, $allowed, true)) {
            $key = 'lote';
        }

        if ($this->sortBy === $key) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $key;
            $this->sortDir = 'asc';
        }

        $this->resetPage();
    }

    public function crear(): void
    {
        $this->editId = null;
        $this->resetForm();
        $this->claveManual = false;
        $this->modal = true;
    }

    public function editar(int $id): void
    {
        $it = Lote::query()->findOrFail($id);

        $this->editId = (int) $it->id;
        $this->fraccionamiento_id = (int) $it->fraccionamiento_id;
        $this->manzana = $it->manzana;
        $this->lote = (string) $it->lote;
        $this->clave = (string) $it->clave;
        $this->area_m2 = $it->area_m2 !== null ? (float) $it->area_m2 : null;
        $this->precio_lista = $it->precio_lista !== null ? (float) $it->precio_lista : null;
        $this->estatus = (string) $it->estatus;
        $this->notas = $it->notas;

        $this->medida_norte = $it->medida_norte;
        $this->medida_sur = $it->medida_sur;
        $this->medida_este = $it->medida_este;
        $this->medida_oeste = $it->medida_oeste;

        $this->colindancia_norte = $it->colindancia_norte;
        $this->colindancia_sur = $it->colindancia_sur;
        $this->colindancia_este = $it->colindancia_este;
        $this->colindancia_oeste = $it->colindancia_oeste;

        $this->claveManual = true;
        $this->modal = true;
    }

    public function guardar(): void
    {
        if (! $this->claveManual) {
            $this->autogenerarClave();
        }

        $rules = [
            'fraccionamiento_id' => ['required', 'exists:fraccionamientos,id'],
            'manzana' => ['nullable', 'string', 'max:255'],
            'lote' => ['required', 'string', 'max:255'],
            'clave' => [
                'required',
                'string',
                'max:255',
                Rule::unique('lotes', 'clave')->ignore($this->editId),
            ],
            'area_m2' => ['nullable', 'numeric', 'min:0'],
            'precio_lista' => ['nullable', 'numeric', 'min:0'],
            'estatus' => ['required', 'in:disponible,apartado,vendido,donacion,cancelado'],
            'notas' => ['nullable', 'string'],

            'medida_norte' => ['nullable', 'string', 'max:255'],
            'medida_sur' => ['nullable', 'string', 'max:255'],
            'medida_este' => ['nullable', 'string', 'max:255'],
            'medida_oeste' => ['nullable', 'string', 'max:255'],

            'colindancia_norte' => ['nullable', 'string', 'max:255'],
            'colindancia_sur' => ['nullable', 'string', 'max:255'],
            'colindancia_este' => ['nullable', 'string', 'max:255'],
            'colindancia_oeste' => ['nullable', 'string', 'max:255'],
        ];

        $data = $this->validate($rules);

        $data['manzana'] = is_string($data['manzana'] ?? null)
            ? (trim($data['manzana']) !== '' ? trim($data['manzana']) : null)
            : null;

        $data['lote'] = trim((string) $data['lote']);
        $data['clave'] = trim((string) $data['clave']);

        foreach ([
            'notas',
            'medida_norte',
            'medida_sur',
            'medida_este',
            'medida_oeste',
            'colindancia_norte',
            'colindancia_sur',
            'colindancia_este',
            'colindancia_oeste',
        ] as $campo) {
            $data[$campo] = is_string($data[$campo] ?? null)
                ? (trim($data[$campo]) !== '' ? trim($data[$campo]) : null)
                : null;
        }

        if ($this->editId) {
            $it = Lote::query()->findOrFail($this->editId);
            $it->update($data);
            $this->dispatch('toast', type: 'success', message: 'Actualizado correctamente.');
        } else {
            Lote::query()->create($data);
            $this->dispatch('toast', type: 'success', message: 'Creado correctamente.');
        }

        $this->modal = false;
        $this->editId = null;
        $this->resetForm();
    }

    public function eliminar(int $id): void
    {
        $it = Lote::query()
            ->withCount(['contratos', 'recibos'])
            ->findOrFail($id);

        if (($it->contratos_count ?? 0) > 0 || ($it->recibos_count ?? 0) > 0) {
            $this->dispatch('toast', type: 'error', message: 'No se puede eliminar: el lote ya tiene contratos o recibos.');
            return;
        }

        $it->delete();
        $this->dispatch('toast', type: 'success', message: 'Eliminado correctamente.');
    }

    public function exportarExcel()
    {
        $rows = $this->buildQuery()->get();
        $filename = 'lotes_' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(new LotesExport($rows), $filename);
    }

    protected function resetForm(): void
    {
        $this->fraccionamiento_id = null;
        $this->manzana = null;
        $this->lote = '';
        $this->clave = '';
        $this->area_m2 = null;
        $this->precio_lista = null;
        $this->estatus = 'disponible';
        $this->notas = null;

        $this->medida_norte = null;
        $this->medida_sur = null;
        $this->medida_este = null;
        $this->medida_oeste = null;

        $this->colindancia_norte = null;
        $this->colindancia_sur = null;
        $this->colindancia_este = null;
        $this->colindancia_oeste = null;

        $this->claveManual = false;

        $this->resetErrorBag();
    }

    protected function applyFilters(Builder $query): Builder
    {
        if ($this->fraccionamientoFilter) {
            $query->where('fraccionamiento_id', (int) $this->fraccionamientoFilter);
        }

        if ($this->propietarioFilter) {
            $query->whereHas('fraccionamiento', fn (Builder $f) =>
                $f->where('propietario_id', (int) $this->propietarioFilter)
            );
        }

        if ($this->estatusFilter) {
            $query->where('estatus', $this->estatusFilter);
        }

        if (is_string($this->manzanaFilter) && trim($this->manzanaFilter) !== '') {
            $query->where('manzana', 'like', '%' . trim($this->manzanaFilter) . '%');
        }

        if (is_string($this->loteFilter) && trim($this->loteFilter) !== '') {
            $query->where('lote', 'like', '%' . trim($this->loteFilter) . '%');
        }

        if ($this->areaMin !== null && $this->areaMin !== '') {
            $query->where('area_m2', '>=', (float) $this->areaMin);
        }

        if ($this->areaMax !== null && $this->areaMax !== '') {
            $query->where('area_m2', '<=', (float) $this->areaMax);
        }

        if ($this->precioMin !== null && $this->precioMin !== '') {
            $query->where('precio_lista', '>=', (float) $this->precioMin);
        }

        if ($this->precioMax !== null && $this->precioMax !== '') {
            $query->where('precio_lista', '<=', (float) $this->precioMax);
        }

        $term = trim($this->q);
        if ($term !== '') {
            $query->where(function (Builder $qq) use ($term) {
                $qq->where('manzana', 'like', "%{$term}%")
                    ->orWhere('lote', 'like', "%{$term}%")
                    ->orWhere('clave', 'like', "%{$term}%")
                    ->orWhere('estatus', 'like', "%{$term}%")
                    ->orWhere('medida_norte', 'like', "%{$term}%")
                    ->orWhere('medida_sur', 'like', "%{$term}%")
                    ->orWhere('medida_este', 'like', "%{$term}%")
                    ->orWhere('medida_oeste', 'like', "%{$term}%")
                    ->orWhere('colindancia_norte', 'like', "%{$term}%")
                    ->orWhere('colindancia_sur', 'like', "%{$term}%")
                    ->orWhere('colindancia_este', 'like', "%{$term}%")
                    ->orWhere('colindancia_oeste', 'like', "%{$term}%")
                    ->orWhereHas('fraccionamiento', fn (Builder $f) => $f->where('nombre', 'like', "%{$term}%"))
                    ->orWhereHas('fraccionamiento.propietario', fn (Builder $p) => $p->where('nombre', 'like', "%{$term}%"));
            });
        }

        return $query;
    }

    protected function applySort(Builder $query): Builder
    {
        $dir = strtolower($this->sortDir) === 'desc' ? 'desc' : 'asc';
        $key = $this->sortBy ?: 'lote';

        $query->leftJoin('fraccionamientos', 'fraccionamientos.id', '=', 'lotes.fraccionamiento_id')
            ->select('lotes.*');

        if ($key === 'propietario') {
            return $query
                ->leftJoin('propietarios', 'propietarios.id', '=', 'fraccionamientos.propietario_id')
                ->orderBy('propietarios.nombre', $dir)
                ->orderBy('fraccionamientos.nombre', $dir)
                ->orderBy('lotes.manzana', $dir)
                ->orderByRaw("CAST(COALESCE(lotes.lote, '0') AS UNSIGNED) {$dir}")
                ->orderBy('lotes.lote', $dir);
        }

        if ($key === 'fraccionamiento') {
            return $query
                ->orderBy('fraccionamientos.nombre', $dir)
                ->orderBy('lotes.manzana', $dir)
                ->orderByRaw("CAST(COALESCE(lotes.lote, '0') AS UNSIGNED) {$dir}")
                ->orderBy('lotes.lote', $dir);
        }

        $allowed = ['id', 'manzana', 'lote', 'clave', 'estatus', 'area_m2', 'precio_lista'];
        if (! in_array($key, $allowed, true)) {
            $key = 'lote';
        }

        if ($key === 'lote') {
            return $query
                ->orderBy('fraccionamientos.nombre', 'asc')
                ->orderBy('lotes.manzana', $dir)
                ->orderByRaw("CAST(COALESCE(lotes.lote, '0') AS UNSIGNED) {$dir}")
                ->orderBy('lotes.lote', $dir);
        }

        if ($key === 'manzana') {
            return $query
                ->orderBy('fraccionamientos.nombre', 'asc')
                ->orderBy('lotes.manzana', $dir)
                ->orderByRaw("CAST(COALESCE(lotes.lote, '0') AS UNSIGNED) asc")
                ->orderBy('lotes.lote', 'asc');
        }

        return $query
            ->orderBy('fraccionamientos.nombre', 'asc')
            ->orderBy("lotes.{$key}", $dir)
            ->orderBy('lotes.manzana', 'asc')
            ->orderByRaw("CAST(COALESCE(lotes.lote, '0') AS UNSIGNED) asc")
            ->orderBy('lotes.lote', 'asc');
    }

    protected function buildQuery(): Builder
    {
        $query = Lote::query()->with(['fraccionamiento.propietario']);

        $query = $this->applyFilters($query);
        $query = $this->applySort($query);

        return $query;
    }

    public function render()
    {
        $fraccionamientos = Fraccionamiento::query()
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        $propietarios = Propietario::query()
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        return view('livewire.admin.lotes.index', [
            'items' => $this->buildQuery()->paginate(10),
            'fraccionamientos' => $fraccionamientos,
            'propietarios' => $propietarios,
        ])->layout('layouts.app');
    }
}