<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notificacion extends Model
{
    protected $table = 'notificaciones_salida';

    protected $fillable = [
        'canal',          // whatsapp | correo
        'tipo',           // cuota_atrasada, etc
        'cliente_id',
        'contrato_id',
        'cuota_id',
        'destino',        // telefono o correo
        'payload',        // JSON
        'estatus',        // en_cola | enviado | fallido
        'enviado_en',
        'error',
    ];

    protected $casts = [
        'payload' => 'array',
        'enviado_en' => 'datetime',
    ];

    /* ========================
       Relaciones (opcional)
    ======================== */

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function contrato()
    {
        return $this->belongsTo(Contrato::class);
    }

    public function cuota()
    {
        return $this->belongsTo(Cuota::class);
    }
}
