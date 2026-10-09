<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Cliente extends Model
{
    protected $table = 'clientes';

    protected $fillable = [
        'nombres',
        'apellidos',
        'nombre_legal',
        'telefono',
        'telefono_uso',
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
        // Quien solo tiene un número lo usa para las dos cosas.
        'telefono_uso' => 'ambos',
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

    /**
     * Los teléfonos de más, aparte del principal.
     *
     * El principal sigue en `clientes.telefono`, que es el que leen contratos,
     * plantillas y reportes: tenerlo también aquí abriría la puerta a que los
     * dos lugares dijeran cosas distintas.
     */
    public function telefonosAdicionales(): HasMany
    {
        return $this->hasMany(ClienteTelefono::class, 'cliente_id');
    }

    /**
     * Todos sus números, con el principal al frente.
     *
     * @return Collection<int, string>
     */
    public function todosLosTelefonos(): Collection
    {
        return $this->telefonosConUso()->pluck('telefono')->values();
    }

    /**
     * Sus números con el uso de cada uno, el principal primero.
     *
     * @return Collection<int, array{telefono: string, uso: string, etiqueta: ?string, principal: bool}>
     */
    public function telefonosConUso(): Collection
    {
        $principal = collect();

        if (filled($this->telefono)) {
            $principal->push([
                'telefono' => (string) $this->telefono,
                'uso' => (string) ($this->telefono_uso ?: 'ambos'),
                'etiqueta' => null,
                'principal' => true,
            ]);
        }

        return $principal
            ->merge($this->telefonosAdicionales->map(fn (ClienteTelefono $t) => [
                'telefono' => (string) $t->telefono,
                'uso' => (string) ($t->uso ?: 'ambos'),
                'etiqueta' => $t->etiqueta,
                'principal' => false,
            ]))
            ->unique('telefono')
            ->values();
    }

    /**
     * Los números a los que sí se le puede mandar WhatsApp.
     *
     * Quien solo tiene un número lo tiene como "ambos", así que sale aquí y
     * también en los de llamar: la división es para quien de verdad separa.
     *
     * @return Collection<int, string>
     */
    public function telefonosParaWhatsapp(): Collection
    {
        return $this->telefonosParaUso('whatsapp');
    }

    /**
     * @return Collection<int, string>
     */
    public function telefonosParaLlamar(): Collection
    {
        return $this->telefonosParaUso('llamadas');
    }

    /** El número al que se le manda WhatsApp, si tiene alguno. */
    public function telefonoParaWhatsapp(): ?string
    {
        return $this->telefonosParaWhatsapp()->first();
    }

    /** El número al que se le marca, si tiene alguno. */
    public function telefonoParaLlamar(): ?string
    {
        return $this->telefonosParaLlamar()->first();
    }

    /**
     * @return Collection<int, string>
     */
    protected function telefonosParaUso(string $uso): Collection
    {
        return $this->telefonosConUso()
            ->filter(fn (array $t) => $t['uso'] === $uso || $t['uso'] === 'ambos')
            ->pluck('telefono')
            ->values();
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
