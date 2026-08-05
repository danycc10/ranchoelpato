<?php

namespace App\Livewire\Admin\Comprobantes;

use App\Models\Cliente;
use App\Models\ComprobantePago;
use App\Models\Contrato;
use App\Models\CuentaBancaria;
use App\Models\Cuota;
use App\Models\FormaPago;
use App\Models\MensajeWhatsappBot;
use App\Models\Recibo;
use App\Models\TipoCobro;
use App\Services\Clientes\ClientePorTelefonoService;
use App\Services\Comprobantes\RegistrarPagoComprobanteService;
use App\Services\Recibos\RecargoCuotaCalculator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Bandeja de comprobantes de pago llegados por WhatsApp.
 *
 * La IA solo pre-llena el formulario: nada se aplica hasta que una persona
 * revisa la imagen contra los campos y confirma. Por eso el detalle muestra la
 * foto y los datos editables lado a lado.
 */
class Index extends Component
{
    use WithPagination;

    protected string $paginationTheme = 'tailwind';

    public string $filtroEstatus = ComprobantePago::ESTATUS_POR_VALIDAR;

    public string $q = '';

    /** Comprobante abierto en el panel de detalle. */
    public ?int $comprobanteId = null;

    // ── Formulario de validación ─────────────────────────────────────────────
    public ?int $cliente_id = null;

    public string $clienteSearch = '';

    public array $clientesResultados = [];

    public bool $mostrarResultadosClientes = false;

    public ?int $contrato_id = null;

    public ?int $cuota_id = null;

    /** @var array<int, int|string> */
    public array $cuotaIds = [];

    /** @var array<int, float|string> Importe de mensualidad asignado manualmente a cada cuota. */
    public array $principalPorCuota = [];

    /** @var array<int, float|string> Recargo asignado manualmente a cada cuota. */
    public array $recargoPorCuota = [];

    /** @var array<int, array{monto:float,dias_atraso:int,mensaje:?string}> */
    public array $recargoSugeridoPorCuota = [];

    public ?int $tipos_cobro_id = null;

    public ?int $forma_pago_id = null;

    public ?int $cuentas_bancarias_id = null;

    public string $monto = '';

    public string $fecha = '';

    public string $referencia = '';

    public string $observaciones = '';

    public bool $fueraDeOrdenAutorizado = false;

    public string $motivoRechazo = '';

    public bool $mostrarRechazo = false;

    protected function queryString(): array
    {
        return [
            'filtroEstatus' => ['except' => ComprobantePago::ESTATUS_POR_VALIDAR],
            'comprobanteId' => ['except' => null],
        ];
    }

    public function mount(): void
    {
        if ($this->comprobanteId) {
            $this->abrir($this->comprobanteId);
        }
    }

    public function updatingFiltroEstatus(): void
    {
        $this->resetPage();
    }

    public function updatingQ(): void
    {
        $this->resetPage();
    }

    public function abrir(int $id): void
    {
        $comprobante = ComprobantePago::with(['cliente', 'contrato.lote', 'recibo', 'recibosGenerados'])->find($id);

        if (! $comprobante) {
            $this->cerrar();

            return;
        }

        $this->vincularClienteAutomaticamente($comprobante);
        $comprobante->load(['cliente', 'contrato.lote', 'recibo', 'recibosGenerados']);

        $this->comprobanteId = $comprobante->id;
        $this->mostrarRechazo = false;
        $this->motivoRechazo = '';
        $this->fueraDeOrdenAutorizado = false;
        $this->resetErrorBag();

        $this->cliente_id = $comprobante->cliente_id;
        $this->clienteSearch = $comprobante->cliente?->nombre_completo ?? '';
        $this->clientesResultados = [];
        $this->mostrarResultadosClientes = false;

        // Se precargan los valores detectados como PROPUESTA editable.
        $this->contrato_id = $comprobante->contrato_id;
        $this->monto = $comprobante->monto_detectado !== null
            ? (string) $comprobante->monto_detectado
            : '';
        $this->fecha = optional($comprobante->fecha_detectada)->toDateString()
            ?? optional($comprobante->recibido_en)->toDateString()
            ?? now()->toDateString();
        $this->referencia = (string) ($comprobante->referencia_detectada ?? '');
        $this->observaciones = '';

        $this->tipos_cobro_id ??= TipoCobro::query()->orderBy('id')->value('id');
        $this->forma_pago_id ??= $this->formaPagoTransferenciaId();

        $this->cuota_id = $comprobante->cuota_id;
        $this->cuotaIds = $comprobante->cuota_id ? [(int) $comprobante->cuota_id] : [];
        $this->principalPorCuota = [];
        $this->recargoPorCuota = [];
        $this->recargoSugeridoPorCuota = [];
        $this->sincronizarDistribucionCuotas();
    }

