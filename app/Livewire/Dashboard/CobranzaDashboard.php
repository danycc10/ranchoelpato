<?php

namespace App\Livewire\Dashboard;

use App\Exports\CobranzaPendientesExport;
use App\Models\Cuota;
use App\Models\Notificacion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;

class CobranzaDashboard extends Component
{
    use WithPagination;

    public string $hoy;
    public int $diasTolerancia = 0;
    public bool $soloConContacto = true;

    public ?string $search = null;
    public ?int $fraccionamientoId = null;

    public array $selectedHoy = [];
    public array $selectedAtrasadas = [];
    
    public string $tipoCuota = 'todos'; // todos | terrenos | servicio

    public function mount(): void
    {
        $this->hoy = now()->toDateString();
    }

    public function updated($property): void
    {
        if (in_array($property, ['hoy', 'diasTolerancia', 'soloConContacto', 'search', 'fraccionamientoId','tipoCuota'])) {
            $this->resetPage('hoyPage');
            $this->resetPage('atrPage');
            $this->selectedHoy = [];
            $this->selectedAtrasadas = [];
        }
    }

    protected function aplicarFiltrosBase(Builder $q): Builder
    {
        if ($this->fraccionamientoId) {
            $q->whereHas('contrato.lote.fraccionamiento', function ($qq) {
                $qq->where('id', $this->fraccionamientoId);
            });
        }

        if (filled($this->search)) {
            $search = trim($this->search);

            $q->where(function ($qq) use ($search) {
                $qq->whereHas('contrato.cliente', function ($c) use ($search) {
                    $c->where('nombres', 'like', "%{$search}%")
                        ->orWhere('apellidos', 'like', "%{$search}%")
                        ->orWhere('telefono', 'like', "%{$search}%")
                        ->orWhere('correo', 'like', "%{$search}%");
                })
                    ->orWhereHas('contrato', function ($c) use ($search) {
                        $c->where('folio_contrato', 'like', "%{$search}%");
                    })
                    ->orWhereHas('contrato.lote', function ($l) use ($search) {
                        $l->where('lote', 'like', "%{$search}%")
                            ->orWhere('manzana', 'like', "%{$search}%");
                    });
            });
        }
        
if ($this->tipoCuota === 'terreno') {
    $q->whereHas('contrato', function ($c) {
        $c->where('tipo', 'terreno');
    });
}

if ($this->tipoCuota === 'servicio') {
    $q->whereHas('contrato', function ($c) {
        $c->where('tipo', 'servicio');
    });
}

        return $q;
    }

    protected function cuotasHoyQuery(): Builder
    {
        $q = Cuota::query()
            ->with([
                'contrato.cliente',
                'contrato.lote.fraccionamiento',
            ])
            ->whereDate('fecha_vencimiento', $this->hoy)
            ->where('estatus', 'pendiente');

        return $this->aplicarFiltrosBase($q);
    }

    protected function cuotasAtrasadasQuery(): Builder
    {
        $limite = Carbon::parse($this->hoy)
            ->subDays($this->diasTolerancia)
            ->toDateString();

        $q = Cuota::query()
            ->with([
                'contrato.cliente',
                'contrato.lote.fraccionamiento',
            ])
            ->whereDate('fecha_vencimiento', '<', $limite)
            ->where('estatus', 'pendiente');

        if ($this->soloConContacto) {
            $q->whereHas('contrato.cliente', function ($qq) {
                $qq->whereNotNull('correo')
                    ->orWhereNotNull('telefono');
            });
        }

        return $this->aplicarFiltrosBase($q);
    }

    public function getKpisProperty(): object
    {
        $hoyCount = (clone $this->cuotasHoyQuery())->count();
        $hoyMonto = (clone $this->cuotasHoyQuery())->sum('monto');

        $atrCount = (clone $this->cuotasAtrasadasQuery())->count();
        $atrMonto = (clone $this->cuotasAtrasadasQuery())->sum('monto');

        return (object) [
            'hoy_count' => $hoyCount,
            'hoy_monto' => (float) $hoyMonto,
            'atr_count' => $atrCount,
            'atr_monto' => (float) $atrMonto,
            'total_count' => $hoyCount + $atrCount,
            'total_monto' => (float) $hoyMonto + (float) $atrMonto,
        ];
    }

