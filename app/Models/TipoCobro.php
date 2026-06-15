<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoCobro extends Model
{
    protected $table = 'tipos_cobro';

    protected $fillable = [
        'nombre',
        'categoria',
        'requiere_periodo',
        'activa',
        'propietario_contable_id',
    ];

    protected $casts = [
        'requiere_periodo' => 'boolean',
        'activa' => 'boolean',
    ];

    public function recibos(): HasMany
    {
        return $this->hasMany(Recibo::class, 'tipos_cobro_id');
    }


    public function propietarioConfigs()
    {
        return $this->hasMany(TipoCobroPropietarioConfig::class, 'tipo_cobro_id');
    }

    public function propietarioContable()
    {
        return $this->belongsTo(Propietario::class, 'propietario_id');
    }
}
