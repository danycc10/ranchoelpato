<?php

namespace App\Livewire\Admin\Crud;

use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

abstract class CrudIndex extends Component
{
    use WithPagination;

    public string $q = '';

    public bool $modal = false;
    public ?int $editId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    // Sorting
    public string $sortBy = '';
    public string $sortDir = 'asc';

    protected $queryString = [
        'q' => ['except' => ''],
        'sortBy' => ['except' => ''],
        'sortDir' => ['except' => 'asc'],
    ];

    abstract protected function modelClass(): string;
    abstract protected function title(): string;
    abstract protected function fields(): array;
    abstract protected function columns(): array;

    protected function baseQuery(): Builder
    {
        $model = $this->modelClass();
        return $model::query();
    }

    /* =========================================================
       Hooks opcionales (no rompen nada)
       ✅ IMPORTANTE: onSelectChanged ahora recibe (key, value)
    ========================================================= */

    public function onSelectChanged(string $key, $value = null): void
    {
        // default: nada (los módulos pueden sobreescribir)
        // Tip: si quieres comportamiento general:
        // $this->form[$key] = $value === '' ? null : $value;
    }

    public function onToggleChanged(string $key): void
    {
        // opcional
    }

    public function onNumberBlur(string $key): void
    {
        // opcional
    }

    public function onDateChanged(string $key): void
    {
        // opcional
    }

    protected function defaultSort(): string
    {
        return 'id';
    }

    protected function defaultSortDirection(): string
    {
        return 'asc';
    }

    public function mount(): void
    {
        $this->resetForm();

        if ($this->sortBy === '') {
            $this->sortBy = $this->defaultSort();
            $this->sortDir = $this->defaultSortDirection();
        }
    }

    public function updatingQ(): void
    {
        $this->resetPage();
    }

    public function sort(string $key): void
    {
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
        $this->modal = true;

        // ✅ para que los selects (wire:ignore) se sincronicen con el form
        $this->dispatch('sync-selects');
    }

    public function editar(int $id): void
    {
        $modelClass = $this->modelClass();
        /** @var Model $item */
        $item = $modelClass::findOrFail($id);

        $this->editId = (int) $item->getKey();

        foreach (array_keys($this->fields()) as $key) {
            $this->form[$key] = $item->getAttribute($key);
        }

        $this->modal = true;

        // ✅ para que los selects (wire:ignore) se sincronicen con el form
        $this->dispatch('sync-selects');
    }

    /**
     * ✅ Normaliza el form:
     * - convierte '' a null (clave para selects "Ninguno")
     * - trim en strings
     */
    protected function normalizeForm(array $data): array
    {
        foreach ($data as $k => $v) {
            if (is_string($v)) {
                $v = trim($v);

                if ($v === '') {
                    $v = null;
                }
            }
            $data[$k] = $v;
        }

        return $data;
    }

    protected function beforeSave(array $data): array
    {
        return $data;
    }

    protected function afterSave(Model $record, bool $isNew): void
    {
        // default: nada
    }

    public function updatedForm($value, $name): void
    {
        // default: nada
    }

    public function guardar(): void
    {
        $validated = $this->validate($this->rules());
        $data = $validated['form'];

        $data = $this->normalizeForm($data);
        $data = $this->beforeSave($data);

        $modelClass = $this->modelClass();

        if ($this->editId) {
            /** @var Model $record */
            $record = $modelClass::findOrFail($this->editId);

            $record->fill($data);
            $record->save();

            $this->afterSave($record, false);

            $this->dispatch('toast', type: 'success', message: 'Actualizado correctamente.');
        } else {
            /** @var Model $record */
            $record = $modelClass::create($data);

            $this->afterSave($record, true);

            $this->dispatch('toast', type: 'success', message: 'Creado correctamente.');
        }

        $this->modal = false;
        $this->editId = null;
        $this->resetForm();
    }

    public function eliminar(int $id): void
    {
        $modelClass = $this->modelClass();
        $modelClass::whereKey($id)->delete();

        $this->dispatch('toast', type: 'success', message: 'Eliminado correctamente.');
    }

    protected function rules(): array
    {
        $rules = [];
        foreach ($this->fields() as $key => $cfg) {
            $rules["form.$key"] = $cfg['rules'] ?? ['nullable'];
        }
        return $rules;
    }

    protected function resetForm(): void
    {
        $this->form = [];
        foreach ($this->fields() as $key => $cfg) {
            $this->form[$key] = $cfg['default'] ?? null;
        }
    }

    public function getOptions(string $field): array
    {
        $cfg = $this->fields()[$field] ?? [];
        $opt = $cfg['options'] ?? null;

        if (is_callable($opt)) return $opt();
        if (is_array($opt)) return $opt;

        return [];
    }

    protected function applySearch(Builder $query): Builder
    {
        $term = trim($this->q);
        if ($term === '') return $query;

        $columns = $this->columns();

        return $query->where(function (Builder $qq) use ($columns, $term) {
            foreach ($columns as $col) {
                if (($col['searchable'] ?? false) !== true) continue;

                if (isset($col['search']) && is_callable($col['search'])) {
                    ($col['search'])($qq, $term);
                    continue;
                }

                $key = $col['key'] ?? '';
                if ($key !== '' && !str_contains($key, '.')) {
                    $qq->orWhere($key, 'like', "%{$term}%");
                }
            }
        });
    }

    protected function applySort(Builder $query): Builder
    {
        $columns = collect($this->columns())->keyBy('key');

        $key = $this->sortBy ?: $this->defaultSort();
        $dir = strtolower($this->sortDir) === 'desc' ? 'desc' : 'asc';

        $col = $columns->get($key);

        if (is_array($col) && isset($col['sort']) && is_callable($col['sort'])) {
            return ($col['sort'])($query, $dir) ?? $query;
        }

        if ($key === '' || str_contains($key, '.')) {
            $key = $this->defaultSort();
        }

        return $query->orderBy($key, $dir);
    }

    public function render()
    {
        $fields = $this->fields();
        $columns = $this->columns();

        $query = $this->baseQuery();
        $query = $this->applySearch($query);
        $query = $this->applySort($query);

        $items = $query->paginate(10);

        return view('livewire.admin.crud.index', [
            'title' => $this->title(),
            'items' => $items,
            'fields' => $fields,
            'columns' => $columns,
            'sortBy' => $this->sortBy,
            'sortDir' => $this->sortDir,
        ])->layout('layouts.app');
    }
}