    public function cerrar(): void
    {
        $this->comprobanteId = null;
        $this->cliente_id = null;
        $this->clienteSearch = '';
        $this->clientesResultados = [];
        $this->mostrarResultadosClientes = false;
        $this->cuota_id = null;
        $this->cuotaIds = [];
        $this->principalPorCuota = [];
        $this->recargoPorCuota = [];
        $this->recargoSugeridoPorCuota = [];
        $this->mostrarRechazo = false;
        $this->resetErrorBag();
    }

    public function updatedContratoId(): void
    {
        $this->cuota_id = null;
        $this->cuotaIds = [];
        $this->principalPorCuota = [];
        $this->recargoPorCuota = [];
        $this->recargoSugeridoPorCuota = [];
        $this->fueraDeOrdenAutorizado = false;

        $comprobante = $this->comprobanteActual();

        if ($comprobante && $this->contrato_id && $this->cliente_id) {
            $contratoValido = Contrato::query()
                ->whereKey($this->contrato_id)
                ->where('cliente_id', $this->cliente_id)
                ->exists();

            if (! $contratoValido) {
                $this->contrato_id = null;
                $this->addError('contrato_id', 'El contrato no pertenece al cliente seleccionado.');

                return;
            }

            $comprobante->update(['contrato_id' => $this->contrato_id]);
        }

    }

    public function updatedCuotaIds(): void
    {
        $this->cuotaIds = collect($this->cuotaIds)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
        $this->cuota_id = $this->cuotaIds[0] ?? null;
        $this->fueraDeOrdenAutorizado = false;
        $this->sincronizarDistribucionCuotas();
        $this->resetErrorBag([
            'cuotaIds',
            'cuotaIds.*',
            'principalPorCuota',
            'recargoPorCuota',
            'distribucion',
            'monto',
        ]);
    }

    public function updatedFormaPagoId(): void
    {
        $this->sincronizarDistribucionCuotas(true);
    }

