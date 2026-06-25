<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
// ✅ Spatie Activity Log
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;

class Lote extends Model
{
    use LogsActivity;

    protected $table = 'lotes';

    protected $fillable = [
        'fraccionamiento_id',
        'manzana',
        'lote',
        'clave',
        'area_m2',
        'precio_lista',
        'estatus',
        'notas',

        'medida_norte',
        'medida_sur',
        'medida_este',
        'medida_oeste',
        'colindancia_norte',
        'colindancia_sur',
        'colindancia_este',
        'colindancia_oeste',
    ];

    protected $casts = [
        'area_m2' => 'decimal:2',
        'precio_lista' => 'decimal:2',
    ];

    /**
     * ✅ Spatie Activity Log options
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('lotes')
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->logExcept([
                'updated_at',
                'created_at',
                'deleted_at',
            ])
            ->setDescriptionForEvent(fn (string $eventName) => match ($eventName) {
                'created' => 'Lote creado',
                'updated' => 'Lote actualizado',
                'deleted' => 'Lote eliminado',
                'restored' => 'Lote restaurado',
                default => "Lote {$eventName}",
            });
    }

    /**
     * ✅ Propiedades extra útiles para auditoría
     */
    public function tapActivity(Activity $activity, string $eventName): void
    {
        $activity->properties = $activity->properties->merge([
            'uuid' => $this->uuid ?? null,
            'fraccionamiento_id' => $this->fraccionamiento_id ?? null,
            'manzana' => $this->manzana ?? null,
            'lote' => $this->lote ?? null,
            'clave' => $this->clave ?? null,
            'estatus' => $this->estatus ?? null,
            'area_m2' => $this->area_m2 ?? null,
            'precio_lista' => $this->precio_lista ?? null,
            // Si usas multiempresa aquí, descomenta:
            // 'empresa_id' => $this->empresa_id ?? session('empresa_id'),
        ]);
    }

    public function fraccionamiento(): BelongsTo
    {
        return $this->belongsTo(Fraccionamiento::class, 'fraccionamiento_id');
    }

    public function contratos(): HasMany
    {
        return $this->hasMany(Contrato::class, 'lote_id');
    }

    public function recibos(): HasMany
    {
        return $this->hasMany(Recibo::class, 'lote_id');
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
}
