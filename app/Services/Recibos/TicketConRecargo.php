<?php

namespace App\Services\Recibos;

use App\Models\Pago;
use App\Models\Recibo;
use App\Models\TipoCobro;
use Illuminate\Support\Str;

/**
 * Empareja un recibo con el de recargo de su misma cuota, y arma el lote que
 * los imprime juntos en un solo ticket.
 *
 * Cuando se cobra por primera vez, el recargo sale dentro del ticket del
 * recibo principal y el total va sumado. Reimprimir después debía dar lo
 * mismo, así que Cuotas y Recibos arman el ticket por aquí: con la lógica
 * copiada en cada pantalla, tarde o temprano una imprime distinto que la otra.
 */
class TicketConRecargo
{
    /** Minutos que vive el lote de impresión. */
    private const VIGENCIA_LOTE = 15;

    /**
     * El par de recibos de una cuota: el del cobro y el de su recargo.
     *
     * @return array{principal: ?Recibo, recargo: ?Recibo}
     */
    public function parDeCuota(int $cuotaId): array
    {
        return [
            'principal' => $this->principalDeCuota($cuotaId),
            'recargo' => $this->recargoDeCuota($cuotaId),
        ];
    }

    /**
     * El par al que pertenece un recibo, sin importar cuál de los dos se pidió.
     *
     * Un recibo sin cuota detrás (servicios sueltos, contado) no tiene pareja:
     * se devuelve él mismo como principal.
     *
     * @return array{principal: ?Recibo, recargo: ?Recibo}
     */
    public function parDe(Recibo $recibo): array
    {
        $cuotaId = $this->cuotaDe($recibo);

        if (! $cuotaId) {
            return ['principal' => $recibo, 'recargo' => null];
        }

        $par = $this->parDeCuota($cuotaId);

        // Si la búsqueda no lo encontró (un recibo raro, histórico), al menos
        // el que se pidió tiene que salir.
        if (! $par['principal'] && ! $par['recargo']) {
            return ['principal' => $recibo, 'recargo' => null];
        }

        return $par;
    }

    /** La cuota que cubre un recibo: la propia o la de su desglose. */
    public function cuotaDe(Recibo $recibo): ?int
    {
        if ($recibo->cuota_id) {
            return (int) $recibo->cuota_id;
        }

        $cuotaId = Pago::query()
            ->where('recibo_id', $recibo->id)
            ->whereNull('deleted_at')
            ->whereNull('anulado_at')
            ->whereNotNull('cuota_id')
            ->orderBy('id')
            ->value('cuota_id');

        return $cuotaId ? (int) $cuotaId : null;
    }

    /** URL para ver e imprimir los dos en un solo ticket. */
    public function urlTicketUnico(int $reciboId, ?int $adjuntoId, int $userId): string
    {
        return route('admin.recibos.print-lote', [
            'token' => $this->lote($reciboId, $adjuntoId, $userId),
        ]);
    }

    /** URL para descargar los dos en un solo PDF. */
    public function urlPdfUnico(int $reciboId, ?int $adjuntoId, int $userId): string
    {
        return route('admin.recibos.pdf-lote', [
            'token' => $this->lote($reciboId, $adjuntoId, $userId),
        ]);
    }

    /**
     * Guarda el lote que junta los dos recibos y devuelve su token. El mismo
     * sirve para la vista de impresión y para el PDF.
     *
     * El adjunto va en las dos listas a propósito: `recibo_ids` es lo que el
     * controlador carga de la base, y `adjunto_ids` solo marca cuál se pega al
     * ticket del principal en vez de salir en su propia hoja.
     */
    public function lote(int $reciboId, ?int $adjuntoId, int $userId): string
    {
        $token = Str::uuid()->toString();
        $adjuntos = array_values(array_filter([$adjuntoId]));

        cache()->put(
            'print_batch:'.$token,
            [
                'user_id' => $userId,
                'recibo_ids' => array_merge([$reciboId], $adjuntos),
                'adjunto_ids' => $adjuntos,
            ],
            now()->addMinutes(self::VIGENCIA_LOTE)
        );

        return $token;
    }

    public function principalDeCuota(int $cuotaId): ?Recibo
    {
        $recargoTipoCobroId = $this->tipoCobroRecargoId();

        // 1) Flujo normal, por el desglose de pagos.
        $pago = Pago::query()
            ->where('cuota_id', $cuotaId)
            ->where('estatus', 'confirmado')
            ->whereNotNull('recibo_id')
            ->with(['recibo:id,uuid,folio,tipos_cobro_id,observaciones,es_historico'])
            ->whereHas('recibo', function ($r) use ($recargoTipoCobroId) {
                if ($recargoTipoCobroId) {
                    $r->where('tipos_cobro_id', '!=', $recargoTipoCobroId);
                }

                $r->where(fn ($x) => $x->whereNull('observaciones')
                    ->orWhereRaw('UPPER(observaciones) NOT LIKE ?', ['%RECARGO%']));
            })
            ->latest('id')
            ->first();

        if ($pago?->recibo) {
            return $pago->recibo;
        }

        // 2) Flujo histórico, directo por el recibo.
        return Recibo::query()
            ->where('cuota_id', $cuotaId)
            ->when($recargoTipoCobroId, fn ($q) => $q->where('tipos_cobro_id', '!=', $recargoTipoCobroId))
            ->where(fn ($x) => $x->whereNull('observaciones')
                ->orWhereRaw('UPPER(observaciones) NOT LIKE ?', ['%RECARGO%']))
            ->latest('id')
            ->first(['id', 'uuid', 'folio']);
    }

    public function recargoDeCuota(int $cuotaId): ?Recibo
    {
        $recargoTipoCobroId = $this->tipoCobroRecargoId();

        $pago = Pago::query()
            ->where('cuota_id', $cuotaId)
            ->where('estatus', 'confirmado')
            ->whereNotNull('recibo_id')
            ->with(['recibo:id,uuid,folio,tipos_cobro_id,observaciones'])
            ->whereHas('recibo', function ($r) use ($recargoTipoCobroId) {
                $r->where(function ($x) use ($recargoTipoCobroId) {
                    if ($recargoTipoCobroId) {
                        $x->where('tipos_cobro_id', $recargoTipoCobroId);
                    }

                    $x->orWhereRaw('UPPER(observaciones) LIKE ?', ['%RECARGO%']);
                });
            })
            ->latest('id')
            ->first();

        if ($pago?->recibo) {
            return $pago->recibo;
        }

        return Recibo::query()
            ->where('cuota_id', $cuotaId)
            ->where(function ($x) use ($recargoTipoCobroId) {
                if ($recargoTipoCobroId) {
                    $x->where('tipos_cobro_id', $recargoTipoCobroId);
                }

                $x->orWhereRaw('UPPER(observaciones) LIKE ?', ['%RECARGO%']);
            })
            ->latest('id')
            ->first(['id', 'uuid', 'folio']);
    }

    /** El tipo de cobro con el que se registran los recargos, si existe. */
    public function tipoCobroRecargoId(): ?int
    {
        $id = TipoCobro::query()
            ->whereRaw('UPPER(nombre) LIKE ?', ['%RECARGO%'])
            ->value('id');

        return $id ? (int) $id : null;
    }
}
