<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// ✅ Spatie Activity Log
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;

class Condonacion extends Model
{
    use LogsActivity;

    protected $table = 'condonaciones';

    protected $fillable = [
        'cliente_id',
        'contrato_id',
        'cuota_id',
        'monto',
        'motivo',
        'recibo_id',
        'created_by_user_id',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
    ];

    /**
     * ✅ Activity Log configuration
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('condonaciones')
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->logExcept([
                'updated_at',
                'created_at',
                'deleted_at',
            ])
            ->setDescriptionForEvent(fn(string $eventName) => match ($eventName) {
                'created' => 'Condonación creada',
                'updated' => 'Condonación actualizada',
                'deleted' => 'Condonación eliminada',
                default => "Condonación {$eventName}",
            });
    }

    /**
     * ✅ Propiedades extra críticas para auditoría
     */
    public function tapActivity(Activity $activity, string $eventName): void
    {
        $activity->properties = $activity->properties->merge([
            'cliente_id' => $this->cliente_id ?? null,
            'contrato_id' => $this->contrato_id ?? null,
            'cuota_id' => $this->cuota_id ?? null,
            'recibo_id' => $this->recibo_id ?? null,

            'monto' => $this->monto ?? null,
            'motivo' => $this->motivo ?? null,

            'created_by_user_id' => $this->created_by_user_id ?? null,

            // Multiempresa si aplica:
            // 'empresa_id' => $this->empresa_id ?? session('empresa_id'),
        ]);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    public function cuota(): BelongsTo
    {
        return $this->belongsTo(Cuota::class);
    }

    public function recibo(): BelongsTo
    {
        return $this->belongsTo(Recibo::class);
    }
}
