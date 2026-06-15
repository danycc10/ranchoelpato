<?php

namespace App\Jobs;

use App\Mail\ReciboMail;
use App\Models\Recibo;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendReciboMail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $reciboId) {}

    public function handle(): void
    {
        $recibo = Recibo::with('cliente')->find($this->reciboId);

        if (!$recibo) {
            Log::warning('SendReciboMail: recibo no encontrado', ['recibo_id' => $this->reciboId]);
            return;
        }

        // ✅ TU CAMPO REAL ES "correo"
        $email = trim((string)($recibo->cliente?->correo ?? ''));

        if ($email === '') {
            Log::info('SendReciboMail: cliente sin correo, se omite', [
                'recibo_id' => $recibo->id,
                'cliente_id' => $recibo->cliente_id,
            ]);
            return;
        }

        try {
            Mail::to($email)->send(new ReciboMail($recibo));
        } catch (\Throwable $e) {
            Log::error('SendReciboMail: error enviando correo', [
                'recibo_id' => $recibo->id,
                'cliente_id' => $recibo->cliente_id,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
        }
    }
}