    public function getCuotasHoyProperty()
    {
        return (clone $this->cuotasHoyQuery())
            ->orderBy('fecha_vencimiento')
            ->paginate(15, pageName: 'hoyPage');
    }

    public function getCuotasAtrasadasProperty()
    {
        return (clone $this->cuotasAtrasadasQuery())
            ->orderBy('fecha_vencimiento')
            ->paginate(15, pageName: 'atrPage');
    }

    public function getFraccionamientosProperty()
    {
        return DB::table('fraccionamientos')
            ->select('id', 'nombre')
            ->orderBy('nombre')
            ->get();
    }

    public function getNotificadasHoyMapProperty(): array
    {
        $rows = Notificacion::query()
            ->select(
                'cuota_id',
                DB::raw("MAX(CASE WHEN estatus='enviado' THEN 2 WHEN estatus='en_cola' THEN 1 ELSE 0 END) as lvl")
            )
            ->where('tipo', 'cuota_atrasada')
            ->whereIn('estatus', ['enviado', 'en_cola'])
            ->whereNotNull('cuota_id')
            ->where(function ($q) {
                $q->whereDate('enviado_en', $this->hoy)
                    ->orWhereDate('created_at', $this->hoy);
            })
            ->groupBy('cuota_id')
            ->get();

        $map = [];

        foreach ($rows as $r) {
            $map[(int) $r->cuota_id] = ((int) $r->lvl === 2) ? 'enviado' : 'en_cola';
        }

        return $map;
    }

    protected function yaNotificadaHoy(int $cuotaId): bool
    {
        return Notificacion::query()
            ->where('tipo', 'cuota_atrasada')
            ->where('estatus', 'enviado')
            ->whereDate('enviado_en', $this->hoy)
            ->where('cuota_id', $cuotaId)
            ->exists();
    }

    protected function yaEnColaHoy(int $cuotaId): bool
    {
        return Notificacion::query()
            ->where('tipo', 'cuota_atrasada')
            ->where('estatus', 'en_cola')
            ->whereDate('created_at', $this->hoy)
            ->where('cuota_id', $cuotaId)
            ->exists();
    }

    protected function getDiasGraciaContrato($contrato): int
    {
        $candidatos = [
            'dias_gracia',
            'dias_gracia_pago',
            'dias_gracia_recargo',
            'dias_gracia_mensualidad',
            'grace_days',
        ];

        foreach ($candidatos as $campo) {
            if (isset($contrato->{$campo}) && is_numeric($contrato->{$campo})) {
                return (int) $contrato->{$campo};
            }
        }

        return 0;
    }

    protected function getFrecuenciaRecargoDias($contrato): int
    {
        $candidatos = [
            'recargo_cada_dias',
            'cada_dias_recargo',
            'frecuencia_recargo_dias',
            'dias_recargo',
            'dias_gracia',
            'dias_gracia_pago',
            'dias_gracia_recargo',
            'dias_gracia_mensualidad',
            'grace_days',
        ];

        foreach ($candidatos as $campo) {
            if (isset($contrato->{$campo}) && is_numeric($contrato->{$campo})) {
                $valor = (int) $contrato->{$campo};

                if ($valor > 0) {
                    return $valor;
                }
            }
        }

        return 1;
    }

    protected function calcularRecargo(Cuota $cuota): float
    {
        $estado = $this->calcularEstadoRecargoCuota($cuota);

        return round((float) ($estado['recargo_monto'] ?? 0), 2);
    }

