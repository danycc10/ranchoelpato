<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContratoHistorial extends Model
{
    protected $table = 'contrato_historial';

    protected $fillable = [
        'contrato_id',
        'user_id',
        'tipo',
        'antes',
        'despues',
        'saldo_anterior',
        'saldo_nuevo',
        'cuotas_eliminadas',
        'cuotas_creadas',
        'nota',
    ];

    protected $casts = [
        'antes' => 'array',
        'despues' => 'array',
        'saldo_anterior' => 'decimal:2',
        'saldo_nuevo' => 'decimal:2',
    ];

    public function contrato()
    {
        return $this->belongsTo(Contrato::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
