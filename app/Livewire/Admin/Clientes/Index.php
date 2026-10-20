<?php

namespace App\Livewire\Admin\Clientes;

use App\Models\Cliente;
use App\Models\ClienteTelefono;
use App\Models\SolicitudDatosCliente;
use App\Services\Clientes\SolicitudDatosClienteService;
use App\Services\Contratos\DatosIdentidadService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class Index extends Component
{
    use WithFileUploads, WithPagination;

    public string $q = '';

    public string $sortBy = 'id';

    public string $sortDir = 'desc';

    public bool $modal = false;

    /** Liga recién generada: se muestra una sola vez para copiarla. */
    public ?string $ligaDatos = null;

    public ?string $ligaDatosCliente = null;

    public ?int $editId = null;

    /** @var array<string,mixed> */
    public array $form = [
        'nombres' => '',
        'apellidos' => '',
        'nombre_legal' => '',
        'telefono' => '',
        'telefono_uso' => 'ambos',
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

    /** Filtro por el estado de la liga de captura: '', sin, enviada, contestada, vencida. */
    public string $ligaFilter = '';

    protected $queryString = [
        'q' => ['except' => ''],
        'ligaFilter' => ['except' => ''],
        'sortBy' => ['except' => 'id'],
        'sortDir' => ['except' => 'desc'],
    ];

    public function updatedQ(): void
    {
        $this->resetPage();
    }

    public function updatedLigaFilter(): void
    {
        $this->resetPage();
    }

    /**
     * Acota la lista por cómo va la liga de cada cliente.
     *
     * Sigue el mismo criterio que la columna: solo cuentan las ligas vivas
     * —pendientes o ya contestadas—, y de esas hay a lo más una por cliente.
     */
    protected function filtrarPorLiga($query): void
    {
        $abiertas = [SolicitudDatosCliente::PENDIENTE, SolicitudDatosCliente::CAPTURADA];

        match ($this->ligaFilter) {
            'sin' => $query->whereDoesntHave(
                'solicitudesDatos',
                fn ($q) => $q->whereIn('estatus', $abiertas)
            ),

            'contestada' => $query->whereHas(
                'solicitudesDatos',
                fn ($q) => $q->where('estatus', SolicitudDatosCliente::CAPTURADA)
            ),

            'enviada' => $query->whereHas(
                'solicitudesDatos',
                fn ($q) => $q->where('estatus', SolicitudDatosCliente::PENDIENTE)
                    ->where('expira_en', '>', now())
            ),

            'vencida' => $query->whereHas(
                'solicitudesDatos',
                fn ($q) => $q->where('estatus', SolicitudDatosCliente::PENDIENTE)
                    ->where('expira_en', '<=', now())
            ),

            default => null,
        };
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
            'telefono_uso' => 'ambos',
            'correo' => '',
            'direccion' => '',
            'rfc' => '',
            'curp' => '',
            'notas' => '',

            // Datos que declara el cliente en el contrato de cesión.
            'persona_tipo' => 'fisica',
            'nacionalidad' => DatosIdentidadService::NACIONALIDAD_DEFAULT,
            'estado_civil' => '',
            'ocupacion' => '',
            'identificacion_tipo' => 'INE',
            'identificacion_numero' => '',
        ];

        $this->telefonosExtra = [];

        $this->ineFrenteFile = null;
        $this->ineReversoFile = null;
        $this->ine_frente = null;
        $this->ine_reverso = null;
        $this->documentos_disk = 'private';

        $this->resetValidation();
        $this->modal = true;
    }

    /**
     * Los teléfonos de más del formulario.
     *
     * @var array<int, array{telefono: string, etiqueta: string}>
     */
    public array $telefonosExtra = [];

    public function agregarTelefono(): void
    {
        $this->telefonosExtra[] = ['telefono' => '', 'uso' => 'ambos', 'etiqueta' => ''];
    }

    public function quitarTelefono(int $indice): void
    {
        unset($this->telefonosExtra[$indice]);

        $this->telefonosExtra = array_values($this->telefonosExtra);
    }

    /**
     * Sube un adicional a principal y baja el que estaba.
     *
     * Es lo que se hace cuando el cliente cambia de número: el de siempre
     * pasa a ser el de recados en vez de perderse.
     */
    public function hacerPrincipal(int $indice): void
    {
        $nuevo = $this->telefonosExtra[$indice] ?? null;

        if (! $nuevo || trim((string) $nuevo['telefono']) === '') {
            return;
        }

        $anterior = trim((string) ($this->form['telefono'] ?? ''));

        $usoAnterior = $this->form['telefono_uso'] ?? 'ambos';

        $this->form['telefono'] = $nuevo['telefono'];
        $this->form['telefono_uso'] = $nuevo['uso'] ?? 'ambos';

        $this->telefonosExtra[$indice]['telefono'] = $anterior;
        $this->telefonosExtra[$indice]['uso'] = $usoAnterior;

        if ($anterior === '') {
            $this->quitarTelefono($indice);
        }
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
            'telefono_uso' => (string) ($c->telefono_uso ?: 'ambos'),
            'correo' => (string) ($c->correo ?? ''),
            'direccion' => (string) ($c->direccion ?? ''),
            'rfc' => (string) ($c->rfc ?? ''),
            'curp' => (string) ($c->curp ?? ''),
            'notas' => (string) ($c->notas ?? ''),

            'persona_tipo' => (string) ($c->persona_tipo ?? 'fisica'),
            'nacionalidad' => (string) ($c->nacionalidad ?? DatosIdentidadService::NACIONALIDAD_DEFAULT),
            'estado_civil' => (string) ($c->estado_civil ?? ''),
            'ocupacion' => (string) ($c->ocupacion ?? ''),
            'identificacion_tipo' => (string) ($c->identificacion_tipo ?? 'INE'),
            'identificacion_numero' => (string) ($c->identificacion_numero ?? ''),
        ];

        $this->telefonosExtra = $c->telefonosAdicionales()
            ->orderBy('id')
            ->get()
            ->map(fn (ClienteTelefono $t) => [
                'telefono' => (string) $t->telefono,
                'uso' => (string) ($t->uso ?: 'ambos'),
                'etiqueta' => (string) ($t->etiqueta ?? ''),
            ])
            ->all();

        $this->ine_frente = $c->ine_frente;
        $this->ine_reverso = $c->ine_reverso;
        $this->documentos_disk = $c->documentos_disk ?: 'private';

        $this->ineFrenteFile = null;
        $this->ineReversoFile = null;

        $this->resetValidation();
        $this->modal = true;
    }

    /**
     * Deja en la base exactamente los adicionales del formulario.
     *
     * Se descarta el que sea igual al principal: tenerlo en los dos lados es
     * lo que haria que un mismo numero saliera repetido en la ficha.
     */
    protected function guardarTelefonosExtra(Cliente $cliente, ?string $principal): void
    {
        $limpios = collect($this->telefonosExtra)
            ->map(fn (array $fila) => [
                'telefono' => $this->normalizePhone($fila['telefono'] ?? null),
                'uso' => in_array($fila['uso'] ?? null, ['ambos', 'llamadas', 'whatsapp'], true)
                    ? $fila['uso']
                    : 'ambos',
                'etiqueta' => $this->stringOrNull($fila['etiqueta'] ?? null),
            ])
            ->filter(fn (array $fila) => $fila['telefono'] !== null && $fila['telefono'] !== $principal)
            ->unique('telefono')
            ->values();

        $cliente->telefonosAdicionales()->delete();

        if ($limpios->isEmpty()) {
            return;
        }

        $cliente->telefonosAdicionales()->createMany($limpios->all());
    }

    protected function normalizePhone(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value);

        if ($digits === '') {
            return null;
        }

        if (strlen($digits) === 13 && str_starts_with($digits, '521')) {
            return substr($digits, 3);
        }

        if (strlen($digits) === 12 && str_starts_with($digits, '52')) {
            return substr($digits, 2);
        }

        return $digits;
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

                $esMexico = strlen($digits) === 10;
                $esEstadosUnidos = strlen($digits) === 11 && str_starts_with($digits, '1');

                if (! $esMexico && ! $esEstadosUnidos) {
                    $fail('El teléfono debe tener 10 dígitos (México) o 1 + 10 dígitos (EE. UU.).');
                }
            }],

            'telefonosExtra.*.telefono' => ['nullable', 'string', function ($attr, $value, $fail) {
                $digits = $this->normalizePhone($value);

                if ($digits === null) {
                    return;
                }

                $esMexico = strlen($digits) === 10;
                $esEstadosUnidos = strlen($digits) === 11 && str_starts_with($digits, '1');

                if (! $esMexico && ! $esEstadosUnidos) {
                    $fail('El teléfono debe tener 10 dígitos (México) o 1 + 10 dígitos (EE. UU.).');
                }
            }],
            'telefonosExtra.*.uso' => ['required', 'in:ambos,llamadas,whatsapp'],
            'telefonosExtra.*.etiqueta' => ['nullable', 'string', 'max:60'],

            'form.telefono_uso' => ['required', 'in:ambos,llamadas,whatsapp'],
            // `filter` rechaza acentos y ñ antes de la arroba: el correo
            // electronico no los admite y el envio truena hasta el final.
            'form.correo' => ['nullable', 'string', 'email:filter,dns', 'max:255'],
            'form.direccion' => ['nullable', 'string', 'max:255'],
            'form.rfc' => ['nullable', 'string', 'max:20'],
            'form.curp' => ['nullable', 'string', 'max:30'],
            'form.notas' => ['nullable', 'string'],

            'ineFrenteFile' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
            'ineReversoFile' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ] + DatosIdentidadService::reglas('form.');
    }

    public function guardar(): void
    {
        $this->validate();

        $telefono = $this->normalizePhone($this->form['telefono'] ?? null);

        $payload = [
            'nombres' => trim((string) $this->form['nombres']),
            'apellidos' => trim((string) $this->form['apellidos']),
            'nombre_legal' => $this->stringOrNull($this->form['nombre_legal'] ?? null),
            'telefono' => $telefono,
            'telefono_uso' => $this->form['telefono_uso'] ?? 'ambos',
            'correo' => $this->form['correo'] ? strtolower(trim((string) $this->form['correo'])) : null,
            'direccion' => $this->stringOrNull($this->form['direccion'] ?? null),
            'rfc' => $this->form['rfc'] ? strtoupper(trim((string) $this->form['rfc'])) : null,
            'curp' => $this->form['curp'] ? strtoupper(trim((string) $this->form['curp'])) : null,
            'notas' => $this->stringOrNull($this->form['notas'] ?? null),
            'documentos_disk' => 'private',
        ];

        foreach (DatosIdentidadService::CAMPOS as $campo) {
            $payload[$campo] = $this->stringOrNull($this->form[$campo] ?? null);
        }

        if ($this->editId) {
            $cliente = Cliente::query()->findOrFail($this->editId);
            $cliente->update($payload);
        } else {
            $payload['estatus'] = 'activo';
            $cliente = Cliente::query()->create($payload);
        }

        $this->guardarTelefonosExtra($cliente, $telefono);

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
        $name = $prefix.'_'.now()->format('Ymd_His').'_'.Str::random(8).'.'.$ext;
        $dir = 'clientes/'.$clienteId.'/documentos';

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

    /**
     * Genera la liga con la que el cliente captura sus propios datos.
     *
     * Se muestra una sola vez: en la base queda solo su hash.
     */
    public function generarLigaDatos(int $id, SolicitudDatosClienteService $solicitudes): void
    {
        $cliente = Cliente::query()->findOrFail($id);

        $resultado = $solicitudes->crear($cliente, auth()->user());

        $this->ligaDatos = route('publico.datos-cliente', $resultado['token']);
        $this->ligaDatosCliente = $cliente->nombre_completo;

        activity()
            ->causedBy(auth()->user())
            ->performedOn($cliente)
            ->withProperties(['expira_en' => $resultado['solicitud']->expira_en->toDateTimeString()])
            ->log('Generó liga para que el cliente capture sus datos');

        $this->dispatch(
            'toast',
            type: 'success',
            message: 'Liga generada. Cópiala y mándasela al cliente.'
        );
    }

    public function cerrarLigaDatos(): void
    {
        $this->ligaDatos = null;
        $this->ligaDatosCliente = null;
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
        // Se precargan para pintar el "+2" de la tabla sin una consulta por fila.
        $query = Cliente::query()->withCount('telefonosAdicionales');

        if ($this->q !== '') {
            $q = trim($this->q);

            $query->where(function ($qq) use ($q) {
                $qq->where('nombres', 'like', "%{$q}%")
                    ->orWhere('apellidos', 'like', "%{$q}%")
                    ->orWhere('nombre_legal', 'like', "%{$q}%")
                    ->orWhere('telefono', 'like', "%{$q}%")
                    // Buscar por el segundo número tiene que encontrarlo igual.
                    ->orWhereHas('telefonosAdicionales', fn ($t) => $t->where('telefono', 'like', "%{$q}%"))
                    ->orWhere('correo', 'like', "%{$q}%")
                    ->orWhere('curp', 'like', "%{$q}%")
                    ->orWhere('rfc', 'like', "%{$q}%");
            });
        }

        if ($this->ligaFilter !== '') {
            $this->filtrarPorLiga($query);
        }

        $allowed = ['id', 'nombres', 'apellidos', 'nombre_legal', 'telefono', 'correo', 'estatus'];
        $sortBy = in_array($this->sortBy, $allowed, true) ? $this->sortBy : 'id';
        $sortDir = strtolower($this->sortDir) === 'asc' ? 'asc' : 'desc';

        $items = $query
            ->orderBy($sortBy, $sortDir)
            ->paginate(12);

        // Las ligas vivas de esta página, en una sola consulta.
        $ligas = SolicitudDatosCliente::query()
            ->whereIn('cliente_id', $items->pluck('id'))
            ->whereIn('estatus', [SolicitudDatosCliente::PENDIENTE, SolicitudDatosCliente::CAPTURADA])
            ->orderBy('id')
            ->get()
            ->keyBy('cliente_id');

        $servicio = app(SolicitudDatosClienteService::class);

        $progresos = $items->mapWithKeys(
            fn ($cliente) => [$cliente->id => $servicio->progreso($cliente)]
        );

        return view('livewire.admin.clientes.index', [
            'items' => $items,
            'ligas' => $ligas,
            'progresos' => $progresos,
        ])->layout('layouts.app');
    }
}