    protected function calcularRecargoDesdeContrato($contrato, Cuota $cuota, int $diasAtraso = 0): float
    {
        $guardado = (float) ($cuota->recargo_aplicado ?? 0);

        if ($guardado > 0) {
            return round($guardado, 2);
        }

        $get = function (array $campos) use ($contrato) {
            foreach ($campos as $campo) {
                if (isset($contrato->{$campo}) && $contrato->{$campo} !== '' && $contrato->{$campo} !== null) {
                    $raw = (string) $contrato->{$campo};
                    $raw = str_replace(['$', ',', ' '], '', $raw);
                    $raw = str_replace('%', '', $raw);

                    if (is_numeric($raw)) {
                        return (float) $raw;
                    }
                }
            }

            return null;
        };

        $tipo = null;

        foreach (['recargo_tipo', 'tipo_recargo', 'recargo_mode', 'recargo_modo'] as $campoTipo) {
            if (isset($contrato->{$campoTipo}) && $contrato->{$campoTipo}) {
                $tipo = mb_strtolower(trim((string) $contrato->{$campoTipo}));
                break;
            }
        }

        $valor = $get([
            'recargo_valor',
            'valor_recargo',
            'recargo_amount',
            'recargo_cantidad',
        ]);

        $frecuenciaDias = max(1, $this->getFrecuenciaRecargoDias($contrato));

        $veces = $diasAtraso >= 0
            ? (int) floor($diasAtraso / $frecuenciaDias) + 1
            : 0;

        if ($tipo && $valor !== null && $valor > 0) {
            if (str_contains($tipo, 'dia') || str_contains($tipo, 'diar')) {
                return $diasAtraso >= 0
                    ? round($valor * ($diasAtraso + 1), 2)
                    : 0.0;
            }

            if (str_contains($tipo, 'por') || str_contains($tipo, 'pct') || str_contains($tipo, '%')) {
                return $diasAtraso >= 0
                    ? round(((float) $cuota->monto) * ($valor / 100) * $veces, 2)
                    : 0.0;
            }

            return $veces > 0 ? round($valor * $veces, 2) : 0.0;
        }

        $porDia = $get([
            'recargo_por_dia',
            'monto_recargo_dia',
            'recargo_diario',
            'recargo_dia',
            'monto_recargo_por_dia',
            'recargo_x_dia',
        ]);

        if ($porDia !== null && $porDia > 0 && $diasAtraso >= 0) {
            return round($porDia * ($diasAtraso + 1), 2);
        }

        $porcentaje = $get([
            'recargo_porcentaje',
            'porcentaje_recargo',
            'recargo_pct',
            'recargo_percent',
            'porc_recargo',
            'recargo_%',
        ]);

        if ($porcentaje !== null && $porcentaje > 0) {
            return $diasAtraso >= 0
                ? round((((float) $cuota->monto) * ($porcentaje / 100)) * $veces, 2)
                : 0.0;
        }

        $fijo = $get([
            'recargo_monto',
            'monto_recargo',
            'recargo_fijo',
            'recargo',
            'recargo_mensualidad',
            'monto_recargo_mensualidad',
            'recargo_importe',
            'importe_recargo',
        ]);

        if ($fijo !== null && $fijo > 0) {
            return $veces > 0 ? round($fijo * $veces, 2) : 0.0;
        }

        return 0.0;
    }

