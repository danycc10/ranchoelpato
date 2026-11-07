<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un abono extraordinario que bajó el saldo del contrato quitando cuotas del
 * final del calendario. Guarda lo que quitó para poder regresarlo.
 */
class AbonoCapital extends Model
{
    protected $table = 'abonos_capital';

    protected $fillable = [
        'contrato_id',
        'recibo_id',
        'promocion_id',
        'monto_abono',
        'monto_bonificacion',
        'saldo_anterior',
        'saldo_nuevo',
        'cuotas_recortadas',
        'created_by_user_id',
        'anulado_at',
        'anulado_por_user_id',
    ];

    protected $casts = [
        'monto_abono' => 'decimal:2',
        'monto_bonificacion' => 'decimal:2',
        'saldo_anterior' => 'decimal:2',
        'saldo_nuevo' => 'decimal:2',
        'cuotas_recortadas' => 'array',
        'anulado_at' => 'datetime',
    ];

    /** Lo que bajó el saldo: el efectivo más la bonificación. */
    public function totalAplicado(): float
    {
        return round((float) $this->monto_abono + (float) $this->monto_bonificacion, 2);
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    public function recibo(): BelongsTo
    {
        return $this->belongsTo(Recibo::class);
    }

    public function promocion(): BelongsTo
    {
        return $this->belongsTo(Promocion::class);
    }

    public function capturadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
