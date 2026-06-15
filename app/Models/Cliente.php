<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cliente extends Model
{
    protected $table = 'clientes';

    protected $fillable = [
        'nombres',
        'apellidos',
        'nombre_legal',
        'telefono',
        'correo',
        'direccion',
        'rfc',
        'curp',
        'notas',
        'estatus',
        'ine_frente',
        'ine_reverso',
        'documentos_disk',
    ];

    protected $attributes = [
        'estatus' => 'activo',
    ];

    protected static function booted(): void
    {
        static::saving(function (Cliente $c) {
            // Teléfono: deja solo dígitos
            if (! is_null($c->telefono)) {
                $c->telefono = preg_replace('/\D+/', '', (string) $c->telefono);
                $c->telefono = $c->telefono !== '' ? $c->telefono : null;
            }

            // Correo: lowercase + trim
            if (! is_null($c->correo)) {
                $c->correo = strtolower(trim((string) $c->correo));
                $c->correo = $c->correo !== '' ? $c->correo : null;
            }

            // Default estatus
            if (! $c->estatus) {
                $c->estatus = 'activo';
            }
        });
    }

    public function contratos(): HasMany
    {
        return $this->hasMany(Contrato::class, 'cliente_id');
    }

    public function recibos(): HasMany
    {
        return $this->hasMany(Recibo::class, 'cliente_id');
    }

    public function getNombreCompletoAttribute(): string
    {
        return trim(($this->nombres ?? '') . ' ' . ($this->apellidos ?? ''));
    }
}