    protected function calcularEstadoRecargoCuota(Cuota $cuota): array
    {
        $contrato = $cuota->contrato;

        if (! $contrato) {
            return [
                'cuota_vencida' => false,
                'cuota_en_gracia' => false,
                'dias_atraso' => 0,
                'dias_gracia_total' => 0,
                'recargo_monto' => 0.0,
                'recargo_monto_original' => 0.0,
                'recargo_condonado' => false,
                'cuota_fecha_vencimiento' => null,
                'cuota_fecha_limite' => null,
                'cuota_fecha_limite_condonada' => null,
                'recargo_mensaje' => null,
            ];
        }

        $contrato->loadMissing('lote.fraccionamiento');

        $vence = Carbon::parse($cuota->fecha_vencimiento)->startOfDay();
        $hoy = Carbon::parse($this->hoy)->startOfDay();

        $diasGracia = max(0, (int) $this->getDiasGraciaContrato($contrato));

        /*
     * En dashboard no hay forma de pago seleccionada todavía.
     * Por eso se calcula SIN beneficio extra de efectivo.
     */
        $esEfectivo = false;

        $fraccionamientoNombre = mb_strtoupper(trim((string) ($contrato->lote?->fraccionamiento?->nombre ?? '')));
        $diaVence = (int) $vence->dayOfWeekIso;

        $aplicaDiaExtraPorFraccionamiento = false;

        if (
            $fraccionamientoNombre === 'DEL NORTE' ||
            $fraccionamientoNombre === 'DEL NORTE LUNES'
        ) {
            $aplicaDiaExtraPorFraccionamiento = in_array($diaVence, [1, 3], true);
        } elseif ($fraccionamientoNombre === 'REYES') {
            $aplicaDiaExtraPorFraccionamiento = ($diaVence === 4);
        }

        $aplicaDiaExtraEfectivo = $esEfectivo && $aplicaDiaExtraPorFraccionamiento;

        $primerDiaRecargo = $vence->copy()->addDays($diasGracia + 1);

        $primerDiaRecargoConDiaExtra = $aplicaDiaExtraEfectivo
            ? $primerDiaRecargo->copy()->addDay()
            : $primerDiaRecargo->copy();

        $cuotaEnGracia = $hoy->lt($primerDiaRecargo);
        $cuotaVencida = $hoy->greaterThanOrEqualTo($primerDiaRecargo);

        $estaDentroDelDiaExtra = $aplicaDiaExtraEfectivo
            && $hoy->greaterThanOrEqualTo($primerDiaRecargo)
            && $hoy->lt($primerDiaRecargoConDiaExtra);

        $diasDesdePrimerRecargo = $hoy->greaterThanOrEqualTo($primerDiaRecargo)
            ? $primerDiaRecargo->diffInDays($hoy)
            : -1;

        $diasAtrasoParaMostrar = $diasDesdePrimerRecargo >= 0
            ? $diasDesdePrimerRecargo + 1
            : 0;

        $recargoCalculado = $this->calcularRecargoDesdeContrato(
            $contrato,
            $cuota,
            $diasDesdePrimerRecargo
        );

        $recargoOriginal = $recargoCalculado;
        $recargoFinal = 0.0;
        $recargoCondonado = false;
        $mensaje = null;

        if ($cuotaEnGracia) {
            $recargoFinal = 0.0;
            $mensaje = 'Cuota aún dentro del periodo sin recargo.';
        } elseif ($estaDentroDelDiaExtra) {
            $recargoFinal = 0.0;
            $recargoCondonado = true;
            $mensaje = 'Cuota vencida: se otorgó 1 día extra antes del primer recargo.';
        } else {
            $recargoFinal = $cuotaVencida ? $recargoCalculado : 0.0;

            if ($cuotaVencida && $recargoCalculado > 0) {
                $mensaje = 'Cuota vencida: se cobrará recargo según el contrato.';
            }
        }

        return [
            'cuota_vencida' => $cuotaVencida,
            'cuota_en_gracia' => $cuotaEnGracia,
            'dias_atraso' => $diasAtrasoParaMostrar,
            'dias_gracia_total' => $diasGracia,
            'recargo_monto' => round($recargoFinal, 2),
            'recargo_monto_original' => round($recargoOriginal, 2),
            'recargo_condonado' => $recargoCondonado,
            'cuota_fecha_vencimiento' => $vence->format('Y-m-d'),
            'cuota_fecha_limite' => $primerDiaRecargo->copy()->subDay()->format('Y-m-d'),
            'cuota_fecha_limite_condonada' => $primerDiaRecargoConDiaExtra->format('Y-m-d'),
            'recargo_mensaje' => $mensaje,
        ];
    }

    protected $listeners = [
        'abrir-whatsapp-desde-cuota' => 'abrirWhatsapp',
        'notificar-cuota-desde-tabla' => 'notificarCuota',
        'notificar-seleccionadas-desde-tabla' => 'notificarSeleccionadasDesdeTabla',
    ];

    public function notificarSeleccionadasDesdeTabla(array $ids): void
    {
        if (empty($ids)) {
            $this->dispatch('toast', type: 'warning', message: 'Selecciona al menos una cuota atrasada.');
            return;
        }

        $this->crearNotificacionesSalida($ids);

        $this->dispatch('toast', type: 'success', message: 'Notificaciones seleccionadas agregadas a cola.');
    }

    public function exportarPendientes()
    {
        $cuotasHoy = (clone $this->cuotasHoyQuery())
            ->with(['contrato.cliente', 'contrato.lote.fraccionamiento'])
            ->orderBy('fecha_vencimiento')
            ->get();

        $cuotasAtrasadas = (clone $this->cuotasAtrasadasQuery())
            ->with(['contrato.cliente', 'contrato.lote.fraccionamiento'])
            ->orderBy('fecha_vencimiento')
            ->get();

        return Excel::download(
            new CobranzaPendientesExport($cuotasHoy, $cuotasAtrasadas, $this->hoy),
            'cobranza-pendientes-' . $this->hoy . '.xlsx'
        );
    }

