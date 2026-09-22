<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Liga temporal con la que un cliente captura sus propios datos.
 */
class SolicitudDatosCliente extends Model
{
    protected $table = 'solicitudes_datos_cliente';

    /** Se mandó la liga y el cliente todavía no la contesta. */
    public const PENDIENTE = 'pendiente';

    /** Ya la contestó y espera revisión. */
    public const CAPTURADA = 'capturada';

    /** Se revisó y sus datos pasaron al cliente. */
    public const APLICADA = 'aplicada';

    /** Se descartó: la liga deja de servir. */
    public const CANCELADA = 'cancelada';

    protected $fillable = [
        'cliente_id', 'creada_por_user_id', 'token_hash', 'estatus', 'expira_en',
        'datos', 'ine_frente', 'ine_reverso', 'capturada_en',
        'aplicada_en', 'aplicada_por_user_id', 'nota_revision',
    ];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'datos' => 'array',
            'expira_en' => 'datetime',
            'capturada_en' => 'datetime',
            'aplicada_en' => 'datetime',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function creadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creada_por_user_id');
    }

    public function aplicadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aplicada_por_user_id');
    }

    public function vencida(): bool
    {
        return $this->expira_en->isPast();
    }

    /** La liga solo sirve mientras nadie la usó, canceló o dejó vencer. */
    public function abierta(): bool
    {
        return in_array($this->estatus, [self::PENDIENTE, self::CAPTURADA], true)
            && ! $this->vencida();
    }

    /** Ya contestada y esperando que alguien la revise. */
    public function esperaRevision(): bool
    {
        return $this->estatus === self::CAPTURADA;
    }

    public function scopePorRevisar(Builder $query): Builder
    {
        return $query->where('estatus', self::CAPTURADA);
    }
}
