<?php

namespace App\Services\Impresion;

use App\Enums\PrintDocumentType;
use App\Enums\PrintJobStatus;
use App\Models\PrintAgent;
use App\Models\PrintJob;
use App\Models\Recibo;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Cola de impresión del agente local: encola el ticket, lo entrega al agente
 * que lo reclama y lo cierra cuando Windows confirma.
 */
class PrintJobService
{
    private const MAX_ATTEMPTS = 5;

    public function __construct(private readonly ModoImpresionSelector $modo) {}

    /**
     * @param  array<int, int>  $adjuntoIds  recibos de recargo que se imprimen
     *                                       dentro del mismo ticket
     */
    public function queueRecibo(User $user, Recibo $recibo, array $adjuntoIds = [], int $copies = 1): PrintJob
    {
        if (! $this->modo->esAgenteLocal()) {
            throw new RuntimeException('La impresión con agente local no está activada.');
        }

        $agent = PrintAgent::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        if (! $agent) {
            throw new RuntimeException('Este usuario no tiene un equipo de impresión emparejado.');
        }

        if (! $agent->isOnline()) {
            throw new RuntimeException('El agente de impresión está desconectado. Ábrelo en esta computadora e intenta de nuevo.');
        }

        return PrintJob::query()->create([
            'print_agent_id' => $agent->id,
            'requested_by_id' => $user->id,
            'document_type' => PrintDocumentType::ReciboTicket,
            'document_id' => $recibo->id,
            'document_label' => mb_substr((string) $recibo->folio, 0, 80),
            'status' => PrintJobStatus::Pending,
            'copies' => max(1, min(5, $copies)),
            'adjunto_ids' => array_values(array_map('intval', $adjuntoIds)),
            'available_at' => now(),
        ]);
    }

    /**
     * Entrega el siguiente trabajo al agente. Un trabajo cuyo permiso venció
     * vuelve a estar disponible: si la PC se apagó a media impresión, otro
     * intento lo recupera.
     */
    public function claim(PrintAgent $agent): ?PrintJob
    {
        return DB::transaction(function () use ($agent): ?PrintJob {
            $now = now();

            PrintJob::query()
                ->where('print_agent_id', $agent->id)
                ->where('status', PrintJobStatus::Processing)
                ->where('attempts', '>=', self::MAX_ATTEMPTS)
                ->where('lease_expires_at', '<=', $now)
                ->update([
                    'status' => PrintJobStatus::Failed,
                    'last_error' => 'El agente no confirmó la impresión después de varios intentos.',
                ]);

            $job = PrintJob::query()
                ->where('print_agent_id', $agent->id)
                ->where('attempts', '<', self::MAX_ATTEMPTS)
                ->where(function ($query) use ($now): void {
                    $query->where(function ($query) use ($now): void {
                        $query->where('status', PrintJobStatus::Pending)
                            ->where('available_at', '<=', $now);
                    })->orWhere(function ($query) use ($now): void {
                        $query->where('status', PrintJobStatus::Processing)
                            ->where('lease_expires_at', '<=', $now);
                    });
                })
                ->orderBy('created_at')
                ->lockForUpdate()
                ->first();

            if (! $job) {
                return null;
            }

            $job->forceFill([
                'status' => PrintJobStatus::Processing,
                'attempts' => $job->attempts + 1,
                'claimed_at' => $now,
                'lease_expires_at' => $now->copy()->addMinutes(3),
                'last_error' => null,
            ])->save();

            return $job->fresh();
        });
    }

    public function complete(PrintJob $job): void
    {
        if ($job->status === PrintJobStatus::Completed) {
            return;
        }

        if ($job->status !== PrintJobStatus::Processing) {
            throw new RuntimeException('El trabajo no está en proceso.');
        }

        $job->forceFill([
            'status' => PrintJobStatus::Completed,
            'completed_at' => now(),
            'lease_expires_at' => null,
            'last_error' => null,
        ])->save();
    }

    public function fail(PrintJob $job, string $error): void
    {
        if ($job->status !== PrintJobStatus::Processing) {
            throw new RuntimeException('El trabajo no está en proceso.');
        }

        $agotado = $job->attempts >= self::MAX_ATTEMPTS;

        $job->forceFill([
            'status' => $agotado ? PrintJobStatus::Failed : PrintJobStatus::Pending,
            'available_at' => now()->addSeconds(min(60, 5 * max(1, $job->attempts))),
            'claimed_at' => null,
            'lease_expires_at' => null,
            'last_error' => mb_substr(trim($error), 0, 1000),
        ])->save();
    }
}
