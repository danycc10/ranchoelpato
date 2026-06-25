<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CuotaAtrasadaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public array $payload) {}

    public function build()
    {

        return $this->subject('Pago atrasado - Se aplicara recargo')
            ->markdown('emails.cuota-atrasada')
            ->with([
                'p' => $this->payload,
            ]);
    }
}
