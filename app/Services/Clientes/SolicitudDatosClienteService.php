<?php

namespace App\Services\Clientes;

use App\Models\Cliente;
use App\Models\SolicitudDatosCliente;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Liga temporal para que el cliente capture sus propios datos.
 *
 * Lo que llena queda guardado aparte hasta que alguien lo revisa: así una liga
 * reenviada o un dedazo no pisan el catálogo de clientes.
 */
class SolicitudDatosClienteService
{
    /** Días que dura la liga si no se indica otra cosa. */
    public const DIAS_VIGENCIA = 7;

    /**
     * Los datos que el cliente puede capturar de sí mismo.
     *
     * Deliberadamente no incluye nombres ni apellidos: con eso se le dio de
     * alta y es como lo identifica el resto del sistema.
     *
     * @var array<string, string>
     */
    public const CAMPOS = [
        'nombre_legal' => 'Nombre completo como aparece en tu identificación',
        'curp' => 'CURP',
        'rfc' => 'RFC',
        'telefono' => 'Teléfono',
        'correo' => 'Correo electrónico',
        'direccion' => 'Domicilio',
        'nacionalidad' => 'Nacionalidad',
        'estado_civil' => 'Estado civil',
        'ocupacion' => 'Ocupación',
        'identificacion_tipo' => 'Tipo de identificación',
        'identificacion_numero' => 'Número de identificación',
    ];

    /**
     * Crea la liga. El token se devuelve una sola vez: en la base queda su hash.
     *
     * @return array{solicitud: SolicitudDatosCliente, token: string}
     */
    public function crear(Cliente $cliente, ?User $creadaPor = null, ?int $dias = null): array
    {
        $token = bin2hex(random_bytes(24));

        $solicitud = DB::transaction(function () use ($cliente, $creadaPor, $dias, $token) {
            // Una liga nueva deja sin efecto las anteriores del mismo cliente.
            SolicitudDatosCliente::query()
                ->where('cliente_id', $cliente->id)
                ->whereIn('estatus', [SolicitudDatosCliente::PENDIENTE, SolicitudDatosCliente::CAPTURADA])
                ->update(['estatus' => SolicitudDatosCliente::CANCELADA]);

            return SolicitudDatosCliente::create([
                'cliente_id' => $cliente->id,
                'creada_por_user_id' => $creadaPor?->id,
                'token_hash' => hash('sha256', $token),
                'estatus' => SolicitudDatosCliente::PENDIENTE,
                'expira_en' => now()->addDays($dias ?: self::DIAS_VIGENCIA),
            ]);
        });

        return ['solicitud' => $solicitud, 'token' => $token];
    }

    /** La solicitud de una liga, solo si sigue sirviendo. */
    public function abiertaPorToken(string $token): ?SolicitudDatosCliente
    {
        $solicitud = SolicitudDatosCliente::query()
            ->with('cliente')
            ->where('token_hash', hash('sha256', $token))
            ->first();

        return $solicitud && $solicitud->abierta() ? $solicitud : null;
    }

    /**
     * Guarda lo que capturó el cliente. No toca su registro todavía.
     *
     * @param  array<string, string|null>  $datos
     */
    public function guardarCaptura(
        SolicitudDatosCliente $solicitud,
        array $datos,
        ?string $ineFrente = null,
        ?string $ineReverso = null,
    ): SolicitudDatosCliente {
        if (! $solicitud->abierta()) {
            throw new RuntimeException('Esta liga ya no está disponible.');
        }

        $limpios = [];

        foreach (self::CAMPOS as $campo => $etiqueta) {
            $valor = trim((string) ($datos[$campo] ?? ''));

            if ($valor !== '') {
                $limpios[$campo] = $valor;
            }
        }

        $solicitud->forceFill([
            'datos' => $limpios,
            'ine_frente' => $ineFrente ?: $solicitud->ine_frente,
            'ine_reverso' => $ineReverso ?: $solicitud->ine_reverso,
            'estatus' => SolicitudDatosCliente::CAPTURADA,
            'capturada_en' => now(),
        ])->save();

        return $solicitud;
    }

    /**
     * Pasa al cliente lo que se revisó y aprobó.
     *
     * Solo se escriben los campos que vienen con valor: lo que el cliente dejó
     * en blanco no borra lo que ya se tenía.
     *
     * @param  array<int, string>|null  $soloCampos  para aplicar una parte
     */
    public function aplicar(
        SolicitudDatosCliente $solicitud,
        ?User $revisadaPor = null,
        ?array $soloCampos = null,
    ): Cliente {
        if (! $solicitud->esperaRevision()) {
            throw new RuntimeException('Esta solicitud no tiene datos por revisar.');
        }

        return DB::transaction(function () use ($solicitud, $revisadaPor, $soloCampos) {
            $cliente = $solicitud->cliente;

            $cambios = [];

            foreach ((array) $solicitud->datos as $campo => $valor) {
                if (! array_key_exists($campo, self::CAMPOS)) {
                    continue;
                }

                if ($soloCampos !== null && ! in_array($campo, $soloCampos, true)) {
                    continue;
                }

                $cambios[$campo] = $valor;
            }

            if ($solicitud->ine_frente && ($soloCampos === null || in_array('ine_frente', $soloCampos, true))) {
                $cambios['ine_frente'] = $solicitud->ine_frente;
                $cambios['documentos_disk'] = 'private';
            }

            if ($solicitud->ine_reverso && ($soloCampos === null || in_array('ine_reverso', $soloCampos, true))) {
                $cambios['ine_reverso'] = $solicitud->ine_reverso;
                $cambios['documentos_disk'] = 'private';
            }

            if ($cambios) {
                $cliente->forceFill($cambios)->save();
            }

            $solicitud->forceFill([
                'estatus' => SolicitudDatosCliente::APLICADA,
                'aplicada_en' => now(),
                'aplicada_por_user_id' => $revisadaPor?->id,
            ])->save();

            return $cliente->fresh();
        });
    }

    public function cancelar(SolicitudDatosCliente $solicitud, ?string $nota = null): void
    {
        $solicitud->forceFill([
            'estatus' => SolicitudDatosCliente::CANCELADA,
            'nota_revision' => $nota,
        ])->save();
    }

    /** Lo que al cliente le falta capturar, para pedirle solo eso. */
    public function faltantes(Cliente $cliente): array
    {
        $faltan = [];

        foreach (self::CAMPOS as $campo => $etiqueta) {
            if (blank($cliente->{$campo} ?? null)) {
                $faltan[$campo] = $etiqueta;
            }
        }

        // La identificación también hace falta para armar el contrato.
        if (blank($cliente->ine_frente ?? null)) {
            $faltan['ine_frente'] = 'Identificación (frente)';
        }

        if (blank($cliente->ine_reverso ?? null)) {
            $faltan['ine_reverso'] = 'Identificación (reverso)';
        }

        return $faltan;
    }

    /**
     * Qué tan completo está el expediente del cliente.
     *
     * @return array{llenos: int, total: int, porcentaje: int, faltan: array<string, string>}
     */
    public function progreso(Cliente $cliente): array
    {
        $total = count(self::CAMPOS) + 2; // los dos lados de la identificación
        $faltan = $this->faltantes($cliente);
        $llenos = $total - count($faltan);

        return [
            'llenos' => $llenos,
            'total' => $total,
            'porcentaje' => $total > 0 ? (int) round($llenos / $total * 100) : 0,
            'faltan' => $faltan,
        ];
    }
}
