<?php

namespace App\Enums;

enum PrintDocumentType: string
{
    /**
     * Ticket de recibo. El valor 'sale_ticket' es el que ya entiende el agente
     * compilado del POS: con él elige la impresora térmica en vez de la de
     * hojas. Cambiarlo obliga a recompilar el agente.
     */
    case ReciboTicket = 'sale_ticket';

    public function label(): string
    {
        return match ($this) {
            self::ReciboTicket => 'Ticket de recibo',
        };
    }
}
