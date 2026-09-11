<?php

namespace App\Enums;

enum PrintJobStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En espera',
            self::Processing => 'Imprimiendo',
            self::Completed => 'Impreso',
            self::Failed => 'Fallido',
        };
    }
}
