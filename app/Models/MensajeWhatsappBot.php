<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MensajeWhatsappBot extends Model
{
    protected $table = 'mensajes_whatsapp_bot';

    public const ESTATUS_PENDIENTE = 'pendiente';

    public const ESTATUS_PROCESANDO = 'procesando';

    public const ESTATUS_RESPONDIDO = 'respondido';

    public const ESTATUS_ERROR = 'error';

    protected $fillable = [
        'wa_message_id',
        'wa_chat_id',
        'telefono',
        'cliente_id',
        'comprobante_id',
        'tipo',
        'contenido',
        'respuesta',
        'modelo',
        'estatus',
        'error',
        'recibido_en',
        'respondido_en',
    ];

    protected $casts = [
        'recibido_en' => 'datetime',
        'respondido_en' => 'datetime',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function comprobante(): BelongsTo
    {
        return $this->belongsTo(ComprobantePago::class);
    }
}