    public function validarYGenerar(RegistrarPagoComprobanteService $servicio): void
    {
        $comprobante = $this->comprobanteActual();

        if (! $comprobante) {
            return;
        }

        $reglas = [
            'cliente_id' => ['required', 'integer', 'exists:clientes,id'],
            'contrato_id' => [
                'required',
                'integer',
                Rule::exists('contratos', 'id')->where('cliente_id', $this->cliente_id),
            ],
            'cuotaIds' => ['required', 'array', 'min:1'],
            'cuotaIds.*' => [
                'required',
                'integer',
                Rule::exists('cuotas', 'id')->where('contrato_id', $this->contrato_id),
            ],
            'tipos_cobro_id' => ['required', 'integer', 'exists:tipos_cobro,id'],
            'forma_pago_id' => ['required', 'integer', 'exists:formas_pago,id'],
            'cuentas_bancarias_id' => ['nullable', 'integer', 'exists:cuentas_bancarias,id'],
            'monto' => ['required', 'numeric', 'gt:0'],
            'fecha' => ['required', 'date'],
            'referencia' => ['nullable', 'string', 'max:190'],
            'observaciones' => ['nullable', 'string', 'max:500'],
        ];

        foreach ($this->cuotaIds as $cuotaId) {
            $reglas["principalPorCuota.{$cuotaId}"] = ['required', 'numeric', 'gt:0'];
            $reglas["recargoPorCuota.{$cuotaId}"] = ['required', 'numeric', 'min:0'];
        }

        $this->validate($reglas, [], [
            'cliente_id' => 'cliente',
            'contrato_id' => 'contrato',
            'cuotaIds' => 'cuotas',
            'cuotaIds.*' => 'cuota',
            'tipos_cobro_id' => 'tipo de cobro',
            'forma_pago_id' => 'forma de pago',
            'principalPorCuota.*' => 'principal de la cuota',
            'recargoPorCuota.*' => 'recargo de la cuota',
        ]);

        $distribucion = collect($this->cuotaIds)->map(fn ($cuotaId) => [
            'cuota_id' => (int) $cuotaId,
            'monto_principal' => round((float) ($this->principalPorCuota[$cuotaId] ?? 0), 2),
            'monto_recargo' => round((float) ($this->recargoPorCuota[$cuotaId] ?? 0), 2),
        ])->all();

        try {
            $recibos = $servicio->registrarVarios($comprobante, [
                'cuota_ids' => $this->cuotaIds,
                'distribucion' => $distribucion,
                'forma_pago_id' => (int) $this->forma_pago_id,
                'cuentas_bancarias_id' => $this->cuentas_bancarias_id ? (int) $this->cuentas_bancarias_id : null,
                'monto' => (float) $this->monto,
                'referencia' => $this->referencia !== '' ? $this->referencia : null,
                'tipos_cobro_id' => (int) $this->tipos_cobro_id,
                'fecha' => $this->fecha,
                'observaciones' => $this->observaciones !== '' ? $this->observaciones : null,
                'fuera_de_orden_autorizado' => $this->fueraDeOrdenAutorizado,
            ], (int) auth()->id());
        } catch (ValidationException $e) {
            // Las reglas de negocio (orden de cuotas, monto excedido) se muestran
            // en el formulario en vez de reventar la pantalla.
            foreach ($e->errors() as $campo => $mensajes) {
                $this->addError($campo, $mensajes[0]);
            }

            return;
        }

        $this->cerrar();

        $this->dispatch(
            'toast',
            type: 'success',
            message: $recibos->count() === 1
                ? "Recibo {$recibos->first()->folio} generado. Se intentó enviar el PDF por WhatsApp de inmediato."
                : "{$recibos->count()} recibos generados. Se intentó enviar los PDF por WhatsApp de inmediato."
        );

        $this->abrirImpresionRecibos($recibos->pluck('id')->map(fn ($id) => (int) $id)->all());
    }

    /** Abre la misma impresión física individual o por lote que usa Crear recibo. */
    protected function abrirImpresionRecibos(array $reciboIds): void
    {
        $reciboIds = collect($reciboIds)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($reciboIds === []) {
            return;
        }

        if (count($reciboIds) === 1) {
            $recibo = Recibo::query()->find($reciboIds[0]);

            if ($recibo) {
                $this->dispatch(
                    'open-print-tab',
                    url: route('admin.recibos.imprimir', ['recibo' => $recibo->uuid ?: $recibo->id])
                );
            }

            return;
        }

        $token = Str::uuid()->toString();

        cache()->put(
            'print_batch:'.$token,
            [
                'user_id' => auth()->id(),
                'recibo_ids' => $reciboIds,
            ],
            now()->addMinutes(15)
        );

        $this->dispatch(
            'open-print-tab',
            url: route('admin.recibos.print-lote', ['token' => $token])
        );
    }

