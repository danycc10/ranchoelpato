<?php

namespace App\Services\Comprobantes;

use App\Models\ComprobantePago;
use App\Models\Contrato;
use App\Models\Cuota;
use App\Models\Notificacion;
use App\Models\Pago;
use App\Models\Recibo;
use App\Models\ReciboPago;
use App\Models\TipoCobro;
use App\Services\Contabilidad\PropietarioContableResolver;
use App\Services\Notificaciones\ProcesarNotificacionService;
use App\Services\Recibos\CuotaAntiguedadSelector;
use App\Services\Recibos\PeriodoResolver;
use App\Services\Recibos\ReciboFolioService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Convierte un comprobante ya validado por una persona en recibo + pago.
 *
 * La decisión siempre es humana. Permite aplicar una transferencia a una o
 * varias cuotas del mismo contrato y genera recibos independientes para el
 * principal y el recargo de cada cuota.
 * Reutiliza folios, orden de antigüedad y propietario contable de la captura.
 */
class RegistrarPagoComprobanteService
{
    public function __construct(
        protected ReciboFolioService $folios,
        protected CuotaAntiguedadSelector $antiguedad,
        protected PropietarioContableResolver $propietarios,
    ) {}

    /**
     * Compatibilidad para cualquier llamada que todavía registre una sola cuota.
     *
     * @param  array{cuota_id:int,forma_pago_id:int,cuentas_bancarias_id:?int,monto:float,referencia:?string,tipos_cobro_id:int,fecha:?string,observaciones:?string,fuera_de_orden_autorizado:bool}  $datos
     */
    public function registrar(ComprobantePago $comprobante, array $datos, int $userId): Recibo
    {
        $datos['cuota_ids'] = [(int) $datos['cuota_id']];

        return $this->registrarVarios($comprobante, $datos, $userId)->firstOrFail();
    }

