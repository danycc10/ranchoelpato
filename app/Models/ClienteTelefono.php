<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un teléfono adicional de un cliente.
 *
 * El principal vive en `clientes.telefono`; aquí solo están los demás.
 */
class ClienteTelefono extends Model
{
    protected $table = 'cliente_telefonos';

    protected $fillable = ['cliente_id', 'telefono', 'uso', 'etiqueta'];

    protected $attributes = ['uso' => 'ambos'];

    /** Para qué sirve cada número. "Ambos" es el caso de quien solo tiene uno. */
    public const USOS = [
        'ambos' => 'Llamadas y WhatsApp',
        'llamadas' => 'Solo llamadas',
        'whatsapp' => 'Solo WhatsApp',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }
}
