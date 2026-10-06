<?php

namespace App\Livewire\Admin\Recibos;

use App\Models\CuentaBancaria;
use App\Models\FormaPago;
use App\Models\ReciboPago;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Bandeja para validar las evidencias de pago.
 *
 * Validar se podía desde el índice de recibos, pero de uno en uno y abriendo
 * un modal por pago: revisar el corte del día era imposible. Aquí llegan
 * juntos los que esperan revisión, con su comprobante a la vista, y se
 * marcan de varios de un jalón.
 */
class ValidacionEvidencias extends Component
{
    use WithPagination;

    protected string $paginationTheme = 'tailwind';

    #[Url(except: '')]
    public string $q = '';

    #[Url(except: null)]
    public ?int $forma_pago_id = null;

    /**
     * Las cuentas o tarjetas a las que entro el dinero. Vacio es "todas":
     * se cotejan varias a la vez cuando el corte abarca mas de una.
     *
     * @var array<int, int>
     */
    #[Url(except: [])]
    public array $cuentas_ids = [];

    #[Url(except: null)]
    public ?string $desde = null;

    #[Url(except: null)]
    public ?string $hasta = null;

    /** sin_validar | validados | todos */
    #[Url(except: 'sin_validar')]
    public string $estado = 'sin_validar';

    /** @var array<int, int> ids de recibos_pagos palomeados */
    public array $seleccion = [];

    public bool $modalEvidenciaOpen = false;

    public ?int $evidenciaPagoId = null;

    public function updated($property): void
    {
        // Al cambiar un filtro la lista es otra: lo palomeado ya no aplica.
        // `cuentas_ids` llega como "cuentas_ids.0" por ser arreglo.
        $filtros = ['q', 'forma_pago_id', 'cuentas_ids', 'desde', 'hasta', 'estado'];

        $esFiltro = collect($filtros)->contains(
            fn (string $filtro) => $property === $filtro || str_starts_with($property, $filtro.'.')
        );

        if ($esFiltro) {
            $this->seleccion = [];
            $this->resetPage();
        }
    }

    public function limpiarFiltros(): void
    {
        $this->q = '';
        $this->forma_pago_id = null;
        $this->cuentas_ids = [];
        $this->desde = null;
        $this->hasta = null;
        $this->estado = 'sin_validar';
        $this->seleccion = [];
        $this->resetPage();
    }

    /** Palomea o despaloma los de la página que se está viendo. */
    public function alternarPagina(): void
    {
        $idsPagina = $this->pagosPaginados()->pluck('id')->all();

        $this->seleccion = $this->paginaCompleta()
            ? array_values(array_diff($this->seleccion, $idsPagina))
            : array_values(array_unique(array_merge($this->seleccion, $idsPagina)));
    }

    public function paginaCompleta(): bool
    {
        $idsPagina = $this->pagosPaginados()->pluck('id')->all();

        return $idsPagina !== [] && ! array_diff($idsPagina, $this->seleccion);
    }

    public function validarSeleccionados(): void
    {
        $this->marcar(validado: true);
    }

    public function quitarValidacionSeleccionados(): void
    {
        $this->marcar(validado: false);
    }

    public function verEvidencia(int $pagoId): void
    {
        $this->evidenciaPagoId = $pagoId;
        $this->modalEvidenciaOpen = true;
    }

    public function cerrarEvidencia(): void
    {
        $this->modalEvidenciaOpen = false;
        $this->evidenciaPagoId = null;
    }

    public function getEvidenciaProperty(): ?ReciboPago
    {
        if (! $this->evidenciaPagoId) {
            return null;
        }

        return ReciboPago::with(['recibo.cliente', 'formaPago', 'cuentaBancaria'])
            ->find($this->evidenciaPagoId);
    }

    public function getFormasPagoProperty(): Collection
    {
        // Solo las que piden comprobante: en efectivo no hay nada que validar.
        return FormaPago::query()
            ->where('requiere_cuenta', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);
    }

    /**
     * Las cuentas que de verdad aparecen en la bandeja. Listarlas todas
     * llenaria el selector de opciones que no devuelven nada.
     */
    public function getCuentasProperty(): Collection
    {
        $usadas = self::baseQuery()
            ->whereNotNull('recibos_pagos.cuenta_bancaria_id')
            ->distinct()
            ->pluck('recibos_pagos.cuenta_bancaria_id');

        return CuentaBancaria::query()
            ->whereIn('id', $usadas)
            ->orderBy('alias')
            ->get(['id', 'alias', 'banco', 'numero']);
    }

    public function limpiarCuentas(): void
    {
        $this->cuentas_ids = [];
        $this->seleccion = [];
        $this->resetPage();
    }

    public function getPendientesProperty(): int
    {
        return self::conteoPendientes();
    }

    /** Lo que falta por revisar, para el badge del menú y el encabezado. */
    public static function conteoPendientes(): int
    {
        return self::baseQuery()->whereNull('recibos_pagos.validado_at')->count();
    }

    public function render()
    {
        return view('livewire.admin.recibos.validacion-evidencias', [
            'pagos' => $this->pagosPaginados(),
        ])->layout('layouts.app');
    }

    private function pagosPaginados()
    {
        return $this->query()->paginate(20);
    }

