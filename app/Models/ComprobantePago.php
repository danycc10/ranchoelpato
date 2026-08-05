<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

/**
 * Comprobante de pago que un cliente mandó por WhatsApp.
 *
 * Los campos *_detectado los llena una IA y son SIEMPRE una propuesta: nada se
 * aplica a la contabilidad hasta que un cobrador valida desde la bandeja. Por eso
 * viven aquí y no en pagos/recibos.
 */
class ComprobantePago extends Model
{
    protected $table = 'comprobantes_pago';

    public const ESTATUS_RECIBIDO = 'recibido';

    public const ESTATUS_POR_VALIDAR = 'por_validar';

    public const ESTATUS_VALIDADO = 'validado';

    public const ESTATUS_RECHAZADO = 'rechazado';

    public const ESTATUS_SIN_ARCHIVO = 'sin_archivo';

    public const ESTATUS_ERROR = 'error';

    protected $fillable = [
        'wa_message_id',
        'wa_idempotency_key',
        'wa_chat_id',
        'telefono',
        'nombre_whatsapp',
        'texto_mensaje',
        'recibido_en',
        'archivo_path',
        'archivo_disk',
        'archivo_mime',
        'archivo_size',
        'estatus',
        'monto_detectado',
        'fecha_detectada',
        'banco_detectado',
        'referencia_detectada',
        'ultimos_digitos',
        'confianza',
        'extraccion',
        'extraccion_modelo',
        'extraido_en',
        'error',
        'cliente_id',
        'contrato_id',
        'cuota_id',
        'recibo_id',
        'validado_por_user_id',
        'validado_en',
        'nota_validacion',
    ];

    protected $casts = [
        'recibido_en' => 'datetime',
        'extraido_en' => 'datetime',
        'validado_en' => 'datetime',
        'fecha_detectada' => 'date',
        'monto_detectado' => 'decimal:2',
        'confianza' => 'decimal:2',
        'archivo_size' => 'integer',
        'extraccion' => 'array',
    ];

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

    public function recibosGenerados(): BelongsToMany
    {
        return $this->belongsToMany(Recibo::class, 'comprobante_pago_recibos')
            ->withPivot(['cuota_id', 'concepto'])
            ->withTimestamps();
    }

    public function validadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validado_por_user_id');
    }

    public function tieneArchivo(): bool
    {
        return ! empty($this->archivo_path);
    }

    /** Bytes crudos de la imagen, o null si no hay archivo o se perdió del disco. */
    public function contenidoArchivo(): ?string
    {
        if (! $this->tieneArchivo()) {
            return null;
        }

        $disk = Storage::disk($this->archivo_disk ?: 'private');

        if (! $disk->exists($this->archivo_path)) {
            return null;
        }

        return $disk->get($this->archivo_path);
    }

    public function scopePendientesDeExtraer($query)
    {
        return $query->where('estatus', self::ESTATUS_RECIBIDO)
            ->whereNotNull('archivo_path');
    }

    public function scopePorValidar($query)
    {
        return $query->where('estatus', self::ESTATUS_POR_VALIDAR);
    }
}
