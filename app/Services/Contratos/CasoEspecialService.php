<?php

namespace App\Services\Contratos;

use App\Models\Contrato;
use App\Models\ContratoHistorial;
use Illuminate\Support\Facades\DB;

/**
 * Marca un contrato como caso especial y lo devuelve después a su estatus previo.
 *
 * Mientras está en "especial" el contrato sigue existiendo y admite pagos, pero
 * sus cuotas quedan fuera de cobranza (atrasos, vencimientos del día, KPIs y
 * notificaciones) y de los reportes mensuales.
 */
class CasoEspecialService
{
    public const TIPO_MARCAR = 'marcar_caso_especial';

    public const TIPO_RESOLVER = 'resolver_caso_especial';

    /**
     * Estatus a los que se puede regresar al resolver el caso.
     */
    public const ESTATUS_RESTAURABLES = ['activo', 'moroso', 'liquidado', 'donacion'];

    public function marcar(Contrato $contrato, ?string $nota = null, ?int $userId = null): Contrato
    {
        if ($contrato->esCasoEspecial()) {
            throw new \RuntimeException('El contrato ya está marcado como caso especial.');
        }

        if ($contrato->trashed() || (string) $contrato->estatus === 'cancelado') {
            throw new \RuntimeException('Un contrato cancelado no puede marcarse como caso especial.');
        }

        return DB::transaction(function () use ($contrato, $nota, $userId) {
            $estatusAnterior = (string) ($contrato->estatus ?? 'activo');

            $contrato->estatus = Contrato::ESTATUS_ESPECIAL;
            $contrato->save();

            ContratoHistorial::create([
                'contrato_id' => $contrato->id,
                'user_id' => $userId,
                'tipo' => self::TIPO_MARCAR,
                'antes' => ['estatus_contrato' => $estatusAnterior],
                'despues' => ['estatus_contrato' => Contrato::ESTATUS_ESPECIAL],
                'saldo_anterior' => (float) ($contrato->saldo_actual ?? 0),
                'saldo_nuevo' => (float) ($contrato->saldo_actual ?? 0),
                'cuotas_eliminadas' => 0,
                'cuotas_creadas' => 0,
                'nota' => $this->nota(
                    $nota,
                    "Contrato marcado como caso especial (estatus anterior: {$estatusAnterior}). Sus cuotas dejan de contar para cobranza y reportes."
                ),
            ]);

            return $contrato->refresh();
        });
    }

    /**
     * Devuelve el contrato al estatus que tenía antes de marcarse como especial.
     */
    public function resolver(Contrato $contrato, ?string $nota = null, ?int $userId = null): Contrato
    {
        if (! $contrato->esCasoEspecial()) {
            throw new \RuntimeException('El contrato no está marcado como caso especial.');
        }

        return DB::transaction(function () use ($contrato, $nota, $userId) {
            $estatusRestaurar = $this->estatusPrevio($contrato);

            $contrato->estatus = $estatusRestaurar;
            $contrato->save();

            ContratoHistorial::create([
                'contrato_id' => $contrato->id,
                'user_id' => $userId,
                'tipo' => self::TIPO_RESOLVER,
                'antes' => ['estatus_contrato' => Contrato::ESTATUS_ESPECIAL],
                'despues' => ['estatus_contrato' => $estatusRestaurar],
                'saldo_anterior' => (float) ($contrato->saldo_actual ?? 0),
                'saldo_nuevo' => (float) ($contrato->saldo_actual ?? 0),
                'cuotas_eliminadas' => 0,
                'cuotas_creadas' => 0,
                'nota' => $this->nota(
                    $nota,
                    "Caso especial resuelto. El contrato regresó a '{$estatusRestaurar}' y sus cuotas vuelven a contar para cobranza y reportes."
                ),
            ]);

            return $contrato->refresh();
        });
    }

    /**
     * Estatus guardado al marcar el caso; cae en "activo" si no hay registro usable.
     */
    public function estatusPrevio(Contrato $contrato): string
    {
        $historial = ContratoHistorial::query()
            ->where('contrato_id', $contrato->id)
            ->where('tipo', self::TIPO_MARCAR)
            ->latest('id')
            ->first();

        $estatusAnterior = (string) data_get($historial?->antes, 'estatus_contrato', 'activo');

        return in_array($estatusAnterior, self::ESTATUS_RESTAURABLES, true)
            ? $estatusAnterior
            : 'activo';
    }

    protected function nota(?string $nota, string $base): string
    {
        $nota = trim((string) $nota);

        return $nota !== '' ? $base.' Motivo: '.$nota : $base;
    }
}
