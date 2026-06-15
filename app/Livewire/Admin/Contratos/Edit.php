<?php

namespace App\Livewire\Admin\Contratos;

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

use App\Models\Contrato;
use App\Models\Cliente;
use App\Models\Cuota;
use App\Models\Pago;
use App\Models\ContratoHistorial;
use App\Models\Fraccionamiento;
use Illuminate\Validation\ValidationException;

class Edit extends Component
{
    public string $uuid;

    public Contrato $contrato;

    public ?int $cliente_id = null;
    public ?int $fraccionamiento_id = null;

    public ?string $nuevo_estatus = null;
    public ?string $nueva_fecha_inicio = null;

    public string $nueva_frecuencia = 'mensual';
    public ?int $nuevo_dia_mes = null;
    public ?int $nuevo_dia_semana = null;

    public ?string $nueva_fecha_primera_cuota = null;
    public ?float $nuevo_monto_pago = null;

    public ?float $nuevo_precio_total = null;
    public ?float $nuevo_enganche = null;
    public ?float $nuevo_saldo_inicial = null;
    public ?float $nuevo_saldo_actual = null;

    public ?int $dias_gracia = null;

    public string $tipo_recargo = 'fijo';
    public ?float $valor_recargo = null;

    public bool $confirmar_reestructura = false;
    public bool $confirmar_cancelacion = false;

    public function mount(string $uuid): void
    {
        $this->uuid = $uuid;

        $this->contrato = Contrato::query()
            ->with(['cliente', 'lote.fraccionamiento'])
            ->where('uuid', $uuid)
            ->firstOrFail();

        $this->cliente_id = (int) $this->contrato->cliente_id;

        $this->fraccionamiento_id = $this->contrato->lote?->fraccionamiento_id
            ? (int) $this->contrato->lote->fraccionamiento_id
            : null;

        $this->nuevo_estatus = (string) ($this->contrato->estatus ?? 'activo');

        $this->nueva_fecha_inicio = $this->contrato->fecha_inicio
            ? Carbon::parse($this->contrato->fecha_inicio)->toDateString()
            : null;

        $this->nueva_frecuencia = (string) ($this->contrato->frecuencia ?? 'mensual');
        $this->nuevo_dia_mes = isset($this->contrato->dia_mes) ? (int) $this->contrato->dia_mes : null;
        $this->nuevo_dia_semana = isset($this->contrato->dia_semana) ? (int) $this->contrato->dia_semana : null;

        $this->nuevo_monto_pago = isset($this->contrato->monto_pago)
            ? (float) $this->contrato->monto_pago
            : null;

        $this->nuevo_precio_total = isset($this->contrato->precio_total)
            ? (float) $this->contrato->precio_total
            : null;

        $this->nuevo_enganche = isset($this->contrato->enganche)
            ? (float) $this->contrato->enganche
            : null;

        $this->nuevo_saldo_inicial = isset($this->contrato->saldo_inicial)
            ? (float) $this->contrato->saldo_inicial
            : null;

        $this->nuevo_saldo_actual = isset($this->contrato->saldo_actual)
            ? (float) $this->contrato->saldo_actual
            : null;

        $this->dias_gracia = isset($this->contrato->dias_gracia)
            ? (int) $this->contrato->dias_gracia
            : 0;

        $this->tipo_recargo = (string) ($this->contrato->tipo_recargo ?? 'fijo');
        $this->valor_recargo = isset($this->contrato->valor_recargo)
            ? (float) $this->contrato->valor_recargo
            : 0;

        $this->nueva_fecha_primera_cuota = $this->resolverFechaInicialSugerida();
    }

    public function updatedNuevaFrecuencia(): void
    {
        if ($this->nueva_frecuencia === 'mensual') {
            $this->nuevo_dia_semana = null;

            if (! $this->nuevo_dia_mes) {
                $this->nuevo_dia_mes = isset($this->contrato->dia_mes)
                    ? (int) $this->contrato->dia_mes
                    : 1;
            }
        } else {
            $this->nuevo_dia_mes = null;

            if (! $this->nuevo_dia_semana) {
                $this->nuevo_dia_semana = isset($this->contrato->dia_semana)
                    ? (int) $this->contrato->dia_semana
                    : 1;
            }
        }

        $this->nueva_fecha_primera_cuota = $this->resolverFechaInicialSugerida();
    }

