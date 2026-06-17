<?php

namespace App\Livewire\Admin\Contratos;

use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Cuota;
use App\Models\Fraccionamiento;
use App\Models\Lote;
use App\Models\Promocion;
use App\Services\Contratos\ContratoPlanService;
use App\Services\Contratos\ContratoWordService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

class Create extends Component
{
    use WithFileUploads;

    public int $step = 1;

    public bool $es_donacion = false;

    public string $cliente_q = '';

    public array $clientes_suggest = [];

    public ?int $cliente_id = null;

    public ?int $fraccionamiento_id = null;

    public ?string $manzana = null;

    public array $manzanasDisponibles = [];

    public array $lotesDisponibles = [];

    public ?int $lote_id = null;

    public ?int $promocion_id = null;

    public string $fecha_inicio = '';

    public string $frecuencia = 'mensual';

    public ?int $dia_semana = null;

    public ?int $dia_mes = null;

    public float $precio_total = 0.0;

    public float $enganche = 0.0;

    public float $saldo_inicial = 0.0;

    public float $saldo_actual = 0.0;

    public float $monto_pago = 0.0;

    public string $tipo_recargo = 'fijo';

    public float $valor_recargo = 0.0;

    public int $dias_gracia = 0;

    public bool $tiene_anualidad = false;

    public ?string $anualidad_fecha = null;

    public float $anualidad_monto = 0.0;

    public bool $usar_configuracion_manual = false;

    public array $previewCuotas = [];

    public string $diaSemanaTexto = '';

    // ===========================
    // Datos legales / snapshot
    // ===========================
    public ?float $area_m2 = null;

    public ?string $medida_norte = null;

    public ?string $medida_sur = null;

    public ?string $medida_este = null;

    public ?string $medida_oeste = null;

    public ?string $colindancia_norte = null;

    public ?string $colindancia_sur = null;

    public ?string $colindancia_este = null;

    public ?string $colindancia_oeste = null;

    public ?string $vendedor_nombre_legal = null;

    public ?string $vendedor_curp = null;

    public ?string $comprador_nombre_legal = null;

    public ?string $comprador_curp = null;

    public $comprador_ine_frente;

    public $comprador_ine_reverso;

    public $vendedor_ine_frente;

    public $vendedor_ine_reverso;

    // Paths precargados
    public ?string $comprador_ine_frente_path = null;

    public ?string $comprador_ine_reverso_path = null;

    public ?string $vendedor_ine_frente_path = null;

    public ?string $vendedor_ine_reverso_path = null;

    public ?string $comprador_docs_disk = 'private';

    public ?string $vendedor_docs_disk = 'private';

    public function mount(): void
    {
        $this->fecha_inicio = now()->toDateString();
        $this->actualizarDiaTextoYDefaults();

        $this->anualidad_fecha = $this->fecha_inicio;
        $this->anualidad_monto = 0;

        $this->loadManzanas();
        $this->loadLotes();

        $this->recalcular();
        $this->refreshPreview();
    }

    public function getDiaNombreProperty(): string
    {
        return $this->diaSemanaTexto ?: '';
    }

    public function getDiasSemanaProperty(): array
    {
        return ContratoPlanService::diasSemana();
    }

    public function getPromocionProperty(): ?Promocion
    {
        if (! $this->promocion_id) {
            return null;
        }

        if (! Schema::hasTable('promociones')) {
            return null;
        }

        return Promocion::query()->find($this->promocion_id);
    }

    public function getTotalPreviewProperty(): float
    {
        return round(
            collect($this->previewCuotas)->sum(fn ($r) => (float) ($r['monto'] ?? 0)),
            2
        );
    }

    public function getEsDonacionProperty(): bool
    {
        return $this->es_donacion;
    }

