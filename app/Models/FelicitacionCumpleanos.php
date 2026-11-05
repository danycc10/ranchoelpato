<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FelicitacionCumpleanos extends Model
{
    public const CORREO = 'correo';

    public const WHATSAPP = 'whatsapp';

    protected $table = 'felicitaciones_cumpleanos';

    protected $fillable = [
        'cliente_id',
        'anio',
        'canal',
        'user_id',
    ];

    protected $casts = [
        'anio' => 'integer',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