    /**
     * @param  array{cuota_ids:array<int,int>,distribucion?:array<int,array{cuota_id:int,monto_principal:float,monto_recargo:float}>,forma_pago_id:int,cuentas_bancarias_id:?int,monto:float,referencia:?string,tipos_cobro_id:int,fecha:?string,observaciones:?string,fuera_de_orden_autorizado:bool}  $datos
     * @return Collection<int, Recibo>
     */
    public function registrarVarios(ComprobantePago $comprobante, array $datos, int $userId): Collection
    {
        if ($comprobante->estatus === ComprobantePago::ESTATUS_VALIDADO) {
            throw ValidationException::withMessages([
                'comprobante' => 'Este comprobante ya fue validado y generó el recibo '.optional($comprobante->recibo)->folio.'.',
            ]);
        }

        $distribucionEntrada = collect($datos['distribucion'] ?? [])
            ->filter(fn ($item) => is_array($item) && ! empty($item['cuota_id']))
            ->mapWithKeys(fn (array $item) => [(int) $item['cuota_id'] => $item]);

        $cuotaIds = ($distribucionEntrada->isNotEmpty()
            ? $distribucionEntrada->keys()
            : collect($datos['cuota_ids'] ?? []))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($cuotaIds->isEmpty()) {
            throw ValidationException::withMessages(['cuotaIds' => 'Selecciona al menos una cuota.']);
        }

        return DB::transaction(function () use ($comprobante, $datos, $userId, $cuotaIds, $distribucionEntrada) {

            $cuotas = Cuota::query()
                ->with('contrato.lote')
                ->whereIn('id', $cuotaIds)
                ->whereIn('estatus', ['pendiente', 'parcial', 'vencida'])
                ->lockForUpdate()
                ->orderBy('fecha_vencimiento')
                ->orderBy('numero')
                ->orderBy('id')
                ->get();

            if ($cuotas->count() !== $cuotaIds->count()) {
                throw ValidationException::withMessages(['cuotaIds' => 'Hay cuotas inválidas o que ya no se pueden cobrar.']);
            }

            if ($cuotas->pluck('contrato_id')->unique()->count() !== 1) {
                throw ValidationException::withMessages(['cuotaIds' => 'Todas las cuotas deben pertenecer al mismo contrato.']);
            }

            $contrato = $cuotas->first()->contrato;

            if (! $contrato) {
                throw ValidationException::withMessages(['cuotaIds' => 'Las cuotas no tienen contrato asociado.']);
            }

            $this->verificarOrden(
                contratoId: (int) $contrato->id,
                cuotaIds: $cuotas->pluck('id')->map(fn ($id) => (int) $id)->all(),
                autorizado: (bool) ($datos['fuera_de_orden_autorizado'] ?? false),
            );

            $pendientes = $cuotas->mapWithKeys(fn (Cuota $cuota) => [
                $cuota->id => round(max(0, (float) $cuota->monto - (float) ($cuota->pagado_total ?? 0)), 2),
            ]);

            if ($pendientes->contains(fn ($pendiente) => (float) $pendiente <= 0)) {
                throw ValidationException::withMessages(['cuotaIds' => 'Una de las cuotas seleccionadas ya no tiene saldo pendiente.']);
            }

            $monto = round((float) $datos['monto'], 2);

            if ($monto <= 0) {
                throw ValidationException::withMessages(['monto' => 'El monto debe ser mayor a cero.']);
            }

            $distribucion = $cuotas->mapWithKeys(function (Cuota $cuota) use ($distribucionEntrada, $pendientes, $cuotas, $monto) {
                $item = $distribucionEntrada->get($cuota->id);
                $principal = $item !== null
                    ? round((float) ($item['monto_principal'] ?? 0), 2)
                    : ($cuotas->count() > 1 ? (float) $pendientes->get($cuota->id) : $monto);
                $recargo = $item !== null
                    ? round((float) ($item['monto_recargo'] ?? 0), 2)
                    : 0.0;

                return [$cuota->id => [
                    'principal' => $principal,
                    'recargo' => $recargo,
                ]];
            });

            foreach ($cuotas as $cuota) {
                $principal = (float) $distribucion->get($cuota->id)['principal'];
                $recargo = (float) $distribucion->get($cuota->id)['recargo'];
                $pendiente = (float) $pendientes->get($cuota->id);

                if ($principal <= 0) {
                    throw ValidationException::withMessages([
                        "principalPorCuota.{$cuota->id}" => 'El principal de cada cuota debe ser mayor a cero.',
                    ]);
                }

                if ($principal - $pendiente > 0.01) {
                    throw ValidationException::withMessages([
                        "principalPorCuota.{$cuota->id}" => 'El principal excede el saldo pendiente de $'.number_format($pendiente, 2).'.',
                    ]);
                }

                if ($recargo < 0) {
                    throw ValidationException::withMessages([
                        "recargoPorCuota.{$cuota->id}" => 'El recargo no puede ser negativo.',
                    ]);
                }
            }

            $totalDistribuido = round((float) $distribucion->sum(
                fn (array $item) => (float) $item['principal'] + (float) $item['recargo']
            ), 2);

            if (abs($monto - $totalDistribuido) > 0.01) {
                throw ValidationException::withMessages([
                    'distribucion' => 'El comprobante es por $'.number_format($monto, 2)
                        .', pero la distribución suma $'.number_format($totalDistribuido, 2).'. Ajusta principal o recargos.',
                ]);
            }

            $tipoRecargoId = null;

            if ($distribucion->contains(fn (array $item) => (float) $item['recargo'] > 0)) {
                $tipoRecargoId = TipoCobro::query()
                    ->whereRaw('UPPER(nombre) LIKE ?', ['%RECARGO%'])
                    ->value('id');

                if (! $tipoRecargoId) {
                    throw ValidationException::withMessages([
                        'distribucion' => 'No existe un tipo de cobro RECARGO. Créalo antes de registrar recargos.',
                    ]);
                }
            }

            $fecha = Carbon::parse($datos['fecha'] ?? now())->startOfDay();
            $recibos = collect();

            foreach ($cuotas as $cuota) {
                $montoCuota = (float) $distribucion->get($cuota->id)['principal'];
                $montoRecargo = (float) $distribucion->get($cuota->id)['recargo'];

                $saldoAnterior = (float) Contrato::query()
                    ->whereKey($contrato->id)
                    ->lockForUpdate()
                    ->value('saldo_actual');

                $recibo = $this->crearRecibo(
                    $comprobante,
                    $cuota,
                    $contrato,
                    $datos,
                    $fecha,
                    $montoCuota,
                    $saldoAnterior,
                    $userId,
                );

                $this->crearDetallePago($recibo, $comprobante, $datos, $montoCuota, $userId);

                Pago::create([
                    'contrato_id' => $contrato->id,
                    'cuota_id' => $cuota->id,
                    'recibo_id' => $recibo->id,
                    'monto' => $montoCuota,
                    'metodo' => 'transferencia',
                    'referencia' => $datos['referencia'] ?? null,
                    'estatus' => 'confirmado',
                    'fecha_pago' => now(),
                ]);

                $this->aplicarACuota($cuota, $montoCuota);
                $this->actualizarContrato($contrato, $montoCuota, $recibo);

                $comprobante->recibosGenerados()->attach($recibo->id, [
                    'cuota_id' => $cuota->id,
                    'concepto' => 'principal',
                ]);
                $this->encolarEnvioPorWhatsapp($comprobante, $recibo);

                activity()
                    ->causedBy(auth()->user())
                    ->performedOn($recibo)
                    ->withProperties([
                        'folio' => $recibo->folio,
                        'monto' => $recibo->monto,
                        'cuota_id' => $cuota->id,
                        'comprobante_id' => $comprobante->id,
                        'origen' => 'comprobante_whatsapp',
                    ])
                    ->log('Recibo creado desde comprobante de WhatsApp');

                $recibos->push($recibo);

                if ($montoRecargo > 0) {
                    $cuota->recargo_aplicado = max((float) ($cuota->recargo_aplicado ?? 0), $montoRecargo);
                    $cuota->save();

                    $saldoContrato = (float) Contrato::query()
                        ->whereKey($contrato->id)
                        ->lockForUpdate()
                        ->value('saldo_actual');
                    $datosRecargo = array_replace($datos, [
                        'tipos_cobro_id' => (int) $tipoRecargoId,
                        'observaciones' => trim('RECARGO de cuota #'.$cuota->numero.' — '.($datos['observaciones'] ?? '')),
                    ]);
                    $reciboRecargo = $this->crearRecibo(
                        $comprobante,
                        $cuota,
                        $contrato,
                        $datosRecargo,
                        $fecha,
                        $montoRecargo,
                        $saldoContrato,
                        $userId,
                    );
                    $reciboRecargo->saldo_posterior = round($saldoContrato, 2);
                    $reciboRecargo->save();

                    $this->crearDetallePago($reciboRecargo, $comprobante, $datosRecargo, $montoRecargo, $userId);

                    Pago::create([
                        'contrato_id' => $contrato->id,
                        'cuota_id' => $cuota->id,
                        'recibo_id' => $reciboRecargo->id,
                        'monto' => $montoRecargo,
                        'metodo' => 'transferencia',
                        'referencia' => $datos['referencia'] ?? null,
                        'estatus' => 'confirmado',
                        'fecha_pago' => now(),
                    ]);

                    $comprobante->recibosGenerados()->attach($reciboRecargo->id, [
                        'cuota_id' => $cuota->id,
                        'concepto' => 'recargo',
                    ]);
                    $this->encolarEnvioPorWhatsapp($comprobante, $reciboRecargo);

                    activity()
                        ->causedBy(auth()->user())
                        ->performedOn($reciboRecargo)
                        ->withProperties([
                            'folio' => $reciboRecargo->folio,
                            'monto' => $reciboRecargo->monto,
                            'cuota_id' => $cuota->id,
                            'comprobante_id' => $comprobante->id,
                            'origen' => 'comprobante_whatsapp',
                            'concepto' => 'recargo',
                        ])
                        ->log('Pago de recargo registrado desde comprobante de WhatsApp');

                    $recibos->push($reciboRecargo);
                }
            }

            $primerRecibo = $recibos->first();
            $primeraCuota = $cuotas->first();

            $comprobante->forceFill([
                'estatus' => ComprobantePago::ESTATUS_VALIDADO,
                'recibo_id' => $primerRecibo->id,
                'cliente_id' => $contrato->cliente_id,
                'contrato_id' => $contrato->id,
                'cuota_id' => $primeraCuota->id,
                'validado_por_user_id' => $userId,
                'validado_en' => now(),
                'nota_validacion' => $datos['observaciones'] ?? null,
            ])->save();

            return $recibos;
        });
    }

