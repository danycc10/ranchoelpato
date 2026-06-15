<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

// ✅ Spatie Activity Log
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;

class Recibo extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'recibos';

    protected $fillable = [
        'folio',
        'uuid',
        'fecha',
        'anio',
        'semana_pago',
        'semana_del_anio',
        'mes_del_anio',

        'cliente_id',
        'contrato_id',
        'cuota_id',
        'lote_id',

        'tipos_cobro_id',
        'forma_pago_id',
        'cuentas_bancarias_id',
        'periodo_id',
        'propietario_contable_id',

        'monto',
        'saldo_anterior',
        'saldo_posterior',
        'observaciones',

        'capturado_por_user_id',

        'anulado_at',
        'anulado_por_user_id',
        'anulado_motivo',

        'afecta_reportes',
        'tipo_movimiento',
        'es_historico',

        'evidencia_path',
        'evidencia_disk',
        'evidencia_mime',
        'evidencia_size',

        'firma_path',
        'firma_disk',
        'firma_mime',
        'firma_size',
        'firmado_en',
        'firmado_por',
        
    ];

    protected $casts = [
        'fecha' => 'date',
        'anio' => 'integer',
        'semana_pago' => 'integer',
        'semana_del_anio' => 'integer',
        'mes_del_anio' => 'integer',
        'monto' => 'decimal:2',
        'saldo_anterior' => 'decimal:2',
        'saldo_posterior' => 'decimal:2',
        'afecta_reportes' => 'boolean',
        'es_historico' => 'boolean',
        'anulado_at' => 'datetime',
        'firmado_en' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('recibos')
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->logExcept([
                'updated_at',
                'created_at',
                'deleted_at',
            ])
            ->setDescriptionForEvent(fn (string $eventName) => match ($eventName) {
                'created' => 'Recibo creado',
                'updated' => 'Recibo actualizado',
                'deleted' => 'Recibo eliminado',
                'restored' => 'Recibo restaurado',
                default => "Recibo {$eventName}",
            });
    }

    public function tapActivity(Activity $activity, string $eventName): void
    {
        $activity->properties = $activity->properties->merge([
            'uuid' => $this->uuid ?? null,
            'folio' => $this->folio ?? null,
            'fecha' => optional($this->fecha)->toDateString(),
            'monto' => $this->monto ?? null,

            'cliente_id' => $this->cliente_id ?? null,
            'contrato_id' => $this->contrato_id ?? null,
            'lote_id' => $this->lote_id ?? null,

            'tipos_cobro_id' => $this->tipos_cobro_id ?? null,
            'forma_pago_id' => $this->forma_pago_id ?? null,
            'cuentas_bancarias_id' => $this->cuentas_bancarias_id ?? null,
            'periodo_id' => $this->periodo_id ?? null,

            'capturado_por_user_id' => $this->capturado_por_user_id ?? null,
        ]);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class, 'contrato_id');
    }

    public function lote(): BelongsTo
    {
        return $this->belongsTo(Lote::class, 'lote_id');
    }

    public function cuota(): BelongsTo
    {
        return $this->belongsTo(Cuota::class, 'cuota_id');
    }

    public function tipoCobro(): BelongsTo
    {
        return $this->belongsTo(TipoCobro::class, 'tipos_cobro_id');
    }

    public function formaPago(): BelongsTo
    {
        return $this->belongsTo(FormaPago::class, 'forma_pago_id');
    }

    public function cuentaBancaria(): BelongsTo
    {
        return $this->belongsTo(CuentaBancaria::class, 'cuentas_bancarias_id');
    }

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(Periodo::class, 'periodo_id');
    }

    public function capturadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'capturado_por_user_id');
    }

    public function anuladoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulado_por_user_id');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class, 'recibo_id');
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function getEvidenciaUrlAttribute(): ?string
    {
        if (! $this->evidencia_path) {
            return null;
        }

        return Storage::disk($this->evidencia_disk ?: 'public')->url($this->evidencia_path);
    }

    public function getEstaFirmadoAttribute(): bool
    {
        return ! empty($this->firma_path);
    }

    public function pagosDetalle()
{
    return $this->hasMany(ReciboPago::class, 'recibo_id')->orderBy('orden');
}

public function propietarioContable()
{
    return $this->belongsTo(\App\Models\Propietario::class, 'propietario_contable_id');
}

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }
}