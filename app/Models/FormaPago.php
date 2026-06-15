<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FormaPago extends Model
{
    protected $table = 'formas_pago';

    protected $fillable = [
        'nombre',
        'requiere_cuenta',
        'activa',
    ];

    protected $casts = [
        'requiere_cuenta' => 'boolean',
        'activa' => 'boolean',
    ];

    public function recibos(): HasMany
    {
        return $this->hasMany(Recibo::class, 'forma_pago_id');
    }

    public function propietarioConfigs()
    {
        return $this->hasMany(TipoCobroPropietarioConfig::class);
    }
}