    public function updatedNuevoDiaMes(): void
    {
        $this->nueva_fecha_primera_cuota = $this->resolverFechaInicialSugerida();
    }

    public function updatedNuevoDiaSemana(): void
    {
        $this->nueva_fecha_primera_cuota = $this->resolverFechaInicialSugerida();
    }

    public function guardarCambios(): void
    {
        $rules = [
            'cliente_id'                 => ['required', 'integer', 'exists:clientes,id'],
            'fraccionamiento_id'         => ['required', 'integer', 'exists:fraccionamientos,id'],
            'nuevo_estatus'              => ['required', 'in:activo,moroso,liquidado'],
            'nueva_fecha_inicio'         => ['nullable', 'date'],
            'nueva_frecuencia'           => ['required', 'in:semanal,mensual'],
            'nueva_fecha_primera_cuota'  => ['nullable', 'date'],
            'nuevo_monto_pago'           => ['required', 'numeric', 'min:0.01'],
            'nuevo_precio_total'         => ['nullable', 'numeric', 'min:0'],
            'nuevo_enganche'             => ['nullable', 'numeric', 'min:0'],
            'nuevo_saldo_inicial'        => ['nullable', 'numeric', 'min:0'],
            'nuevo_saldo_actual'         => ['nullable', 'numeric', 'min:0'],
            'dias_gracia'                => ['required', 'integer', 'min:0', 'max:365'],
            'tipo_recargo'               => ['required', 'in:fijo,porcentaje'],
            'valor_recargo'              => ['required', 'numeric', 'min:0'],
        ];

        if ($this->nueva_frecuencia === 'mensual') {
            $rules['nuevo_dia_mes'] = ['required', 'integer', 'min:1', 'max:31'];
        } else {
            $rules['nuevo_dia_semana'] = ['required', 'integer', 'min:1', 'max:7'];
        }

        $cambiaCalendarioOMonto =
            ((string) ($this->contrato->frecuencia ?? 'mensual') !== (string) $this->nueva_frecuencia) ||
            ((int) ($this->contrato->dia_mes ?? 0) !== (int) ($this->nuevo_dia_mes ?? 0)) ||
            ((int) ($this->contrato->dia_semana ?? 0) !== (int) ($this->nuevo_dia_semana ?? 0)) ||
            ((float) ($this->contrato->monto_pago ?? 0) !== (float) ($this->nuevo_monto_pago ?? 0));

        $cambiaSaldoActual =
            ((float) ($this->contrato->saldo_actual ?? 0) !== (float) ($this->nuevo_saldo_actual ?? 0));

        $requiereReestructura = $cambiaCalendarioOMonto || $cambiaSaldoActual;

        if ($requiereReestructura) {
            $rules['nueva_fecha_primera_cuota'] = ['required', 'date'];
        }

        $this->validate($rules);

        if (
            $this->nuevo_precio_total !== null &&
            $this->nuevo_enganche !== null &&
            (float) $this->nuevo_enganche > (float) $this->nuevo_precio_total
        ) {
            $this->addError('nuevo_enganche', 'El enganche no puede ser mayor al precio total.');
            return;
        }

        if (
            $this->nuevo_saldo_inicial !== null &&
            $this->nuevo_saldo_actual !== null &&
            (float) $this->nuevo_saldo_actual > (float) $this->nuevo_saldo_inicial
        ) {
            $this->addError('nuevo_saldo_actual', 'El saldo actual no puede ser mayor al saldo inicial.');
            return;
        }

        if ($requiereReestructura) {
            $this->validarFechaPrimeraCuota();
        }

        if ($requiereReestructura && ! $this->confirmar_reestructura) {
            $this->addError('confirmar_reestructura', 'Debes confirmar para continuar.');
            return;
        }

        DB::transaction(function () use ($requiereReestructura) {
            $this->contrato->refresh();
            $this->contrato->loadMissing(['lote.fraccionamiento']);

            $antes = [
                'cliente_id'            => (int) $this->contrato->cliente_id,
                'lote_id'               => (int) ($this->contrato->lote_id ?? 0),
                'fraccionamiento_id'    => (int) ($this->contrato->lote?->fraccionamiento_id ?? 0),
                'fecha_inicio'          => optional($this->contrato->fecha_inicio)?->toDateString(),
                'frecuencia'            => (string) ($this->contrato->frecuencia ?? 'mensual'),
                'dia_mes'               => isset($this->contrato->dia_mes) ? (int) $this->contrato->dia_mes : null,
                'dia_semana'            => isset($this->contrato->dia_semana) ? (int) $this->contrato->dia_semana : null,
                'fecha_primera_cuota'   => null,
                'monto_pago'            => isset($this->contrato->monto_pago) ? (float) $this->contrato->monto_pago : null,
                'precio_total'          => isset($this->contrato->precio_total) ? (float) $this->contrato->precio_total : null,
                'enganche'              => isset($this->contrato->enganche) ? (float) $this->contrato->enganche : null,
                'saldo_inicial'         => isset($this->contrato->saldo_inicial) ? (float) $this->contrato->saldo_inicial : null,
                'saldo_actual'          => isset($this->contrato->saldo_actual) ? (float) $this->contrato->saldo_actual : null,
                'dias_gracia'           => isset($this->contrato->dias_gracia) ? (int) $this->contrato->dias_gracia : 0,
                'tipo_recargo'          => (string) ($this->contrato->tipo_recargo ?? 'fijo'),
                'valor_recargo'         => isset($this->contrato->valor_recargo) ? (float) $this->contrato->valor_recargo : 0,
                'estatus'               => (string) ($this->contrato->estatus ?? 'activo'),
            ];

            $saldoAnterior = (float) ($this->contrato->saldo_actual ?? 0);

            $this->contrato->cliente_id = (int) $this->cliente_id;
            $this->contrato->estatus = $this->nuevo_estatus;

            if (isset($this->contrato->fecha_inicio)) {
                $this->contrato->fecha_inicio = $this->nueva_fecha_inicio
                    ? Carbon::parse($this->nueva_fecha_inicio)->toDateString()
                    : null;
            }

            $this->contrato->frecuencia = $this->nueva_frecuencia;

            if ($this->nueva_frecuencia === 'mensual') {
                if (isset($this->contrato->dia_mes)) {
                    $this->contrato->dia_mes = (int) $this->nuevo_dia_mes;
                }

                if (isset($this->contrato->dia_semana)) {
                    $this->contrato->dia_semana = null;
                }
            } else {
                if (isset($this->contrato->dia_semana)) {
                    $this->contrato->dia_semana = (int) $this->nuevo_dia_semana;
                }

                if (isset($this->contrato->dia_mes)) {
                    $this->contrato->dia_mes = null;
                }
            }

            if (isset($this->contrato->monto_pago)) {
                $this->contrato->monto_pago = (float) $this->nuevo_monto_pago;
            }

            if (isset($this->contrato->precio_total)) {
                $this->contrato->precio_total = $this->nuevo_precio_total !== null
                    ? (float) $this->nuevo_precio_total
                    : null;
            }

            if (isset($this->contrato->enganche)) {
                $this->contrato->enganche = $this->nuevo_enganche !== null
                    ? (float) $this->nuevo_enganche
                    : 0;
            }

            if (isset($this->contrato->saldo_inicial)) {
                $this->contrato->saldo_inicial = $this->nuevo_saldo_inicial !== null
                    ? (float) $this->nuevo_saldo_inicial
                    : null;
            }

            if (isset($this->contrato->saldo_actual)) {
                $this->contrato->saldo_actual = $this->nuevo_saldo_actual !== null
                    ? (float) $this->nuevo_saldo_actual
                    : null;
            }

            if (isset($this->contrato->dias_gracia)) {
                $this->contrato->dias_gracia = $this->dias_gracia !== null
                    ? (int) $this->dias_gracia
                    : 0;
            }

            if (isset($this->contrato->tipo_recargo)) {
                $this->contrato->tipo_recargo = (string) $this->tipo_recargo;
            }

            if (isset($this->contrato->valor_recargo)) {
                $this->contrato->valor_recargo = $this->valor_recargo !== null
                    ? (float) $this->valor_recargo
                    : 0;
            }

            $this->contrato->save();

            if ($this->contrato->lote && isset($this->contrato->lote->fraccionamiento_id)) {
                $this->contrato->lote->fraccionamiento_id = (int) $this->fraccionamiento_id;
                $this->contrato->lote->save();
            }

            $cuotasEliminadas = 0;
            $cuotasCreadas = 0;

            if ($requiereReestructura) {
                [$cuotasEliminadas, $cuotasCreadas] = $this->reestructurarSoloPendientesEnAdelante();
            }

            $this->contrato->refresh();
            $this->contrato->load(['cliente', 'lote.fraccionamiento']);

            $despues = [
                'cliente_id'            => (int) $this->contrato->cliente_id,
                'lote_id'               => (int) ($this->contrato->lote_id ?? 0),
                'fraccionamiento_id'    => (int) ($this->contrato->lote?->fraccionamiento_id ?? 0),
                'fecha_inicio'          => optional($this->contrato->fecha_inicio)?->toDateString(),
                'frecuencia'            => (string) ($this->contrato->frecuencia ?? 'mensual'),
                'dia_mes'               => isset($this->contrato->dia_mes) ? (int) $this->contrato->dia_mes : null,
                'dia_semana'            => isset($this->contrato->dia_semana) ? (int) $this->contrato->dia_semana : null,
                'fecha_primera_cuota'   => $this->nueva_fecha_primera_cuota,
                'monto_pago'            => isset($this->contrato->monto_pago) ? (float) $this->contrato->monto_pago : null,
                'precio_total'          => isset($this->contrato->precio_total) ? (float) $this->contrato->precio_total : null,
                'enganche'              => isset($this->contrato->enganche) ? (float) $this->contrato->enganche : null,
                'saldo_inicial'         => isset($this->contrato->saldo_inicial) ? (float) $this->contrato->saldo_inicial : null,
                'saldo_actual'          => isset($this->contrato->saldo_actual) ? (float) $this->contrato->saldo_actual : null,
                'dias_gracia'           => isset($this->contrato->dias_gracia) ? (int) $this->contrato->dias_gracia : 0,
                'tipo_recargo'          => (string) ($this->contrato->tipo_recargo ?? 'fijo'),
                'valor_recargo'         => isset($this->contrato->valor_recargo) ? (float) $this->contrato->valor_recargo : 0,
                'estatus'               => (string) ($this->contrato->estatus ?? 'activo'),
            ];

            $saldoNuevo = (float) ($this->contrato->saldo_actual ?? 0);

            $cambioCliente = ((int) $antes['cliente_id'] !== (int) $despues['cliente_id']);

            $cambioCalendario = (
                (($antes['fecha_inicio'] ?? null) !== ($despues['fecha_inicio'] ?? null)) ||
                ($antes['frecuencia'] !== $despues['frecuencia']) ||
                ((int) ($antes['dia_mes'] ?? 0) !== (int) ($despues['dia_mes'] ?? 0)) ||
                ((int) ($antes['dia_semana'] ?? 0) !== (int) ($despues['dia_semana'] ?? 0)) ||
                (($antes['fecha_primera_cuota'] ?? null) !== ($despues['fecha_primera_cuota'] ?? null)) ||
                ((float) ($antes['monto_pago'] ?? 0) !== (float) ($despues['monto_pago'] ?? 0)) ||
                ($cuotasEliminadas > 0 || $cuotasCreadas > 0)
            );

            $cambioFinanciero = (
                ((float) ($antes['precio_total'] ?? 0) !== (float) ($despues['precio_total'] ?? 0)) ||
                ((float) ($antes['enganche'] ?? 0) !== (float) ($despues['enganche'] ?? 0)) ||
                ((float) ($antes['saldo_inicial'] ?? 0) !== (float) ($despues['saldo_inicial'] ?? 0)) ||
                ((float) ($antes['saldo_actual'] ?? 0) !== (float) ($despues['saldo_actual'] ?? 0)) ||
                ((int) ($antes['dias_gracia'] ?? 0) !== (int) ($despues['dias_gracia'] ?? 0)) ||
                ((string) ($antes['tipo_recargo'] ?? 'fijo') !== (string) ($despues['tipo_recargo'] ?? 'fijo')) ||
                ((float) ($antes['valor_recargo'] ?? 0) !== (float) ($despues['valor_recargo'] ?? 0))
            );

            $cambioFraccionamiento =
                ((int) ($antes['fraccionamiento_id'] ?? 0) !== (int) ($despues['fraccionamiento_id'] ?? 0));

            $cambioEstatus =
                ((string) ($antes['estatus'] ?? '') !== (string) ($despues['estatus'] ?? ''));

            $tipo = 'edicion_contrato';

            if ($cambioFraccionamiento && $cambioEstatus) {
                $tipo = 'cambio_fraccionamiento_estatus';
            } elseif ($cambioFraccionamiento) {
                $tipo = 'cambio_fraccionamiento';
            } elseif ($cambioEstatus) {
                $tipo = 'cambio_estatus';
            } elseif ($cambioCliente && $cambioCalendario) {
                $tipo = 'ambos';
            } elseif ($cambioCliente) {
                $tipo = 'cambio_cliente';
            } elseif ($cambioCalendario) {
                $tipo = 'cambio_calendario';
            } elseif ($cambioFinanciero) {
                $tipo = 'cambio_financiero';
            }

            ContratoHistorial::create([
                'contrato_id'       => $this->contrato->id,
                'user_id'           => auth()->id(),
                'tipo'              => $tipo,
                'antes'             => $antes,
                'despues'           => $despues,
                'saldo_anterior'    => $saldoAnterior,
                'saldo_nuevo'       => $saldoNuevo,
                'cuotas_eliminadas' => $cuotasEliminadas,
                'cuotas_creadas'    => $cuotasCreadas,
                'nota'              => $requiereReestructura
                    ? 'Edicion de contrato con regeneracion de cuotas pendientes usando fecha manual de primera cuota.'
                    : 'Edicion de datos del contrato sin regeneracion de cuotas.',
            ]);
        });

        $this->dispatch('toast', type: 'success', message: 'Contrato actualizado correctamente.');
        $this->redirectRoute('admin.contratos.show', $this->contrato->uuid);
    }

