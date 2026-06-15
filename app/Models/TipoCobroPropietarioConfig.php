<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoCobroPropietarioConfig extends Model
{
    protected $fillable = [
        'tipo_cobro_id',
        'fraccionamiento_id',
        'forma_pago_id',
        'propietario_id',
        'prioridad',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'prioridad' => 'integer',
    ];

    public function tipoCobro()
    {
        return $this->belongsTo(TipoCobro::class, 'tipo_cobro_id');
    }

    public function fraccionamiento()
    {
        return $this->belongsTo(Fraccionamiento::class);
    }

    public function formaPago()
    {
        return $this->belongsTo(FormaPago::class);
    }

    public function propietario()
    {
        return $this->belongsTo(Propietario::class);
    }
}