<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Periodo extends Model
{
    protected $table = 'periodos';

    protected $fillable = [
        'tipo',   // mensual|anual
        'anio',
        'mes',
        'nombre',
    ];

    protected $casts = [
        'anio' => 'integer',
        'mes' => 'integer',
    ];

    public function recibos(): HasMany
    {
        return $this->hasMany(Recibo::class, 'periodo_id');
    }
}