    public function cancelarContrato(): void
    {
        $this->resetErrorBag('confirmar_cancelacion');

        if (! $this->confirmar_cancelacion) {
            $this->addError('confirmar_cancelacion', 'Debes confirmar la cancelación del contrato.');
            return;
        }

        $this->contrato->refresh();
        $this->contrato->loadMissing(['lote']);

        if (($this->contrato->estatus ?? null) === 'cancelado') {
            $this->dispatch('toast', type: 'warning', message: 'Este contrato ya está cancelado.');
            return;
        }

        if (
            ($this->contrato->estatus ?? null) === 'liquidado' ||
            (float) ($this->contrato->saldo_actual ?? 0) <= 0
        ) {
            $this->dispatch(
                'toast',
                type: 'warning',
                message: 'No se puede cancelar un contrato liquidado.'
            );
            return;
        }

        DB::transaction(function () {
            $this->contrato->refresh();
            $this->contrato->loadMissing(['lote']);

            $cuotasPendientes = Cuota::query()
                ->where('contrato_id', $this->contrato->id)
                ->where('estatus', '!=', 'pagada')
                ->get(['id']);

            if ($cuotasPendientes->isNotEmpty()) {
                $idsPendientes = $cuotasPendientes->pluck('id')->all();

                $tienenPagosConfirmados = Pago::query()
                    ->whereIn('cuota_id', $idsPendientes)
                    ->where('estatus', 'confirmado')
                    ->exists();

                if ($tienenPagosConfirmados) {
                    throw new \RuntimeException(
                        'No se puede cancelar el contrato porque existen cuotas pendientes con pagos confirmados. Ajusta o cancela esos pagos primero.'
                    );
                }
            }

            $antes = [
                'estatus_contrato' => (string) ($this->contrato->estatus ?? 'activo'),
                'lote_estatus'     => (string) ($this->contrato->lote?->estatus ?? 'ocupado'),
                'saldo_actual'     => (float) ($this->contrato->saldo_actual ?? 0),
                'deleted_at'       => $this->contrato->deleted_at?->toDateTimeString(),
            ];

            $cuotasEliminadas = (int) Cuota::query()
                ->where('contrato_id', $this->contrato->id)
                ->where('estatus', '!=', 'pagada')
                ->count();

            Cuota::query()
                ->where('contrato_id', $this->contrato->id)
                ->where('estatus', '!=', 'pagada')
                ->delete();

            $this->contrato->estatus = 'cancelado';
            $this->contrato->deleted_at = now();
            $this->contrato->save();

            if ($this->contrato->lote && isset($this->contrato->lote->estatus)) {
                $this->contrato->lote->estatus = 'disponible';
                $this->contrato->lote->save();
            }

            $this->contrato->refresh();
            $this->contrato->load(['cliente', 'lote.fraccionamiento']);

            $despues = [
                'estatus_contrato' => (string) ($this->contrato->estatus ?? 'cancelado'),
                'lote_estatus'     => (string) ($this->contrato->lote?->estatus ?? 'disponible'),
                'saldo_actual'     => (float) ($this->contrato->saldo_actual ?? 0),
                'deleted_at'       => $this->contrato->deleted_at?->toDateTimeString(),
            ];

            ContratoHistorial::create([
                'contrato_id'       => $this->contrato->id,
                'user_id'           => auth()->id(),
                'tipo'              => 'cancelacion_contrato',
                'antes'             => $antes,
                'despues'           => $despues,
                'saldo_anterior'    => (float) ($antes['saldo_actual'] ?? 0),
                'saldo_nuevo'       => (float) ($despues['saldo_actual'] ?? 0),
                'cuotas_eliminadas' => $cuotasEliminadas,
                'cuotas_creadas'    => 0,
                'nota'              => 'Contrato cancelado. Se eliminaron cuotas pendientes, el contrato quedó cancelado, se marcó deleted_at y el lote se liberó.',
            ]);
        });

        $this->dispatch('toast', type: 'success', message: 'Contrato cancelado correctamente.');
        $this->redirectRoute('admin.contratos.show', $this->contrato->uuid);
    }