    public function notificarSeleccionadasAtrasadas(): void
    {
        $ids = array_map('intval', $this->selectedAtrasadas);

        if (empty($ids)) {
            $this->dispatch('toast', type: 'warning', message: 'Selecciona al menos una cuota atrasada.');
            return;
        }

        $this->crearNotificacionesSalida($ids);

        activity()
            ->causedBy(auth()->user())
            ->event('notificaciones_seleccionadas_cuotas_atrasadas')
            ->withProperties([
                'cuotas_ids' => $ids,
                'total_cuotas' => count($ids),
                'fecha' => now()->toDateTimeString(),
            ])
            ->log('Generó notificaciones seleccionadas de cuotas atrasadas');

        $this->selectedAtrasadas = [];

        $this->dispatch('toast', type: 'success', message: 'Notificaciones seleccionadas agregadas a cola.');
    }

    public function notificarCuota(int $cuotaId): void
    {
        if ($this->yaNotificadaHoy($cuotaId)) {

            activity()
                ->causedBy(auth()->user())
                ->event('intento_notificacion_duplicada')
                ->withProperties([
                    'cuota_id' => $cuotaId,
                    'motivo' => 'Ya notificada hoy',
                    'fecha' => now()->toDateTimeString(),
                ])
                ->log('Intentó notificar una cuota ya notificada hoy');

            $this->dispatch('toast', type: 'warning', message: 'Ya fue notificada hoy con éxito.');

            return;
        }

        if ($this->yaEnColaHoy($cuotaId)) {

            activity()
                ->causedBy(auth()->user())
                ->event('intento_notificacion_ya_en_cola')
                ->withProperties([
                    'cuota_id' => $cuotaId,
                    'motivo' => 'Ya en cola hoy',
                    'fecha' => now()->toDateTimeString(),
                ])
                ->log('Intentó notificar una cuota que ya está en cola');

            $this->dispatch('toast', type: 'warning', message: 'Ya está en cola para enviar hoy.');

            return;
        }

        $this->crearNotificacionesSalida([$cuotaId]);

        activity()
            ->causedBy(auth()->user())
            ->event('notificacion_individual_cuota_atrasada')
            ->withProperties([
                'cuota_id' => $cuotaId,
                'fecha' => now()->toDateTimeString(),
            ])
            ->log('Generó una notificación individual de cuota atrasada');

        $this->dispatch('toast', type: 'success', message: 'Notificación agregada a cola.');
    }

    public function notificarAtrasadasMasivo(): void
    {
        $ids = (clone $this->cuotasAtrasadasQuery())
            ->limit(300)
            ->pluck('id')
            ->all();

        if (empty($ids)) {
            $this->dispatch('toast', type: 'warning', message: 'No hay cuotas atrasadas.');
            return;
        }

        $this->crearNotificacionesSalida($ids);

        activity()
            ->causedBy(auth()->user())
            ->event('notificaciones_masivas_cuotas_atrasadas')
            ->withProperties([
                'cuotas_ids' => $ids,
                'total_cuotas' => count($ids),
                'limite' => 300,
            ])
            ->log('Generó notificaciones masivas de cuotas atrasadas');

        $this->dispatch('toast', type: 'success', message: 'Notificaciones masivas generadas sin duplicar hoy.');
    }

    protected function crearNotificacionesSalida(array $cuotaIds): void
    {
        $cuotas = Cuota::query()
            ->with([
                'contrato.cliente',
                'contrato.lote.fraccionamiento',
            ])
            ->whereIn('id', $cuotaIds)
            ->get();

        foreach ($cuotas as $cuota) {
            $cliente = $cuota->contrato?->cliente;

            if (! $cliente) {
                continue;
            }

            if ($this->yaNotificadaHoy($cuota->id) || $this->yaEnColaHoy($cuota->id)) {
                continue;
            }

            $payload = $this->payloadCuota($cuota);

            if (! empty($cliente->correo)) {
                Notificacion::create([
                    'canal'       => 'correo',
                    'tipo'        => 'cuota_atrasada',
                    'cliente_id'  => $cliente->id,
                    'contrato_id' => $cuota->contrato_id,
                    'cuota_id'    => $cuota->id,
                    'destino'     => $cliente->correo,
                    'payload'     => $payload,
                    'estatus'     => 'en_cola',
                ]);
            }
        }
    }

