<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CuentaBancaria extends Model
{
    protected $table = 'cuentas_bancarias';

    protected $fillable = [
        'propietario_id',
        'alias',
        'banco',
        'tipo',
        'numero',
        'activa',
    ];

    protected $casts = [
        'activa' => 'boolean',
    ];

    public function propietario(): BelongsTo
    {
        return $this->belongsTo(Propietario::class, 'propietario_id');
    }

    public function recibos(): HasMany
    {
        return $this->hasMany(Recibo::class, 'cuenta_bancaria_id');
    }
}
