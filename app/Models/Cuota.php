<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
// ✅ Spatie Activity Log
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;

class Cuota extends Model
{
    use LogsActivity;

    protected $table = 'cuotas';

    protected $fillable = [
        'contrato_id',
        'numero',
        'fecha_vencimiento',
        'monto',
        'pagado_total',
        'recargo_aplicado',
        'estatus', // pendiente|parcial|pagada|vencida|anulada
        'es_anualidad',
        'concepto',
        'origen_pago',
    ];

    protected $casts = [
        'numero' => 'integer',
        'fecha_vencimiento' => 'date',
        'monto' => 'decimal:2',
        'pagado_total' => 'decimal:2',
        'recargo_aplicado' => 'decimal:2',
        'es_anualidad' => 'boolean',
    ];

    /**
     * ✅ Spatie Activity Log options
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('cuotas')
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->logExcept([
                'updated_at',
                'created_at',
                'deleted_at',
            ])
            ->setDescriptionForEvent(fn (string $eventName) => match ($eventName) {
                'created' => 'Cuota creada',
                'updated' => 'Cuota actualizada',
                'deleted' => 'Cuota eliminada',
                'restored' => 'Cuota restaurada',
                default => "Cuota {$eventName}",
            });
    }

    /**
     * ✅ Propiedades extra útiles para auditoría
     */
    public function tapActivity(Activity $activity, string $eventName): void
    {
        $activity->properties = $activity->properties->merge([
            'uuid' => $this->uuid ?? null,
            'contrato_id' => $this->contrato_id ?? null,
            'numero' => $this->numero ?? null,
            'estatus' => $this->estatus ?? null,
            'fecha_vencimiento' => optional($this->fecha_vencimiento)->toDateString(),
            'monto' => $this->monto ?? null,
            'pagado_total' => $this->pagado_total ?? null,
            'recargo_aplicado' => $this->recargo_aplicado ?? null,
            // Si usas multiempresa en cuotas, descomenta:
            // 'empresa_id' => $this->empresa_id ?? session('empresa_id'),
        ]);
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class, 'contrato_id');
    }

    public function pagos()
    {
        return $this->hasMany(Pago::class, 'cuota_id');
    }

    public function condonaciones()
    {
        return $this->hasMany(Condonacion::class);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function recibos()
    {
        return $this->hasMany(Recibo::class, 'cuota_id');
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
