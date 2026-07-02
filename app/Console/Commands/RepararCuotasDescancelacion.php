<?php

namespace App\Console\Commands;

use App\Models\Contrato;
use App\Models\ContratoHistorial;
use App\Models\Cuota;
use App\Models\Pago;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepararCuotasDescancelacion extends Command
{
    protected $signature = 'app:reparar-cuotas-descancelacion
        {contrato : UUID o folio del contrato}
        {--dry-run : Muestra los cambios sin actualizar datos}';

    protected $description = 'Reacomoda cuotas pendientes para que inicien despues de la ultima cuota pagada.';

    public function handle(): int
    {
        $contrato = Contrato::withTrashed()
            ->where('uuid', $this->argument('contrato'))
            ->orWhere('folio_contrato', $this->argument('contrato'))
            ->first();

        if (! $contrato) {
            $this->error('No se encontro el contrato indicado.');

            return self::FAILURE;
        }

        $cambios = $this->generarCambios($contrato);

        $this->info("Contrato: {$contrato->folio_contrato} ({$contrato->uuid})");

        if ($cambios['motivo']) {
            $this->warn($cambios['motivo']);

            return self::SUCCESS;
        }

        $this->info("Ultima pagada: {$cambios['ultima_pagada']}");
        $this->info("Cuotas a mover: {$cambios['total']}");
        $this->table(
            ['#', 'Antes', 'Despues', 'Monto', 'Estatus'],
            collect($cambios['preview'])->map(fn (array $cambio) => [
                $cambio['numero'],
                $cambio['antes'],
                $cambio['despues'],
                '$'.number_format($cambio['monto'], 2),
                $cambio['estatus'],
            ])->all()
        );

        if ($this->option('dry-run')) {
            $this->warn('Dry run: no se actualizaron datos.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($contrato): void {
            $contrato = Contrato::withTrashed()
                ->lockForUpdate()
                ->whereKey($contrato->id)
                ->firstOrFail();

            $cambios = $this->generarCambios($contrato);

            if ($cambios['motivo']) {
                throw new \RuntimeException($cambios['motivo']);
            }

            foreach ($cambios['cambios'] as $cambio) {
                Cuota::query()
                    ->whereKey($cambio['id'])
                    ->update([
                        'fecha_vencimiento' => $cambio['despues'],
                        'updated_at' => now(),
                    ]);
            }

            ContratoHistorial::create([
                'contrato_id' => $contrato->id,
                'user_id' => auth()->id(),
                'tipo' => 'reparacion_cuotas_descancelacion',
                'antes' => [
                    'ultima_pagada' => $cambios['ultima_pagada'],
                    'primera_pendiente' => $cambios['primera_pendiente'],
                    'cuotas' => $cambios['preview'],
                ],
                'despues' => [
                    'primera_pendiente' => $cambios['cambios'][0]['despues'] ?? null,
                    'cuotas_actualizadas' => $cambios['total'],
                    'cuotas' => $cambios['preview'],
                ],
                'saldo_anterior' => (float) ($contrato->saldo_actual ?? 0),
                'saldo_nuevo' => (float) ($contrato->saldo_actual ?? 0),
                'cuotas_eliminadas' => 0,
                'cuotas_creadas' => 0,
                'nota' => 'Reparacion de fechas de cuotas pendientes despues de descancelar contrato.',
            ]);
        });

        $this->info('Cuotas reparadas correctamente.');

        return self::SUCCESS;
    }

    private function generarCambios(Contrato $contrato): array
    {
        $ultimaPagada = Cuota::query()
            ->where('contrato_id', $contrato->id)
            ->where('estatus', 'pagada')
            ->orderByDesc('fecha_vencimiento')
            ->orderByDesc('numero')
            ->first(['id', 'numero', 'fecha_vencimiento']);

        if (! $ultimaPagada) {
            return $this->sinCambios('El contrato no tiene cuotas pagadas para tomar como base.');
        }

        $pendientes = Cuota::query()
            ->where('contrato_id', $contrato->id)
            ->where('estatus', '!=', 'pagada')
            ->orderBy('numero')
            ->orderBy('id')
            ->get(['id', 'numero', 'fecha_vencimiento', 'monto', 'estatus']);

        if ($pendientes->isEmpty()) {
            return $this->sinCambios('El contrato no tiene cuotas pendientes por reparar.');
        }

        $idsPendientes = $pendientes->pluck('id')->all();
        $tienenPagosConfirmados = Pago::query()
            ->whereIn('cuota_id', $idsPendientes)
            ->where('estatus', 'confirmado')
            ->exists();

        if ($tienenPagosConfirmados) {
            return $this->sinCambios('Hay cuotas no pagadas con pagos confirmados. Revisa esos pagos antes de mover fechas.');
        }

        $ultimaFecha = Carbon::parse($ultimaPagada->fecha_vencimiento)->startOfDay();
        $primeraPendiente = Carbon::parse($pendientes->first()->fecha_vencimiento)->startOfDay();

        if ($primeraPendiente->gt($ultimaFecha)) {
            return $this->sinCambios('La primera cuota pendiente ya esta despues de la ultima pagada.');
        }

        $cambios = $pendientes
            ->values()
            ->map(function (Cuota $cuota, int $index) use ($contrato, $ultimaFecha): array {
                $fecha = $this->siguienteFecha($contrato, $ultimaFecha, $index);

                return [
                    'id' => (int) $cuota->id,
                    'numero' => (int) $cuota->numero,
                    'antes' => Carbon::parse($cuota->fecha_vencimiento)->toDateString(),
                    'despues' => $fecha->toDateString(),
                    'monto' => (float) $cuota->monto,
                    'estatus' => (string) $cuota->estatus,
                ];
            })
            ->all();

        return [
            'motivo' => null,
            'ultima_pagada' => $ultimaFecha->toDateString(),
            'primera_pendiente' => $primeraPendiente->toDateString(),
            'total' => count($cambios),
            'preview' => array_slice($cambios, 0, 15),
            'cambios' => $cambios,
        ];
    }

    private function siguienteFecha(Contrato $contrato, Carbon $ultimaFecha, int $index): Carbon
    {
        if ($contrato->frecuencia === 'semanal') {
            $diaSemana = (int) ($contrato->dia_semana ?: $ultimaFecha->isoWeekday());
            if ($diaSemana < 1 || $diaSemana > 7) {
                $diaSemana = (int) $ultimaFecha->isoWeekday();
            }

            $fecha = $ultimaFecha->copy();
            $delta = $diaSemana - (int) $fecha->isoWeekday();
            if ($delta <= 0) {
                $delta += 7;
            }

            return $fecha->addDays($delta)->addWeeks($index)->startOfDay();
        }

        $diaMes = (int) ($contrato->dia_mes ?: $ultimaFecha->day);
        if ($diaMes < 1) {
            $diaMes = 1;
        }
        if ($diaMes > 31) {
            $diaMes = 31;
        }

        $fecha = $ultimaFecha->copy()
            ->addMonthsNoOverflow($index + 1)
            ->startOfMonth();
        $fecha->day(min($diaMes, $fecha->daysInMonth));

        return $fecha->startOfDay();
    }

    private function sinCambios(string $motivo): array
    {
        return [
            'motivo' => $motivo,
            'ultima_pagada' => null,
            'primera_pendiente' => null,
            'total' => 0,
            'preview' => [],
            'cambios' => [],
        ];
    }
}
