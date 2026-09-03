<?php

namespace App\Services\Contratos;

/**
 * Opciones de los datos con los que las partes se identifican en el contrato
 * de cesión de derechos. Las comparten clientes y propietarios.
 */
class DatosIdentidadService
{
    public const PERSONA_TIPOS = [
        'fisica' => 'Persona física',
        'moral' => 'Persona moral',
    ];

    public const ESTADOS_CIVILES = [
        'Soltero(a)',
        'Casado(a)',
        'Divorciado(a)',
        'Viudo(a)',
        'Unión libre',
    ];

    public const IDENTIFICACIONES = [
        'INE',
        'Pasaporte',
        'Cédula profesional',
        'Licencia de conducir',
        'Otro',
    ];

    public const NACIONALIDAD_DEFAULT = 'Mexicana';

    /** Campos que se capturan igual en clientes y propietarios. */
    public const CAMPOS = [
        'persona_tipo',
        'nacionalidad',
        'estado_civil',
        'ocupacion',
        'identificacion_tipo',
        'identificacion_numero',
    ];

    /**
     * Reglas de validación de los campos, con el prefijo del formulario.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function reglas(string $prefijo = ''): array
    {
        return [
            $prefijo.'persona_tipo' => ['nullable', 'string', 'in:'.implode(',', array_keys(self::PERSONA_TIPOS))],
            $prefijo.'nacionalidad' => ['nullable', 'string', 'max:60'],
            $prefijo.'estado_civil' => ['nullable', 'string', 'max:40'],
            $prefijo.'ocupacion' => ['nullable', 'string', 'max:80'],
            $prefijo.'identificacion_tipo' => ['nullable', 'string', 'max:40'],
            $prefijo.'identificacion_numero' => ['nullable', 'string', 'max:60'],
        ];
    }
}
