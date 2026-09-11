<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un equipo emparejado con impresión directa. Hay a lo más uno por usuario: el
 * ticket sale en la impresora de quien lo capturó.
 */
class PrintAgent extends Model
{
    protected $fillable = [
        'user_id', 'created_by_id', 'name', 'token_hash', 'token_last_four', 'is_active',
        'version', 'ticket_printer', 'quote_printer', 'available_printers', 'last_seen_at',
    ];

    protected $hidden = [
        'token_hash',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'available_printers' => 'array',
            'last_seen_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(PrintJob::class);
    }

    /**
     * El agente late cada pocos segundos; sin latido reciente se da por caído.
     */
    public function isOnline(): bool
    {
        return $this->is_active && $this->last_seen_at?->isAfter(now()->subMinutes(2)) === true;
    }
}
