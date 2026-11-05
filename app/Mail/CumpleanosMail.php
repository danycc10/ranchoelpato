<?php

namespace App\Mail;

use App\Models\Cliente;
use App\Services\Clientes\CumpleanosClientes;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CumpleanosMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Cliente $cliente) {}

    public function build()
    {
        $nombre = CumpleanosClientes::nombreDePila($this->cliente);

        return $this->subject('¡Feliz cumpleaños, '.$nombre.'! 🎂')
            ->markdown('emails.cumpleanos')
            ->with(['nombre' => $nombre]);
    }
}
