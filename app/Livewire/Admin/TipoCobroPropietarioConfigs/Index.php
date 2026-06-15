<?php

namespace App\Livewire\Admin\TipoCobroPropietarioConfigs;

use App\Models\FormaPago;
use App\Models\Fraccionamiento;
use App\Models\Propietario;
use App\Models\TipoCobro;
use App\Models\TipoCobroPropietarioConfig;
use Livewire\Component;

class Index extends Component
{
    public ?int $tipo_cobro_id = null;
    public ?int $fraccionamiento_id = null;

    public array $rows = [];

    public function mount(): void
    {
        $this->tipo_cobro_id = TipoCobro::query()->orderBy('nombre')->value('id');
        $this->fraccionamiento_id = Fraccionamiento::query()->orderBy('nombre')->value('id');

        $this->cargarMatriz();
    }

    public function updatedTipoCobroId(): void
    {
        $this->cargarMatriz();
    }

    public function updatedFraccionamientoId(): void
    {
        $this->cargarMatriz();
    }

    public function cargarMatriz(): void
    {
        $this->rows = [];

        if (!$this->tipo_cobro_id || !$this->fraccionamiento_id) {
            return;
        }

        $formasPago = FormaPago::query()
            ->where('activa', true)
            ->orderBy('nombre')
            ->get();

        $configs = TipoCobroPropietarioConfig::query()
            ->where('tipo_cobro_id', $this->tipo_cobro_id)
            ->where('fraccionamiento_id', $this->fraccionamiento_id)
            ->get()
            ->keyBy('forma_pago_id');

        foreach ($formasPago as $formaPago) {
            $config = $configs->get($formaPago->id);

            $this->rows[] = [
                'forma_pago_id' => $formaPago->id,
                'forma_pago_nombre' => $formaPago->nombre,
                'propietario_id' => $config?->propietario_id,
                'activo' => $config?->activo ?? true,
                'prioridad' => $config?->prioridad ?? 0,
            ];
        }
    }

    public function guardar(): void
    {
        $this->validate([
            'tipo_cobro_id' => ['required', 'exists:tipos_cobro,id'],
            'fraccionamiento_id' => ['required', 'exists:fraccionamientos,id'],
            'rows' => ['array'],
            'rows.*.forma_pago_id' => ['required', 'exists:formas_pago,id'],
            'rows.*.propietario_id' => ['nullable', 'exists:propietarios,id'],
            'rows.*.activo' => ['boolean'],
            'rows.*.prioridad' => ['required', 'integer', 'min:0'],
        ]);

        foreach ($this->rows as $row) {
            if (empty($row['propietario_id'])) {
                TipoCobroPropietarioConfig::query()
                    ->where('tipo_cobro_id', $this->tipo_cobro_id)
                    ->where('fraccionamiento_id', $this->fraccionamiento_id)
                    ->where('forma_pago_id', $row['forma_pago_id'])
                    ->delete();

                continue;
            }

            TipoCobroPropietarioConfig::updateOrCreate(
                [
                    'tipo_cobro_id' => $this->tipo_cobro_id,
                    'fraccionamiento_id' => $this->fraccionamiento_id,
                    'forma_pago_id' => $row['forma_pago_id'],
                ],
                [
                    'propietario_id' => $row['propietario_id'],
                    'activo' => (bool) $row['activo'],
                    'prioridad' => (int) $row['prioridad'],
                ]
            );
        }

        $this->cargarMatriz();

        session()->flash('success', 'Configuración guardada correctamente.');
    }

    public function limpiarFila(int $index): void
    {
        if (!isset($this->rows[$index])) {
            return;
        }

        $this->rows[$index]['propietario_id'] = null;
        $this->rows[$index]['activo'] = true;
        $this->rows[$index]['prioridad'] = 0;
    }

    public function aplicarPropietarioATodos(?int $propietarioId): void
    {
        if (!$propietarioId) {
            return;
        }

        foreach ($this->rows as $index => $row) {
            $this->rows[$index]['propietario_id'] = $propietarioId;
        }
    }

    public function render()
    {
        return view('livewire.admin.tipo-cobro-propietario-configs.index', [
            'tiposCobro' => TipoCobro::query()->orderBy('nombre')->get(),
            'fraccionamientos' => Fraccionamiento::query()->orderBy('nombre')->get(),
            'propietarios' => Propietario::query()->orderBy('nombre')->get(),
        ])->layout('layouts.app');
    }
}