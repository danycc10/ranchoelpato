<?php

namespace App\Services\Recibos;

use App\Models\Contrato;
use App\Models\Cuota;
use App\Models\FormaPago;
use Carbon\Carbon;

class RecargoCuotaCalculator
{
    public function estado(
        Cuota $cuota,
        ?int $formaPagoId = null,
        bool $permiteAjusteUsuario = false,
        string $recargoModo = 'auto',
        ?float $recargoMontoManual = null,
    ): array {
        $contrato = $cuota->contrato;
        $contrato->loadMissing('lote.fraccionamiento');

        $vence = Carbon::parse($cuota->fecha_vencimiento)->startOfDay();
        $hoy = Carbon::now()->startOfDay();

        $diasGracia = max(0, $this->diasGraciaContrato($contrato));
        $esEfectivo = $this->formaPagoEsEfectivo($formaPagoId);

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

        $recargoCalculado = $this->recargoDesdeContrato(
            $contrato,
            $cuota,
            $diasDesdePrimerRecargo
        );

        $recargoOriginal = $recargoCalculado;
        $recargoFinal = 0.0;
        $recargoCondonado = false;
        $mensaje = null;

        if ($esEfectivo) {
            if ($cuotaEnGracia) {
                $recargoFinal = 0.0;
                $mensaje = "Cuota a\u{00FA}n dentro del periodo sin recargo.";
            } elseif ($estaDentroDelDiaExtra) {
                $recargoFinal = 0.0;
                $recargoCondonado = true;

                if ($fraccionamientoNombre === 'DEL NORTE') {
                    $mensaje = "Cuota vencida: por pago en efectivo se otorga 1 d\u{00ED}a extra antes del primer recargo para Del Norte.";
                } elseif ($fraccionamientoNombre === 'REYES') {
                    $mensaje = "Cuota vencida: por pago en efectivo se otorga 1 d\u{00ED}a extra antes del primer recargo para Reyes.";
                } else {
                    $mensaje = "Cuota vencida: se otorg\u{00F3} 1 d\u{00ED}a extra por pago en efectivo.";
                }
            } else {
                $recargoFinal = $recargoCalculado;
                $mensaje = "Cuota vencida: se cobrar\u{00E1} recargo seg\u{00FA}n las reglas del contrato.";
            }
        } else {
            $recargoFinal = $cuotaVencida ? $recargoCalculado : 0.0;

            if ($cuotaVencida && $recargoCalculado > 0) {
                $mensaje = "Cuota vencida: se cobrar\u{00E1} recargo seg\u{00FA}n el contrato.";
            } elseif ($cuotaEnGracia) {
                $mensaje = "Cuota a\u{00FA}n dentro del periodo sin recargo.";
            }
        }

        if ($permiteAjusteUsuario) {
            if ($recargoModo === 'condonar') {
                $recargoFinal = 0.0;
                $recargoCondonado = true;
            } elseif ($recargoModo === 'manual') {
                $recargoFinal = round(max(0, (float) ($recargoMontoManual ?? 0)), 2);
                $recargoCondonado = $recargoFinal <= 0;
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

    protected function diasGraciaContrato(Contrato $contrato): int
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

    protected function recargoDesdeContrato(Contrato $contrato, Cuota $cuota, int $diasAtraso = 0): float
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

        $frecuenciaDias = max(1, $this->frecuenciaRecargoDias($contrato));
        $veces = $diasAtraso >= 0
            ? (int) floor($diasAtraso / $frecuenciaDias) + 1
            : 0;

        if ($tipo && $valor !== null && $valor > 0) {
            if (str_contains($tipo, 'dia') || str_contains($tipo, 'diar')) {
                return $diasAtraso >= 0 ? round($valor * ($diasAtraso + 1), 2) : 0.0;
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

    protected function frecuenciaRecargoDias(Contrato $contrato): int
    {
        $frecuencia = (int) ($contrato->frecuencia_recargo_dias ?? 7);

        return max(1, $frecuencia);
    }

    protected function formaPagoEsEfectivo(?int $formaPagoId): bool
    {
        if (! $formaPagoId) {
            return false;
        }

        $formaPago = FormaPago::find($formaPagoId);
        if (! $formaPago) {
            return false;
        }

        $nombre = mb_strtoupper(trim((string) $formaPago->nombre));

        return str_contains($nombre, 'EFECTIVO');
    }
}
