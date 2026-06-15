<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Fraccionamiento extends Model
{
    protected $table = 'fraccionamientos';

    protected $fillable = [
        'propietario_id',
        'nombre',
        'ubicacion',
        'logo_path', // ✅ nuevo
        'contrato_base_path',
    ];

    protected $appends = [
        'logo_url',
    ];

    public function propietario(): BelongsTo
    {
        return $this->belongsTo(Propietario::class, 'propietario_id');
    }

    public function lotes(): HasMany
    {
        return $this->hasMany(Lote::class, 'fraccionamiento_id');
    }

    // ✅ URL lista para <img src="">
    public function getLogoUrlAttribute(): ?string
    {
        if (! $this->logo_path) return null;
        return asset('storage/' . ltrim($this->logo_path, '/'));
    }

    public function getContratoBaseNombreAttribute(): ?string
    {
        if (! $this->contrato_base_path) {
            return null;
        }

        return basename($this->contrato_base_path);
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

    public function propietarioConfigs()
    {
        return $this->hasMany(TipoCobroPropietarioConfig::class);
    }
}