    /**
     * Los pagos que de verdad se pueden validar: con comprobante guardado,
     * vigentes, y de una forma de pago que pide comprobante.
     */
    private static function baseQuery()
    {
        return ReciboPago::query()
            ->join('formas_pago', 'formas_pago.id', '=', 'recibos_pagos.forma_pago_id')
            ->join('recibos', 'recibos.id', '=', 'recibos_pagos.recibo_id')
            ->whereNull('recibos_pagos.deleted_at')
            ->whereNull('recibos_pagos.anulado_at')
            ->whereNull('recibos.deleted_at')
            ->whereNull('recibos.anulado_at')
            ->whereNotNull('recibos_pagos.evidencia_path')
            ->where('formas_pago.requiere_cuenta', true);
    }

    private function query()
    {
        return self::baseQuery()
            ->with(['recibo.cliente', 'formaPago', 'cuentaBancaria', 'validadoPor'])
            ->when($this->estado === 'sin_validar', fn ($q) => $q->whereNull('recibos_pagos.validado_at'))
            ->when($this->estado === 'validados', fn ($q) => $q->whereNotNull('recibos_pagos.validado_at'))
            ->when($this->forma_pago_id, fn ($q) => $q->where('recibos_pagos.forma_pago_id', $this->forma_pago_id))
            ->when($this->cuentas_ids !== [], fn ($q) => $q->whereIn('recibos_pagos.cuenta_bancaria_id', $this->cuentas_ids))
            ->when($this->desde, fn ($q) => $q->whereDate('recibos_pagos.fecha_efectiva', '>=', $this->desde))
            ->when($this->hasta, fn ($q) => $q->whereDate('recibos_pagos.fecha_efectiva', '<=', $this->hasta))
            ->when($this->buscado(), function ($q, string $termino) {
                $like = '%'.$termino.'%';

                $q->where(function ($sub) use ($like) {
                    $sub->where('recibos.folio', 'like', $like)
                        ->orWhere('recibos_pagos.referencia', 'like', $like)
                        ->orWhereExists(fn ($cliente) => $cliente
                            ->selectRaw('1')
                            ->from('clientes')
                            ->whereColumn('clientes.id', 'recibos.cliente_id')
                            ->whereRaw("TRIM(CONCAT_WS(' ', clientes.nombres, clientes.apellidos)) like ?", [$like])
                        );
                });
            })
            // Lo más viejo primero: es lo que lleva más tiempo esperando.
            ->orderBy('recibos_pagos.fecha_efectiva')
            ->orderBy('recibos_pagos.id')
            ->select('recibos_pagos.*');
    }

    private function buscado(): ?string
    {
        $termino = trim($this->q);

        return $termino === '' ? null : $termino;
    }

    private function marcar(bool $validado): void
    {
        if ($this->seleccion === []) {
            $this->dispatch('toast', type: 'warning', message: 'No has seleccionado ningún pago.');

            return;
        }

        // Solo los que de verdad cambian. Volver a validar uno ya validado le
        // borraría la fecha y el nombre de quien lo revisó la primera vez, que
        // es justo el rastro que sirve para auditar.
        $porCambiar = $this->seleccionadosQueCambian($validado);

        if ($porCambiar->isEmpty()) {
            $this->dispatch(
                'toast',
                type: 'warning',
                message: $validado
                    ? 'Lo que seleccionaste ya estaba validado.'
                    : 'Lo que seleccionaste no estaba validado.'
            );
            $this->seleccion = [];

            return;
        }

        ReciboPago::whereIn('id', $porCambiar)->update([
            'validado_at' => $validado ? now() : null,
            'validado_por_user_id' => $validado ? auth()->id() : null,
        ]);

        $this->seleccion = [];

        $cuantos = $porCambiar->count();
        $plural = $cuantos === 1 ? 'pago' : 'pagos';

        $this->dispatch(
            'toast',
            type: $validado ? 'success' : 'warning',
            message: $validado
                ? "{$cuantos} {$plural} marcados como validados."
                : "Se quitó la validación a {$cuantos} {$plural}."
        );
    }

    /**
     * De lo seleccionado, lo que cambiaría de estado con esta acción.
     *
     * Se reconsulta contra la base: así nadie toca un pago que se anuló
     * mientras tenía la pantalla abierta, ni revalida lo ya validado.
     *
     * @return Collection<int, int>
     */
    private function seleccionadosQueCambian(bool $validado): Collection
    {
        $ids = collect($this->seleccion)->filter()->map(fn ($id) => (int) $id)->unique();

        if ($ids->isEmpty()) {
            return collect();
        }

        return self::baseQuery()
            ->whereIn('recibos_pagos.id', $ids)
            ->when($validado, fn ($q) => $q->whereNull('recibos_pagos.validado_at'))
            ->when(! $validado, fn ($q) => $q->whereNotNull('recibos_pagos.validado_at'))
            ->pluck('recibos_pagos.id');
    }

    /** Cuántos de los seleccionados están por validar. */
    public function getSeleccionPorValidarProperty(): int
    {
        return $this->seleccionadosQueCambian(validado: true)->count();
    }

    /** Cuántos de los seleccionados ya están validados. */
    public function getSeleccionValidadosProperty(): int
    {
        return $this->seleccionadosQueCambian(validado: false)->count();
    }
}
