<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Promocion extends Model
{
    protected $table = 'promociones';

    protected $fillable = [
        'nombre', 'codigo', 'tipo',
        'dias_diferidos', 'numero_cuotas', 'porcentaje', 'monto_fijo',
        'monto_pago_inicial', 'meses_pago_inicial', 'monto_pago_posterior',
        'abono_por_bloque', 'bonificacion_por_bloque',
        'activa', 'fecha_inicio', 'fecha_fin',
    ];

    protected $casts = [
        'activa' => 'boolean',
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'porcentaje' => 'decimal:2',
        'monto_fijo' => 'decimal:2',
        'monto_pago_inicial' => 'decimal:2',
        'meses_pago_inicial' => 'integer',
        'monto_pago_posterior' => 'decimal:2',
        'abono_por_bloque' => 'decimal:2',
        'bonificacion_por_bloque' => 'decimal:2',
    ];

    /** Un monto los primeros meses y otro lo que resta. */
    public const PAGO_ESCALONADO = 'pago_escalonado';

    /**
     * Se aplica al cobrar, no al crear el contrato: por cada bloque completo
     * de abono extraordinario en efectivo se bonifica un monto al saldo.
     */
    public const ADELANTA_Y_GANA = 'adelanta_y_gana';

    /** Las que se eligen al crear un contrato; las de cobranza no. */
    public function scopeDeContrato($query)
    {
        return $query->where('tipo', '!=', self::ADELANTA_Y_GANA);
    }

    /** Dónde se ofrece; sin fraccionamientos marcados no aplica en ninguno. */
    public function fraccionamientos(): BelongsToMany
    {
        return $this->belongsToMany(Fraccionamiento::class, 'fraccionamiento_promocion')->withTimestamps();
    }

    /** Los abonos que se cobraron con esta promoción, anulados incluidos. */
    public function abonosCapital(): HasMany
    {
        return $this->hasMany(AbonoCapital::class);
    }

    public function esPagoEscalonado(): bool
    {
        return $this->tipo === self::PAGO_ESCALONADO
            && (float) $this->monto_pago_inicial > 0
            && (int) $this->meses_pago_inicial > 0;
    }

    /**
     * Cuántos pagos van con el monto de la promoción.
     *
     * Se cuentan pagos y no fechas: el primer año son 12 mensuales o 52
     * semanales. Por fecha saldrían 53 semanales, porque 52 semanas no
     * alcanzan a llenar el año.
     */
    public function pagosConMontoInicial(string $frecuencia): int
    {
        if (! $this->esPagoEscalonado()) {
            return 0;
        }

        $meses = (int) $this->meses_pago_inicial;

        return $frecuencia === 'semanal'
            ? (int) round($meses * 52 / 12)
            : $meses;
    }

    /** Lo que la pantalla muestra de la promoción, en palabras. */
    public function descripcionPagoEscalonado(): string
    {
        return '$'.number_format((float) $this->monto_pago_inicial, 2)
            .' los primeros '.(int) $this->meses_pago_inicial.' meses'
            .((float) $this->monto_pago_posterior > 0
                ? ', después $'.number_format((float) $this->monto_pago_posterior, 2)
                : '');
    }
}
