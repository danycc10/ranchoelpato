<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReciboPago extends Model
{
    use SoftDeletes;

    protected $table = 'recibos_pagos';

    protected $fillable = [
        'recibo_id',
        'forma_pago_id',
        'cuenta_bancaria_id',
        'monto',
        'fecha_efectiva',
        'referencia',
        'observaciones',
        'evidencia_path',
        'evidencia_disk',
        'evidencia_mime',
        'evidencia_size',
        'orden',
        'capturado_por_user_id',
                'anulado_at',
        'anulado_por_user_id',
        'anulado_motivo',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
    ];

    public function recibo()
    {
        return $this->belongsTo(Recibo::class, 'recibo_id');
    }

    public function formaPago()
    {
        return $this->belongsTo(FormaPago::class, 'forma_pago_id');
    }

    public function cuentaBancaria()
    {
        return $this->belongsTo(CuentaBancaria::class, 'cuenta_bancaria_id');
    }

    public function capturadoPor()
    {
        return $this->belongsTo(User::class, 'capturado_por_user_id');
    }
}