    protected function reestructurarSoloPendientesEnAdelante(): array
    {
        $contratoId = $this->contrato->id;

        $saldo = (float) ($this->contrato->saldo_actual ?? 0);
        if ($saldo <= 0) {
            return [0, 0];
        }

        $montoPago = (float) $this->nuevo_monto_pago;
        $frecuencia = $this->nueva_frecuencia;

        $cuotasPendientes = Cuota::query()
            ->where('contrato_id', $contratoId)
            ->where('estatus', '!=', 'pagada')
            ->get(['id']);

        if ($cuotasPendientes->isNotEmpty()) {
            $ids = $cuotasPendientes->pluck('id')->all();

            $tienenPagosConfirmados = Pago::query()
                ->whereIn('cuota_id', $ids)
                ->where('estatus', 'confirmado')
                ->exists();

            if ($tienenPagosConfirmados) {
                throw new \RuntimeException(
                    'No se puede cambiar calendario: existen cuotas pendientes con pagos confirmados. Ajusta/cancela esos pagos primero.'
                );
            }
        }

        $ultimoNumeroPagado = (int) Cuota::query()
            ->where('contrato_id', $contratoId)
            ->where('estatus', 'pagada')
            ->max('numero');

        $numero = $ultimoNumeroPagado > 0 ? ($ultimoNumeroPagado + 1) : 1;

        $cuotasEliminadas = (int) Cuota::query()
            ->where('contrato_id', $contratoId)
            ->where('estatus', '!=', 'pagada')
            ->count();

        Cuota::query()
            ->where('contrato_id', $contratoId)
            ->where('estatus', '!=', 'pagada')
            ->delete();

        $inicio = Carbon::parse($this->nueva_fecha_primera_cuota)->startOfDay();

        $cuotasNuevas = [];
        $restante = $saldo;
        $i = 0;

        while ($restante > 0.00001) {
            $montoCuota = min($montoPago, $restante);

            $fechaVenc = (clone $inicio);

            if ($frecuencia === 'semanal') {
                $fechaVenc->addWeeks($i);
            } else {
                if ($i > 0) {
                    $fechaVenc->addMonthsNoOverflow($i);
                }
            }

            $cuotasNuevas[] = [
                'uuid' => (string) Str::uuid(),
                'contrato_id' => $contratoId,
                'numero' => $numero,
                'fecha_vencimiento' => $fechaVenc->toDateString(),
                'monto' => round($montoCuota, 2),
                'estatus' => 'pendiente',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $restante = round($restante - $montoCuota, 2);
            $numero++;
            $i++;

            if ($i > 2000) {
                throw new \RuntimeException('Demasiadas cuotas generadas. Revisa saldo/monto.');
            }
        }

        Cuota::insert($cuotasNuevas);

        return [$cuotasEliminadas, count($cuotasNuevas)];
    }

    protected function validarFechaPrimeraCuota(): void
    {
        if (! $this->nueva_fecha_primera_cuota) {
            return;
        }

        $fecha = Carbon::parse($this->nueva_fecha_primera_cuota)->startOfDay();

        if ($this->nueva_frecuencia === 'semanal') {
            $dow = (int) $this->nuevo_dia_semana;

            if ($dow < 1 || $dow > 7) {
                throw ValidationException::withMessages([
                    'nuevo_dia_semana' => 'Selecciona un día de semana válido.',
                ]);
            }

            if ((int) $fecha->isoWeekday() !== $dow) {
                throw ValidationException::withMessages([
                    'nueva_fecha_primera_cuota' => 'La fecha de la primera cuota debe coincidir con el día semanal seleccionado.',
                ]);
            }

            return;
        }

        $dia = (int) $this->nuevo_dia_mes;

        if ($dia < 1 || $dia > 31) {
            throw ValidationException::withMessages([
                'nuevo_dia_mes' => 'Selecciona un día del mes válido.',
            ]);
        }

        if ((int) $fecha->day !== $dia) {
            throw ValidationException::withMessages([
                'nueva_fecha_primera_cuota' => 'La fecha de la primera cuota debe coincidir con el día del mes seleccionado.',
            ]);
        }
    }

    protected function resolverFechaInicialSugerida(): ?string
    {
        $proximaPendiente = Cuota::query()
            ->where('contrato_id', $this->contrato->id)
            ->where('estatus', '!=', 'pagada')
            ->orderBy('fecha_vencimiento')
            ->first(['fecha_vencimiento']);

        $ultimaPagada = Cuota::query()
            ->where('contrato_id', $this->contrato->id)
            ->where('estatus', 'pagada')
            ->orderByDesc('fecha_vencimiento')
            ->first(['fecha_vencimiento']);

        if ($this->nueva_frecuencia === 'semanal') {
            $base = $ultimaPagada?->fecha_vencimiento
                ? Carbon::parse($ultimaPagada->fecha_vencimiento)->startOfDay()
                : ($proximaPendiente?->fecha_vencimiento
                    ? Carbon::parse($proximaPendiente->fecha_vencimiento)->startOfDay()->subWeek()
                    : ($this->contrato->fecha_inicio
                        ? Carbon::parse($this->contrato->fecha_inicio)->startOfDay()->subWeek()
                        : now()->startOfDay()->subWeek()));

            $dow = (int) ($this->nuevo_dia_semana ?? 1);
            if ($dow < 1 || $dow > 7) {
                $dow = 1;
            }

            $fecha = $base->copy();
            $delta = $dow - (int) $fecha->isoWeekday();

            if ($delta <= 0) {
                $delta += 7;
            }

            return $fecha->addDays($delta)->toDateString();
        }

        $day = (int) ($this->nuevo_dia_mes ?? 1);
        if ($day < 1) {
            $day = 1;
        }
        if ($day > 31) {
            $day = 31;
        }

        $base = $proximaPendiente?->fecha_vencimiento
            ? Carbon::parse($proximaPendiente->fecha_vencimiento)->startOfDay()
            : ($this->contrato->fecha_inicio
                ? Carbon::parse($this->contrato->fecha_inicio)->startOfDay()
                : now()->startOfDay());

        $candidate = $base->copy()->startOfMonth();
        $useDay = min($day, $candidate->daysInMonth);
        $candidate->day($useDay)->startOfDay();

        if ($candidate->lt($base)) {
            $candidate = $base->copy()->addMonthNoOverflow()->startOfMonth();
            $useDay = min($day, $candidate->daysInMonth);
            $candidate->day($useDay)->startOfDay();
        }

        return $candidate->toDateString();
    }

    public function getFraccionamientosProperty()
    {
        return Fraccionamiento::query()
            ->orderBy('nombre')
            ->get(['id', 'nombre']);
    }

    public function render()
    {
        return view('livewire.admin.contratos.edit', [
            'clientes' => Cliente::orderBy('nombres')->orderBy('apellidos')->get(),
            'fraccionamientos' => $this->fraccionamientos,
        ])->layout('layouts.app');
    }
}