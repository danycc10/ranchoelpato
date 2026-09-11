<?php

namespace App\Enums;

enum PrintingMode: string
{
    /** El navegador abre el ticket y el usuario manda a imprimir. */
    case Browser = 'browser';

    /** El agente instalado en la computadora jala el ticket y lo imprime solo. */
    case LocalAgent = 'local_agent';

    public function label(): string
    {
        return match ($this) {
            self::Browser => 'Navegador y PDF',
            self::LocalAgent => 'Agente local',
        };
    }
}
