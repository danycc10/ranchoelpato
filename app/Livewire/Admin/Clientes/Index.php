<?php

namespace App\Livewire\Admin\Clientes;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use App\Models\Cliente;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Illuminate\Support\Facades\Route;

class Index extends Component
{
    use WithPagination, WithFileUploads;

    public string $q = '';
    public string $sortBy = 'id';
    public string $sortDir = 'desc';

    public bool $modal = false;
    public ?int $editId = null;

    /** @var array<string,mixed> */
    public array $form = [
        'nombres' => '',
        'apellidos' => '',
        'nombre_legal' => '',
        'telefono' => '',
        'correo' => '',
        'direccion' => '',
        'rfc' => '',
        'curp' => '',
        'notas' => '',
    ];

    public $ineFrenteFile = null;
    public $ineReversoFile = null;

    public ?string $ine_frente = null;
    public ?string $ine_reverso = null;
    public ?string $documentos_disk = 'private';

    protected $queryString = [
        'q' => ['except' => ''],
        'sortBy' => ['except' => 'id'],
        'sortDir' => ['except' => 'desc'],
    ];

    public function updatedQ(): void
    {
        $this->resetPage();
    }

    public function sort(string $field): void
    {
        $allowed = ['id', 'nombres', 'apellidos', 'nombre_legal', 'telefono', 'correo', 'estatus'];

        if (! in_array($field, $allowed, true)) {
            $field = 'id';
        }

        if ($this->sortBy === $field) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $field;
            $this->sortDir = 'asc';
        }