    public function rechazar(RegistrarPagoComprobanteService $servicio): void
    {
        $comprobante = $this->comprobanteActual();

        if (! $comprobante) {
            return;
        }

        $this->validate([
            'motivoRechazo' => ['required', 'string', 'min:5', 'max:500'],
        ], [], ['motivoRechazo' => 'motivo']);

        $servicio->rechazar($comprobante, $this->motivoRechazo, (int) auth()->id());

        $this->cerrar();
        $this->dispatch('toast', type: 'success', message: 'Comprobante rechazado.');
    }

    /** Vuelve a poner un comprobante en cola para que la IA lo relea. */
    public function reintentarLectura(): void
    {
        $comprobante = $this->comprobanteActual();

        if (! $comprobante || ! $comprobante->tieneArchivo()) {
            return;
        }

        $comprobante->forceFill([
            'estatus' => ComprobantePago::ESTATUS_RECIBIDO,
            'error' => null,
        ])->save();

        $this->cerrar();
        $this->dispatch('toast', type: 'success', message: 'Se releerá en la próxima corrida de comprobantes:extraer.');
    }

    public function comprobanteActual(): ?ComprobantePago
    {
        if (! $this->comprobanteId) {
            return null;
        }

        return ComprobantePago::with(['cliente', 'contrato.lote.fraccionamiento', 'recibo', 'recibosGenerados'])
            ->find($this->comprobanteId);
    }

    /** URL temporal para mostrar la imagen sin exponerla públicamente. */
    public function getImagenUrlProperty(): ?string
    {
        $comprobante = $this->comprobanteActual();

        if (! $comprobante || ! $comprobante->tieneArchivo()) {
            return null;
        }

        $disk = Storage::disk($comprobante->archivo_disk ?: 'private');

        if (! $disk->exists($comprobante->archivo_path)) {
            return null;
        }

        // El disco privado es local: se sirve como data URI para no crear rutas
        // públicas hacia comprobantes de clientes.
        return 'data:'.($comprobante->archivo_mime ?: 'image/jpeg')
            .';base64,'.base64_encode($disk->get($comprobante->archivo_path));
    }

    protected function formaPagoTransferenciaId(): ?int
    {
        return FormaPago::query()
            ->where('nombre', 'like', '%transferencia%')
            ->value('id')
            ?? FormaPago::query()->orderBy('id')->value('id');
    }