    // ===========================
    // Cliente buscador
    // ===========================
    public function updatedClienteQ(): void
    {
        $q = trim($this->cliente_q);

        if ($q === '') {
            $this->cliente_id = null;
            $this->clientes_suggest = [];

            return;
        }

        if (mb_strlen($q) < 2) {
            $this->clientes_suggest = [];

            return;
        }

        $this->clientes_suggest = Cliente::query()
            ->where('estatus', 'activo')
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
                'label' => $c->nombre_completo.($c->telefono ? " 路 {$c->telefono}" : ''),
            ])
            ->toArray();
    }

    public function selectCliente(int $id): void
    {
        $this->cliente_id = $id;

        $c = Cliente::query()->find($id);

        $this->cliente_q = $c ? $c->nombre_completo : '';
        $this->clientes_suggest = [];

        if (! $c) {
            return;
        }

        $this->comprador_nombre_legal = $c->nombre_legal ?: $c->nombre_completo;
        $this->comprador_curp = $c->curp ?? null;

        $this->comprador_ine_frente_path = $c->ine_frente ?: null;
        $this->comprador_ine_reverso_path = $c->ine_reverso ?: null;
        $this->comprador_docs_disk = 'private';
    }

    // ===========================
    // Fraccionamiento / Manzana / Lote
    // ===========================
    public function updatedFraccionamientoId(): void
    {
        $this->manzana = null;
        $this->lote_id = null;

        $this->limpiarDatosLoteLegal();
        $this->limpiarDatosVendedorLegal();

        $this->loadManzanas();
        $this->loadLotes();
        $this->refreshPreview();
    }

    public function updatedManzana(): void
    {
        $this->lote_id = null;

        $this->limpiarDatosLoteLegal();
        $this->limpiarDatosVendedorLegal();

        $this->loadLotes();
        $this->refreshPreview();
    }

    public function updatedLoteId(): void
    {
        $this->limpiarDatosLoteLegal();
        $this->limpiarDatosVendedorLegal();

        if ($this->lote_id) {
            $lote = Lote::query()
                ->with('fraccionamiento.propietario')
                ->find($this->lote_id);

            if ($lote) {
                $this->precio_total = (float) ($lote->precio_lista ?? 0);

                $this->area_m2 = $lote->area_m2 !== null ? (float) $lote->area_m2 : null;

                $this->medida_norte = $lote->medida_norte ? (string) $lote->medida_norte : null;
                $this->medida_sur = $lote->medida_sur ? (string) $lote->medida_sur : null;
                $this->medida_este = $lote->medida_este ? (string) $lote->medida_este : null;
                $this->medida_oeste = $lote->medida_oeste ? (string) $lote->medida_oeste : null;

                $this->colindancia_norte = $lote->colindancia_norte;
                $this->colindancia_sur = $lote->colindancia_sur;
                $this->colindancia_este = $lote->colindancia_este;
                $this->colindancia_oeste = $lote->colindancia_oeste;

                $propietario = $lote->fraccionamiento?->propietario;

                if ($propietario) {
                    $this->vendedor_nombre_legal = $propietario->nombre_legal ?: $propietario->nombre;
                    $this->vendedor_curp = $propietario->curp ?: null;

                    $this->vendedor_ine_frente_path = $propietario->ine_frente ?: null;
                    $this->vendedor_ine_reverso_path = $propietario->ine_reverso ?: null;
                    $this->vendedor_docs_disk = $propietario->documentos_disk ?: 'private';

                    if ($this->vendedor_ine_frente_path) {
                        $this->vendedor_ine_frente = null;
                    }

                    if ($this->vendedor_ine_reverso_path) {
                        $this->vendedor_ine_reverso = null;
                    }
                }
            }
        }

        $this->recalcular();
        $this->refreshPreview();
    }

    protected function limpiarDatosLoteLegal(): void
    {
        $this->area_m2 = null;

        $this->medida_norte = null;
        $this->medida_sur = null;
        $this->medida_este = null;
        $this->medida_oeste = null;

        $this->colindancia_norte = null;
        $this->colindancia_sur = null;
        $this->colindancia_este = null;
        $this->colindancia_oeste = null;
    }

    protected function limpiarDatosVendedorLegal(): void
    {
        $this->vendedor_nombre_legal = null;
        $this->vendedor_curp = null;

        $this->vendedor_ine_frente = null;
        $this->vendedor_ine_reverso = null;

        $this->vendedor_ine_frente_path = null;
        $this->vendedor_ine_reverso_path = null;
        $this->vendedor_docs_disk = 'private';
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

        $q = Lote::query()
            ->where('fraccionamiento_id', $this->fraccionamiento_id)
            ->where('estatus', 'disponible');

        if ($this->manzana) {
            $q->where('manzana', $this->manzana);
        }

        $this->lotesDisponibles = $q
            ->orderByRaw('CAST(lote AS UNSIGNED) ASC')
            ->orderBy('lote', 'ASC')
            ->get(['id', 'lote'])
            ->map(fn ($l) => [
                'id' => (int) $l->id,
                'label' => (string) $l->lote,
            ])
            ->toArray();
    }

    // ===========================
    // Fechas / frecuencia
    // ===========================
    public function updatedFechaInicio(): void
    {
        $this->actualizarDiaTextoYDefaults();

        if ($this->tiene_anualidad && ! $this->anualidad_fecha) {
            $this->anualidad_fecha = $this->fecha_inicio ?: now()->toDateString();
        }

        $this->refreshPreview();
    }

    public function updatedFrecuencia(): void
    {
        $this->actualizarDiaTextoYDefaults();
        $this->refreshPreview();
    }

    public function updatedPromocionId(): void
    {
        if ($this->promocion && $this->promocion->tipo === 'cuotas_fijas') {
            $this->tiene_anualidad = false;
            $this->anualidad_monto = 0;
            $this->anualidad_fecha = null;
        }

        $this->recalcular();
        $this->refreshPreview();
    }

    public function updatedTieneAnualidad(): void
    {
        if (! $this->tiene_anualidad) {
            $this->anualidad_monto = 0;
            $this->anualidad_fecha = null;
        } else {
            $this->anualidad_fecha = $this->anualidad_fecha ?: ($this->fecha_inicio ?: now()->toDateString());
        }

        $this->recalcular();
        $this->refreshPreview();
    }

    public function updatedUsarConfiguracionManual($value): void
    {
        $activo = filter_var($value, FILTER_VALIDATE_BOOL);

        if ($activo && empty($this->previewCuotas)) {
            $this->previewCuotas = $this->generarPreviewAutomatico();
        }

        if (! $activo) {
            $this->previewCuotas = $this->generarPreviewAutomatico();
        }

        $this->renumerarPreviewCuotas();
    }

    public function updated($key): void
    {
        if (in_array($key, [
            'cliente_id',
            'fraccionamiento_id',
            'manzana',
            'lote_id',
            'promocion_id',
            'precio_total',
            'enganche',
            'monto_pago',
            'dia_semana',
            'dia_mes',
            'frecuencia',
            'tipo_recargo',
            'valor_recargo',
            'dias_gracia',
            'tiene_anualidad',
            'anualidad_fecha',
            'anualidad_monto',
        ], true)) {
            $this->recalcular();
            $this->refreshPreview();
        }
    }

    protected function actualizarDiaTextoYDefaults(): void
    {
        if (! $this->fecha_inicio) {
            $this->diaSemanaTexto = '';

            return;
        }

        $d = Carbon::parse($this->fecha_inicio);
        $iso = $d->isoWeekday();
        $this->diaSemanaTexto = $this->diasSemana[$iso] ?? '';

        if ($this->frecuencia === 'semanal') {
            $this->dia_mes = null;
            $this->dia_semana = $this->dia_semana ?? $iso;
        } else {
            $this->dia_semana = null;
            $this->dia_mes = $this->dia_mes ?? (int) $d->day;
        }
    }

    protected function recalcular(): void
    {
        $precioTotal = data_get($this, 'precio_total', 0);
        $enganche = data_get($this, 'enganche', 0);
        $montoPago = data_get($this, 'monto_pago', 0);
        $valorRecargo = data_get($this, 'valor_recargo', 0);
        $diasGracia = data_get($this, 'dias_gracia', 0);
        $anualidadMonto = data_get($this, 'anualidad_monto', 0);

        $this->precio_total = max(0, (float) ($precioTotal ?: 0));
        $this->enganche = max(0, (float) ($enganche ?: 0));
        $this->monto_pago = max(0, (float) ($montoPago ?: 0));

        $this->saldo_inicial = round(max(0, $this->precio_total - $this->enganche), 2);
        $this->saldo_actual = $this->saldo_inicial;

        $this->valor_recargo = max(0, (float) ($valorRecargo ?: 0));
        $this->dias_gracia = max(0, (int) ($diasGracia ?: 0));

        $this->anualidad_monto = max(0, (float) ($anualidadMonto ?: 0));

        if ($this->promocion && $this->promocion->tipo === 'cuotas_fijas') {
            $this->tiene_anualidad = false;
            $this->anualidad_monto = 0;
            $this->anualidad_fecha = null;
        }

        if (! $this->tiene_anualidad) {
            $this->anualidad_monto = 0;
            $this->anualidad_fecha = null;
        }

        if ($this->esDonacion) {
            $this->enganche = 0;
            $this->saldo_inicial = 0;
            $this->saldo_actual = 0;
            $this->monto_pago = 0;

            $this->tipo_recargo = 'fijo';
            $this->valor_recargo = 0;
            $this->dias_gracia = 0;

            $this->tiene_anualidad = false;
            $this->anualidad_fecha = null;
            $this->anualidad_monto = 0;

            $this->usar_configuracion_manual = false;
            $this->previewCuotas = [];
        }
    }

    protected function refreshPreview(): void
    {
        if ($this->usar_configuracion_manual) {
            return;
        }

        $this->previewCuotas = $this->generarPreviewAutomatico();
        $this->renumerarPreviewCuotas();
    }

    protected function generarPreviewAutomatico(): array
    {

        if ($this->esDonacion) {
            return [];
        }
        if (
            ! $this->fecha_inicio ||
            $this->saldo_inicial <= 0 ||
            $this->monto_pago <= 0 ||
            ! $this->lote_id
        ) {
            return [];
        }

        try {
            $data = $this->payloadContrato();

            ContratoPlanService::aplicarPromocionEconomica($data, $this->promocion);

            $plan = ContratoPlanService::generarCuotas($data, $this->promocion);

            return collect($plan)
                ->map(fn ($row, $i) => [
                    'numero' => (int) ($row['numero'] ?? ($i + 1)),
                    'fecha_vencimiento' => (string) ($row['fecha_vencimiento'] ?? ''),
                    'monto' => round((float) ($row['monto'] ?? 0), 2),
                    'concepto' => $row['concepto'] ?? null,
                    'es_anualidad' => (bool) ($row['es_anualidad'] ?? false),
                ])
                ->values()
                ->toArray();
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }

    protected function payloadContrato(): array
    {
        $promoTipo = $this->promocion?->tipo;
        $tieneAnual = $this->tiene_anualidad;

        if ($promoTipo === 'cuotas_fijas') {
            $tieneAnual = false;
        }

        return [
            'fecha_inicio' => $this->fecha_inicio,
            'frecuencia' => $this->frecuencia,
            'dia_semana' => $this->frecuencia === 'semanal' ? $this->dia_semana : null,
            'dia_mes' => $this->frecuencia === 'mensual' ? $this->dia_mes : null,
            'precio_total' => $this->precio_total,
            'enganche' => $this->enganche,
            'saldo_inicial' => $this->saldo_inicial,
            'saldo_actual' => $this->saldo_actual,
            'monto_pago' => $this->monto_pago,
            'tipo_recargo' => $this->tipo_recargo,
            'valor_recargo' => $this->valor_recargo,
            'dias_gracia' => $this->dias_gracia,
            'frecuencia_recargo_dias' => Contrato::frecuenciaRecargoDiasPorGracia((int) $this->dias_gracia),
            'tiene_anualidad' => (bool) $tieneAnual,
            'anualidad_fecha' => $tieneAnual ? $this->anualidad_fecha : null,
            'anualidad_monto' => $tieneAnual ? $this->anualidad_monto : 0,
        ];
    }

    // ===========================
    // Preview manual
    // ===========================
    public function regenerarPreviewAutomatico(): void
    {
        $this->previewCuotas = $this->generarPreviewAutomatico();
        $this->renumerarPreviewCuotas();
    }

    public function agregarCuotaManual(): void
    {
        $ultimaFecha = $this->fecha_inicio ?: now()->toDateString();

        if (! empty($this->previewCuotas)) {
            $ultima = collect($this->previewCuotas)->last();
            $ultimaFecha = $ultima['fecha_vencimiento'] ?? $ultimaFecha;
        }

        $siguienteFecha = Carbon::parse($ultimaFecha);

        if ($this->frecuencia === 'semanal') {
            $siguienteFecha->addWeek();
        } else {
            $siguienteFecha->addMonth();
        }

        $this->previewCuotas[] = [
            'numero' => count($this->previewCuotas) + 1,
            'fecha_vencimiento' => $siguienteFecha->toDateString(),
            'monto' => round((float) $this->monto_pago, 2),
            'concepto' => null,
            'es_anualidad' => false,
        ];

        $this->renumerarPreviewCuotas();
    }

    public function eliminarCuotaManual(int $index): void
    {
        if (! isset($this->previewCuotas[$index])) {
            return;
        }

        unset($this->previewCuotas[$index]);
        $this->previewCuotas = array_values($this->previewCuotas);
        $this->renumerarPreviewCuotas();
    }

    public function duplicarCuotaManual(int $index): void
    {
        if (! isset($this->previewCuotas[$index])) {
            return;
        }

        $row = $this->previewCuotas[$index];

        array_splice($this->previewCuotas, $index + 1, 0, [[
            'numero' => 0,
            'fecha_vencimiento' => $row['fecha_vencimiento'] ?? now()->toDateString(),
            'monto' => round((float) ($row['monto'] ?? 0), 2),
            'concepto' => $row['concepto'] ?? null,
            'es_anualidad' => (bool) ($row['es_anualidad'] ?? false),
        ]]);

        $this->renumerarPreviewCuotas();
    }

    public function ordenarCuotasPorFecha(): void
    {
        usort($this->previewCuotas, function ($a, $b) {
            return strcmp((string) ($a['fecha_vencimiento'] ?? ''), (string) ($b['fecha_vencimiento'] ?? ''));
        });

        $this->renumerarPreviewCuotas();
    }

    protected function renumerarPreviewCuotas(): void
    {
        $this->previewCuotas = collect($this->previewCuotas)
            ->values()
            ->map(function ($row, $i) {
                return [
                    'numero' => $i + 1,
                    'fecha_vencimiento' => (string) ($row['fecha_vencimiento'] ?? ''),
                    'monto' => round((float) ($row['monto'] ?? 0), 2),
                    'concepto' => $row['concepto'] ?? null,
                    'es_anualidad' => (bool) ($row['es_anualidad'] ?? false),
                ];
            })
            ->toArray();
    }

    protected function validarPreviewManual(): void
    {
        if (empty($this->previewCuotas)) {
            $this->addError('previewCuotas', 'Debes tener al menos una cuota en el calendario.');

            return;
        }

        foreach ($this->previewCuotas as $i => $row) {
            $fecha = $row['fecha_vencimiento'] ?? null;
            $monto = (float) ($row['monto'] ?? 0);

            if (! $fecha) {
                $this->addError("previewCuotas.{$i}.fecha_vencimiento", 'La fecha es obligatoria.');
            }

            if ($monto <= 0) {
                $this->addError("previewCuotas.{$i}.monto", 'El monto debe ser mayor a 0.');
            }
        }
    }

    protected function obtenerPlanFinal(): array
    {
        if ($this->usar_configuracion_manual) {
            $this->renumerarPreviewCuotas();

            return collect($this->previewCuotas)
                ->map(fn ($row, $i) => [
                    'numero' => $i + 1,
                    'fecha_vencimiento' => $row['fecha_vencimiento'],
                    'monto' => round((float) $row['monto'], 2),
                    'es_anualidad' => (bool) ($row['es_anualidad'] ?? false),
                    'concepto' => $row['concepto'] ?? null,
                ])
                ->values()
                ->toArray();
        }

        $data = $this->payloadContrato();
        ContratoPlanService::aplicarPromocionEconomica($data, $this->promocion);

        return ContratoPlanService::generarCuotas($data, $this->promocion);
    }

    // ===========================
    // Wizard
    // ===========================
    public function next(): void
    {
        $this->validateStep();

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        $this->step = min(3, $this->step + 1);
    }

    public function back(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    protected function validateStep(): void
    {
        if ($this->step === 1) {
            $this->validate([
                'cliente_id' => ['required', 'exists:clientes,id'],
                'fraccionamiento_id' => ['required', 'exists:fraccionamientos,id'],
                'manzana' => ['required'],
                'lote_id' => ['required', 'exists:lotes,id'],
                'fecha_inicio' => ['required', 'date'],
                'frecuencia' => ['required', Rule::in(['semanal', 'mensual'])],
                'promocion_id' => ['nullable'],
                'es_donacion' => ['boolean'],
            ]);

            if ($this->frecuencia === 'semanal') {
                $this->validate([
                    'dia_semana' => ['required', 'integer', 'min:1', 'max:7'],
                ]);
            } else {
                $this->validate([
                    'dia_mes' => ['required', 'integer', 'min:1', 'max:31'],
                ]);
            }

            if ($this->promocion_id && Schema::hasTable('promociones')) {
                $this->validate([
                    'promocion_id' => ['exists:promociones,id'],
                ]);
            }
        }

        if ($this->step === 2) {

            if ($this->esDonacion) {

                $rules = [
                    'precio_total' => ['nullable', 'numeric', 'min:0'],
                    'enganche' => ['nullable', 'numeric', 'min:0'],
                    'monto_pago' => ['nullable', 'numeric', 'min:0'],
                    'valor_recargo' => ['nullable', 'numeric', 'min:0'],
                    'dias_gracia' => ['nullable', 'integer', 'min:0'],
                ];
            } else {

                $rules = [
                    'precio_total' => ['required', 'numeric', 'min:0.01'],
                    'enganche' => ['required', 'numeric', 'min:0'],
                    'monto_pago' => ['required', 'numeric', 'min:0.01'],
                    'tipo_recargo' => ['required', Rule::in(['fijo', 'porcentaje'])],
                    'valor_recargo' => ['required', 'numeric', 'min:0'],
                    'dias_gracia' => ['required', 'integer', 'min:0'],
                    'tiene_anualidad' => ['boolean'],
                ];
            }

            $rules = array_merge($rules, [

                'area_m2' => ['nullable', 'numeric', 'min:0.01'],
                'medida_norte' => ['nullable', 'string', 'max:255'],
                'medida_sur' => ['nullable', 'string', 'max:255'],
                'medida_este' => ['nullable', 'string', 'max:255'],
                'medida_oeste' => ['nullable', 'string', 'max:255'],

                'colindancia_norte' => ['nullable', 'string', 'max:255'],
                'colindancia_sur' => ['nullable', 'string', 'max:255'],
                'colindancia_este' => ['nullable', 'string', 'max:255'],
                'colindancia_oeste' => ['nullable', 'string', 'max:255'],

                'vendedor_nombre_legal' => ['nullable', 'string', 'max:255'],
                'vendedor_curp' => ['nullable', 'string', 'max:30'],
                'comprador_nombre_legal' => ['nullable', 'string', 'max:255'],
                'comprador_curp' => ['nullable', 'string', 'max:30'],

                /*    'area_m2' => ['required', 'numeric', 'min:0.01'],
                'medida_norte' => ['required', 'string', 'max:255'],
                'medida_sur' => ['required', 'string', 'max:255'],
                'medida_este' => ['required', 'string', 'max:255'],
                'medida_oeste' => ['required', 'string', 'max:255'],

                'colindancia_norte' => ['required', 'string', 'max:255'],
                'colindancia_sur' => ['required', 'string', 'max:255'],
                'colindancia_este' => ['required', 'string', 'max:255'],
                'colindancia_oeste' => ['required', 'string', 'max:255'],

                'vendedor_nombre_legal' => ['required', 'string', 'max:255'],
                'vendedor_curp' => ['required', 'string', 'max:30'],
                'comprador_nombre_legal' => ['required', 'string', 'max:255'],
                'comprador_curp' => ['required', 'string', 'max:30'],

                'comprador_ine_frente' => [$this->comprador_ine_frente_path ? 'nullable' : 'required', 'image', 'max:10240'],
                'comprador_ine_reverso' => [$this->comprador_ine_reverso_path ? 'nullable' : 'required', 'image', 'max:10240'],
                'vendedor_ine_frente' => [$this->vendedor_ine_frente_path ? 'nullable' : 'required', 'image', 'max:10240'],
                'vendedor_ine_reverso' => [$this->vendedor_ine_reverso_path ? 'nullable' : 'required', 'image', 'max:10240'],*/

                'comprador_ine_frente' => ['nullable', 'image', 'max:10240'],
                'comprador_ine_reverso' => ['nullable', 'image', 'max:10240'],
                'vendedor_ine_frente' => ['nullable', 'image', 'max:10240'],
                'vendedor_ine_reverso' => ['nullable', 'image', 'max:10240'],
            ]);

            if ($this->promocion && $this->promocion->tipo === 'cuotas_fijas') {
                if ($this->tiene_anualidad) {
                    $this->tiene_anualidad = false;
                    $this->anualidad_fecha = null;
                    $this->anualidad_monto = 0;
                }
            } else {
                if ($this->tiene_anualidad) {
                    $rules['anualidad_fecha'] = ['required', 'date'];
                    $rules['anualidad_monto'] = ['required', 'numeric', 'min:0.01'];
                }
            }

            $this->validate($rules);

            if ($this->enganche > $this->precio_total) {
                $this->addError('enganche', 'El enganche no puede ser mayor al precio total.');
            }
        }

        if ($this->step === 3) {
            if ($this->usar_configuracion_manual) {
                $this->validarPreviewManual();
            }
        }
    }

    protected function generarFolioSeguro(): string
    {
        return 'CT-'.now()->format('YmdHis').'-'.strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    }

    protected function buildContratoPrivateBase(Lote $lote, Contrato $contrato): string
    {
        $fraccionamientoNombre = $lote->fraccionamiento?->nombre ?: 'sin-fraccionamiento';
        $fraccionamientoSlug = Str::slug($fraccionamientoNombre);

        $loteNombre = $lote->lote ?: ($lote->lote ? 'lote-'.$lote->lote : 'sin-lote');
        $loteSlug = Str::slug($loteNombre);

        return 'contratos/'.$fraccionamientoSlug.'/'.$loteSlug.'/'.$contrato->uuid;
    }

    protected function persistirDatosCompradorSiFaltan(?string $compradorFrente, ?string $compradorReverso): void
    {
        if (! $this->cliente_id) {
            return;
        }

        $cliente = Cliente::query()->find($this->cliente_id);

        if (! $cliente) {
            return;
        }

        $updates = [];

        if (blank($cliente->nombre_legal ?? null) && filled($this->comprador_nombre_legal)) {
            $updates['nombre_legal'] = $this->comprador_nombre_legal;
        }

        if (blank($cliente->curp ?? null) && filled($this->comprador_curp)) {
            $updates['curp'] = $this->comprador_curp;
        }

        if (blank($cliente->ine_frente ?? null) && filled($compradorFrente)) {
            $updates['ine_frente'] = $compradorFrente;
        }

        if (blank($cliente->ine_reverso ?? null) && filled($compradorReverso)) {
            $updates['ine_reverso'] = $compradorReverso;
        }

        if (! empty($updates)) {
            $cliente->update($updates);
        }
    }

    protected function persistirDatosVendedorSiFaltan(Lote $lote, ?string $vendedorFrente, ?string $vendedorReverso): void
    {
        $propietario = $lote->fraccionamiento?->propietario;

        if (! $propietario) {
            return;
        }

        $updates = [];

        if (blank($propietario->nombre_legal ?? null) && filled($this->vendedor_nombre_legal)) {
            $updates['nombre_legal'] = $this->vendedor_nombre_legal;
        }

        if (blank($propietario->curp ?? null) && filled($this->vendedor_curp)) {
            $updates['curp'] = $this->vendedor_curp;
        }

        if (blank($propietario->ine_frente ?? null) && filled($vendedorFrente)) {
            $updates['ine_frente'] = $vendedorFrente;
        }

        if (blank($propietario->ine_reverso ?? null) && filled($vendedorReverso)) {
            $updates['ine_reverso'] = $vendedorReverso;
        }

        if (Schema::hasColumn('propietarios', 'documentos_disk') && blank($propietario->documentos_disk ?? null)) {
            $updates['documentos_disk'] = 'private';
        }

        if (! empty($updates)) {
            $propietario->update($updates);
        }
    }

    public function guardar(
        ContratoWordService $contratoWordService,
    ) {
        $this->resetErrorBag();

        $this->step = 1;
        $this->validateStep();

        $this->step = 2;
        $this->validateStep();

        $this->step = 3;
        $this->validateStep();

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        $data = $this->payloadContrato();

        if (! $this->esDonacion) {
            ContratoPlanService::aplicarPromocionEconomica($data, $this->promocion);
        }

        $contrato = null;

        try {
            DB::transaction(function () use ($data, &$contrato) {
                $lote = Lote::query()
                    ->with('fraccionamiento')
                    ->lockForUpdate()
                    ->findOrFail($this->lote_id);

                if ($lote->estatus !== 'disponible') {
                    throw new \RuntimeException('El lote ya no está disponible.');
                }

                if ((int) $lote->fraccionamiento_id !== (int) $this->fraccionamiento_id) {
                    throw new \RuntimeException('El lote no pertenece al fraccionamiento seleccionado.');
                }

                if ((string) $lote->manzana !== (string) $this->manzana) {
                    throw new \RuntimeException('El lote no pertenece a la manzana seleccionada.');
                }

                $lote->update([
                    'area_m2' => $this->area_m2,
                    'medida_norte' => $this->medida_norte,
                    'medida_sur' => $this->medida_sur,
                    'medida_este' => $this->medida_este,
                    'medida_oeste' => $this->medida_oeste,
                    'colindancia_norte' => $this->colindancia_norte,
                    'colindancia_sur' => $this->colindancia_sur,
                    'colindancia_este' => $this->colindancia_este,
                    'colindancia_oeste' => $this->colindancia_oeste,
                ]);

                $folio = $this->generarFolioSeguro();

                $contratoData = [
                    'cliente_id' => $this->cliente_id,
                    'lote_id' => $this->lote_id,
                    'promocion_id' => $this->esDonacion ? null : $this->promocion_id,
                    'folio_contrato' => $folio,
                    'fecha_inicio' => $data['fecha_inicio'],
                    'frecuencia' => $data['frecuencia'],
                    'dia_semana' => $data['dia_semana'],
                    'dia_mes' => $data['dia_mes'],

                    'precio_total' => $data['precio_total'],
                    'enganche' => $this->esDonacion ? 0 : $data['enganche'],
                    'saldo_inicial' => $this->esDonacion ? 0 : $data['saldo_inicial'],
                    'saldo_actual' => $this->esDonacion ? 0 : $data['saldo_actual'],
                    'monto_pago' => $this->esDonacion ? 0 : $data['monto_pago'],

                    'tipo_recargo' => $this->esDonacion ? 'fijo' : $data['tipo_recargo'],
                    'valor_recargo' => $this->esDonacion ? 0 : $data['valor_recargo'],
                    'dias_gracia' => $this->esDonacion ? 0 : $data['dias_gracia'],
                    'frecuencia_recargo_dias' => $this->esDonacion ? 1 : $data['frecuencia_recargo_dias'],

                    'estatus' => $this->es_donacion ? 'donacion' : 'activo',
                    'liquidado_at' => $this->es_donacion ? now() : null,

                    'tiene_anualidad' => $this->esDonacion ? false : (bool) $data['tiene_anualidad'],
                    'anualidad_fecha' => $this->esDonacion ? null : $data['anualidad_fecha'],
                    'anualidad_monto' => $this->esDonacion ? 0 : (float) $data['anualidad_monto'],

                    'archivo_contrato_disk' => 'private',
                    'credenciales_disk' => 'private',

                    'vendedor_nombre_legal' => $this->vendedor_nombre_legal,
                    'vendedor_curp' => $this->vendedor_curp,
                    'comprador_nombre_legal' => $this->comprador_nombre_legal,
                    'comprador_curp' => $this->comprador_curp,

                    'area_m2_snapshot' => $this->area_m2,
                    'medida_norte_snapshot' => $this->medida_norte,
                    'medida_sur_snapshot' => $this->medida_sur,
                    'medida_este_snapshot' => $this->medida_este,
                    'medida_oeste_snapshot' => $this->medida_oeste,
                    'colindancia_norte_snapshot' => $this->colindancia_norte,
                    'colindancia_sur_snapshot' => $this->colindancia_sur,
                    'colindancia_este_snapshot' => $this->colindancia_este,
                    'colindancia_oeste_snapshot' => $this->colindancia_oeste,
                ];

                if (Schema::hasColumn('contratos', 'calendario_tipo')) {
                    $contratoData['calendario_tipo'] = $this->esDonacion
                        ? 'sin_cuotas'
                        : ($this->usar_configuracion_manual ? 'manual' : 'automatico');
                }

                if (Schema::hasColumn('contratos', 'calendario_json')) {
                    $contratoData['calendario_json'] = $this->esDonacion
                        ? []
                        : $this->obtenerPlanFinal();
                }

                $contrato = Contrato::query()->create($contratoData);

                $baseContrato = $this->buildContratoPrivateBase($lote, $contrato);
                $baseCredenciales = $baseContrato.'/credenciales';

                $compradorFrente = $this->comprador_ine_frente_path;
                $compradorReverso = $this->comprador_ine_reverso_path;
                $vendedorFrente = $this->vendedor_ine_frente_path;
                $vendedorReverso = $this->vendedor_ine_reverso_path;

                if ($this->comprador_ine_frente) {
                    $compradorFrente = $this->comprador_ine_frente->store($baseCredenciales, 'private');
                }

                if ($this->comprador_ine_reverso) {
                    $compradorReverso = $this->comprador_ine_reverso->store($baseCredenciales, 'private');
                }

                if ($this->vendedor_ine_frente) {
                    $vendedorFrente = $this->vendedor_ine_frente->store($baseCredenciales, 'private');
                }

                if ($this->vendedor_ine_reverso) {
                    $vendedorReverso = $this->vendedor_ine_reverso->store($baseCredenciales, 'private');
                }

                $contrato->update([
                    'comprador_ine_frente' => $compradorFrente,
                    'comprador_ine_reverso' => $compradorReverso,
                    'vendedor_ine_frente' => $vendedorFrente,
                    'vendedor_ine_reverso' => $vendedorReverso,
                ]);

                $this->persistirDatosCompradorSiFaltan($compradorFrente, $compradorReverso);
                $this->persistirDatosVendedorSiFaltan($lote, $vendedorFrente, $vendedorReverso);

                activity()
                    ->causedBy(auth()->user())
                    ->performedOn($contrato)
                    ->withProperties([
                        'folio' => $contrato->folio_contrato,
                        'cliente_id' => $contrato->cliente_id,
                        'calendario_tipo' => $this->esDonacion
                            ? 'sin_cuotas'
                            : ($this->usar_configuracion_manual ? 'manual' : 'automatico'),
                    ])
                    ->log($this->esDonacion ? 'Contrato creado por donación' : 'Contrato creado');

                if (! $this->esDonacion) {
                    $plan = $this->obtenerPlanFinal();

                    foreach ($plan as $row) {
                        Cuota::query()->create([
                            'contrato_id' => $contrato->id,
                            'numero' => (int) $row['numero'],
                            'fecha_vencimiento' => $row['fecha_vencimiento'],
                            'monto' => round((float) $row['monto'], 2),
                            'pagado_total' => 0,
                            'recargo_aplicado' => 0,
                            'estatus' => 'pendiente',
                            'es_anualidad' => (bool) ($row['es_anualidad'] ?? false),
                            'concepto' => $row['concepto'] ?? null,
                        ]);
                    }
                }

                $lote->update([
                    'estatus' => $this->esDonacion ? 'donacion' : 'vendido',
                ]);
            });

            if (! $contrato) {
                $this->dispatch('toast', type: 'error', message: 'No se pudo generar el contrato.');

                return;
            }

            $contrato->update(
                $contratoWordService->generarTodosDocx($contrato)
            );

            session()->flash(
                'success',
                $this->esDonacion
                    ? 'Contrato de donación creado y documento generado correctamente.'
                    : 'Contrato creado y documento generado correctamente.'
            );

            return redirect()->route('admin.contratos.show', [
                'contrato' => $contrato->uuid,
            ]);
        } catch (\Throwable $e) {
            report($e);

            $this->dispatch(
                'toast',
                type: 'error',
                message: 'Ocurrió un error al guardar el contrato: '.$e->getMessage()
            );

            return;
        }
    }

    protected function resolvePreviewUrl($file, ?string $path): ?string
    {
        if ($file && method_exists($file, 'temporaryUrl')) {
            try {
                return $file->temporaryUrl();
            } catch (\Throwable $e) {
                report($e);
            }
        }

        if (blank($path)) {
            return null;
        }

        try {
            return url('/admin/private-files/show?disk=private&path='.urlencode(encrypt($path)));
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    public function getCompradorIneFrentePreviewProperty(): ?string
    {
        return $this->resolvePreviewUrl(
            $this->comprador_ine_frente,
            $this->comprador_ine_frente_path,
            $this->comprador_docs_disk
        );
    }

    public function getCompradorIneReversoPreviewProperty(): ?string
    {
        return $this->resolvePreviewUrl(
            $this->comprador_ine_reverso,
            $this->comprador_ine_reverso_path,
            $this->comprador_docs_disk
        );
    }

    public function getVendedorIneFrentePreviewProperty(): ?string
    {
        return $this->resolvePreviewUrl(
            $this->vendedor_ine_frente,
            $this->vendedor_ine_frente_path,
            $this->vendedor_docs_disk
        );
    }

    public function getVendedorIneReversoPreviewProperty(): ?string
    {
        return $this->resolvePreviewUrl(
            $this->vendedor_ine_reverso,
            $this->vendedor_ine_reverso_path,
            $this->vendedor_docs_disk
        );
    }

    public function render()
    {
        $promos = collect();

        if (Schema::hasTable('promociones')) {
            $promos = Promocion::query()
                ->where('activa', true)
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'tipo', 'dias_diferidos', 'numero_cuotas']);
        }

        return view('livewire.admin.contratos.create', [
            'fraccionamientos' => Fraccionamiento::query()
                ->orderBy('nombre')
                ->get(['id', 'nombre']),
            'promociones' => $promos,
        ])->layout('layouts.app');
    }
}
