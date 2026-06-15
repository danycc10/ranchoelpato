<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Propietario extends Model
{
    protected $table = 'propietarios';

    protected $fillable = [
       'nombre',
        'nombre_legal',
        'curp',
        'telefono',
        'correo',
        'notas',
        'ine_frente',
        'ine_reverso',
        'documentos_disk',
    ];

    public function fraccionamientos(): HasMany
    {
        return $this->hasMany(Fraccionamiento::class, 'propietario_id');
    }

    public function cuentasBancarias(): HasMany
    {
        return $this->hasMany(CuentaBancaria::class, 'propietario_id');
    }

    public function lotesOverride(): HasMany
    {
        return $this->hasMany(Lote::class, 'propietario_id'); // override opcional en lotes
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function tipoCobroConfigs()
{
    return $this->hasMany(TipoCobroPropietarioConfig::class);
}
}
