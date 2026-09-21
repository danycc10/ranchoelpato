<?php

namespace App\Livewire\Admin\Recibos;

use App\Jobs\SendReciboMail;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\CuentaBancaria;
use App\Models\Cuota;
use App\Models\FormaPago;
use App\Models\Lote;
use App\Models\Pago;
use App\Models\Periodo;
use App\Models\PrintAgent;
use App\Models\Recibo;
use App\Models\ReciboPago;
use App\Models\TipoCobro;
use App\Services\Contabilidad\PropietarioContableResolver;
use App\Services\ImageUploadService;
use App\Services\Impresion\PrintJobService;
use App\Services\Recibos\CuotaAntiguedadSelector;
use App\Services\Recibos\PeriodoResolver;
use App\Services\Recibos\RecargoCuotaCalculator;
use App\Services\Recibos\RecargoSeleccionAjustador;
use App\Services\Recibos\ReciboFolioService;
use App\Services\Recibos\ReciboPagoNormalizer;
use App\Services\Recibos\TipoCobroClassifier;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class Crear extends Component
{
    use WithFileUploads;

    public ?int $cuotaId = null; // solo compatibilidad para links viejos

    public array $cuotaIds = [];

    public ?int $cliente_id = null;

    public ?int $contrato_id = null;

    public ?int $lote_id = null;

    public ?int $tipos_cobro_id = null;

    public ?int $forma_pago_id = null;

    public ?int $cuentas_bancarias_id = null;

    public ?int $periodo_id = null;

    public array $pagos = [];

    public string $fecha = '';

    public string $folio = '';

    public float $monto = 0;

    public ?string $observaciones = null;

    public string $metodo = 'efectivo';

    public ?string $referencia = null;

    public array $contratosOptions = [];

    public array $lotesOptions = [];

    public bool $requiereCuentaBancaria = false;

    public array $cuotasOptions = [];

    public bool $asociarACuota = false;

    public ?string $avisoOrdenCuotas = null;

    public bool $showAvisoOrdenCuotasModal = false;

    public bool $cuotaFueraDeOrdenAutorizada = false;

    public bool $tipoEsServicio = false;

    public array $infoContrato = [
        'folio' => null,
        'tipo' => null,
        'enganche' => null,
        'precio_total' => null,
        'saldo' => null,
        'fecha_inicio' => null,
    ];

    public array $infoLote = [
        'clave' => null,
        'fraccionamiento' => null,
        'manzana' => null,
        'lote' => null,
    ];

    public string $clienteSearch = '';

    public array $clientesResultados = [];

    public bool $mostrarResultadosClientes = false;

    public ?int $mensualidadTipoCobroId = null;

    public ?int $anualidadTipoCobroId = null;

    // Compatibilidad con UI previa
    public bool $cuotaVencida = false;

    public bool $cuotaEnGracia = false;

    public int $diasAtraso = 0;

    public int $diasGraciaTotal = 0;

    public float $recargoMonto = 0.0;

    public float $recargoMontoOriginal = 0.0;

    public bool $recargoCondonado = false;

    public bool $recargoFueEditadoManualmente = false;

    public ?string $cuotaFechaVencimiento = null;

    public ?string $cuotaFechaLimite = null;

    public ?string $recargoMensaje = null;

    public string $recargoModo = 'auto';

    public ?float $recargoMontoManual = null;

    public array $cuotasSeleccionadasInfo = [];

    public float $montoTotalSeleccionado = 0.0;

    public float $recargoTotalSeleccionado = 0.0;

    /** Recargo que se cobraría automáticamente, antes del ajuste del cajero. */
    public float $recargoTotalAutomatico = 0.0;

    public ?TemporaryUploadedFile $evidencia = null;

    public ?string $evidenciaPreviewUrl = null;

    public ?int $pagoEvidenciaIndex = null;

    public bool $showPagoEvidenciaModal = false;

    public ?string $pagoEvidenciaPreviewUrl = null;

    public bool $showConfirmRecargoModal = false;

    public bool $guardarConRecargoConfirmado = false;

    public bool $alertaConvenioActiva = false;

    public bool $alertaConvenioAceptada = false;

    public bool $imprimirPendiente = false;

    public ?string $mensajeConfirmacionRecargo = null;

    // Pago independiente del recargo
    public ?int $recargo_forma_pago_id = null;

    public ?int $recargo_cuentas_bancarias_id = null;

    public string $recargo_metodo = 'efectivo';

    public ?string $recargo_referencia = null;

    public bool $recargoRequiereCuentaBancaria = false;

    public bool $showRecargoPagoBox = false;

    public ?TemporaryUploadedFile $recargo_evidencia = null;

    public ?string $recargoEvidenciaPreviewUrl = null;

    public function mount(): void
    {
        $this->fecha = now()->toDateString();
        $this->folio = $this->generarFolio();
        $this->pagos = [$this->nuevoPagoRow()];

        $this->mensualidadTipoCobroId = TipoCobro::query()
            ->where('nombre', 'MENSUALIDAD')
            ->value('id');

        $this->anualidadTipoCobroId = TipoCobro::query()
            ->whereRaw('UPPER(nombre) LIKE ?', ['%ANUAL%'])
            ->orderBy('id')
            ->value('id');

        $incomingCuotaUuid = (string) request()->get('cuota', '');
        $incomingCuotaId = request()->integer('cuota_id') ?: null;
        $tcParam = (string) request()->get('tc', '');

        $incomingResolvedId = null;

        if (trim($incomingCuotaUuid) !== '') {
            $incomingResolvedId = Cuota::query()
                ->where('uuid', trim($incomingCuotaUuid))
                ->value('id');
        } elseif ($incomingCuotaId) {
            $incomingResolvedId = $incomingCuotaId;
        }

        if ($incomingResolvedId) {
            $cuotaSolicitada = Cuota::with('contrato.cliente', 'contrato.lote.fraccionamiento')
                ->findOrFail((int) $incomingResolvedId);

            // Los accesos desde la pantalla de cuotas pueden apuntar al mes visible
            // (por ejemplo, agosto), aunque exista una mensualidad anterior pendiente.
            $cuotaMasAntigua = $this->resolverCuotaPendienteMasAntiguaEquivalente($cuotaSolicitada);

            if ((int) $cuotaSolicitada->id !== (int) $cuotaMasAntigua->id) {
                $this->avisoOrdenCuotas = 'La cuota #'.$cuotaSolicitada->numero
                    .' no es la más antigua. La cuota pendiente más antigua es la #'.$cuotaMasAntigua->numero
                    .' con vencimiento '.$cuotaMasAntigua->fecha_vencimiento?->format('d/m/Y').'.';
                $this->showAvisoOrdenCuotasModal = true;
            }

            // Conservar la cuota solicitada para que el usuario pueda autorizarla
            // expresamente como una excepción desde el modal.
            $cuota = $cuotaSolicitada;
            $incomingResolvedId = (int) $cuotaSolicitada->id;

            $cuotaEsAnualidad = (bool) ($cuota->es_anualidad ?? false)
                || (mb_strtoupper((string) ($cuota->concepto ?? '')) === 'ANUALIDAD');

            if (trim($tcParam) === '' && $cuotaEsAnualidad) {
                $tcParam = 'anualidad';
            }

            $this->asociarACuota = true;
            $this->cuotaId = (int) $incomingResolvedId;
            $this->cuotaIds = [(int) $incomingResolvedId];

            $this->setCliente(
                $cuota->contrato->cliente_id,
                $cuota->contrato->cliente?->nombre_completo ?? '',
                true
            );

            $this->contrato_id = $cuota->contrato_id;

            $tcResolved = $this->resolverTipoCobroDefaultDesdeTc($tcParam);

            if ($tcResolved) {
                $this->tipos_cobro_id = $tcResolved;
            } elseif ($cuotaEsAnualidad && $this->anualidadTipoCobroId) {
                $this->tipos_cobro_id = $this->anualidadTipoCobroId;
            } elseif ($this->mensualidadTipoCobroId) {
                $this->tipos_cobro_id = $this->mensualidadTipoCobroId;
            }

            $this->tipoEsServicio = $this->tipoCobroEsServicio($this->tipos_cobro_id);

            $this->setPeriodoDesdeCuota($cuota->fecha_vencimiento);

            $principalPendiente = max(0, (float) $cuota->monto - (float) ($cuota->pagado_total ?? 0));
            $this->monto = $principalPendiente > 0 ? $principalPendiente : (float) $cuota->monto;

            $this->cargarInfoDesdeContrato();
            $this->cargarLotesPorContrato();
            $this->cargarCuotasPendientes();
            $this->recalcularResumenCuotasSeleccionadas();
            $this->sincronizarDefaultsRecargoDesdePrincipal();
            $this->setMontoDefaultPrimerPago();

            $this->mostrarResultadosClientes = false;
            $this->clientesResultados = [];

            return;
        }

        if ($tcParam !== '') {
            $tcResolved = $this->resolverTipoCobroDefaultDesdeTc($tcParam);

            if ($tcResolved) {
                $this->tipos_cobro_id = $tcResolved;
                $this->tipoEsServicio = $this->tipoCobroEsServicio($this->tipos_cobro_id);

                if ($this->tipoEsServicio) {
                    $this->setPeriodoMesActualSiVacio();
                }
            }
        }

        $this->sincronizarDefaultsRecargoDesdePrincipal();
    }

    public function updated($property, $value): void
    {
        if ($property === 'pagos' || str_starts_with($property, 'pagos.')) {

            if (str_contains($property, '.monto') || $property === 'pagos') {
                $this->autoDistribuirPagos();
            }

            if (preg_match('/^pagos\.(\d+)\.evidencia$/', $property, $matches)) {
                $this->validarEvidenciaPago((int) $matches[1]);
                $this->actualizarPreviewModalEvidenciaPago();
            }

            if (preg_match('/^pagos\.(\d+)\.forma_pago_id$/', $property, $matches)) {
                $index = (int) $matches[1];

                if (! $this->pagoRequiereCuenta($index)) {
                    $this->pagos[$index]['cuentas_bancarias_id'] = null;
                    $this->pagos[$index]['evidencia'] = null;
                }

                if ($this->formaPagoEsEfectivo(data_get($this->pagos, $index.'.forma_pago_id'))) {
                    $this->pagos[$index]['referencia'] = null;
                }

                if ($this->showPagoEvidenciaModal && $this->pagoEvidenciaIndex === $index) {
                    $this->actualizarPreviewModalEvidenciaPago();
                }
            }

            $this->sincronizarCamposPrincipalesDesdePagos();
            $this->limpiarCuentasBancariasInnecesariasEnPagos();
            $this->limpiarReferenciasDePagosSiSonEfectivo();
            $this->actualizarRequiereCuentaBancaria();
            $this->sincronizarDefaultsRecargoDesdePrincipal();
            $this->limpiarEvidenciaRecargoSiNoAplica();
            $this->recalcularResumenCuotasSeleccionadas();

            return;
        }

        if ($property === 'forma_pago_id') {
            $this->actualizarRequiereCuentaBancaria();

            if (! $this->recargo_forma_pago_id) {
                $this->recargo_forma_pago_id = $this->forma_pago_id;
            }

            if (! $this->recargo_cuentas_bancarias_id) {
                $this->recargo_cuentas_bancarias_id = $this->cuentas_bancarias_id;
            }

            if (blank($this->recargo_referencia)) {
                $this->recargo_referencia = $this->referencia;
            }

            if ($this->recargo_metodo === 'efectivo') {
                $this->recargo_metodo = $this->metodo;
            }

            $this->actualizarRequiereCuentaBancariaRecargo();
            $this->limpiarEvidenciaSiNoAplica();
            $this->limpiarEvidenciaRecargoSiNoAplica();
            $this->recalcularResumenCuotasSeleccionadas();
        }

        if ($property === 'cuentas_bancarias_id' && ! $this->recargo_cuentas_bancarias_id) {
            $this->recargo_cuentas_bancarias_id = $this->cuentas_bancarias_id;
            $this->actualizarRequiereCuentaBancariaRecargo();
            $this->limpiarEvidenciaRecargoSiNoAplica();
        }

        if ($property === 'metodo' && $this->recargo_metodo === 'efectivo') {
            $this->recargo_metodo = $this->metodo;
        }

        if ($property === 'referencia' && blank($this->recargo_referencia)) {
            $this->recargo_referencia = $this->referencia;
        }

        if ($property === 'recargo_forma_pago_id') {
            $this->actualizarRequiereCuentaBancariaRecargo();

            if ($this->formaPagoEsEfectivo($this->recargo_forma_pago_id)) {
                $this->recargo_cuentas_bancarias_id = null;
                $this->recargo_referencia = null;
                $this->recargo_metodo = 'efectivo';
            }

            $this->limpiarEvidenciaRecargoSiNoAplica();
        }

        if ($property === 'recargo_cuentas_bancarias_id') {
            $this->limpiarEvidenciaRecargoSiNoAplica();
        }

        if ($property === 'tipos_cobro_id') {
            $this->avisoOrdenCuotas = null;
            $this->showAvisoOrdenCuotasModal = false;
            $this->cuotaFueraDeOrdenAutorizada = false;
            $this->tipoEsServicio = $this->tipoCobroEsServicio($this->tipos_cobro_id);
            $this->asociarACuota = $this->tipoCobroRequiereAsociarCuota($this->tipos_cobro_id);

            if (! empty($this->cuotaIds)) {
                $this->sincronizarPeriodoDesdeCuotasSeleccionadas();
            } elseif ($this->tipoEsServicio) {
                $this->setPeriodoMesActualSiVacio();
            }

            if (! $this->asociarACuota) {
                $this->cuotaId = null;
                $this->cuotaIds = [];
                $this->cuotasOptions = [];
                $this->recalcularResumenCuotasSeleccionadas();

                return;
            }

            if ($this->contrato_id) {
                $this->cargarCuotasPendientes();
            }

            $this->sincronizarPeriodoDesdeCuotasSeleccionadas();

            if (! $this->tipoCobroAplicaRecargoPorAtraso($this->tipos_cobro_id)) {
                $this->recargoModo = 'auto';
                $this->recargoMontoManual = null;
            }

            $this->recalcularResumenCuotasSeleccionadas();
        }

        if ($property === 'contrato_id') {
            $this->avisoOrdenCuotas = null;
            $this->showAvisoOrdenCuotasModal = false;
            $this->cuotaFueraDeOrdenAutorizada = false;
            $this->cuotaId = null;
            $this->cuotaIds = [];
            $this->cuotasOptions = [];

            $this->cargarInfoDesdeContrato();
            $this->cargarLotesPorContrato();

            if ($this->asociarACuota) {
                $this->cargarCuotasPendientes();
                $this->sincronizarPeriodoDesdeCuotasSeleccionadas();
            }

            $this->recalcularResumenCuotasSeleccionadas();
        }

        if ($property === 'periodo_id') {
            return;
        }

        if ($property === 'recargoModo') {
            if ($this->recargoModo === 'manual' && ($this->recargoMontoManual === null || $this->recargoMontoManual === '')) {
                // Arranca en el total automático de toda la selección.
                $this->recargoMontoManual = $this->recargoAutomaticoTotal();
            }

            $this->recalcularResumenCuotasSeleccionadas();
        }

        if ($property === 'recargoMontoManual') {
            if ($this->recargoModo === 'manual') {
                $this->recargoMontoManual = max(0, (float) $this->recargoMontoManual);
                $this->recalcularResumenCuotasSeleccionadas();
            }
        }

        if ($property === 'cuotaIds' || str_starts_with($property, 'cuotaIds.')) {
            $this->sincronizarPeriodoDesdeCuotasSeleccionadas();
            $this->recalcularResumenCuotasSeleccionadas();
            $this->sincronizarDefaultsRecargoDesdePrincipal();
            $this->sincronizarMontoPagosConTotalEsperado();
        }

        if ($property === 'recargo_forma_pago_id' || $property === 'recargo_cuentas_bancarias_id' || $property === 'recargo_referencia') {
            $this->recargoFueEditadoManualmente = true;
        }
    }

    public function updatedCuotaIds(): void
    {
        $this->cuotaIds = collect($this->cuotaIds ?? [])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
        $this->cuotaId = count($this->cuotaIds) === 1 ? $this->cuotaIds[0] : null;

        $this->cuotaFueraDeOrdenAutorizada = false;
        $seleccionFueraDeOrden = $this->seleccionActualFueraDeOrden();

        if ($seleccionFueraDeOrden) {
            $this->avisoOrdenCuotas = 'Seleccionaste una cuota que no es la más antigua. '
                .'Puedes cambiar a la más antigua o continuar como una excepción.';
            $this->showAvisoOrdenCuotasModal = true;

            $this->dispatch(
                'toast',
                type: 'warning',
                message: $this->avisoOrdenCuotas
            );
        } else {
            $this->avisoOrdenCuotas = null;
            $this->showAvisoOrdenCuotasModal = false;
        }

        // Si por cualquier motivo no hay pagos, siempre deja uno
        if (empty($this->pagos) || ! is_array($this->pagos) || count($this->pagos) === 0) {
            $this->pagos = [$this->nuevoPagoRow()];
        }

        $this->sincronizarPeriodoDesdeCuotasSeleccionadas();
        $this->sincronizarCamposPrincipalesDesdePagos();
        $this->actualizarRequiereCuentaBancaria();
        $this->sincronizarDefaultsRecargoDesdePrincipal();
        $this->recalcularResumenCuotasSeleccionadas();
        $this->sincronizarMontoPagosConTotalEsperado();
    }

    public function getMostrarCampoEvidenciaProperty(): bool
    {
        return false;
    }

    public function updatedEvidencia(): void
    {
        // La evidencia principal ya no se usa para recibos nuevos.
        $this->evidenciaPreviewUrl = null;
    }

    protected function validarEvidenciaPago(int $index): void
    {
        $this->validateOnly('pagos.'.$index.'.evidencia', [
            'pagos.'.$index.'.evidencia' => data_get($this->pagos, $index.'.sin_evidencia')
    ? 'nullable'
    : 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:10240',

        ]);
    }

    protected function actualizarPreviewModalEvidenciaPago(): void
    {
        $this->pagoEvidenciaPreviewUrl = null;

        if ($this->pagoEvidenciaIndex === null) {
            return;
        }

        $archivo = data_get($this->pagos, $this->pagoEvidenciaIndex.'.evidencia');

        if (! $archivo instanceof TemporaryUploadedFile) {
            return;
        }

        $ext = strtolower($archivo->getClientOriginalExtension());

        if ($ext === 'pdf') {
            return;
        }

        $this->pagoEvidenciaPreviewUrl = $archivo->temporaryUrl();
    }

    public function abrirModalEvidenciaPago(int $index): void
    {
        if (! array_key_exists($index, $this->pagos)) {
            return;
        }

        $this->pagoEvidenciaIndex = $index;
        $this->showPagoEvidenciaModal = true;
        $this->actualizarPreviewModalEvidenciaPago();
    }

    public function cerrarModalEvidenciaPago(): void
    {
        $this->showPagoEvidenciaModal = false;
        $this->pagoEvidenciaIndex = null;
        $this->pagoEvidenciaPreviewUrl = null;
    }

    public function quitarEvidenciaPagoActual(): void
    {
        if ($this->pagoEvidenciaIndex === null || ! array_key_exists($this->pagoEvidenciaIndex, $this->pagos)) {
            return;
        }

        $this->pagos[$this->pagoEvidenciaIndex]['evidencia'] = null;
        $this->pagoEvidenciaPreviewUrl = null;
    }

    public function pagoTieneEvidencia(int $index): bool
    {
        if (data_get($this->pagos, $index.'.sin_evidencia')) {
            return true;
        }

        return ! empty(data_get($this->pagos, $index.'.evidencia'));
    }

    public function pagoNombreEvidencia(int $index): ?string
    {
        if (data_get($this->pagos, $index.'.sin_evidencia')) {
            return 'Sin evidencia';
        }

        $archivo = data_get($this->pagos, $index.'.evidencia');

        if ($archivo instanceof TemporaryUploadedFile) {
            return $archivo->getClientOriginalName();
        }

        return null;
    }

    public function pagoDebePedirEvidencia(int $index): bool
    {
        return $this->pagoRequiereCuenta($index);
    }

    protected function setCliente(int $clienteId, string $label = '', bool $preservarCuota = false): void
    {
        $this->cliente_id = $clienteId;
        $this->contrato_id = null;
        $this->lote_id = null;

        if (! $preservarCuota) {
            $this->cuotaId = null;
            $this->cuotaIds = [];
        }

        $this->cuotasOptions = [];
        $this->resetInfoContratoYLote();

        if ($label !== '') {
            $this->clienteSearch = $label;
        }

        $this->cargarContratos();

        if (count($this->contratosOptions) === 1) {
            $this->contrato_id = (int) array_key_first($this->contratosOptions);
            $this->cargarInfoDesdeContrato();
            $this->cargarLotesPorContrato();

            if ($this->asociarACuota) {
                $this->cargarCuotasPendientes();
            }
        }
    }

    public function updatedClienteSearch($value): void
    {
        $term = trim((string) $value);
        $this->mostrarResultadosClientes = true;

        if ($term === '') {
            $this->cliente_id = null;
            $this->contrato_id = null;
            $this->lote_id = null;
            $this->contratosOptions = [];
            $this->lotesOptions = [];
            $this->cuotaId = null;
            $this->cuotaIds = [];
            $this->resetInfoContratoYLote();
            $this->clientesResultados = [];

            $this->recalcularResumenCuotasSeleccionadas();

            return;
        }

        if (mb_strlen($term) < 2) {
            $this->clientesResultados = [];

            return;
        }

        $this->clientesResultados = Cliente::query()
            ->select(['id', 'nombres', 'apellidos'])
            ->where(function ($q) use ($term) {
                $q->where('nombres', 'like', "%{$term}%")
                    ->orWhere('apellidos', 'like', "%{$term}%");
            })
            ->orderBy('nombres')
            ->limit(15)
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'label' => trim($c->nombres.' '.$c->apellidos),
            ])
            ->toArray();
    }

    public function seleccionarClienteDesdeBusqueda(int $clienteId): void
    {
        $cliente = Cliente::find($clienteId);
        if (! $cliente) {
            return;
        }

        $this->avisoOrdenCuotas = null;
        $this->showAvisoOrdenCuotasModal = false;
        $this->cuotaFueraDeOrdenAutorizada = false;
        $this->setCliente($clienteId, $cliente->nombre_completo);
        $this->mostrarResultadosClientes = false;
        $this->clientesResultados = [];

        $this->pagos = [$this->nuevoPagoRow()];
        $this->sincronizarCamposPrincipalesDesdePagos();
        $this->recalcularResumenCuotasSeleccionadas();
    }

    public function ocultarResultadosCliente(): void
    {
        $this->mostrarResultadosClientes = false;
    }

    public function cerrarModalAvisoOrdenCuotas(): void
    {
        $this->showAvisoOrdenCuotasModal = false;
    }

    public function usarCuotaMasAntigua(): void
    {
        $cuotaId = array_key_first($this->cuotasOptions);

        if (! $cuotaId) {
            $this->cerrarModalAvisoOrdenCuotas();

            return;
        }

        $this->cuotaIds = [(int) $cuotaId];
        $this->cuotaId = (int) $cuotaId;
        $this->cuotaFueraDeOrdenAutorizada = false;
        $this->avisoOrdenCuotas = null;
        $this->showAvisoOrdenCuotasModal = false;

        $this->sincronizarPeriodoDesdeCuotasSeleccionadas();
        $this->recalcularResumenCuotasSeleccionadas();
        $this->sincronizarDefaultsRecargoDesdePrincipal();
        $this->sincronizarMontoPagosConTotalEsperado();
    }

    public function continuarConCuotaFueraDeOrden(): void
    {
        if (! $this->seleccionActualFueraDeOrden()) {
            $this->cerrarModalAvisoOrdenCuotas();

            return;
        }

        $this->cuotaFueraDeOrdenAutorizada = true;
        $this->showAvisoOrdenCuotasModal = false;
        $this->avisoOrdenCuotas = 'Excepción confirmada: se cobrará la cuota seleccionada aunque no sea la más antigua.';

        $this->dispatch(
            'toast',
            type: 'warning',
            message: $this->avisoOrdenCuotas
        );
    }

    /**
     * Abre la pestaña del ticket. Desde ahí el cajero elige si lo imprime por
     * el navegador o lo manda directo a su impresora con el agente local.
     *
     * @param  array<int, int>  $reciboIds
     * @param  array<int, int>  $adjuntoIds  los de recargo, que van dentro del
     *                                       ticket del recibo principal
     */
    protected function imprimir(array $reciboIds, array $adjuntoIds = []): void
    {
        if (empty($reciboIds)) {
            return;
        }

        // Con equipo emparejado el ticket sale solo en la impresora del cajero.
        if ($this->enviarAlAgenteLocal($reciboIds, $adjuntoIds)) {
            return;
        }

        if (count($reciboIds) === 1) {
            $this->openPrintTab($reciboIds[0]);

            return;
        }

        $this->openPrintBatchTab($reciboIds, $adjuntoIds);
    }

    /**
     * Encola un trabajo por ticket en el agente del cajero.
     *
     * Devuelve false cuando hay que caer al navegador: sin equipo emparejado
     * (el caso normal de quien no lo usa) o cuando no se pudo encolar nada.
     *
     * @param  array<int, int>  $reciboIds
     * @param  array<int, int>  $adjuntoIds
     */
    protected function enviarAlAgenteLocal(array $reciboIds, array $adjuntoIds): bool
    {
        $usuario = auth()->user();

        if (! $usuario || ! PrintAgent::deUsuario($usuario)) {
            return false;
        }

        $adjuntos = array_values(array_map('intval', $adjuntoIds));

        // Los adjuntos no llevan ticket propio: viajan con el principal.
        $principales = array_values(array_diff(array_map('intval', $reciboIds), $adjuntos));

        if (empty($principales)) {
            $principales = array_map('intval', $reciboIds);
            $adjuntos = [];
        }

        $encolados = 0;

        try {
            $cola = app(PrintJobService::class);

            foreach ($principales as $i => $reciboId) {
                // Como en el navegador, los recargos se pegan al primer ticket.
                $cola->queueRecibo($usuario, Recibo::findOrFail($reciboId), $i === 0 ? $adjuntos : []);

                $encolados++;
            }
        } catch (\Throwable $e) {
            Log::warning('No se pudo encolar la impresión con agente local', [
                'recibo_ids' => $reciboIds,
                'encolados' => $encolados,
                'error' => $e->getMessage(),
            ]);

            // Si ya salió algo, abrir la pestaña lo imprimiría dos veces.
            if ($encolados > 0) {
                $this->dispatch(
                    'toast',
                    type: 'warning',
                    message: $encolados.' de '.count($principales).' tickets se enviaron a tu impresora. '
                        .'El resto quedó pendiente: '.$e->getMessage()
                );

                return true;
            }

            $this->dispatch(
                'toast',
                type: 'warning',
                message: $e->getMessage().' Se abrirá el ticket para imprimir desde el navegador.'
            );

            return false;
        }

        $this->dispatch(
            'toast',
            type: 'success',
            message: $encolados === 1
                ? 'Ticket enviado a tu impresora.'
                : $encolados.' tickets enviados a tu impresora.'
        );

        return true;
    }

    protected function openPrintTab(int $reciboId): void
    {
        $uuid = Recibo::whereKey($reciboId)->value('uuid');
        $param = $uuid ?: $reciboId;
        $url = route('admin.recibos.imprimir', ['recibo' => $param]);

        $this->dispatch('open-print-tab', url: $url);
    }

    protected function tipoCobroEsMensualidad(?int $tipoCobroId): bool
    {
        return app(TipoCobroClassifier::class)->esMensualidad($tipoCobroId);
    }

    protected function tipoCobroEsAnualidad(?int $tipoCobroId): bool
    {
        return app(TipoCobroClassifier::class)->esAnualidad($tipoCobroId);
    }

    protected function tipoCobroEsRecargo(?int $tipoCobroId): bool
    {
        return app(TipoCobroClassifier::class)->esRecargo($tipoCobroId);
    }

    protected function tipoCobroEsServicio(?int $tipoCobroId): bool
    {
        return app(TipoCobroClassifier::class)->esServicio($tipoCobroId);
    }

    protected function tipoCobroAplicaRecargoPorAtraso(?int $tipoCobroId): bool
    {
        return app(TipoCobroClassifier::class)->aplicaRecargoPorAtraso($tipoCobroId);
    }

    /** El cajero puede condonar o editar el recargo de la selección completa. */
    public function puedeAjustarRecargo(): bool
    {
        return $this->asociarACuota
            && ! empty($this->cuotaIds)
            && $this->tipoCobroAplicaRecargoPorAtraso($this->tipos_cobro_id);
    }

    protected function tipoCobroRequiereAsociarCuota(?int $tipoCobroId): bool
    {
        return app(TipoCobroClassifier::class)->requiereAsociarCuota($tipoCobroId);
    }

    protected function tipoCobroAfectaSaldoContrato(?int $tipoCobroId): bool
    {
        return app(TipoCobroClassifier::class)->afectaSaldoContrato($tipoCobroId);
    }

    protected function resetInfoContratoYLote(): void
    {
        $this->infoContrato = [
            'folio' => null,
            'tipo' => null,
            'enganche' => null,
            'precio_total' => null,
            'saldo' => null,
            'fecha_inicio' => null,
        ];

        $this->infoLote = [
            'clave' => null,
            'fraccionamiento' => null,
            'manzana' => null,
            'lote' => null,
        ];
    }

    protected function actualizarRequiereCuentaBancaria(): void
    {
        $this->requiereCuentaBancaria = $this->formaPagoRequiereCuenta($this->forma_pago_id);

        if (! $this->requiereCuentaBancaria) {
            $this->cuentas_bancarias_id = null;
        }
    }

    protected function actualizarRequiereCuentaBancariaRecargo(): void
    {
        $this->recargoRequiereCuentaBancaria = $this->formaPagoRequiereCuenta($this->recargo_forma_pago_id);

        if (! $this->recargoRequiereCuentaBancaria || $this->formaPagoEsEfectivo($this->recargo_forma_pago_id)) {
            $this->recargo_cuentas_bancarias_id = null;
            $this->recargo_referencia = null;
        }
    }

    protected function sincronizarDefaultsRecargoDesdePrincipal(): void
    {
        if ($this->recargoFueEditadoManualmente) {
            $this->actualizarRequiereCuentaBancariaRecargo();

            return;
        }

        $this->recargo_forma_pago_id = $this->forma_pago_id;
        $this->recargo_cuentas_bancarias_id = $this->formaPagoEsEfectivo($this->forma_pago_id)
            ? null
            : $this->cuentas_bancarias_id;

        $this->recargo_referencia = $this->formaPagoEsEfectivo($this->forma_pago_id)
            ? null
            : $this->referencia;

        $this->recargo_metodo = $this->formaPagoEsEfectivo($this->forma_pago_id)
            ? 'efectivo'
            : $this->metodo;

        $this->actualizarRequiereCuentaBancariaRecargo();
    }

    protected function formaPagoRequiereCuenta(?int $formaPagoId): bool
    {
        if (! $formaPagoId) {
            return false;
        }

        $fp = FormaPago::find($formaPagoId);

        return (bool) ($fp?->requiere_cuenta);
    }

    protected function formaPagoEsEfectivo(?int $formaPagoId): bool
    {
        if (! $formaPagoId) {
            return false;
        }

        $fp = FormaPago::find($formaPagoId);
        if (! $fp) {
            return false;
        }

        $nombre = mb_strtoupper(trim((string) $fp->nombre));

        return str_contains($nombre, 'EFECTIVO');
    }

    /**
     * Suma del recargo automático de todas las cuotas seleccionadas, sin el
     * ajuste del cajero. Es el punto de partida cuando elige editarlo a mano.
     */
    protected function recargoAutomaticoTotal(): float
    {
        $ids = collect($this->cuotaIds)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->all();

        if ($ids === [] || ! $this->contrato_id) {
            return 0.0;
        }

        $cuotas = Cuota::with('contrato')
            ->whereIn('id', $ids)
            ->where('contrato_id', $this->contrato_id)
            ->get();

        return round((float) $cuotas->sum(
            fn (Cuota $cuota) => (float) ($this->calcularEstadoRecargoCuota($cuota)['recargo_monto'] ?? 0)
        ), 2);
    }

    protected function calcularEstadoRecargoCuota(Cuota $cuota): array
    {
        // El modo elegido por el cajero se aplica sobre la selección completa
        // (ver recargosFinalesPorCuota), no cuota por cuota: si se aplicara
        // aquí, un monto manual se cobraría repetido en cada cuota.
        return app(RecargoCuotaCalculator::class)->estado(
            cuota: $cuota,
            formaPagoId: $this->recargo_forma_pago_id ?: $this->forma_pago_id,
            permiteAjusteUsuario: false,
        );
    }

    /**
     * Recargo final de cada cuota seleccionada, ya con el modo del cajero
     * aplicado y repartido entre ellas.
     *
     * @param  Collection<int, Cuota>  $cuotas
     * @return array<int, array{estado: array, recargo: float, condonado: bool}>
     */
    protected function recargosFinalesPorCuota($cuotas): array
    {
        $estados = [];
        $calculados = [];

        foreach ($cuotas as $cuota) {
            $estado = $this->calcularEstadoRecargoCuota($cuota);

            $estados[$cuota->id] = $estado;
            $calculados[$cuota->id] = (float) ($estado['recargo_monto'] ?? 0);
        }

        $puedeAjustar = $this->tipoCobroAplicaRecargoPorAtraso($this->tipos_cobro_id)
            || $this->tipoCobroEsRecargo($this->tipos_cobro_id);

        $finales = $puedeAjustar
            ? app(RecargoSeleccionAjustador::class)->ajustar(
                $calculados,
                $this->recargoModo,
                $this->recargoMontoManual
            )
            : $calculados;

        $resultado = [];

        foreach ($estados as $cuotaId => $estado) {
            $final = (float) ($finales[$cuotaId] ?? 0);

            $resultado[$cuotaId] = [
                'estado' => $estado,
                'recargo' => $final,
                // Condonado si lo condonó el cajero o si ya venía condonado por
                // las reglas del contrato (día extra por pago en efectivo).
                'condonado' => $final <= 0 && (
                    $this->recargoModo === 'condonar'
                    || (bool) ($estado['recargo_condonado'] ?? false)
                    || (float) ($calculados[$cuotaId] ?? 0) > 0
                ),
            ];
        }

        return $resultado;
    }

    protected function recalcularResumenCuotasSeleccionadas(): void
    {
        $modoActual = $this->recargoModo;
        $montoManualActual = $this->recargoMontoManual;

        $this->cuotasSeleccionadasInfo = [];
        $this->montoTotalSeleccionado = 0.0;
        $this->recargoTotalSeleccionado = 0.0;
        $this->recargoTotalAutomatico = 0.0;

        $this->cuotaVencida = false;
        $this->cuotaEnGracia = false;
        $this->diasAtraso = 0;
        $this->diasGraciaTotal = 0;
        $this->recargoMonto = 0.0;
        $this->recargoMontoOriginal = 0.0;
        $this->recargoCondonado = false;
        $this->cuotaFechaVencimiento = null;
        $this->cuotaFechaLimite = null;
        $this->recargoMensaje = null;

        if (! $this->asociarACuota || empty($this->cuotaIds) || ! $this->contrato_id) {
            return;
        }

        $ids = collect($this->cuotaIds)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (empty($ids)) {
            return;
        }

        $cuotas = Cuota::with('contrato')
            ->whereIn('id', $ids)
            ->where('contrato_id', $this->contrato_id)
            ->orderBy('fecha_vencimiento')
            ->get();

        // El modo del cajero (condonar / monto manual) se aplica sobre toda la
        // selección y se reparte entre las cuotas.
        $recargosFinales = $this->recargosFinalesPorCuota($cuotas);

        foreach ($cuotas as $index => $cuota) {
            $pendientePrincipal = max(0, (float) $cuota->monto - (float) ($cuota->pagado_total ?? 0));

            $estadoRecargo = $recargosFinales[$cuota->id]['estado'];
            $recargoFinal = (float) $recargosFinales[$cuota->id]['recargo'];
            $recargoCondonado = (bool) $recargosFinales[$cuota->id]['condonado'];

            $recargoOriginal = (float) ($estadoRecargo['recargo_monto_original'] ?? 0);

            $item = [
                'id' => $cuota->id,
                'numero' => $cuota->numero,
                'fecha_vencimiento' => $cuota->fecha_vencimiento,
                'principal' => round($pendientePrincipal, 2),
                'recargo' => round($recargoFinal, 2),
                'recargo_condonado' => $recargoCondonado,
                'total' => round($pendientePrincipal + $recargoFinal, 2),
                'cuota_vencida' => (bool) $estadoRecargo['cuota_vencida'],
                'cuota_en_gracia' => (bool) $estadoRecargo['cuota_en_gracia'],
                'dias_atraso' => (int) $estadoRecargo['dias_atraso'],
                'dias_gracia_total' => (int) $estadoRecargo['dias_gracia_total'],
                'cuota_fecha_vencimiento' => $estadoRecargo['cuota_fecha_vencimiento'],
                'cuota_fecha_limite' => $estadoRecargo['cuota_fecha_limite'],
                'recargo_mensaje' => $estadoRecargo['recargo_mensaje'],
                'recargo_monto_original' => $recargoOriginal,
            ];

            $this->cuotasSeleccionadasInfo[] = $item;
            $this->montoTotalSeleccionado += $pendientePrincipal;
            $this->recargoTotalSeleccionado += $recargoFinal;
            $this->recargoTotalAutomatico += (float) ($estadoRecargo['recargo_monto'] ?? 0);

            if ($index === 0) {
                $this->cuotaVencida = $item['cuota_vencida'];
                $this->cuotaEnGracia = $item['cuota_en_gracia'];
                $this->diasAtraso = $item['dias_atraso'];
                $this->diasGraciaTotal = $item['dias_gracia_total'];
                $this->recargoMonto = $item['recargo'];
                $this->recargoMontoOriginal = $item['recargo_monto_original'];
                $this->recargoCondonado = $item['recargo_condonado'];
                $this->cuotaFechaVencimiento = $item['cuota_fecha_vencimiento'];
                $this->cuotaFechaLimite = $item['cuota_fecha_limite'];
                $this->recargoMensaje = $item['recargo_mensaje'];
            }
        }

        $this->montoTotalSeleccionado = round($this->montoTotalSeleccionado, 2);
        $this->recargoTotalSeleccionado = round($this->recargoTotalSeleccionado, 2);
        $this->recargoTotalAutomatico = round($this->recargoTotalAutomatico, 2);

        // Restaurar el modo elegido por el usuario, valga para una cuota o varias.
        $this->recargoModo = $modoActual;
        $this->recargoMontoManual = $modoActual === 'manual'
            ? max(0, (float) $montoManualActual)
            : null;
    }

    protected function cargarInfoDesdeContrato(): void
    {
        if (! $this->contrato_id) {
            $this->lote_id = null;
            $this->resetInfoContratoYLote();

            return;
        }

        $contrato = Contrato::with(['lote.fraccionamiento'])->find($this->contrato_id);
        if (! $contrato) {
            return;
        }

        $this->infoContrato = [
            'folio' => $contrato->folio_contrato ?? null,
            'tipo' => $contrato->tipo ?? ($contrato->tipo_contrato ?? null),
            'enganche' => $contrato->enganche ?? null,
            'precio_total' => $contrato->precio_total ?? null,
            'saldo' => $contrato->saldo_actual ?? ($contrato->saldo ?? null),
            'fecha_inicio' => $contrato->fecha_inicio ?? ($contrato->created_at?->toDateTimeString()),
        ];

        $this->alertaConvenioActiva = (bool) ($contrato->alerta_convenio ?? false);
        $this->alertaConvenioAceptada = false;

        $l = $contrato->lote;
        $this->infoLote = [
            'clave' => $l?->clave,
            'fraccionamiento' => $l?->fraccionamiento?->nombre,
            'manzana' => $l?->manzana,
            'lote' => $l?->lote,
        ];
    }

    protected function resolverTipoCobroDefaultDesdeTc(string $tc): ?int
    {
        $tc = mb_strtolower(trim($tc));
        if ($tc === '') {
            return null;
        }

        if ($tc === 'mensualidad') {
            return TipoCobro::query()->where('nombre', 'MENSUALIDAD')->value('id') ?: null;
        }

        if ($tc === 'anualidad') {
            $id = TipoCobro::query()->where('nombre', 'ANUALIDAD')->value('id');
            if ($id) {
                return (int) $id;
            }

            $id = TipoCobro::query()
                ->whereRaw('UPPER(nombre) LIKE ?', ['%ANUAL%'])
                ->orderBy('id')
                ->value('id');

            return $id ? (int) $id : null;
        }

        $map = [
            'agua' => 'SERVICIO - AGUA',
            'electricidad' => 'SERVICIO - ELECTRICIDAD',
            'fosa' => 'SERVICIO - FOSA',
            'limpieza' => 'LIMPIEZA',
        ];

        if (! isset($map[$tc])) {
            return null;
        }

        return TipoCobro::query()
            ->where('nombre', $map[$tc])
            ->value('id') ?: null;
    }

    protected function setPeriodoMesActualSiVacio(): void
    {
        if ($this->periodo_id) {
            return;
        }

        $this->periodo_id = $this->resolverPeriodoIdDesdeFecha(now());
    }

    protected function cargarCuotasPendientes(): void
    {
        $this->cuotasOptions = [];

        if (! $this->contrato_id) {
            $this->cuotaIds = [];

            return;
        }

        $query = Cuota::query()
            ->where('contrato_id', $this->contrato_id)
            ->where('estatus', '!=', 'pagada')
            ->orderBy('fecha_vencimiento')
            ->orderBy('numero')
            ->orderBy('id');

        $query = $this->scopeCuotasSegunTipoCobro($query);

        $cuotas = $query->get();

        $this->cuotasOptions = $cuotas->mapWithKeys(function ($c) {
            $pendientePrincipal = max(0, (float) $c->monto - (float) ($c->pagado_total ?? 0));

            $label = 'Cuota #'.$c->numero
                .' | Vence: '.Carbon::parse($c->fecha_vencimiento)->format('d/m/Y')
                .' | Pendiente: $'.number_format($pendientePrincipal, 2);

            $esAnual = (bool) ($c->es_anualidad ?? false)
                || (mb_strtoupper((string) ($c->concepto ?? '')) === 'ANUALIDAD');

            if ($esAnual) {
                $label = 'ANUALIDAD — '.$label;
            }

            return [$c->id => $label];
        })->toArray();

        $this->cuotaIds = array_values(array_filter(
            $this->cuotaIds,
            fn ($id) => array_key_exists((int) $id, $this->cuotasOptions)
        ));

        if ($this->cuotasOptions !== []) {
            if ($this->cuotaIds === []) {
                $this->cuotaIds = [(int) array_key_first($this->cuotasOptions)];
            }
        }

        if (count($this->cuotaIds) === 1) {
            $this->cuotaId = (int) $this->cuotaIds[0];
        } else {
            $this->cuotaId = null;
        }

        $this->cargarInfoDesdeContrato();
        $this->recalcularResumenCuotasSeleccionadas();
    }

    protected function autoseleccionarCuotaPorPeriodo(): void
    {
        if (! $this->contrato_id || ! $this->periodo_id) {
            return;
        }

        $periodo = Periodo::find($this->periodo_id);
        if (! $periodo?->nombre) {
            return;
        }

        [$mesNum, $anioNum] = $this->parsePeriodoNombre($periodo->nombre);
        if (! $mesNum || ! $anioNum) {
            return;
        }

        $query = Cuota::query()
            ->where('contrato_id', $this->contrato_id)
            ->where('estatus', '!=', 'pagada')
            ->orderBy('fecha_vencimiento');

        $query = $this->scopeCuotasSegunTipoCobro($query);

        if (! $this->tipoCobroEsAnualidad($this->tipos_cobro_id)) {
            $query->whereYear('fecha_vencimiento', $anioNum)
                ->whereMonth('fecha_vencimiento', $mesNum);
        }

        $cuota = $query->first();

        if ($cuota) {
            $this->cuotaIds = [$cuota->id];
            $this->cuotaId = $cuota->id;
            $this->recalcularResumenCuotasSeleccionadas();
        }
    }

    protected function parsePeriodoNombre(string $nombre): array
    {
        $txt = mb_strtoupper(trim($nombre));

        $map = [
            'ENERO' => 1,
            'FEBRERO' => 2,
            'MARZO' => 3,
            'ABRIL' => 4,
            'MAYO' => 5,
            'JUNIO' => 6,
            'JULIO' => 7,
            'AGOSTO' => 8,
            'SEPTIEMBRE' => 9,
            'SETIEMBRE' => 9,
            'OCTUBRE' => 10,
            'NOVIEMBRE' => 11,
            'DICIEMBRE' => 12,
        ];

        $mes = null;
        foreach ($map as $k => $v) {
            if (str_contains($txt, $k)) {
                $mes = $v;
                break;
            }
        }

        preg_match('/(20\d{2})/', $txt, $m);
        $anio = isset($m[1]) ? (int) $m[1] : null;

        return [$mes, $anio];
    }

    protected function cargarContratos(): void
    {
        $this->contratosOptions = [];
        $this->lotesOptions = [];

        if (! $this->cliente_id) {
            return;
        }

        $contratos = Contrato::with('lote.fraccionamiento')
            ->where('cliente_id', $this->cliente_id)
            ->orderBy('folio_contrato')
            ->get();

        $this->contratosOptions = $contratos->mapWithKeys(function ($c) {
            $l = $c->lote;
            $txtLote = $l
                ? ($l->fraccionamiento?->nombre.' | MZ '.$l->manzana.' LT '.$l->lote.' | '.$l->clave)
                : 'Sin lote';

            return [$c->id => $c->folio_contrato.' — '.$txtLote];
        })->toArray();
    }

    protected function cargarLotesPorContrato(): void
    {
        $this->lotesOptions = [];
        $this->lote_id = null;

        if (! $this->contrato_id) {
            return;
        }

        $contrato = Contrato::with('lote.fraccionamiento')->find($this->contrato_id);
        if (! $contrato) {
            return;
        }

        if ($contrato->lote) {
            $this->lotesOptions = [
                $contrato->lote_id => $contrato->lote->clave,
            ];
            $this->lote_id = $contrato->lote_id;
        }
    }

    protected function nuevoPagoRow(): array
    {
        return [
            'forma_pago_id' => null,
            'cuentas_bancarias_id' => null,
            'monto' => null,
            'referencia' => null,
            'evidencia' => null,
            'sin_evidencia' => false,
        ];
    }

    public function agregarPago(): void
    {
        $this->pagos[] = $this->nuevoPagoRow();
        $this->sincronizarCamposPrincipalesDesdePagos();
    }

    public function eliminarPago(int $index): void
    {
        if (count($this->pagos) <= 1) {
            return;
        }

        unset($this->pagos[$index]);
        $this->pagos = array_values($this->pagos);

        if ($this->showPagoEvidenciaModal && $this->pagoEvidenciaIndex !== null) {
            if ($this->pagoEvidenciaIndex === $index) {
                $this->cerrarModalEvidenciaPago();
            } elseif ($this->pagoEvidenciaIndex > $index) {
                $this->pagoEvidenciaIndex--;
            }
        }

        $this->sincronizarCamposPrincipalesDesdePagos();
        $this->actualizarRequiereCuentaBancaria();
        $this->sincronizarDefaultsRecargoDesdePrincipal();
    }

    protected function limpiarCuentasBancariasInnecesariasEnPagos(): void
    {
        foreach ($this->pagos as $i => $pago) {
            $formaPagoId = isset($pago['forma_pago_id']) ? (int) $pago['forma_pago_id'] : null;

            if (! $this->formaPagoRequiereCuenta($formaPagoId)) {
                $this->pagos[$i]['cuentas_bancarias_id'] = null;
            }
        }
    }

    protected function limpiarReferenciasDePagosSiSonEfectivo(): void
    {
        foreach ($this->pagos as $i => $pago) {
            $formaPagoId = isset($pago['forma_pago_id']) ? (int) $pago['forma_pago_id'] : null;

            if ($this->formaPagoEsEfectivo($formaPagoId)) {
                $this->pagos[$i]['referencia'] = null;
            }
        }
    }

    public function pagoRequiereCuenta(int $index): bool
    {
        $formaPagoId = data_get($this->pagos, $index.'.forma_pago_id');

        return $this->formaPagoRequiereCuenta($formaPagoId ? (int) $formaPagoId : null);
    }

    protected function sincronizarCamposPrincipalesDesdePagos(): void
    {
        $primerPago = $this->pagos[0] ?? [];

        $this->forma_pago_id = isset($primerPago['forma_pago_id']) && $primerPago['forma_pago_id'] !== ''
            ? (int) $primerPago['forma_pago_id']
            : null;

        $this->cuentas_bancarias_id = isset($primerPago['cuentas_bancarias_id']) && $primerPago['cuentas_bancarias_id'] !== ''
            ? (int) $primerPago['cuentas_bancarias_id']
            : null;

        $this->referencia = isset($primerPago['referencia']) && trim((string) $primerPago['referencia']) !== ''
            ? trim((string) $primerPago['referencia'])
            : null;

        $this->metodo = $this->resolverMetodoDesdeFormaPago($this->forma_pago_id);

        if (! $this->requiereCuentaBancaria) {
            $this->cuentas_bancarias_id = null;
        }
    }

    protected function normalizarPagosPrincipales(?float $montoEsperado = null): array
    {
        return app(ReciboPagoNormalizer::class)->normalizar(
            pagos: $this->pagos,
            montoEsperado: $montoEsperado,
            formaPagoRequiereCuenta: fn (?int $formaPagoId): bool => $this->formaPagoRequiereCuenta($formaPagoId),
        );
    }

    protected function resolverMetodoDesdeFormaPago(?int $formaPagoId): string
    {
        return app(ReciboPagoNormalizer::class)->metodoDesdeFormaPago($formaPagoId);
    }

    protected function crearDetallesPagoRecibo(
        Recibo $recibo,
        array $pagos,
        ImageUploadService $imageUploadService
    ): void {
        foreach ($pagos as $index => $pago) {
            $evidenciaData = null;

            if (! empty($pago['evidencia'])) {
                $evidenciaData = $imageUploadService->saveOptimized(
                    file: $pago['evidencia'],
                    folder: 'recibos/evidencias',
                    maxWidth: 1600,
                    maxHeight: 1600,
                    quality: 72,
                    referenceFolder: $recibo->folio.'/pago-'.($index + 1)
                );
            }

            ReciboPago::create([
                'recibo_id' => $recibo->id,
                'forma_pago_id' => $pago['forma_pago_id'],
                'cuenta_bancaria_id' => $pago['cuentas_bancarias_id'] ?? null,
                'monto' => round((float) ($pago['monto'] ?? 0), 2),
                'fecha_efectiva' => $recibo->fecha?->format('Y-m-d'),
                'referencia' => $pago['referencia'] ?? null,
                'observaciones' => null,
                'evidencia_path' => $evidenciaData['path'] ?? null,
                'evidencia_disk' => $evidenciaData['disk'] ?? null,
                'evidencia_mime' => $evidenciaData['mime'] ?? null,
                'evidencia_size' => $evidenciaData['size'] ?? null,
                'orden' => (int) ($pago['orden'] ?? ($index + 1)),
                'capturado_por_user_id' => auth()->id(),
            ]);
        }
    }

    protected function rules(): array
    {
        return [
            'cliente_id' => ['required', 'exists:clientes,id'],
            'contrato_id' => ['required', 'exists:contratos,id'],
            'lote_id' => ['required', 'exists:lotes,id'],

            'cuotaIds' => [$this->asociarACuota ? 'required' : 'nullable', 'array'],
            'cuotaIds.*' => ['integer', 'exists:cuotas,id'],

            'tipos_cobro_id' => ['required', 'exists:tipos_cobro,id'],

            'pagos' => ['required', 'array', 'min:1'],
            'pagos.*.forma_pago_id' => ['required', 'exists:formas_pago,id'],
            'pagos.*.cuentas_bancarias_id' => ['nullable', 'exists:cuentas_bancarias,id'],
            'pagos.*.monto' => ['required', 'numeric', 'min:0.01'],
            'pagos.*.referencia' => ['nullable', 'string', 'max:255'],
            'pagos.*.evidencia' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],

            // Compatibilidad mientras convive la estructura vieja
            'forma_pago_id' => ['nullable', 'exists:formas_pago,id'],
            'cuentas_bancarias_id' => ['nullable', 'exists:cuentas_bancarias,id'],

            'periodo_id' => ['nullable', 'exists:periodos,id'],

            'fecha' => ['required', 'date'],
            'monto' => [$this->asociarACuota ? 'nullable' : 'required', 'numeric', 'min:0'],
            'observaciones' => ['nullable', 'string', 'max:255'],

            'metodo' => ['required', 'in:efectivo,transferencia,oxxo,stripe'],
            'referencia' => ['nullable', 'string', 'max:255'],

            'recargo_forma_pago_id' => [
                $this->debeCapturarPagoRecargoSeparado() ? 'required' : 'nullable',
                'exists:formas_pago,id',
            ],
            'recargo_cuentas_bancarias_id' => [
                $this->debeCapturarPagoRecargoSeparado() && $this->recargoRequiereCuentaBancaria ? 'required' : 'nullable',
                'exists:cuentas_bancarias,id',
            ],
            'recargo_metodo' => [
                $this->debeCapturarPagoRecargoSeparado() ? 'required' : 'nullable',
                'in:efectivo,transferencia,oxxo,stripe',
            ],
            'recargo_referencia' => ['nullable', 'string', 'max:255'],

            'recargo_evidencia' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:10240',
        ];
    }

    protected function requiereCuentaBancancaria(): bool
    {
        return $this->requiereCuentaBancaria === true;
    }

    public function debeCapturarPagoRecargoSeparado(): bool
    {
        return $this->tipoCobroAplicaRecargoPorAtraso($this->tipos_cobro_id)
            && $this->asociarACuota
            && ! empty($this->cuotaIds)
            && (float) $this->recargoTotalSeleccionado > 0;
    }

    protected function generarFolio(?int $anio = null): string
    {
        return app(ReciboFolioService::class)->generar($anio);
    }

    protected function restarSaldoContrato(float $monto): void
    {
        if (! $this->contrato_id) {
            return;
        }

        $contrato = Contrato::find($this->contrato_id);
        if (! $contrato) {
            return;
        }

        $campo = isset($contrato->saldo_actual) ? 'saldo_actual' : 'saldo';
        if (! isset($contrato->{$campo})) {
            return;
        }

        $contrato->{$campo} = max(0, (float) $contrato->{$campo} - (float) $monto);
        $contrato->save();

        $this->cargarInfoDesdeContrato();
    }

    protected function actualizarEstatusContratoSiEstaLiquidado(int $contratoId): void
    {
        $contrato = Contrato::find($contratoId);
        if (! $contrato) {
            return;
        }

        $tieneCuotasPendientes = Cuota::query()
            ->where('contrato_id', $contratoId)
            ->where('estatus', '!=', 'pagada')
            ->exists();

        if ($tieneCuotasPendientes) {
            return;
        }

        $contrato->estatus = 'liquidado';

        if (isset($contrato->saldo_actual)) {
            $contrato->saldo_actual = 0;
        } elseif (isset($contrato->saldo)) {
            $contrato->saldo = 0;
        }

        $contrato->liquidado_at ??= now();

        $contrato->save();

        $this->cargarInfoDesdeContrato();
    }

    protected function setPeriodoDesdeCuota($fechaVencimiento): void
    {
        $this->periodo_id = $this->resolverPeriodoIdDesdeFecha($fechaVencimiento);
    }

    protected function scopeCuotasSegunTipoCobro($query)
    {
        if ($this->tipoCobroEsAnualidad($this->tipos_cobro_id)) {
            return $query->where(function ($q) {
                $q->where('es_anualidad', 1)
                    ->orWhereRaw("UPPER(COALESCE(concepto,'')) = 'ANUALIDAD'");
            });
        }

        return $query->where(function ($q) {
            $q->whereNull('es_anualidad')
                ->orWhere('es_anualidad', 0);
        })->where(function ($q) {
            $q->whereNull('concepto')
                ->orWhereRaw("UPPER(COALESCE(concepto,'')) <> 'ANUALIDAD'");
        });
    }

    protected function resolverCuotaPendienteMasAntiguaEquivalente(Cuota $cuota): Cuota
    {
        $esAnualidad = (bool) ($cuota->es_anualidad ?? false)
            || mb_strtoupper((string) ($cuota->concepto ?? '')) === 'ANUALIDAD';

        $query = Cuota::with('contrato.cliente', 'contrato.lote.fraccionamiento')
            ->where('contrato_id', $cuota->contrato_id)
            ->where('estatus', '!=', 'pagada')
            ->orderBy('fecha_vencimiento')
            ->orderBy('numero')
            ->orderBy('id');

        if ($esAnualidad) {
            $query->where(function ($q) {
                $q->where('es_anualidad', 1)
                    ->orWhereRaw("UPPER(COALESCE(concepto,'')) = 'ANUALIDAD'");
            });
        } else {
            $query->where(function ($q) {
                $q->whereNull('es_anualidad')
                    ->orWhere('es_anualidad', 0);
            })->where(function ($q) {
                $q->whereNull('concepto')
                    ->orWhereRaw("UPPER(COALESCE(concepto,'')) <> 'ANUALIDAD'");
            });
        }

        return $query->first() ?? $cuota;
    }

    protected function seleccionActualFueraDeOrden(): bool
    {
        $idsOrdenados = array_map('intval', array_keys($this->cuotasOptions));

        if ($idsOrdenados === [] || $this->cuotaIds === []) {
            return false;
        }

        return ! app(CuotaAntiguedadSelector::class)
            ->seleccionEsPrefijo($idsOrdenados, $this->cuotaIds);
    }

    protected function obtenerTipoCobroRecargoId(): ?int
    {
        $id = TipoCobro::query()
            ->whereRaw('UPPER(nombre) LIKE ?', ['%RECARGO%'])
            ->value('id');

        return $id ?: null;
    }

    /**
     * Tipo de cobro del recibo de recargo.
     *
     * El recargo de un servicio (luz, agua, fosa) se queda bajo el mismo tipo
     * de cobro que el servicio, para que herede su propietario contable y el
     * ingreso del servicio salga completo en los reportes. El de las
     * mensualidades sí usa el tipo RECARGO, como siempre.
     */
    protected function tipoCobroParaReciboRecargo(): ?int
    {
        if ($this->tipoCobroEsServicio($this->tipos_cobro_id)) {
            return $this->tipos_cobro_id;
        }

        return $this->obtenerTipoCobroRecargoId() ?: $this->tipos_cobro_id;
    }

    protected function crearReciboConFolioSeguro(array $data, int $maxIntentos = 10): Recibo
    {
        return app(ReciboFolioService::class)->crearRecibo($data, $maxIntentos);
    }

    protected function getConteoRecibosEsperados(): array
    {
        $principales = 0;
        $recargos = 0;

        foreach ($this->cuotasSeleccionadasInfo as $item) {
            $principal = (float) ($item['principal'] ?? 0);
            $recargo = (float) ($item['recargo'] ?? 0);

            if (! $this->tipoCobroEsRecargo($this->tipos_cobro_id) && $principal > 0) {
                $principales++;
            }

            if (
                ($this->tipoCobroAplicaRecargoPorAtraso($this->tipos_cobro_id) || $this->tipoCobroEsRecargo($this->tipos_cobro_id))
                && $recargo > 0
            ) {
                $recargos++;
            }
        }

        return [
            'principales' => $principales,
            'recargos' => $recargos,
            'total' => $principales + $recargos,
        ];
    }

    public function guardar(bool $imprimir = false, ?ImageUploadService $imageUploadService = null): void
    {
        if ($this->alertaConvenioActiva && ! $this->alertaConvenioAceptada) {
            return;
        }

        if (
            ! $this->guardarConRecargoConfirmado &&
            $this->recargoModo !== 'condonar' &&
            $this->debeConfirmarRecargoAntesDeGuardar()
        ) {
            $this->imprimirPendiente = $imprimir;
            $this->mensajeConfirmacionRecargo = $this->construirMensajeConfirmacionRecargo();
            $this->showConfirmRecargoModal = true;

            return;
        }

        $this->sincronizarPeriodoDesdeCuotasSeleccionadas();

        $this->validate();

        $montoEsperadoPagos = ! $this->asociarACuota
            ? (float) $this->monto
            : (
                $this->tipoCobroEsRecargo($this->tipos_cobro_id)
                ? (float) $this->recargoTotalSeleccionado
                : (float) $this->montoTotalSeleccionado
            );

        $pagosPrincipales = $this->normalizarPagosPrincipales($montoEsperadoPagos);

        if ($this->tipoCobroEsRecargo($this->tipos_cobro_id) && empty($this->cuotaIds)) {
            throw ValidationException::withMessages([
                'cuotaIds' => 'Debes seleccionar al menos una cuota para generar recargos.',
            ]);
        }

        $imageUploadService ??= app(ImageUploadService::class);

        $recibosParaEnviar = [];
        $recibosParaImprimir = [];
        // Recibos que se imprimen dentro del ticket del principal (los recargos).
        $recibosAdjuntosParaImprimir = [];

        DB::transaction(function () use (&$recibosParaEnviar, &$recibosParaImprimir, &$recibosAdjuntosParaImprimir, $imageUploadService, $pagosPrincipales) {
            $fecha = Carbon::parse($this->fecha);

            $obtenerSaldoContrato = function (int $contratoId): float {
                return (float) Contrato::query()
                    ->whereKey($contratoId)
                    ->lockForUpdate()
                    ->value('saldo_actual');
            };

            $actualizarSaldoPosteriorRecibo = function ($recibo, int $contratoId): void {
                $saldoPosterior = (float) Contrato::query()
                    ->whereKey($contratoId)
                    ->value('saldo_actual');

                $recibo->saldo_posterior = round(max(0, $saldoPosterior), 2);
                $recibo->save();
            };

            if (! $this->asociarACuota) {
                $afectaSaldoContrato = $this->tipoCobroAfectaSaldoContrato($this->tipos_cobro_id);

                $saldoAnterior = $afectaSaldoContrato
                    ? $obtenerSaldoContrato((int) $this->contrato_id)
                    : null;

                $propietarioContablePrincipalId = $this->resolverPropietarioContableId(
                    $this->tipos_cobro_id,
                    $this->getFormaPagoPrincipal($pagosPrincipales)
                );

                $recibo = $this->crearReciboConFolioSeguro([
                    'fecha' => $fecha->toDateString(),
                    'anio' => (int) $fecha->format('Y'),

                    'semana_pago' => null,
                    'semana_del_anio' => (int) $fecha->format('W'),
                    'mes_del_anio' => (int) $fecha->format('m'),

                    'cliente_id' => $this->cliente_id,
                    'contrato_id' => $this->contrato_id,
                    'lote_id' => $this->lote_id,
                    'cuota_id' => null,

                    'tipos_cobro_id' => $this->tipos_cobro_id,
                    'forma_pago_id' => $pagosPrincipales[0]['forma_pago_id'] ?? $this->forma_pago_id,
                    'cuentas_bancarias_id' => $pagosPrincipales[0]['cuentas_bancarias_id'] ?? $this->cuentas_bancarias_id,
                    'periodo_id' => $this->periodo_id,

                    'propietario_contable_id' => $propietarioContablePrincipalId,

                    'monto' => $this->monto,
                    'saldo_anterior' => $afectaSaldoContrato ? round((float) $saldoAnterior, 2) : null,
                    'saldo_posterior' => null,

                    'observaciones' => $this->observaciones,
                    'capturado_por_user_id' => auth()->id(),

                    'evidencia_path' => null,
                    'evidencia_disk' => null,
                    'evidencia_mime' => null,
                    'evidencia_size' => null,
                ]);

                $this->crearDetallesPagoRecibo($recibo, $pagosPrincipales, $imageUploadService);

                $recibosParaEnviar[] = $recibo->id;
                $recibosParaImprimir[] = $recibo->id;

                Pago::create([
                    'contrato_id' => $this->contrato_id,
                    'cuota_id' => null,
                    'recibo_id' => $recibo->id,
                    'monto' => $this->monto,
                    'metodo' => $this->metodo,
                    'referencia' => $this->referencia,
                    'estatus' => 'confirmado',
                    'fecha_pago' => now(),
                ]);

                if ($afectaSaldoContrato) {
                    $this->restarSaldoContrato((float) $this->monto);
                    $actualizarSaldoPosteriorRecibo($recibo, (int) $this->contrato_id);
                } else {
                    $this->cargarInfoDesdeContrato();
                }

                activity()
                    ->causedBy(auth()->user())
                    ->performedOn($recibo)
                    ->withProperties([
                        'folio' => $recibo->folio,
                        'monto' => $recibo->monto,
                        'cliente_id' => $recibo->cliente_id,
                        'contrato_id' => $recibo->contrato_id,
                        'evidencia_path' => $recibo->evidencia_path,
                        'saldo_anterior' => $recibo->saldo_anterior,
                        'saldo_posterior' => $recibo->saldo_posterior,
                    ])
                    ->log('Recibo creado');

                $this->dispatch('toast', type: 'success', message: 'Recibo creado correctamente.');

                $this->limpiarFormularioDespuesDeGuardar();

                return;
            }

            $cuotaIds = collect($this->cuotaIds)
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            if ($cuotaIds->isEmpty()) {
                throw ValidationException::withMessages([
                    'cuotaIds' => 'Debes seleccionar al menos una cuota.',
                ]);
            }

            $cuotasPendientesQuery = Cuota::query()
                ->where('contrato_id', $this->contrato_id)
                ->where('estatus', '!=', 'pagada');

            $cuotasPendientesQuery = $this->scopeCuotasSegunTipoCobro($cuotasPendientesQuery);

            $idsPendientesOrdenados = $cuotasPendientesQuery
                ->lockForUpdate()
                ->orderBy('fecha_vencimiento')
                ->orderBy('numero')
                ->orderBy('id')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if (! app(CuotaAntiguedadSelector::class)->seleccionPermitida(
                $idsPendientesOrdenados,
                $cuotaIds->all(),
                $this->cuotaFueraDeOrdenAutorizada
            )) {
                throw ValidationException::withMessages([
                    'cuotaIds' => 'Debes cobrar la cuota más antigua o confirmar la excepción para continuar con otra cuota.',
                ]);
            }

            $cuotas = Cuota::with('contrato')
                ->whereIn('id', $cuotaIds)
                ->where('contrato_id', $this->contrato_id)
                ->where('estatus', '!=', 'pagada')
                ->lockForUpdate()
                ->orderBy('fecha_vencimiento')
                ->get();

            if ($cuotas->count() !== $cuotaIds->count()) {
                throw ValidationException::withMessages([
                    'cuotaIds' => 'Hay cuotas inválidas o que no pertenecen al contrato seleccionado.',
                ]);
            }

            // Un solo recibo agrupa todas las cuotas seleccionadas: el desglose
            // por cuota vive en la tabla `pagos` (un renglón por cuota) y el
            // ticket lo reconstruye desde ahí.
            $datosCuotas = [];
            $totalPrincipal = 0.0;
            $totalRecargo = 0.0;

            $esCobroDeRecargo = $this->tipoCobroEsRecargo($this->tipos_cobro_id);

            // El modo del cajero (automático, condonar o monto manual) ya viene
            // aplicado y repartido entre las cuotas seleccionadas.
            $recargosFinales = $this->recargosFinalesPorCuota($cuotas);

            $cobraRecargo = $esCobroDeRecargo
                || $this->tipoCobroAplicaRecargoPorAtraso($this->tipos_cobro_id);

            foreach ($cuotas as $cuota) {
                $pendientePrincipal = max(0, (float) $cuota->monto - (float) ($cuota->pagado_total ?? 0));
                $recargoFinal = (float) $recargosFinales[$cuota->id]['recargo'];

                $aplicaRecargo = $cobraRecargo && $recargoFinal > 0;

                $principal = $esCobroDeRecargo ? 0.0 : $pendientePrincipal;

                $datosCuotas[] = [
                    'cuota' => $cuota,
                    'principal' => $principal,
                    'recargo' => $aplicaRecargo ? $recargoFinal : 0.0,
                    // El periodo se resuelve una vez por cuota: lo comparten el
                    // recibo principal y el de recargo.
                    'periodo_id' => $this->resolverPeriodoIdDesdeFecha($cuota->fecha_vencimiento),
                ];

                $totalPrincipal += $principal;
                $totalRecargo += $aplicaRecargo ? $recargoFinal : 0.0;
            }

            $totalPrincipal = round($totalPrincipal, 2);
            $totalRecargo = round($totalRecargo, 2);

            $cuotasConPrincipal = collect($datosCuotas)->filter(fn ($d) => $d['principal'] > 0)->values();
            $cuotasConRecargo = collect($datosCuotas)->filter(fn ($d) => $d['recargo'] > 0)->values();

            if ($totalPrincipal > 0) {
                // Las cuotas vienen ordenadas por vencimiento: la primera es el ancla.
                $ancla = $cuotasConPrincipal->first();
                $anclaCuota = $ancla['cuota'];

                $saldoAnterior = $obtenerSaldoContrato((int) $this->contrato_id);

                $propietarioContablePrincipalId = $this->resolverPropietarioContableId(
                    $this->tipos_cobro_id,
                    $this->getFormaPagoPrincipal($pagosPrincipales)
                );

                $recibo = $this->crearReciboConFolioSeguro([
                    'fecha' => $fecha->toDateString(),
                    'anio' => (int) $fecha->format('Y'),

                    'semana_pago' => (int) ($anclaCuota->numero ?? 0),
                    'semana_del_anio' => (int) $fecha->format('W'),
                    'mes_del_anio' => (int) $fecha->format('m'),

                    'cliente_id' => $this->cliente_id,
                    'contrato_id' => $this->contrato_id,
                    'lote_id' => $this->lote_id,
                    'cuota_id' => $anclaCuota->id,

                    'tipos_cobro_id' => $this->tipos_cobro_id,
                    'forma_pago_id' => $pagosPrincipales[0]['forma_pago_id'] ?? $this->forma_pago_id,
                    'cuentas_bancarias_id' => $pagosPrincipales[0]['cuentas_bancarias_id'] ?? $this->cuentas_bancarias_id,
                    'periodo_id' => $ancla['periodo_id'],
                    'propietario_contable_id' => $propietarioContablePrincipalId,

                    'monto' => $totalPrincipal,
                    'saldo_anterior' => round((float) $saldoAnterior, 2),
                    'saldo_posterior' => null,

                    'observaciones' => $this->observaciones,
                    'capturado_por_user_id' => auth()->id(),

                    'evidencia_path' => null,
                    'evidencia_disk' => null,
                    'evidencia_mime' => null,
                    'evidencia_size' => null,
                ]);

                $this->crearDetallesPagoRecibo($recibo, $pagosPrincipales, $imageUploadService);

                $recibosParaEnviar[] = $recibo->id;
                $recibosParaImprimir[] = $recibo->id;

                foreach ($cuotasConPrincipal as $item) {
                    $cuotaItem = $item['cuota'];

                    Pago::create([
                        'contrato_id' => $cuotaItem->contrato_id,
                        'cuota_id' => $cuotaItem->id,
                        'recibo_id' => $recibo->id,
                        'monto' => $item['principal'],
                        'metodo' => $this->metodo,
                        'referencia' => $this->referencia,
                        'estatus' => 'confirmado',
                        'fecha_pago' => now(),
                    ]);

                    $cuotaItem->pagado_total = (float) ($cuotaItem->pagado_total ?? 0) + $item['principal'];
                    $cuotaItem->estatus = $cuotaItem->pagado_total >= (float) $cuotaItem->monto ? 'pagada' : 'pendiente';
                    $cuotaItem->save();
                }

                $this->restarSaldoContrato($totalPrincipal);
                $actualizarSaldoPosteriorRecibo($recibo, (int) $this->contrato_id);

                activity()
                    ->causedBy(auth()->user())
                    ->performedOn($recibo)
                    ->withProperties([
                        'folio' => $recibo->folio,
                        'monto' => $recibo->monto,
                        'cliente_id' => $recibo->cliente_id,
                        'contrato_id' => $recibo->contrato_id,
                        'cuota_id' => $anclaCuota->id,
                        'cuota_ids' => $cuotasConPrincipal->map(fn ($d) => (int) $d['cuota']->id)->all(),
                        'saldo_anterior' => $recibo->saldo_anterior,
                        'saldo_posterior' => $recibo->saldo_posterior,
                    ])
                    ->log('Recibo creado');
            }

            if ($totalRecargo > 0) {
                // El recargo conserva su propio folio porque puede resolver a otro
                // propietario contable que la mensualidad; se imprime junto con el
                // recibo principal en el mismo ticket.
                $anclaRecargo = $cuotasConRecargo->first();

                foreach ($cuotasConRecargo as $item) {
                    $cuotaItem = $item['cuota'];
                    $cuotaItem->recargo_aplicado = max(
                        (float) ($cuotaItem->recargo_aplicado ?? 0),
                        (float) $item['recargo']
                    );
                    $cuotaItem->save();
                }

                $tipoCobroReciboRecargoId = $this->tipoCobroParaReciboRecargo();
                $formaPagoRecargo = $this->recargo_forma_pago_id
                    ?: $this->getFormaPagoPrincipal($pagosPrincipales);

                $propietarioContableRecargoId = $this->resolverPropietarioContableId(
                    $tipoCobroReciboRecargoId,
                    $formaPagoRecargo
                );

                $etiquetaCuotasRecargo = $cuotasConRecargo
                    ->map(fn ($d) => '#'.$d['cuota']->numero)
                    ->implode(', ');

                $reciboRecargo = $this->crearReciboConFolioSeguro([
                    'fecha' => $fecha->toDateString(),
                    'anio' => (int) $fecha->format('Y'),

                    'semana_pago' => (int) ($anclaRecargo['cuota']->numero ?? 0),
                    'semana_del_anio' => (int) $fecha->format('W'),
                    'mes_del_anio' => (int) $fecha->format('m'),

                    'cliente_id' => $this->cliente_id,
                    'contrato_id' => $this->contrato_id,
                    'lote_id' => $this->lote_id,
                    'cuota_id' => $anclaRecargo['cuota']->id,

                    'tipos_cobro_id' => $tipoCobroReciboRecargoId,
                    'forma_pago_id' => $this->recargo_forma_pago_id ?: $this->forma_pago_id,
                    'cuentas_bancarias_id' => $this->formaPagoEsEfectivo($this->recargo_forma_pago_id ?: $this->forma_pago_id)
                        ? null
                        : ($this->recargo_cuentas_bancarias_id ?? $this->cuentas_bancarias_id),
                    'periodo_id' => $anclaRecargo['periodo_id'],

                    'propietario_contable_id' => $propietarioContableRecargoId,

                    'monto' => $totalRecargo,
                    'saldo_anterior' => null,
                    'saldo_posterior' => null,

                    'observaciones' => rtrim(trim(
                        'RECARGO de cuota(s) '.$etiquetaCuotasRecargo.' — '.($this->observaciones ?? '')
                    ), ' —'),
                    'capturado_por_user_id' => auth()->id(),

                    'evidencia_path' => null,
                    'evidencia_disk' => null,
                    'evidencia_mime' => null,
                    'evidencia_size' => null,
                ]);

                $pagosRecargo = [[
                    'forma_pago_id' => (int) ($this->recargo_forma_pago_id ?: $this->forma_pago_id),
                    'cuentas_bancarias_id' => $this->formaPagoEsEfectivo($this->recargo_forma_pago_id ?: $this->forma_pago_id)
                        ? null
                        : ($this->recargo_cuentas_bancarias_id ?? $this->cuentas_bancarias_id),
                    'monto' => $totalRecargo,
                    'referencia' => $this->recargo_referencia,
                    'evidencia' => $this->mostrarCampoEvidenciaRecargo ? $this->recargo_evidencia : null,
                    'orden' => 1,
                ]];

                if (! $this->debeCapturarPagoRecargoSeparado()) {
                    $pagosRecargo = [[
                        'forma_pago_id' => $pagosPrincipales[0]['forma_pago_id'] ?? null,
                        'cuentas_bancarias_id' => $pagosPrincipales[0]['cuentas_bancarias_id'] ?? null,
                        'monto' => $totalRecargo,
                        'referencia' => $pagosPrincipales[0]['referencia'] ?? null,
                        'evidencia' => $pagosPrincipales[0]['evidencia'] ?? null,
                        'orden' => 1,
                    ]];
                }

                $this->crearDetallesPagoRecibo($reciboRecargo, $pagosRecargo, $imageUploadService);

                $recibosParaEnviar[] = $reciboRecargo->id;
                $recibosParaImprimir[] = $reciboRecargo->id;
                $recibosAdjuntosParaImprimir[] = $reciboRecargo->id;

                foreach ($cuotasConRecargo as $item) {
                    $cuotaItem = $item['cuota'];

                    Pago::create([
                        'contrato_id' => $cuotaItem->contrato_id,
                        'cuota_id' => $cuotaItem->id,
                        'recibo_id' => $reciboRecargo->id,
                        'monto' => $item['recargo'],
                        'metodo' => $this->recargo_metodo ?: $this->metodo,
                        'referencia' => $this->recargo_referencia,
                        'estatus' => 'confirmado',
                        'fecha_pago' => now(),
                    ]);

                    activity()
                        ->causedBy(auth()->user())
                        ->performedOn($cuotaItem)
                        ->withProperties([
                            'cuota_id' => $cuotaItem->id,
                            'recibo_id' => $reciboRecargo->id,
                            'recargo_monto' => $item['recargo'],
                            'recargo_forma_pago_id' => $this->recargo_forma_pago_id,
                            'recargo_metodo' => $this->recargo_metodo,
                        ])
                        ->log('Pago de recargo registrado');
                }
            }

            $this->actualizarEstatusContratoSiEstaLiquidado((int) $this->contrato_id);

            $this->cuotaId = null;
            $this->cuotaIds = [];
            $this->cargarCuotasPendientes();

            $this->dispatch(
                'toast',
                type: 'success',
                message: count($recibosParaImprimir) > 1
                    ? 'Recibo y recargo generados correctamente.'
                    : 'Recibo generado correctamente.'
            );

            $this->limpiarFormularioDespuesDeGuardar();
        });

        foreach ($recibosParaEnviar as $rid) {
            try {
                SendReciboMail::dispatch($rid)->afterCommit();
            } catch (\Throwable $e) {
                Log::warning('No se pudo encolar SendReciboMail (se omite)', [
                    'recibo_id' => $rid,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($imprimir) {
            $this->imprimir($recibosParaImprimir, $recibosAdjuntosParaImprimir);
        }
    }

    private function getFormaPagoPrincipal(array $pagosPrincipales): ?int
    {
        return $pagosPrincipales[0]['forma_pago_id'] ?? $this->forma_pago_id;
    }

    protected function limpiarFormularioDespuesDeGuardar(): void
    {
        $this->reset([
            'forma_pago_id',
            'cuentas_bancarias_id',
            'periodo_id',
            'monto',
            'observaciones',
            'referencia',
            'evidencia',
            'evidenciaPreviewUrl',

            'recargo_forma_pago_id',
            'recargo_cuentas_bancarias_id',
            'recargo_referencia',
            'recargo_evidencia',
            'recargoEvidenciaPreviewUrl',

            'cuotaId',
            'cuotaIds',
            'cuotasSeleccionadasInfo',
            'montoTotalSeleccionado',
            'recargoTotalSeleccionado',
            'avisoOrdenCuotas',
            'showAvisoOrdenCuotasModal',
            'cuotaFueraDeOrdenAutorizada',
            'pagos',
        ]);

        $this->tipoEsServicio = false;
        $this->fecha = now()->toDateString();
        $this->folio = $this->generarFolio();

        $this->metodo = 'efectivo';
        $this->recargo_metodo = 'efectivo';

        $this->requiereCuentaBancaria = false;
        $this->recargoRequiereCuentaBancaria = false;

        $this->evidencia = null;
        $this->evidenciaPreviewUrl = null;
        $this->pagoEvidenciaIndex = null;
        $this->showPagoEvidenciaModal = false;
        $this->pagoEvidenciaPreviewUrl = null;

        $this->recargo_evidencia = null;
        $this->recargoEvidenciaPreviewUrl = null;

        $this->showRecargoPagoBox = false;
        $this->recargoModo = 'auto';
        $this->recargoMontoManual = null;

        $this->recalcularResumenCuotasSeleccionadas();
    }

    protected function limpiarEvidenciaSiNoAplica(): void
    {
        // La evidencia principal ya no se usa para recibos nuevos.
        $this->evidencia = null;
        $this->evidenciaPreviewUrl = null;
    }

    protected function debeConfirmarRecargoAntesDeGuardar(): bool
    {
        return $this->tieneRecargoPendientePorConfirmar();
    }

    protected function construirMensajeConfirmacionRecargo(): string
    {
        $conteo = $this->getConteoRecibosEsperados();
        $principales = $conteo['principales'];
        $recargos = $conteo['recargos'];

        if ($this->tipoCobroEsRecargo($this->tipos_cobro_id)) {
            return "Esta operación generará {$recargos} recibo(s) de RECARGO por un total de $".number_format((float) $this->recargoTotalSeleccionado, 2).'. ¿Desea continuar?';
        }

        if ($this->tipoCobroAplicaRecargoPorAtraso($this->tipos_cobro_id)) {
            return "Esta operación generará {$principales} recibo(s) principal(es) por $".number_format((float) $this->montoTotalSeleccionado, 2).
                " y {$recargos} recibo(s) de recargo por $".number_format((float) $this->recargoTotalSeleccionado, 2).'. ¿Desea continuar?';
        }

        return "Esta operación generará {$principales} recibo(s). ¿Desea continuar?";
    }

    public function intentarGuardar(bool $imprimir = false): void
    {
        Log::info('INTENTAR GUARDAR - INICIO', [
            'confirmado' => $this->guardarConRecargoConfirmado,
            'tiene_recargo' => $this->tieneRecargoPendientePorConfirmar(),
            'debe_confirmar' => $this->debeConfirmarRecargoAntesDeGuardar(),
            'recargo_total' => $this->recargoTotalSeleccionado,
            'cuotaIds' => $this->cuotaIds,
            'tipo_cobro' => $this->tipos_cobro_id,
        ]);

        if (! $this->guardarConRecargoConfirmado && $this->debeConfirmarRecargoAntesDeGuardar()) {
            Log::info('ABRIENDO MODAL DE RECARGO');

            $this->imprimirPendiente = $imprimir;
            $this->mensajeConfirmacionRecargo = $this->construirMensajeConfirmacionRecargo();
            $this->showConfirmRecargoModal = true;

            return;
        }

        Log::info('NO ABRE MODAL → SE VA A GUARDAR DIRECTO');

        $this->guardar($imprimir);
    }

    public function aceptarAlertaConvenio(): void
    {
        $this->alertaConvenioAceptada = true;
    }

    public function confirmarGuardarConRecargo(): void
    {
        $this->guardarConRecargoConfirmado = true;
        $this->showConfirmRecargoModal = false;

        $imprimir = $this->imprimirPendiente;

        $this->imprimirPendiente = false;
        $this->mensajeConfirmacionRecargo = null;

        try {
            $this->guardar($imprimir);
        } finally {
            $this->guardarConRecargoConfirmado = false;
        }
    }

    public function cancelarGuardarConRecargo(): void
    {
        $this->showConfirmRecargoModal = false;
        $this->guardarConRecargoConfirmado = false;
        $this->imprimirPendiente = false;
        $this->mensajeConfirmacionRecargo = null;

        $this->dispatch('toast', type: 'warning', message: 'Operación cancelada. No se generó el recibo.');
    }

    protected function tieneRecargoPendientePorConfirmar(): bool
    {
        if (! $this->asociarACuota || empty($this->cuotaIds)) {
            return false;
        }

        if ($this->recargoModo === 'condonar') {
            return false;
        }

        $this->recalcularResumenCuotasSeleccionadas();

        if ((float) $this->recargoTotalSeleccionado <= 0) {
            return false;
        }

        if ($this->tipoCobroEsRecargo($this->tipos_cobro_id)) {
            return true;
        }

        if ($this->tipoCobroAplicaRecargoPorAtraso($this->tipos_cobro_id)) {
            return true;
        }

        return false;
    }

    public function toggleRecargoPagoBox(): void
    {
        $this->showRecargoPagoBox = ! $this->showRecargoPagoBox;
    }

    public function getMostrarCampoEvidenciaRecargoProperty(): bool
    {
        return $this->debePedirEvidenciaRecargo();
    }

    protected function debePedirEvidenciaRecargo(): bool
    {
        if (! $this->debeCapturarPagoRecargoSeparado()) {
            return false;
        }

        $formaRecargoId = $this->recargo_forma_pago_id ?: $this->forma_pago_id;

        if (! $formaRecargoId) {
            return false;
        }

        return $this->formaPagoRequiereCuenta((int) $formaRecargoId);
    }

    protected function limpiarEvidenciaRecargoSiNoAplica(): void
    {
        if ($this->mostrarCampoEvidenciaRecargo) {
            return;
        }

        $this->reset(['recargo_evidencia', 'recargoEvidenciaPreviewUrl']);
        $this->recargo_evidencia = null;
        $this->recargoEvidenciaPreviewUrl = null;
    }

    protected function autoDistribuirPagos(): void
    {
        // Total esperado
        $total = $this->asociarACuota
            ? ($this->tipoCobroEsRecargo($this->tipos_cobro_id)
                ? $this->recargoTotalSeleccionado
                : $this->montoTotalSeleccionado)
            : $this->monto;

        $total = (float) $total;

        if ($total <= 0) {
            return;
        }

        $count = count($this->pagos);

        if ($count === 0) {
            return;
        }

        // Si solo hay uno → se pone todo
        if ($count === 1) {
            $this->pagos[0]['monto'] = $total;

            return;
        }

        // Si hay varios → sumar todos menos el último
        $sumaSinUltimo = 0;

        foreach ($this->pagos as $i => $pago) {
            if ($i === $count - 1) {
                continue;
            }
            $sumaSinUltimo += (float) ($pago['monto'] ?? 0);
        }

        // Calcular restante
        $restante = $total - $sumaSinUltimo;

        // Evitar negativos
        $restante = max(0, round($restante, 2));

        // Asignar al último
        $this->pagos[$count - 1]['monto'] = $restante;
    }

    protected function setMontoDefaultPrimerPago(): void
    {
        $total = $this->asociarACuota
            ? ($this->tipoCobroEsRecargo($this->tipos_cobro_id)
                ? $this->recargoTotalSeleccionado
                : $this->montoTotalSeleccionado)
            : $this->monto;

        $total = (float) $total;

        if ($total > 0 && isset($this->pagos[0])) {
            $this->pagos[0]['monto'] = $total;
        }
    }

    protected function obtenerSaldoActualContrato(int $contratoId): float
    {
        return (float) Contrato::query()
            ->whereKey($contratoId)
            ->lockForUpdate()
            ->value('saldo_actual');
    }

    protected function ponerSnapshotSaldoEnRecibo(
        Recibo $recibo,
        float $saldoAnterior,
        float $montoAplicado,
        ?float $saldoPosterior = null
    ): void {
        $recibo->saldo_anterior = round($saldoAnterior, 2);
        $recibo->monto_aplicado = round($montoAplicado, 2);

        if ($saldoPosterior !== null) {
            $recibo->saldo_posterior = round(max(0, $saldoPosterior), 2);
        }

        $recibo->save();
    }

    protected function sincronizarPeriodoDesdeCuotasSeleccionadas(): void
    {
        if (! $this->asociarACuota || empty($this->cuotaIds)) {
            return;
        }

        $cuotaId = collect($this->cuotaIds)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->first();

        if (! $cuotaId) {
            return;
        }

        $cuota = Cuota::query()
            ->select('id', 'fecha_vencimiento')
            ->find($cuotaId);

        if (! $cuota || ! $cuota->fecha_vencimiento) {
            return;
        }

        $this->setPeriodoDesdeCuota($cuota->fecha_vencimiento);
    }

    protected function sincronizarMontoPagosConTotalEsperado(): void
    {
        $total = $this->asociarACuota
            ? ($this->tipoCobroEsRecargo($this->tipos_cobro_id)
                ? (float) $this->recargoTotalSeleccionado
                : (float) $this->montoTotalSeleccionado)
            : (float) $this->monto;

        $total = round(max(0, $total), 2);

        // Mantener también sincronizado el monto general
        $this->monto = $total;

        if (empty($this->pagos) || ! is_array($this->pagos)) {
            $this->pagos = [$this->nuevoPagoRow()];
        }

        if (count($this->pagos) <= 1) {
            $this->pagos[0]['monto'] = $total;

            return;
        }

        $this->autoDistribuirPagos();
    }

    protected function resolverPropietarioContableId(
        ?int $tipoCobroId,
        ?int $formaPagoId = null
    ): ?int {
        if (! $tipoCobroId) {
            return null;
        }

        $fraccionamientoId = null;

        if ($this->lote_id) {
            $fraccionamientoId = Lote::query()
                ->whereKey($this->lote_id)
                ->value('fraccionamiento_id');
        }

        if (! $fraccionamientoId && $this->contrato_id) {
            $contrato = Contrato::with('lote')->find($this->contrato_id);
            $fraccionamientoId = $contrato?->lote?->fraccionamiento_id;
        }

        return app(PropietarioContableResolver::class)->resolver(
            tipoCobroId: $tipoCobroId,
            fraccionamientoId: $fraccionamientoId,
            formaPagoId: $formaPagoId
        );
    }

    protected function resolverPeriodoIdDesdeFecha($fechaVencimiento): ?int
    {
        return app(PeriodoResolver::class)->idParaFecha($fechaVencimiento);
    }

    /**
     * @param  array<int, int>  $reciboIds  todos los recibos del lote
     * @param  array<int, int>  $adjuntoIds  los que van dentro del ticket de otro
     *                                       recibo en vez de en su propia hoja
     */
    protected function openPrintBatchTab(array $reciboIds, array $adjuntoIds = []): void
    {
        $token = Str::uuid()->toString();

        cache()->put(
            'print_batch:'.$token,
            [
                'user_id' => auth()->id(),
                'recibo_ids' => array_values($reciboIds),
                // El recargo se imprime dentro del ticket del recibo principal.
                'adjunto_ids' => array_values($adjuntoIds),
            ],
            now()->addMinutes(15)
        );

        $url = route('admin.recibos.print-lote', ['token' => $token]);

        $this->dispatch('open-print-tab', url: $url);
    }

    public function render()
    {
        $this->actualizarRequiereCuentaBancaria();
        $this->actualizarRequiereCuentaBancariaRecargo();
        if (empty($this->pagos) || ! is_array($this->pagos) || count($this->pagos) === 0) {
            $this->pagos = [$this->nuevoPagoRow()];
        }

        return view('livewire.admin.recibos.crear', [
            'tiposCobro' => TipoCobro::orderBy('nombre')->get(),
            'formasPago' => FormaPago::orderBy('nombre')->get(),
            'cuentas' => CuentaBancaria::where('activa', true)->orderBy('alias')->get(),
            'periodos' => Periodo::query()
                ->where('tipo', 'mensual')
                ->orderByDesc('anio')
                ->orderByDesc('mes')
                ->get(),
        ])->layout('layouts.app');
    }
}
