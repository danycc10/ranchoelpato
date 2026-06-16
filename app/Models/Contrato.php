<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
// ✅ Spatie Activity Log
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;

class Contrato extends Model
{
    use LogsActivity,SoftDeletes;

    protected $table = 'contratos';

    protected $fillable = [
        'cliente_id',
        'lote_id',
        'folio_contrato',
        'fecha_inicio',
        'frecuencia',     // semanal|mensual
        'dia_semana',     // 1-7
        'dia_mes',        // 1-28
        'promocion_id',
        'precio_total',
        'enganche',
        'saldo_inicial',
        'saldo_actual',
        'monto_pago',
        'tipo_recargo',   // fijo|porcentaje
        'valor_recargo',
        'dias_gracia',
        'frecuencia_recargo_dias',
        'estatus',        // activo|moroso|liquidado|cancelado
        'archivo_contrato',
        'tipo',
        'servicio_tipo',
        'contrato_base_id',
        'es_financiamiento_instalacion',
        'archivo_contrato_disk',

        'archivo_contrato_docx',
        'archivo_constancia_terminacion_pago',
        'archivo_constancia_terminacion_pago_docx',
        'archivo_convenio_pago',
        'archivo_convenio_pago_docx',
        'archivo_convenio_pago_reconocimiento_adeudo',
        'archivo_convenio_pago_reconocimiento_adeudo_docx',

        'comprador_ine_frente',
        'comprador_ine_reverso',
        'vendedor_ine_frente',
        'vendedor_ine_reverso',
        'credenciales_disk',

        'vendedor_nombre_legal',
        'vendedor_curp',
        'comprador_nombre_legal',
        'comprador_curp',

        'area_m2_snapshot',
        'medida_norte_snapshot',
        'medida_sur_snapshot',
        'medida_este_snapshot',
        'medida_oeste_snapshot',
        'colindancia_norte_snapshot',
        'colindancia_sur_snapshot',
        'colindancia_este_snapshot',
        'colindancia_oeste_snapshot',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'precio_total' => 'decimal:2',
        'enganche' => 'decimal:2',
        'saldo_inicial' => 'decimal:2',
        'saldo_actual' => 'decimal:2',
        'monto_pago' => 'decimal:2',
        'valor_recargo' => 'decimal:2',
        'dias_gracia' => 'integer',
        'frecuencia_recargo_dias' => 'integer',
        'dia_semana' => 'integer',
        'dia_mes' => 'integer',
        'es_financiamiento_instalacion' => 'boolean',

        'area_m2_snapshot' => 'decimal:2',
        'medida_norte_snapshot' => 'decimal:2',
        'medida_sur_snapshot' => 'decimal:2',
        'medida_este_snapshot' => 'decimal:2',
        'medida_oeste_snapshot' => 'decimal:2',
    ];

    /**
     * ✅ Spatie Activity Log options
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('contratos')
            ->logFillable()            // audita lo que está en $fillable
            ->logOnlyDirty()           // solo cambios reales
            ->dontSubmitEmptyLogs()    // evita logs vacíos
            ->logExcept([              // evita ruido
                'updated_at',
                'created_at',
                'deleted_at',
            ])
            ->setDescriptionForEvent(fn (string $eventName) => match ($eventName) {
                'created' => 'Contrato creado',
                'updated' => 'Contrato actualizado',
                'deleted' => 'Contrato eliminado',
                'restored' => 'Contrato restaurado',
                default => "Contrato {$eventName}",
            });
    }

    /**
     * ✅ Agrega propiedades extra útiles para auditoría
     */
    public function tapActivity(Activity $activity, string $eventName): void
    {
        $activity->properties = $activity->properties->merge([
            'uuid' => $this->uuid ?? null,
            'folio_contrato' => $this->folio_contrato ?? null,
            'estatus' => $this->estatus ?? null,
            'tipo' => $this->tipo ?? null,
            'servicio_tipo' => $this->servicio_tipo ?? null,
            'cliente_id' => $this->cliente_id ?? null,
            'lote_id' => $this->lote_id ?? null,
            // Si usas multiempresa en contratos, descomenta:
            // 'empresa_id' => $this->empresa_id ?? session('empresa_id'),
        ]);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function lote(): BelongsTo
    {
        return $this->belongsTo(Lote::class, 'lote_id');
    }

    public function cuotas(): HasMany
    {
        return $this->hasMany(Cuota::class, 'contrato_id');
    }

    public function recibos(): HasMany
    {
        return $this->hasMany(Recibo::class, 'contrato_id');
    }

    public function promocion(): BelongsTo
    {
        return $this->belongsTo(Promocion::class, 'promocion_id');
    }

    public function contratoBase()
    {
        return $this->belongsTo(self::class, 'contrato_base_id');
    }

    public function contratosServicios()
    {
        return $this->hasMany(self::class, 'contrato_base_id')
            ->where('tipo', 'servicio');
    }

    public function getContratoReferenciaAttribute(): self
    {
        return $this->contratoBase ?: $this;
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function historial()
    {
        return $this->hasMany(ContratoHistorial::class);
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
