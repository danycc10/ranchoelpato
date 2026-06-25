<?php

namespace App\Livewire\Admin\Fraccionamientos;

use App\Models\Fraccionamiento;
use App\Models\Propietario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class Index extends Component
{
    use WithFileUploads;
    use WithPagination;

    // Tabla
    public string $q = '';

    public string $sortBy = 'nombre';

    public string $sortDir = 'asc';

    // Modal / edición
    public bool $modal = false;

    public ?int $editId = null;

    // Campos
    public ?int $propietario_id = null;

    public string $nombre = '';

    public ?string $ubicacion = null;

    // Logo
    public $logo = null;

    public bool $removeLogo = false;

    // ✅ Contrato base DOCX por fraccionamiento
    public $contrato_base = null;

    public bool $removeContratoBase = false;

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
        $allowed = ['id', 'propietario', 'nombre', 'ubicacion'];

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
        $this->modal = true;
    }

    public function editar(int $id): void
    {
        $it = Fraccionamiento::query()->findOrFail($id);

        $this->editId = (int) $it->id;
        $this->propietario_id = (int) $it->propietario_id;
        $this->nombre = (string) $it->nombre;
        $this->ubicacion = $it->ubicacion;

        $this->logo = null;
        $this->removeLogo = false;

        $this->contrato_base = null;
        $this->removeContratoBase = false;

        $this->resetErrorBag();
        $this->modal = true;
    }

    public function guardar(): void
    {
        $data = $this->validate([
            'propietario_id' => ['required', 'exists:propietarios,id'],
            'nombre' => ['required', 'string', 'max:255'],
            'ubicacion' => ['nullable', 'string', 'max:255'],

            // Logo
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'removeLogo' => ['boolean'],

            // ✅ contrato base
            'contrato_base' => ['nullable', 'file', 'mimes:docx', 'max:10240'], // 10 MB
            'removeContratoBase' => ['boolean'],
        ]);

        $data['nombre'] = trim($data['nombre']);
        $data['ubicacion'] = is_string($data['ubicacion'] ?? null)
            ? (trim($data['ubicacion']) !== '' ? trim($data['ubicacion']) : null)
            : null;

        unset(
            $data['logo'],
            $data['removeLogo'],
            $data['contrato_base'],
            $data['removeContratoBase']
        );

        if ($this->editId) {
            $it = Fraccionamiento::query()->findOrFail($this->editId);

            // Quitar logo
            if ($this->removeLogo && $it->logo_path) {
                Storage::disk('public')->delete($it->logo_path);
                $it->logo_path = null;
            }

            // Quitar contrato base
            if ($this->removeContratoBase && $it->contrato_base_path) {
                Storage::disk('private')->delete($it->contrato_base_path);
                $it->contrato_base_path = null;
            }

            $it->update($data);

            // Reemplazar logo
            if ($this->logo) {
                $oldLogo = $it->logo_path;
                $path = $this->storeFraccionamientoLogo($it->id, $this->logo);

                if ($oldLogo && $oldLogo !== $path) {
                    Storage::disk('public')->delete($oldLogo);
                }

                $it->update(['logo_path' => $path]);
            }

            // ✅ Reemplazar contrato base
            if ($this->contrato_base) {
                $oldContrato = $it->contrato_base_path;
                $pathContrato = $this->storeContratoBase($it->id, $this->contrato_base);

                if ($oldContrato && $oldContrato !== $pathContrato) {
                    Storage::disk('private')->delete($oldContrato);
                }

                $it->update(['contrato_base_path' => $pathContrato]);
            }

            $this->dispatch('toast', type: 'success', message: 'Actualizado correctamente.');
        } else {
            $it = Fraccionamiento::query()->create($data);

            // Logo
            if ($this->logo) {
                $path = $this->storeFraccionamientoLogo($it->id, $this->logo);
                $it->update(['logo_path' => $path]);
            }

            // ✅ Contrato base
            if ($this->contrato_base) {
                $pathContrato = $this->storeContratoBase($it->id, $this->contrato_base);
                $it->update(['contrato_base_path' => $pathContrato]);
            }

            $this->dispatch('toast', type: 'success', message: 'Creado correctamente.');
        }

        $this->modal = false;
        $this->editId = null;
        $this->resetForm();
    }

    public function eliminar(int $id): void
    {
        $it = Fraccionamiento::query()->withCount('lotes')->findOrFail($id);

        if (($it->lotes_count ?? 0) > 0) {
            $this->dispatch('toast', type: 'error', message: 'No se puede eliminar: el fraccionamiento ya tiene lotes.');

            return;
        }

        if ($it->logo_path) {
            Storage::disk('public')->delete($it->logo_path);
        }

        if ($it->contrato_base_path) {
            Storage::disk('private')->delete($it->contrato_base_path);
        }

        $it->delete();

        $this->dispatch('toast', type: 'success', message: 'Eliminado correctamente.');
    }

    protected function resetForm(): void
    {
        $this->propietario_id = null;
        $this->nombre = '';
        $this->ubicacion = null;

        $this->logo = null;
        $this->removeLogo = false;

        $this->contrato_base = null;
        $this->removeContratoBase = false;

        $this->resetErrorBag();
        $this->resetValidation();
    }

    protected function applySearch(Builder $query): Builder
    {
        $term = trim($this->q);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $qq) use ($term) {
            $qq->where('nombre', 'like', "%{$term}%")
                ->orWhere('ubicacion', 'like', "%{$term}%")
                ->orWhereHas('propietario', fn (Builder $p) => $p->where('nombre', 'like', "%{$term}%"));
        });
    }

    protected function applySort(Builder $query): Builder
    {
        $dir = strtolower($this->sortDir) === 'desc' ? 'desc' : 'asc';
        $key = $this->sortBy ?: 'nombre';

        if ($key === 'propietario') {
            return $query
                ->leftJoin('propietarios', 'propietarios.id', '=', 'fraccionamientos.propietario_id')
                ->orderBy('propietarios.nombre', $dir)
                ->select('fraccionamientos.*');
        }

        $allowed = ['id', 'nombre', 'ubicacion'];

        if (! in_array($key, $allowed, true)) {
            $key = 'nombre';
        }

        return $query->orderBy($key, $dir);
    }

    /**
     * Guarda logo en WEBP.
     * Ruta: fraccionamientos/{id}/logo.webp
     */
    protected function storeFraccionamientoLogo(int $id, $uploadedFile): string
    {
        $manager = new ImageManager(new Driver);

        $img = $manager->read($uploadedFile->getRealPath());
        $encoded = $img->toWebp(75);

        $path = "fraccionamientos/{$id}/logo.webp";

        Storage::disk('public')->put($path, (string) $encoded);

        return $path;
    }

    /**
     * ✅ Guarda contrato base en storage privado.
     * Ruta: fraccionamientos/{id}/contratos/contrato_base.docx
     */
    protected function storeContratoBase(int $id, $uploadedFile): string
    {
        $extension = strtolower($uploadedFile->getClientOriginalExtension());

        if ($extension !== 'docx') {
            throw new \RuntimeException('El archivo debe ser DOCX.');
        }

        $path = "fraccionamientos/{$id}/contratos/contrato_base.docx";

        Storage::disk('private')->put(
            $path,
            file_get_contents($uploadedFile->getRealPath())
        );

        return $path;
    }

    public function render()
    {
        $propietarios = Propietario::query()
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        $query = Fraccionamiento::query()->with('propietario');
        $query = $this->applySearch($query);
        $query = $this->applySort($query);

        return view('livewire.admin.fraccionamientos.index', [
            'items' => $query->paginate(10),
            'propietarios' => $propietarios,
        ])->layout('layouts.app');
    }
}