    /** Marca el comprobante como rechazado, sin tocar contabilidad. */
    public function rechazar(ComprobantePago $comprobante, string $motivo, int $userId): void
    {
        $comprobante->forceFill([
            'estatus' => ComprobantePago::ESTATUS_RECHAZADO,
            'validado_por_user_id' => $userId,
            'validado_en' => now(),
            'nota_validacion' => $motivo,
        ])->save();

        activity()
            ->causedBy(auth()->user())
            ->performedOn($comprobante)
            ->withProperties(['motivo' => $motivo])
            ->log('Comprobante de WhatsApp rechazado');
    }

    /**
     * Aplica la misma regla de antigüedad que la pantalla de captura: no se cobra
     * una cuota salteándose las anteriores sin autorización explícita.
     */
    protected function verificarOrden(int $contratoId, array $cuotaIds, bool $autorizado): void
    {
        $idsPendientes = Cuota::query()
            ->where('contrato_id', $contratoId)
            ->whereIn('estatus', ['pendiente', 'parcial', 'vencida'])
            ->orderBy('fecha_vencimiento')
            ->orderBy('numero')
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (! $this->antiguedad->seleccionPermitida($idsPendientes, $cuotaIds, $autorizado)) {
            throw ValidationException::withMessages([
                'cuotaIds' => 'Hay cuotas más antiguas pendientes. Cóbralas primero o autoriza la excepción.',
            ]);
        }
    }

