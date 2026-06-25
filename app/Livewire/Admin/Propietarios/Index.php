<?php

namespace App\Livewire\Admin\Propietarios;

use App\Models\Propietario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class Index extends Component
{
    use WithFileUploads, WithPagination;

    // Tabla
    public string $q = '';

    public string $sortBy = 'nombre';

    public string $sortDir = 'asc';

    // Modal / edición
    public bool $modal = false;

    public ?int $editId = null;

    // Campos base
    public string $nombre = '';

    public ?string $nombre_legal = null;

    public ?string $curp = null;

    public ?string $telefono = null;

    public ?string $correo = null;

    public ?string $notas = null;

    // Archivos guardados
    public ?string $ine_frente = null;

    public ?string $ine_reverso = null;

    public ?string $documentos_disk = 'private';

    // Archivos temporales Livewire
    public $ineFrenteFile = null;

    public $ineReversoFile = null;

    protected $queryString = [
        'q' => ['except' => ''],
        'sortBy' => ['except' => 'nombre'],
        'sortDir' => ['except' => 'asc'],
    ];

    public function updatingQ(): void
    {
        $this->resetPage();
    }

    public function sort(string $key): void
    {
        $allowed = ['id', 'nombre', 'nombre_legal', 'curp', 'telefono', 'correo'];

        if (! in_array($key, $allowed, true)) {
            $key = 'nombre';
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
        $this->resetValidation();
        $this->modal = true;
    }

    public function editar(int $id): void
    {
        $it = Propietario::query()->findOrFail($id);

        $this->editId = (int) $it->id;
        $this->nombre = (string) $it->nombre;
        $this->nombre_legal = $it->nombre_legal;
        $this->curp = $it->curp;
        $this->telefono = $it->telefono;
        $this->correo = $it->correo;
        $this->notas = $it->notas;

        $this->ine_frente = $it->ine_frente;
        $this->ine_reverso = $it->ine_reverso;
        $this->documentos_disk = $it->documentos_disk ?: 'private';

        $this->ineFrenteFile = null;
        $this->ineReversoFile = null;

        $this->resetValidation();
        $this->modal = true;
    }

    public function guardar(): void
    {
        $data = $this->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'nombre_legal' => ['nullable', 'string', 'max:255'],
            'curp' => ['nullable', 'string', 'max:30'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'correo' => ['nullable', 'email', 'max:255'],
            'notas' => ['nullable', 'string'],

            'ineFrenteFile' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
            'ineReversoFile' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ]);

        // Normaliza strings
        $data['nombre'] = trim($data['nombre']);

        foreach (['nombre_legal', 'curp', 'telefono', 'correo'] as $k) {
            $data[$k] = is_string($data[$k] ?? null)
                ? (trim($data[$k]) !== '' ? trim($data[$k]) : null)
                : null;
        }

        if ($data['curp']) {
            $data['curp'] = Str::upper($data['curp']);
        }

        $data['notas'] = is_string($data['notas'] ?? null)
            ? (trim($data['notas']) !== '' ? trim($data['notas']) : null)
            : null;

        unset($data['ineFrenteFile'], $data['ineReversoFile']);

        $disk = 'private';
        $data['documentos_disk'] = $disk;

        if ($this->editId) {
            $it = Propietario::query()->findOrFail($this->editId);
            $it->update($data);
        } else {
            $it = Propietario::query()->create($data);
        }

        // Guardar archivos si vienen
        $updates = [];

        if ($this->ineFrenteFile instanceof TemporaryUploadedFile) {
            $newPath = $this->storeDocumento(
                file: $this->ineFrenteFile,
                propietarioId: $it->id,
                prefix: 'ine_frente'
            );

            if ($it->ine_frente && Storage::disk($disk)->exists($it->ine_frente)) {
                Storage::disk($disk)->delete($it->ine_frente);
            }

            $updates['ine_frente'] = $newPath;
        }

        if ($this->ineReversoFile instanceof TemporaryUploadedFile) {
            $newPath = $this->storeDocumento(
                file: $this->ineReversoFile,
                propietarioId: $it->id,
                prefix: 'ine_reverso'
            );

            if ($it->ine_reverso && Storage::disk($disk)->exists($it->ine_reverso)) {
                Storage::disk($disk)->delete($it->ine_reverso);
            }

            $updates['ine_reverso'] = $newPath;
        }

        if (! empty($updates)) {
            $updates['documentos_disk'] = $disk;
            $it->update($updates);
        }

        $this->dispatch(
            'toast',
            type: 'success',
            message: $this->editId ? 'Actualizado correctamente.' : 'Creado correctamente.'
        );

        $this->modal = false;
        $this->editId = null;
        $this->resetForm();
    }

    protected function storeDocumento(TemporaryUploadedFile $file, int $propietarioId, string $prefix): string
    {
        $disk = 'private';
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $name = $prefix.'_'.now()->format('Ymd_His').'_'.Str::random(8).'.'.$ext;
        $dir = 'propietarios/'.$propietarioId.'/documentos';

        return $file->storeAs($dir, $name, $disk);
    }

    public function eliminarDocumento(string $campo): void
    {
        if (! $this->editId) {
            if ($campo === 'ine_frente') {
                $this->ineFrenteFile = null;
                $this->ine_frente = null;
            }

            if ($campo === 'ine_reverso') {
                $this->ineReversoFile = null;
                $this->ine_reverso = null;
            }

            return;
        }

        $it = Propietario::query()->findOrFail($this->editId);
        $disk = $it->documentos_disk ?: 'private';

        if (! in_array($campo, ['ine_frente', 'ine_reverso'], true)) {
            return;
        }

        $path = $it->{$campo};

        if ($path && Storage::disk($disk)->exists($path)) {
            Storage::disk($disk)->delete($path);
        }

        $it->update([$campo => null]);

        $this->{$campo} = null;

        if ($campo === 'ine_frente') {
            $this->ineFrenteFile = null;
        }

        if ($campo === 'ine_reverso') {
            $this->ineReversoFile = null;
        }

        $this->dispatch('toast', type: 'success', message: 'Documento eliminado correctamente.');
    }

    public function eliminar(int $id): void
    {
        $it = Propietario::query()
            ->withCount(['fraccionamientos', 'cuentasBancarias', 'lotesOverride'])
            ->findOrFail($id);

        if (
            ($it->fraccionamientos_count ?? 0) > 0 ||
            ($it->cuentas_bancarias_count ?? 0) > 0 ||
            ($it->lotes_override_count ?? 0) > 0
        ) {
            $this->dispatch('toast', type: 'error', message: 'No se puede eliminar: el propietario ya está en uso.');

            return;
        }

        $disk = $it->documentos_disk ?: 'private';

        foreach (['ine_frente', 'ine_reverso'] as $campo) {
            if ($it->{$campo} && Storage::disk($disk)->exists($it->{$campo})) {
                Storage::disk($disk)->delete($it->{$campo});
            }
        }

        $it->delete();

        $this->dispatch('toast', type: 'success', message: 'Eliminado correctamente.');
    }

    protected function resolveDocumentoPreviewUrl($file, ?string $path, ?string $disk = 'private'): ?string
    {
        if ($file && method_exists($file, 'temporaryUrl')) {
            try {
                return $file->temporaryUrl();
            } catch (\Throwable $e) {
                report($e);
            }
        }

        if (! $path) {
            return null;
        }

        $disk = $disk ?: 'private';

        try {
            return route('admin.private-files.show', [
                'disk' => $disk,
                'path' => encrypt($path),
            ]);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    public function getIneFrentePreviewProperty(): ?string
    {
        return $this->resolveDocumentoPreviewUrl(
            $this->ineFrenteFile,
            $this->ine_frente,
            $this->documentos_disk
        );
    }

    public function getIneReversoPreviewProperty(): ?string
    {
        return $this->resolveDocumentoPreviewUrl(
            $this->ineReversoFile,
            $this->ine_reverso,
            $this->documentos_disk
        );
    }

    public function getIneFrenteEsPdfProperty(): bool
    {
        if ($this->ineFrenteFile instanceof TemporaryUploadedFile) {
            $ext = strtolower($this->ineFrenteFile->getClientOriginalExtension() ?: '');

            return $ext === 'pdf';
        }

        return $this->ine_frente
            ? strtolower(pathinfo($this->ine_frente, PATHINFO_EXTENSION)) === 'pdf'
            : false;
    }

    public function getIneReversoEsPdfProperty(): bool
    {
        if ($this->ineReversoFile instanceof TemporaryUploadedFile) {
            $ext = strtolower($this->ineReversoFile->getClientOriginalExtension() ?: '');

            return $ext === 'pdf';
        }

        return $this->ine_reverso
            ? strtolower(pathinfo($this->ine_reverso, PATHINFO_EXTENSION)) === 'pdf'
            : false;
    }

    protected function resetForm(): void
    {
        $this->nombre = '';
        $this->nombre_legal = null;
        $this->curp = null;
        $this->telefono = null;
        $this->correo = null;
        $this->notas = null;

        $this->ine_frente = null;
        $this->ine_reverso = null;
        $this->documentos_disk = 'private';

        $this->ineFrenteFile = null;
        $this->ineReversoFile = null;
    }

    protected function applySearch(Builder $query): Builder
    {
        $term = trim($this->q);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $qq) use ($term) {
            $qq->where('nombre', 'like', "%{$term}%")
                ->orWhere('nombre_legal', 'like', "%{$term}%")
                ->orWhere('curp', 'like', "%{$term}%")
                ->orWhere('telefono', 'like', "%{$term}%")
                ->orWhere('correo', 'like', "%{$term}%");
        });
    }

    protected function applySort(Builder $query): Builder
    {
        $dir = strtolower($this->sortDir) === 'desc' ? 'desc' : 'asc';
        $key = $this->sortBy ?: 'nombre';

        $allowed = ['id', 'nombre', 'nombre_legal', 'curp', 'telefono', 'correo'];

        if (! in_array($key, $allowed, true)) {
            $key = 'nombre';
        }

        return $query->orderBy($key, $dir);
    }

    public function render()
    {
        $query = Propietario::query();
        $query = $this->applySearch($query);
        $query = $this->applySort($query);

        return view('livewire.admin.propietarios.index', [
            'items' => $query->paginate(10),
        ])->layout('layouts.app');
    }
}