    protected function payloadCuota(Cuota $cuota): array
    {
        $cliente = $cuota->contrato?->cliente;
        $lote = $cuota->contrato?->lote;

        $fraccionamiento = $lote?->fraccionamiento?->nombre ?? '—';
        $loteTexto = $lote?->lote ?? '—';

        $recargo = (float) ($cuota->recargo_aplicado ?? 0);

        if ($recargo <= 0) {
            $recargo = $this->calcularRecargo($cuota);
        }

        $total = (float) $cuota->monto + (float) $recargo;

        return [
            'cuota_id'        => $cuota->id,
            'contrato'        => $cuota->contrato?->folio_contrato,
            'cliente'         => trim(($cliente?->nombres ?? '') . ' ' . ($cliente?->apellidos ?? '')),
            'lote'            => $loteTexto,
            'fraccionamiento' => $fraccionamiento,
            'vencimiento'     => $cuota->fecha_vencimiento,
            'monto'           => (float) $cuota->monto,
            'recargo'         => (float) $recargo,
            'total'           => (float) $total,
            'mensaje'         => "Hola {$cliente?->nombres}, te recordamos que tienes una cuota pendiente. Lote: {$loteTexto}. Fraccionamiento: {$fraccionamiento}. Monto: $" .
                number_format((float) $cuota->monto, 2) .
                ". Recargo: $" . number_format((float) $recargo, 2) .
                ". Total: $" . number_format($total, 2) . ".",
        ];
    }

    public function abrirWhatsapp(int $cuotaId): void
    {
        $cuota = Cuota::query()
            ->with([
                'contrato.cliente',
                'contrato.lote.fraccionamiento',
            ])
            ->findOrFail($cuotaId);

        $cliente = $cuota->contrato?->cliente;

        if (! $cliente || empty($cliente->telefono)) {
            $this->dispatch('toast', type: 'warning', message: 'El cliente no tiene teléfono registrado.');
            return;
        }

        $telefono = $this->normalizarTelefonoMexico($cliente->telefono);

        if (! $telefono) {
            $this->dispatch('toast', type: 'warning', message: 'El teléfono no es válido.');
            return;
        }

        $payload = $this->payloadCuota($cuota);
        $url = 'https://wa.me/' . $telefono . '?text=' . urlencode($payload['mensaje']);

        $this->dispatch('open-url', url: $url);
    }

    protected function normalizarTelefonoMexico(?string $telefono): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $telefono);

        if (strlen($digits) === 10) {
            return '52' . $digits;
        }

        if (strlen($digits) === 12 && str_starts_with($digits, '52')) {
            return $digits;
        }

        if (strlen($digits) === 13 && str_starts_with($digits, '521')) {
            return '52' . substr($digits, 3);
        }

        return null;
    }

    public function aplicarRecargoYCrearRecibo(int $cuotaId): void
    {
        DB::transaction(function () use ($cuotaId) {
            $cuota = Cuota::query()
                ->with(['contrato.cliente'])
                ->lockForUpdate()
                ->findOrFail($cuotaId);

            if ($cuota->estatus !== 'pendiente') {
                return;
            }

            $recargo = (float) ($cuota->recargo_aplicado ?? 0);

            if ($recargo <= 0) {
                $recargo = $this->calcularRecargo($cuota);
                $cuota->recargo_aplicado = $recargo;
                $cuota->save();
            }

            if ($recargo <= 0) {
                return;
            }

            $tipoRecargoId = DB::table('tipos_cobro')
                ->whereRaw('UPPER(nombre) LIKE "%RECARGO%"')
                ->value('id');

            if (! $tipoRecargoId) {
                return;
            }

            DB::table('recibos')->insert([
                'contrato_id'    => $cuota->contrato_id,
                'cliente_id'     => $cuota->contrato->cliente_id ?? null,
                'tipos_cobro_id' => $tipoRecargoId,
                'monto'          => $recargo,
                'fecha'          => $this->hoy,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
        });

        $this->dispatch('toast', type: 'success', message: 'Recargo aplicado y recibo generado.');
    }

    public function render()
    {
        return view('livewire.dashboard.cobranza-dashboard', [
            'kpis'              => $this->kpis,
            'cuotasHoy'         => $this->cuotasHoy,
            'cuotasAtrasadas'   => $this->cuotasAtrasadas,
            'notificadasHoyMap' => $this->notificadasHoyMap,
            'fraccionamientos'  => $this->fraccionamientos,
        ])->layout('layouts.app');
    }
}
