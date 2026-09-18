<?php

namespace App\Services\Contratos;

use App\Models\Contrato;
use App\Models\Recibo;
use App\Models\TipoCobro;
use App\Services\Contabilidad\PropietarioContableResolver;
use App\Services\Recibos\ReciboFolioService;
use Carbon\Carbon;
use RuntimeException;

/**
 * Venta de contado: el contrato se paga completo al firmarse.
 *
 * El contrato nace liquidado y sin calendario, así que no hay cuota a la cual
 * colgar el pago: el recibo se emite contra el contrato con su propio concepto.
 */
class VentaContadoService
{
    public const TIPO_COBRO = 'CONTADO';

    public function __construct(
        protected ReciboFolioService $folios,
        protected PropietarioContableResolver $propietarios,
    ) {}

    /**
     * Cómo queda un contrato pagado de contado: sin saldo, sin cuotas y
     * liquidado desde el primer día.
     *
     * @return array<string, mixed>
     */
    public function camposDelContrato(float $precioTotal, ?Carbon $fecha = null): array
    {
        $fecha = $fecha ?: now();

        return [
            'es_contado' => true,
            'precio_total' => round($precioTotal, 2),
            // Lo pagado no es enganche de un crédito: es el precio completo.
            'enganche' => 0,
            'saldo_inicial' => 0,
            'saldo_actual' => 0,
            'monto_pago' => 0,
            'tipo_recargo' => 'fijo',
            'valor_recargo' => 0,
            'dias_gracia' => 0,
            'frecuencia_recargo_dias' => 1,
            'estatus' => 'liquidado',
            'liquidado_at' => $fecha,
        ];
    }

    /**
     * @param  array{forma_pago_id: int, cuentas_bancarias_id?: int|null, fecha?: string|null, observaciones?: string|null}  $pago
     */
    public function generarRecibo(Contrato $contrato, array $pago, ?int $userId = null): Recibo
    {
        if (! $contrato->es_contado) {
            throw new RuntimeException('El contrato no está marcado como pagado de contado.');
        }

        $monto = round((float) $contrato->precio_total, 2);

        if ($monto <= 0) {
            throw new RuntimeException('Un contrato de contado necesita un precio mayor a cero.');
        }

        $fecha = Carbon::parse($pago['fecha'] ?? null ?: now());
        $tipoCobroId = $this->tipoCobroId();
        $formaPagoId = (int) $pago['forma_pago_id'];

        $contrato->loadMissing('lote');

        return $this->folios->crearRecibo([
            'fecha' => $fecha->toDateString(),
            'anio' => (int) $fecha->format('Y'),

            'semana_del_anio' => (int) $fecha->format('W'),
            'mes_del_anio' => (int) $fecha->format('m'),

            'cliente_id' => $contrato->cliente_id,
            'contrato_id' => $contrato->id,
            'lote_id' => $contrato->lote_id,
            // Sin calendario no hay cuota que referenciar.
            'cuota_id' => null,

            'tipos_cobro_id' => $tipoCobroId,
            'forma_pago_id' => $formaPagoId,
            'cuentas_bancarias_id' => $pago['cuentas_bancarias_id'] ?? null,
            'periodo_id' => null,
            'propietario_contable_id' => $this->propietarios->resolver(
                tipoCobroId: $tipoCobroId,
                fraccionamientoId: $contrato->lote?->fraccionamiento_id,
                formaPagoId: $formaPagoId,
            ),

            'monto' => $monto,
            'saldo_anterior' => $monto,
            'saldo_posterior' => 0,

            'observaciones' => ($pago['observaciones'] ?? null) ?: 'Pago de contado al firmar el contrato.',
            'capturado_por_user_id' => $userId,
        ]);
    }

    /**
     * El concepto con el que salen estos recibos, para que los reportes
     * separen la venta de contado de las mensualidades y los enganches.
     */
    public function tipoCobroId(): int
    {
        $tipo = TipoCobro::query()->firstOrCreate(
            ['nombre' => self::TIPO_COBRO],
            [
                'categoria' => 'contrato',
                'requiere_periodo' => false,
                'activa' => true,
            ],
        );

        return (int) $tipo->id;
    }
}