    protected function crearRecibo(
        ComprobantePago $comprobante,
        Cuota $cuota,
        Contrato $contrato,
        array $datos,
        Carbon $fecha,
        float $monto,
        float $saldoAnterior,
        int $userId
    ): Recibo {
        return $this->folios->crearRecibo([
            'fecha' => $fecha->toDateString(),
            'anio' => (int) $fecha->format('Y'),

            'semana_pago' => (int) ($cuota->numero ?? 0),
            'semana_del_anio' => (int) $fecha->format('W'),
            'mes_del_anio' => (int) $fecha->format('m'),

            'cliente_id' => $contrato->cliente_id,
            'contrato_id' => $contrato->id,
            'lote_id' => $contrato->lote_id,
            'cuota_id' => $cuota->id,

            'tipos_cobro_id' => $datos['tipos_cobro_id'],
            'forma_pago_id' => $datos['forma_pago_id'],
            'cuentas_bancarias_id' => $datos['cuentas_bancarias_id'] ?? null,
            'periodo_id' => $this->periodoDe($cuota),
            'propietario_contable_id' => $this->propietarios->resolver(
                tipoCobroId: $datos['tipos_cobro_id'],
                fraccionamientoId: $contrato->lote?->fraccionamiento_id,
                formaPagoId: $datos['forma_pago_id']
            ),

            'monto' => $monto,
            'saldo_anterior' => round($saldoAnterior, 2),
            'saldo_posterior' => null,

            'observaciones' => $datos['observaciones']
                ?? 'Pago validado desde comprobante enviado por WhatsApp.',
            'capturado_por_user_id' => $userId,
        ]);
    }

    /**
     * El detalle de pago guarda la foto que mandó el cliente como evidencia.
     *
     * Se copia el archivo en vez de apuntar al del comprobante: la evidencia de un
     * recibo debe sobrevivir aunque el comprobante se depure después.
     */
    protected function crearDetallePago(
        Recibo $recibo,
        ComprobantePago $comprobante,
        array $datos,
        float $monto,
        int $userId
    ): void {
        $evidencia = $this->copiarEvidencia($comprobante, $recibo);

        ReciboPago::create([
            'recibo_id' => $recibo->id,
            'forma_pago_id' => $datos['forma_pago_id'],
            'cuenta_bancaria_id' => $datos['cuentas_bancarias_id'] ?? null,
            'monto' => $monto,
            'fecha_efectiva' => $recibo->fecha?->format('Y-m-d'),
            'referencia' => $datos['referencia'] ?? null,
            'observaciones' => null,
            'evidencia_path' => $evidencia['path'] ?? null,
            'evidencia_disk' => $evidencia['disk'] ?? null,
            'evidencia_mime' => $evidencia['mime'] ?? null,
            'evidencia_size' => $evidencia['size'] ?? null,
            'orden' => 1,
            'capturado_por_user_id' => $userId,
        ]);
    }

