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

        // Datos que declaran las partes en el contrato de cesión.
        'persona_tipo',
        'nacionalidad',
        'estado_civil',
        'ocupacion',
        'identificacion_tipo',
        'identificacion_numero',
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

    /** Las ligas que se le han mandado para que capture sus datos. */
    public function solicitudesDatos(): HasMany
    {
        return $this->hasMany(SolicitudDatosCliente::class, 'cliente_id');
    }

    public function recibos(): HasMany
    {
        return $this->hasMany(Recibo::class, 'cliente_id');
    }

    public function getNombreCompletoAttribute(): string
    {
        return trim(($this->nombres ?? '').' '.($this->apellidos ?? ''));
    }

    /**
     * Cómo queda este cliente congelado como comprador en un contrato.
     *
     * Los contratos guardan estos datos aparte para que un documento ya
     * firmado no cambie si después se corrige el catálogo. Por eso hay que
     * volver a tomarlos cuando el contrato cambia de cliente: si no, el
     * contrato seguiría nombrando al comprador anterior.
     *
     * @return array<string, string|null>
     */
    public function datosComoComprador(): array
    {
        return [
            'comprador_nombre_legal' => $this->nombre_legal ?: $this->nombre_completo,
            'comprador_curp' => $this->curp,
            // La credencial del comprador anterior no puede quedarse pegada.
            'comprador_ine_frente' => $this->ine_frente,
            'comprador_ine_reverso' => $this->ine_reverso,
        ];
    }
}
