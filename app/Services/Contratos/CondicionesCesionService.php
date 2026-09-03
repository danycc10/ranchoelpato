<?php

namespace App\Services\Contratos;

use App\Models\AjusteSistema;
use App\Models\Contrato;
use Illuminate\Support\Facades\Schema;

/**
 * Condiciones que el contrato de cesión de derechos deja a elección de las
 * partes: entrega de la posesión, estado de los servicios, parcialidades para
 * rescindir y tolerancia en las medidas.
 *
 * Cada contrato puede fijar las suyas; si no lo hace, se usan las de ajustes
 * del sistema, para que los contratos anteriores tampoco salgan en blanco.
 */
class CondicionesCesionService
{
    /** Valor por defecto de fábrica de cada condición. */
    public const DEFAULTS = [
        'posesion_entrega' => 'A la firma',
        'servicios_estado' => 'En gestión',
        'servicios_a_cargo' => 'LA PARTE ADQUIRENTE',
        'parcialidades_rescision' => '2',
        'tolerancia_medidas' => '5',
    ];

    public const POSESION_OPCIONES = [
        'A la firma',
        'Al cubrir el enganche',
        'A la liquidación',
    ];

    public const SERVICIOS_OPCIONES = [
        'Existente',
        'En gestión',
        'Proyectada',
    ];

    public const A_CARGO_OPCIONES = [
        'LA PARTE ADQUIRENTE',
        'LA PARTE CEDENTE',
    ];

    private const PREFIJO = 'cesion_';

    /**
     * Valores por defecto vigentes, con los ajustes del sistema aplicados.
     *
     * @return array<string, string>
     */
    public static function porDefecto(): array
    {
        $valores = self::DEFAULTS;

        if (! Schema::hasTable('ajustes_sistema')) {
            return $valores;
        }

        $guardados = AjusteSistema::query()
            ->whereIn('clave', array_map(fn (string $campo) => self::PREFIJO.$campo, array_keys($valores)))
            ->pluck('valor', 'clave');

        foreach ($valores as $campo => $valor) {
            $guardado = trim((string) $guardados->get(self::PREFIJO.$campo));

            if ($guardado !== '') {
                $valores[$campo] = $guardado;
            }
        }

        return $valores;
    }

    /**
     * Guarda los valores por defecto del sistema.
     *
     * @param  array<string, mixed>  $valores
     */
    public static function guardarPorDefecto(array $valores): void
    {
        foreach (self::DEFAULTS as $campo => $default) {
            if (! array_key_exists($campo, $valores)) {
                continue;
            }

            $valor = trim((string) $valores[$campo]);

            AjusteSistema::query()->updateOrCreate(
                ['clave' => self::PREFIJO.$campo],
                ['valor' => $valor !== '' ? $valor : $default],
            );
        }
    }

    /**
     * Condiciones de un contrato: las suyas, o las de ajustes del sistema.
     *
     * @return array<string, string>
     */
    public static function paraContrato(Contrato $contrato): array
    {
        $valores = self::porDefecto();

        foreach (array_keys(self::DEFAULTS) as $campo) {
            $propio = trim((string) $contrato->{$campo});

            if ($propio !== '') {
                $valores[$campo] = $propio;
            }
        }

        return $valores;
    }
}