    protected function copiarEvidencia(ComprobantePago $comprobante, Recibo $recibo): array
    {
        if (! $comprobante->tieneArchivo()) {
            return [];
        }

        $disk = $comprobante->archivo_disk ?: 'private';
        $origen = $comprobante->archivo_path;

        if (! Storage::disk($disk)->exists($origen)) {
            return [];
        }

        $destino = 'recibos/evidencias/'.$recibo->folio.'/comprobante-'.basename($origen);

        Storage::disk($disk)->copy($origen, $destino);

        return [
            'path' => $destino,
            'disk' => $disk,
            'mime' => $comprobante->archivo_mime,
            'size' => $comprobante->archivo_size,
        ];
    }

    protected function aplicarACuota(Cuota $cuota, float $monto): void
    {
        $cuota->pagado_total = round((float) ($cuota->pagado_total ?? 0) + $monto, 2);
        $cuota->estatus = $cuota->pagado_total >= (float) $cuota->monto ? 'pagada' : 'parcial';
        $cuota->save();
    }

    protected function actualizarContrato(Contrato $contrato, float $monto, Recibo $recibo): void
    {
        $contrato->refresh();

        $campo = isset($contrato->saldo_actual) ? 'saldo_actual' : 'saldo';

        if (isset($contrato->{$campo})) {
            $contrato->{$campo} = max(0, (float) $contrato->{$campo} - $monto);
            $contrato->save();
        }

        $recibo->saldo_posterior = round(max(0, (float) ($contrato->{$campo} ?? 0)), 2);
        $recibo->save();

        $quedanPendientes = Cuota::query()
            ->where('contrato_id', $contrato->id)
            ->whereIn('estatus', ['pendiente', 'parcial', 'vencida'])
            ->exists();

        if (! $quedanPendientes) {
            $contrato->estatus = 'liquidado';

            if (isset($contrato->{$campo})) {
                $contrato->{$campo} = 0;
            }

            $contrato->liquidado_at ??= now();
            $contrato->save();
        }
    }

    protected function periodoDe(Cuota $cuota): ?int
    {
        return app(PeriodoResolver::class)->idParaFecha($cuota->fecha_vencimiento);
    }

    /**
     * Deja el recibo listo para salir por WhatsApp.
     *
     * Primero se guarda en cola dentro de la transacción. Después del commit se
     * intenta entregar de inmediato; si OpenWA falla, permanece en cola para que
     * `notificaciones:procesar` lo reintente sin afectar el pago registrado.
     */
    protected function encolarEnvioPorWhatsapp(ComprobantePago $comprobante, Recibo $recibo): void
    {
        $telefono = $comprobante->telefono;

        if (empty($telefono)) {
            return;
        }

        $notificacion = Notificacion::create([
            'canal' => 'whatsapp',
            'tipo' => 'recibo_pago',
            'cliente_id' => $recibo->cliente_id,
            'contrato_id' => $recibo->contrato_id,
            'cuota_id' => $recibo->cuota_id,
            'destino' => $telefono,
            'payload' => [
                'recibo_id' => $recibo->id,
                'folio' => $recibo->folio,
                'mensaje' => 'Recibimos tu pago. Adjuntamos tu recibo '.$recibo->folio
                    .' por $'.number_format((float) $recibo->monto, 2).'. ¡Gracias!',
            ],
            'estatus' => 'en_cola',
        ]);

        $notificacionId = (int) $notificacion->id;

        DB::afterCommit(function () use ($notificacionId): void {
            $pendiente = Notificacion::find($notificacionId);

            if (! $pendiente || $pendiente->estatus !== 'en_cola') {
                return;
            }

            app(ProcesarNotificacionService::class)->procesar(
                $pendiente,
                mantenerEnColaSiFalla: true,
            );
        });
    }
}
