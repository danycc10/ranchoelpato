<?php

namespace App\Livewire\Admin\Contratos;

use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Fraccionamiento;
use App\Models\Lote;
use App\Services\Contratos\ContratoFolioService;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Alta de contratos que ya se terminaron antes del sistema.
 *
 * Solo sirven para resguardar sus documentos digitales (constancia de
 * terminación de pago, contrato escaneado), así que se crean liquidados, en
 * ceros y sin cuotas: no aparecen en ingresos, esperado ni cobranza porque
 * esos reportes se arman desde cuotas y recibos.
 */
class CrearHistorico extends Component
{
    public string $cliente_q = '';

    public array $clientes_suggest = [];

    public ?int $cliente_id = null;

    public ?int $fraccionamiento_id = null;

    public ?string $manzana = null;

    public array $manzanasDisponibles = [];

    public array $lotesDisponibles = [];

    public ?int $lote_id = null;

    public ?string $fecha_inicio = null;

    public string $fecha_terminacion = '';

    public ?string $comprador_nombre_legal = null;

    public ?string $comprador_curp = null;

    public ?string $vendedor_nombre_legal = null;

    public ?string $vendedor_curp = null;

    public function mount(): void
    {
        $this->fecha_terminacion = now()->toDateString();
    }

    protected function rules(): array
    {
        return [
            'cliente_id' => ['required', 'exists:clientes,id'],
            'lote_id' => ['required', 'exists:lotes,id'],
            'fecha_inicio' => ['nullable', 'date', 'before_or_equal:fecha_terminacion'],
            'fecha_terminacion' => ['required', 'date'],
            'comprador_nombre_legal' => ['nullable', 'string', 'max:255'],
            'comprador_curp' => ['nullable', 'string', 'max:30'],
            'vendedor_nombre_legal' => ['nullable', 'string', 'max:255'],
            'vendedor_curp' => ['nullable', 'string', 'max:30'],
        ];
    }

    protected function messages(): array
    {
        return [
            'cliente_id.required' => 'Selecciona al cliente.',
            'lote_id.required' => 'Selecciona el lote del contrato.',
            'fecha_inicio.before_or_equal' => 'La fecha de inicio no puede ser posterior a la de terminación.',
            'fecha_terminacion.required' => 'Captura la fecha en que terminó de pagarse.',
        ];
    }

    public function updatedClienteQ(): void
    {
        $q = trim($this->cliente_q);

        if (mb_strlen($q) < 2) {
            $this->cliente_id = null;
            $this->clientes_suggest = [];

            return;
        }

        $this->clientes_suggest = Cliente::query()
            ->where(function ($qq) use ($q) {
                $qq->where('nombres', 'like', "%{$q}%")
                    ->orWhere('apellidos', 'like', "%{$q}%")
                    ->orWhere('telefono', 'like', "%{$q}%")
                    ->orWhere('correo', 'like', "%{$q}%");
            })
            ->orderBy('nombres')
            ->orderBy('apellidos')
            ->limit(8)
            ->get()
            ->map(fn ($c) => [
                'id' => (int) $c->id,
                'label' => $c->nombre_completo.($c->telefono ? " · {$c->telefono}" : ''),
            ])
            ->toArray();
    }

    public function selectCliente(int $id): void
    {
        $cliente = Cliente::query()->find($id);

        $this->cliente_id = $id;
        $this->cliente_q = $cliente?->nombre_completo ?? '';
        $this->clientes_suggest = [];

        if (! $cliente) {
            return;
        }

        $this->comprador_nombre_legal = $cliente->nombre_legal ?: $cliente->nombre_completo;
        $this->comprador_curp = $cliente->curp ?: null;
    }

    public function updatedFraccionamientoId(): void
    {
        $this->manzana = null;
        $this->lote_id = null;
        $this->vendedor_nombre_legal = null;
        $this->vendedor_curp = null;

        $this->loadManzanas();
        $this->loadLotes();
    }

    public function updatedManzana(): void
    {
        $this->lote_id = null;

        $this->loadLotes();
    }

    public function updatedLoteId(): void
    {
        $propietario = Lote::query()
            ->with('fraccionamiento.propietario')
            ->find($this->lote_id)
            ?->fraccionamiento
            ?->propietario;

        $this->vendedor_nombre_legal = $propietario
            ? ($propietario->nombre_legal ?: $propietario->nombre)
            : null;
        $this->vendedor_curp = $propietario?->curp ?: null;
    }