        $this->resetPage();
    }

    public function crear(): void
    {
        $this->editId = null;
        $this->form = [
            'nombres' => '',
            'apellidos' => '',
            'nombre_legal' => '',
            'telefono' => '',
            'correo' => '',
            'direccion' => '',
            'rfc' => '',
            'curp' => '',
            'notas' => '',
        ];

        $this->ineFrenteFile = null;
        $this->ineReversoFile = null;
        $this->ine_frente = null;
        $this->ine_reverso = null;
        $this->documentos_disk = 'private';

        $this->resetValidation();
        $this->modal = true;
    }

    public function editar(int $id): void
    {
        $c = Cliente::query()->findOrFail($id);

        $this->editId = $c->id;
        $this->form = [
            'nombres' => (string) $c->nombres,
            'apellidos' => (string) $c->apellidos,
            'nombre_legal' => (string) ($c->nombre_legal ?? ''),
            'telefono' => (string) ($c->telefono ?? ''),
            'correo' => (string) ($c->correo ?? ''),
            'direccion' => (string) ($c->direccion ?? ''),
            'rfc' => (string) ($c->rfc ?? ''),
            'curp' => (string) ($c->curp ?? ''),
            'notas' => (string) ($c->notas ?? ''),
        ];

        $this->ine_frente = $c->ine_frente;
        $this->ine_reverso = $c->ine_reverso;
        $this->documentos_disk = $c->documentos_disk ?: 'private';

        $this->ineFrenteFile = null;
        $this->ineReversoFile = null;

        $this->resetValidation();
        $this->modal = true;
    }

    protected function normalizePhone(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value);
        return $digits !== '' ? $digits : null;
    }

    protected function rules(): array
    {
        return [
            'form.nombres' => ['required', 'string', 'min:2', 'max:255'],
            'form.apellidos' => ['required', 'string', 'min:2', 'max:255'],
            'form.nombre_legal' => ['nullable', 'string', 'max:255'],

            'form.telefono' => ['nullable', 'string', function ($attr, $value, $fail) {
                $digits = $this->normalizePhone($value);
                if ($digits === null) {
                    return;
                }

                if (strlen($digits) === 12 && str_starts_with($digits, '52')) {
                    $digits = substr($digits, 2);
                }

                if (strlen($digits) !== 10) {
                    $fail('El teléfono debe tener 10 dígitos (MX).');
                }
            }],

            'form.correo' => ['nullable', 'string', 'email:rfc,dns', 'max:255'],
            'form.direccion' => ['nullable', 'string', 'max:255'],
            'form.rfc' => ['nullable', 'string', 'max:20'],
            'form.curp' => ['nullable', 'string', 'max:30'],
            'form.notas' => ['nullable', 'string'],

            'ineFrenteFile' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
            'ineReversoFile' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ];
    }

    public function guardar(): void
    {
        $this->validate();

        $telefono = $this->normalizePhone($this->form['telefono'] ?? null);

        if ($telefono && strlen($telefono) === 12 && str_starts_with($telefono, '52')) {
            $telefono = substr($telefono, 2);
        }

        $payload = [
            'nombres' => trim((string) $this->form['nombres']),
            'apellidos' => trim((string) $this->form['apellidos']),
            'nombre_legal' => $this->stringOrNull($this->form['nombre_legal'] ?? null),
            'telefono' => $telefono,
            'correo' => $this->form['correo'] ? strtolower(trim((string) $this->form['correo'])) : null,
            'direccion' => $this->stringOrNull($this->form['direccion'] ?? null),
            'rfc' => $this->form['rfc'] ? strtoupper(trim((string) $this->form['rfc'])) : null,
            'curp' => $this->form['curp'] ? strtoupper(trim((string) $this->form['curp'])) : null,
            'notas' => $this->stringOrNull($this->form['notas'] ?? null),
            'documentos_disk' => 'private',
        ];

        if ($this->editId) {
            $cliente = Cliente::query()->findOrFail($this->editId);
            $cliente->update($payload);
        } else {
            $payload['estatus'] = 'activo';
            $cliente = Cliente::query()->create($payload);
        }

        $updates = [];
        $disk = 'private';

        if ($this->ineFrenteFile instanceof TemporaryUploadedFile) {
            $newPath = $this->storeDocumento(
                file: $this->ineFrenteFile,
                clienteId: $cliente->id,
                prefix: 'ine_frente'
            );

            if ($cliente->ine_frente && Storage::disk($disk)->exists($cliente->ine_frente)) {
                Storage::disk($disk)->delete($cliente->ine_frente);
            }

            $updates['ine_frente'] = $newPath;
        }

        if ($this->ineReversoFile instanceof TemporaryUploadedFile) {
            $newPath = $this->storeDocumento(
                file: $this->ineReversoFile,
                clienteId: $cliente->id,
                prefix: 'ine_reverso'
            );

            if ($cliente->ine_reverso && Storage::disk($disk)->exists($cliente->ine_reverso)) {
                Storage::disk($disk)->delete($cliente->ine_reverso);
            }

            $updates['ine_reverso'] = $newPath;
        }

        if (! empty($updates)) {
            $updates['documentos_disk'] = $disk;
            $cliente->update($updates);
        }

        $this->dispatch(
            'toast',
            type: 'success',
            message: $this->editId ? 'Cliente actualizado.' : 'Cliente creado.'
        );

        $this->modal = false;
        $this->editId = null;
    }

    protected function stringOrNull(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        return $value !== '' ? $value : null;
    }

    protected function storeDocumento(TemporaryUploadedFile $file, int $clienteId, string $prefix): string
    {
        $disk = 'private';
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $name = $prefix . '_' . now()->format('Ymd_His') . '_' . Str::random(8) . '.' . $ext;
        $dir = 'clientes/' . $clienteId . '/documentos';

        return $file->storeAs($dir, $name, $disk);
    }

    public function eliminarDocumento(string $campo): void
    {
        if (! in_array($campo, ['ine_frente', 'ine_reverso'], true)) {
            return;
        }

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

        $cliente = Cliente::query()->findOrFail($this->editId);
        $disk = $cliente->documentos_disk ?: 'private';

        $path = $cliente->{$campo};

        if ($path && Storage::disk($disk)->exists($path)) {
            Storage::disk($disk)->delete($path);
        }

        $cliente->update([$campo => null]);

        $this->{$campo} = null;

        if ($campo === 'ine_frente') {
            $this->ineFrenteFile = null;
        }

        if ($campo === 'ine_reverso') {
            $this->ineReversoFile = null;
        }

        $this->dispatch('toast', type: 'success', message: 'Documento eliminado correctamente.');
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

    public function toggleEstatus(int $id): void
    {
        $c = Cliente::query()->findOrFail($id);
        $nuevo = $c->estatus === 'activo' ? 'inactivo' : 'activo';
        $c->update(['estatus' => $nuevo]);

        $this->dispatch('toast', type: 'success', message: "Estatus actualizado: {$nuevo}");
    }

    public function render()
    {
        $query = Cliente::query();

        if ($this->q !== '') {
            $q = trim($this->q);

            $query->where(function ($qq) use ($q) {
                $qq->where('nombres', 'like', "%{$q}%")
                    ->orWhere('apellidos', 'like', "%{$q}%")
                    ->orWhere('nombre_legal', 'like', "%{$q}%")
                    ->orWhere('telefono', 'like', "%{$q}%")
                    ->orWhere('correo', 'like', "%{$q}%")
                    ->orWhere('curp', 'like', "%{$q}%")
                    ->orWhere('rfc', 'like', "%{$q}%");
            });
        }

        $allowed = ['id', 'nombres', 'apellidos', 'nombre_legal', 'telefono', 'correo', 'estatus'];
        $sortBy = in_array($this->sortBy, $allowed, true) ? $this->sortBy : 'id';
        $sortDir = strtolower($this->sortDir) === 'asc' ? 'asc' : 'desc';

        $items = $query
            ->orderBy($sortBy, $sortDir)
            ->paginate(12);

        return view('livewire.admin.clientes.index', [
            'items' => $items,
        ])->layout('layouts.app');
    }
}