    public function updatedClienteSearch(mixed $valor): void
    {
        $termino = trim((string) $valor);
        $this->mostrarResultadosClientes = true;

        if ($this->cliente_id) {
            $clienteActual = Cliente::find($this->cliente_id);

            if (! $clienteActual || $termino !== $clienteActual->nombre_completo) {
                $this->cliente_id = null;
                $this->contrato_id = null;
                $this->cuota_id = null;
                $this->cuotaIds = [];
                $this->principalPorCuota = [];
                $this->recargoPorCuota = [];
                $this->recargoSugeridoPorCuota = [];
                $this->fueraDeOrdenAutorizado = false;
            }
        }

        if (mb_strlen($termino) < 2) {
            $this->clientesResultados = [];

            return;
        }

        $partes = preg_split('/\s+/', $termino, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $digitos = preg_replace('/\D+/', '', $termino) ?: '';

        $this->clientesResultados = Cliente::query()
            ->select(['id', 'nombres', 'apellidos', 'telefono'])
            ->where(function ($query) use ($partes, $digitos) {
                foreach ($partes as $parte) {
                    $query->where(function ($subquery) use ($parte) {
                        $subquery->where('nombres', 'like', "%{$parte}%")
                            ->orWhere('apellidos', 'like', "%{$parte}%");
                    });
                }

                if ($digitos !== '') {
                    $query->orWhere('telefono', 'like', "%{$digitos}%");
                }
            })
            ->orderBy('nombres')
            ->limit(15)
            ->get()
            ->map(fn (Cliente $cliente) => [
                'id' => $cliente->id,
                'label' => $cliente->nombre_completo,
                'telefono' => $cliente->telefono,
            ])
            ->all();
    }

    public function seleccionarClienteDesdeBusqueda(int $clienteId): void
    {
        $comprobante = $this->comprobanteActual();
        $cliente = Cliente::find($clienteId);

        if (! $comprobante || ! $cliente || in_array($comprobante->estatus, [
            ComprobantePago::ESTATUS_VALIDADO,
            ComprobantePago::ESTATUS_RECHAZADO,
        ], true)) {
            return;
        }

        $contratos = Contrato::query()
            ->where('cliente_id', $cliente->id)
            ->orderByDesc('id')
            ->get();

        $this->cliente_id = $cliente->id;
        $this->clienteSearch = $cliente->nombre_completo;
        $this->contrato_id = $contratos->count() === 1 ? $contratos->first()->id : null;
        $this->cuota_id = null;
        $this->cuotaIds = [];
        $this->principalPorCuota = [];
        $this->recargoPorCuota = [];
        $this->recargoSugeridoPorCuota = [];
        $this->fueraDeOrdenAutorizado = false;
        $this->mostrarResultadosClientes = false;
        $this->clientesResultados = [];
        $this->resetErrorBag(['cliente_id', 'contrato_id', 'cuotaIds', 'cuotaIds.*']);

        $comprobante->forceFill([
            'cliente_id' => $cliente->id,
            'contrato_id' => $this->contrato_id,
            'telefono' => $comprobante->telefono ?: ($cliente->telefono ?? ''),
        ])->save();

        MensajeWhatsappBot::query()
            ->where('comprobante_id', $comprobante->id)
            ->update([
                'cliente_id' => $cliente->id,
                'telefono' => $comprobante->telefono ?: $cliente->telefono,
            ]);

        $this->dispatch('toast', type: 'success', message: 'Cliente vinculado al comprobante.');
    }

    protected function vincularClienteAutomaticamente(ComprobantePago $comprobante): void
    {
        if ($comprobante->cliente_id || $comprobante->telefono === '') {
            return;
        }

        $cliente = app(ClientePorTelefonoService::class)->buscarUnico($comprobante->telefono);

        if (! $cliente) {
            return;
        }

        $comprobante->cliente_id = $cliente->id;

        $contratos = $cliente->contratos()
            ->whereIn('estatus', ['activo', 'moroso'])
            ->limit(2)
            ->get();

        if ($contratos->count() === 1) {
            $comprobante->contrato_id = $contratos->first()->id;
        }

        $comprobante->save();

        MensajeWhatsappBot::query()
            ->where('comprobante_id', $comprobante->id)
            ->update(['cliente_id' => $cliente->id]);
    }

    public function render()
    {
        $comprobantes = ComprobantePago::query()
            ->with(['cliente', 'contrato'])
            ->when($this->filtroEstatus !== 'todos', fn ($q) => $q->where('estatus', $this->filtroEstatus))
            ->when(trim($this->q) !== '', function ($q) {
                $t = '%'.trim($this->q).'%';
                $q->where(fn ($s) => $s->where('telefono', 'like', $t)
                    ->orWhere('nombre_whatsapp', 'like', $t)
                    ->orWhere('referencia_detectada', 'like', $t));
            })
            ->orderByRaw("CASE estatus WHEN 'por_validar' THEN 1 WHEN 'recibido' THEN 2 WHEN 'error' THEN 3 WHEN 'sin_archivo' THEN 4 WHEN 'validado' THEN 5 WHEN 'rechazado' THEN 6 ELSE 7 END")
            ->orderByDesc('recibido_en')
            ->paginate(15);

        $comprobante = $this->comprobanteActual();

        $cuotas = $this->contrato_id
            ? Cuota::query()
                ->where('contrato_id', $this->contrato_id)
                ->whereIn('estatus', ['pendiente', 'parcial', 'vencida'])
                ->orderBy('fecha_vencimiento')
                ->orderBy('numero')
                ->get()
            : collect();

        $cuotasSeleccionadas = $cuotas->whereIn('id', collect($this->cuotaIds)->map(fn ($id) => (int) $id));
        $totalPrincipalSeleccionado = round((float) collect($this->principalPorCuota)
            ->only($this->cuotaIds)
            ->sum(fn ($monto) => is_numeric($monto) ? (float) $monto : 0), 2);
        $totalRecargosSeleccionados = round((float) collect($this->recargoPorCuota)
            ->only($this->cuotaIds)
            ->sum(fn ($monto) => is_numeric($monto) ? (float) $monto : 0), 2);
        $totalDistribucion = round($totalPrincipalSeleccionado + $totalRecargosSeleccionados, 2);

        $contratos = $this->cliente_id
            ? Contrato::query()
                ->where('cliente_id', $this->cliente_id)
                ->with('lote.fraccionamiento')
                ->orderByDesc('id')
                ->get()
            : collect();

        return view('livewire.admin.comprobantes.index', [
            'comprobantes' => $comprobantes,
            'comprobante' => $comprobante,
            'cuotas' => $cuotas,
            'cuotasSeleccionadas' => $cuotasSeleccionadas,
            'totalPrincipalSeleccionado' => $totalPrincipalSeleccionado,
            'totalRecargosSeleccionados' => $totalRecargosSeleccionados,
            'totalDistribucion' => $totalDistribucion,
            'contratos' => $contratos,
            'tiposCobro' => TipoCobro::orderBy('nombre')->get(),
            'formasPago' => FormaPago::orderBy('nombre')->get(),
            'cuentas' => CuentaBancaria::orderBy('alias')->get(),
            'conteos' => $this->conteosPorEstatus(),
        ])->layout('layouts.app');
    }

    /**
     * Precarga el saldo principal y el recargo contractual como ayuda. Ambos
     * quedan editables: la persona que revisa decide la distribución final.
     */
    protected function sincronizarDistribucionCuotas(bool $actualizarSugerencias = false): void
    {
        $ids = collect($this->cuotaIds)->map(fn ($id) => (int) $id)->unique()->values();

        if ($ids->isEmpty() || ! $this->contrato_id) {
            $this->principalPorCuota = [];
            $this->recargoPorCuota = [];
            $this->recargoSugeridoPorCuota = [];

            return;
        }

        $cuotas = Cuota::query()
            ->with('contrato.lote.fraccionamiento')
            ->where('contrato_id', $this->contrato_id)
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $principal = [];
        $recargos = [];
        $sugerencias = [];
        $calculadora = app(RecargoCuotaCalculator::class);

        foreach ($ids as $id) {
            $cuota = $cuotas->get($id);

            if (! $cuota) {
                continue;
            }

            $pendiente = round(max(0, (float) $cuota->monto - (float) ($cuota->pagado_total ?? 0)), 2);
            $estado = $calculadora->estado($cuota, $this->forma_pago_id);
            $sugeridoAnterior = (float) ($this->recargoSugeridoPorCuota[$id]['monto'] ?? 0);
            $recargoActual = $this->recargoPorCuota[$id] ?? null;
            $sugerido = round((float) ($estado['recargo_monto'] ?? 0), 2);

            $principal[$id] = $this->principalPorCuota[$id] ?? $pendiente;
            $recargos[$id] = $recargoActual;

            if ($recargoActual === null || ($actualizarSugerencias && abs((float) $recargoActual - $sugeridoAnterior) <= 0.01)) {
                $recargos[$id] = $sugerido;
            }

            $sugerencias[$id] = [
                'monto' => $sugerido,
                'dias_atraso' => (int) ($estado['dias_atraso'] ?? 0),
                'mensaje' => $estado['recargo_mensaje'] ?? null,
            ];
        }

        $this->principalPorCuota = $principal;
        $this->recargoPorCuota = $recargos;
        $this->recargoSugeridoPorCuota = $sugerencias;
    }

    protected function conteosPorEstatus(): array
    {
        return ComprobantePago::query()
            ->selectRaw('estatus, count(*) as total')
            ->groupBy('estatus')
            ->pluck('total', 'estatus')
            ->all();
    }
}
