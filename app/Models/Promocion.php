<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Promocion extends Model
{
    protected $table = 'promociones';

    protected $fillable = [
        'nombre', 'codigo', 'tipo',
        'dias_diferidos', 'numero_cuotas', 'porcentaje', 'monto_fijo',
        'activa', 'fecha_inicio', 'fecha_fin',
    ];

    protected $casts = [
        'activa' => 'boolean',
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'porcentaje' => 'decimal:2',
        'monto_fijo' => 'decimal:2',
    ];
}
