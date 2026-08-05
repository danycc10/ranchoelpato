<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OpenAiUso extends Model
{
    protected $table = 'openai_usos';

    protected $fillable = [
        'request_id',
        'modelo',
        'operacion',
        'endpoint',
        'tokens_entrada',
        'tokens_entrada_cache',
        'tokens_salida',
        'tokens_razonamiento',
        'tokens_total',
        'precio_entrada_millon',
        'precio_cache_millon',
        'precio_salida_millon',
        'costo_estimado_usd',
        'consumido_en',
    ];

    protected $casts = [
        'tokens_entrada' => 'integer',
        'tokens_entrada_cache' => 'integer',
        'tokens_salida' => 'integer',
        'tokens_razonamiento' => 'integer',
        'tokens_total' => 'integer',
        'precio_entrada_millon' => 'decimal:6',
        'precio_cache_millon' => 'decimal:6',
        'precio_salida_millon' => 'decimal:6',
        'costo_estimado_usd' => 'decimal:10',
        'consumido_en' => 'datetime',
    ];

    public static function etiquetaOperacion(string $operacion): string
    {
        return match ($operacion) {
            'bot_whatsapp' => 'Bot de WhatsApp',
            'comprobante_extraccion' => 'Lectura de comprobante',
            default => str($operacion)->replace('_', ' ')->title()->toString(),
        };
    }
}