    protected function loadManzanas(): void
    {
        $this->manzanasDisponibles = [];

        if (! $this->fraccionamiento_id) {
            return;
        }

        $this->manzanasDisponibles = Lote::query()
            ->where('fraccionamiento_id', $this->fraccionamiento_id)
            ->where('estatus', 'disponible')
            ->select('manzana')
            ->distinct()
            ->orderBy('manzana')
            ->pluck('manzana')
            ->filter()
            ->values()
            ->toArray();
    }

    protected function loadLotes(): void
    {
        $this->lotesDisponibles = [];

        if (! $this->fraccionamiento_id) {
            return;
        }

        $query = Lote::query()
            ->where('fraccionamiento_id', $this->fraccionamiento_id)
            ->where('estatus', 'disponible');

        if ($this->manzana) {
            $query->where('manzana', $this->manzana);
        }

        $this->lotesDisponibles = $query
            ->orderByRaw('CAST(lote AS UNSIGNED) ASC')
            ->orderBy('lote', 'ASC')
            ->get(['id', 'lote', 'manzana'])
            ->map(fn ($l) => [
                'id' => (int) $l->id,
                'label' => 'Lote '.$l->lote.($l->manzana ? ' · Mz '.$l->manzana : ''),
            ])
            ->toArray();
    }

    public function guardar()
    {
        abort_unless(auth()->user()?->can('contratos.editar'), 403);

        $datos = $this->validate();

        $lote = Lote::query()->findOrFail($this->lote_id);

        if ((string) $lote->estatus !== 'disponible') {
            $this->addError('lote_id', 'Ese lote ya no está disponible. Vuelve a elegirlo.');
            $this->loadLotes();

            return null;
        }

        $contrato = DB::transaction(function () use ($datos, $lote) {
            $contrato = Contrato::create([
                'cliente_id' => $datos['cliente_id'],
                'lote_id' => $datos['lote_id'],
                'folio_contrato' => app(ContratoFolioService::class)->generar(),

                // Contrato terminado: sin calendario, sin saldos y sin cuotas.
                'fecha_inicio' => $datos['fecha_inicio'] ?: $datos['fecha_terminacion'],
                'frecuencia' => 'mensual',
                'dia_mes' => null,
                'dia_semana' => null,
                'precio_total' => 0,
                'enganche' => 0,
                'saldo_inicial' => 0,
                'saldo_actual' => 0,
                'monto_pago' => 0,
                'tipo_recargo' => 'fijo',
                'valor_recargo' => 0,
                'dias_gracia' => 0,

                'estatus' => 'liquidado',
                'liquidado_at' => $datos['fecha_terminacion'],
                'tipo' => 'terreno',
                'solo_documentos' => true,
                'archivo_contrato_disk' => 'private',

                'comprador_nombre_legal' => $datos['comprador_nombre_legal'] ?? null,
                'comprador_curp' => $datos['comprador_curp'] ?? null,
                'vendedor_nombre_legal' => $datos['vendedor_nombre_legal'] ?? null,
                'vendedor_curp' => $datos['vendedor_curp'] ?? null,

                // Snapshot del lote para que los documentos salgan completos.
                'area_m2_snapshot' => $lote->area_m2,
                'medida_norte_snapshot' => $lote->medida_norte,
                'medida_sur_snapshot' => $lote->medida_sur,
                'medida_este_snapshot' => $lote->medida_este,
                'medida_oeste_snapshot' => $lote->medida_oeste,
                'colindancia_norte_snapshot' => $lote->colindancia_norte,
                'colindancia_sur_snapshot' => $lote->colindancia_sur,
                'colindancia_este_snapshot' => $lote->colindancia_este,
                'colindancia_oeste_snapshot' => $lote->colindancia_oeste,
            ]);

            // El lote deja de estar disponible aunque el contrato ya esté pagado.
            $lote->update(['estatus' => 'vendido']);

            return $contrato;
        });

        session()->flash('success', 'Contrato histórico dado de alta. Ya puedes subir sus documentos.');

        return redirect()->route('admin.contratos.show', ['uuid' => $contrato->uuid]);
    }

    public function render()
    {
        return view('livewire.admin.contratos.crear-historico', [
            'fraccionamientos' => Fraccionamiento::query()
                ->orderBy('nombre')
                ->get(['id', 'nombre']),
        ])->layout('layouts.app');
    }
}
