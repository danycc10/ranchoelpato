<?php

namespace App\Services\Contratos;

use App\Models\Contrato;
use Illuminate\Database\Eloquent\Model;

/**
 * Datos que el contrato de cesión de derechos necesita y que todavía no están
 * capturados, para pedirlos al momento de generar el documento en lugar de
 * imprimir una línea en blanco.
 */
class DatosContratoCesionService
{
    /**
     * De dónde derivan los derechos que transmite el cedente (declaración I.b).
     */
    public const ANTECEDENTE_OPCIONES = [
        'Certificado parcelario',
        'Contrato',
        'Cesión',
        'Constancia de posesión',
        'Resolución',
        'Otro',
    ];

    /**
     * Campos por origen, con su etiqueta y cómo se captura.
     *
     * @var array<string, array<string, array{etiqueta: string, tipo: string}>>
     */
    private const CAMPOS = [
        'fraccionamiento' => [
            'ubicacion' => ['etiqueta' => 'Ubicación del proyecto', 'tipo' => 'texto'],
            'ejido' => ['etiqueta' => 'Núcleo agrario / ejido', 'tipo' => 'texto'],
            'municipio' => ['etiqueta' => 'Municipio', 'tipo' => 'texto'],
            'antecedente_tipo' => ['etiqueta' => 'Antecedente de los derechos', 'tipo' => 'antecedente'],
            'antecedente_datos' => ['etiqueta' => 'Datos del documento del antecedente', 'tipo' => 'parrafo'],
        ],
        'propietario' => [
            'persona_tipo' => ['etiqueta' => 'Tipo de persona', 'tipo' => 'persona_tipo'],
            'nacionalidad' => ['etiqueta' => 'Nacionalidad', 'tipo' => 'texto'],
            'estado_civil' => ['etiqueta' => 'Estado civil', 'tipo' => 'estado_civil'],
            'ocupacion' => ['etiqueta' => 'Ocupación', 'tipo' => 'texto'],
            'direccion' => ['etiqueta' => 'Domicilio', 'tipo' => 'texto'],
            'curp' => ['etiqueta' => 'CURP', 'tipo' => 'texto'],
            'rfc' => ['etiqueta' => 'RFC', 'tipo' => 'texto'],
            'telefono' => ['etiqueta' => 'Teléfono', 'tipo' => 'texto'],
            'correo' => ['etiqueta' => 'Correo electrónico', 'tipo' => 'texto'],
            'identificacion_tipo' => ['etiqueta' => 'Identificación oficial', 'tipo' => 'identificacion'],
            'identificacion_numero' => ['etiqueta' => 'Número de identificación', 'tipo' => 'texto'],
        ],
        'cliente' => [
            'persona_tipo' => ['etiqueta' => 'Tipo de persona', 'tipo' => 'persona_tipo'],
            'nacionalidad' => ['etiqueta' => 'Nacionalidad', 'tipo' => 'texto'],
            'estado_civil' => ['etiqueta' => 'Estado civil', 'tipo' => 'estado_civil'],
            'ocupacion' => ['etiqueta' => 'Ocupación', 'tipo' => 'texto'],
            'direccion' => ['etiqueta' => 'Domicilio', 'tipo' => 'texto'],
            'curp' => ['etiqueta' => 'CURP', 'tipo' => 'texto'],
            'rfc' => ['etiqueta' => 'RFC', 'tipo' => 'texto'],
            'telefono' => ['etiqueta' => 'Teléfono', 'tipo' => 'texto'],
            'correo' => ['etiqueta' => 'Correo electrónico', 'tipo' => 'texto'],
            'identificacion_tipo' => ['etiqueta' => 'Identificación oficial', 'tipo' => 'identificacion'],
            'identificacion_numero' => ['etiqueta' => 'Número de identificación', 'tipo' => 'texto'],
        ],
        // Del lote se guarda lo que quedó congelado en el contrato.
        'contrato' => [
            'area_m2_snapshot' => ['etiqueta' => 'Superficie (m²)', 'tipo' => 'numero'],
            'medida_norte_snapshot' => ['etiqueta' => 'Medida norte (m)', 'tipo' => 'numero'],
            'medida_sur_snapshot' => ['etiqueta' => 'Medida sur (m)', 'tipo' => 'numero'],
            'medida_este_snapshot' => ['etiqueta' => 'Medida oriente (m)', 'tipo' => 'numero'],
            'medida_oeste_snapshot' => ['etiqueta' => 'Medida poniente (m)', 'tipo' => 'numero'],
            'colindancia_norte_snapshot' => ['etiqueta' => 'Colindancia norte', 'tipo' => 'texto'],
            'colindancia_sur_snapshot' => ['etiqueta' => 'Colindancia sur', 'tipo' => 'texto'],
            'colindancia_este_snapshot' => ['etiqueta' => 'Colindancia oriente', 'tipo' => 'texto'],
            'colindancia_oeste_snapshot' => ['etiqueta' => 'Colindancia poniente', 'tipo' => 'texto'],
        ],
    ];

    /**
     * Lo que falta por capturar, en el orden en que aparece en el contrato.
     *
     * @return list<array{clave: string, origen: string, campo: string, etiqueta: string, tipo: string, grupo: string}>
     */
    public static function faltantes(Contrato $contrato): array
    {
        $contrato->loadMissing(['cliente', 'lote.fraccionamiento.propietario']);

        $faltantes = [];

        foreach (self::CAMPOS as $origen => $campos) {
            $modelo = self::modelo($contrato, $origen);

            if (! $modelo) {
                continue;
            }

            foreach ($campos as $campo => $config) {
                if (self::tieneValor($contrato, $modelo, $origen, $campo, $config)) {
                    continue;
                }

                $faltantes[] = [
                    'clave' => $origen.'.'.$campo,
                    'origen' => $origen,
                    'campo' => $campo,
                    'etiqueta' => $config['etiqueta'],
                    'tipo' => $config['tipo'],
                    'grupo' => self::grupo($contrato, $origen),
                ];
            }
        }

        return $faltantes;
    }

    /**
     * Guarda los datos capturados en el modal. Devuelve cuántos se guardaron.
     *
     * @param  array<string, array<string, mixed>>  $valores  [origen][campo] => valor
     */
    public static function guardar(Contrato $contrato, array $valores): int
    {
        $contrato->loadMissing(['cliente', 'lote.fraccionamiento.propietario']);

        $porOrigen = [];

        foreach ($valores as $origen => $campos) {
            foreach ((array) $campos as $campo => $valor) {
                if (! isset(self::CAMPOS[$origen][$campo])) {
                    continue;
                }

                $valor = is_string($valor) ? trim($valor) : $valor;

                if ($valor === null || $valor === '') {
                    continue;
                }

                if (self::CAMPOS[$origen][$campo]['tipo'] === 'numero') {
                    $valor = (float) $valor;

                    if ($valor <= 0) {
                        continue;
                    }
                }

                $porOrigen[$origen][$campo] = $valor;
            }
        }

        $guardados = 0;

        foreach ($porOrigen as $origen => $datos) {
            $modelo = self::modelo($contrato, $origen);

            if (! $modelo) {
                continue;
            }

            $modelo->update($datos);
            $guardados += count($datos);

            if ($origen === 'contrato') {
                self::completarCatalogoDelLote($contrato, $datos);
            }
        }

        return $guardados;
    }

    /**
     * Lo que se captura del lote queda en el contrato, pero también llena el
     * catálogo cuando estaba vacío, para que sirva en los demás documentos.
     *
     * Nunca pisa un dato que el catálogo ya tenga.
     *
     * @param  array<string, mixed>  $datos
     */
    private static function completarCatalogoDelLote(Contrato $contrato, array $datos): void
    {
        $lote = $contrato->lote;

        if (! $lote) {
            return;
        }

        $catalogo = [];

        foreach ($datos as $campo => $valor) {
            if (! str_ends_with($campo, '_snapshot')) {
                continue;
            }

            $campoLote = str_replace('_snapshot', '', $campo);
            $actual = $lote->{$campoLote};

            $vacio = is_numeric($valor)
                ? ! ((float) $actual > 0)
                : trim((string) $actual) === '';

            if ($vacio) {
                $catalogo[$campoLote] = $valor;
            }
        }

        if ($catalogo !== []) {
            $lote->update($catalogo);
        }
    }

    /**
     * Opciones de los campos que se capturan con lista.
     *
     * @return array<int|string, string>
     */
    public static function opciones(string $tipo): array
    {
        return match ($tipo) {
            'antecedente' => array_combine(self::ANTECEDENTE_OPCIONES, self::ANTECEDENTE_OPCIONES),
            'persona_tipo' => DatosIdentidadService::PERSONA_TIPOS,
            'estado_civil' => array_combine(
                DatosIdentidadService::ESTADOS_CIVILES,
                DatosIdentidadService::ESTADOS_CIVILES
            ),
            'identificacion' => array_combine(
                DatosIdentidadService::IDENTIFICACIONES,
                DatosIdentidadService::IDENTIFICACIONES
            ),
            default => [],
        };
    }

    private static function modelo(Contrato $contrato, string $origen): ?Model
    {
        return match ($origen) {
            'fraccionamiento' => $contrato->lote?->fraccionamiento,
            'propietario' => $contrato->lote?->fraccionamiento?->propietario,
            'cliente' => $contrato->cliente,
            'contrato' => $contrato,
            default => null,
        };
    }

    /**
     * Un dato del lote también se da por capturado si está en el catálogo.
     *
     * @param  array{etiqueta: string, tipo: string}  $config
     */
    private static function tieneValor(
        Contrato $contrato,
        Model $modelo,
        string $origen,
        string $campo,
        array $config
    ): bool {
        $valor = $modelo->{$campo};

        if ($origen === 'contrato' && str_ends_with($campo, '_snapshot')) {
            $valor = $valor ?: $contrato->lote?->{str_replace('_snapshot', '', $campo)};
        }

        return $config['tipo'] === 'numero'
            ? (float) $valor > 0
            : trim((string) $valor) !== '';
    }

    private static function grupo(Contrato $contrato, string $origen): string
    {
        return match ($origen) {
            'fraccionamiento' => 'Fraccionamiento: '.($contrato->lote?->fraccionamiento?->nombre ?: '—'),
            'propietario' => 'Parte cedente: '.(
                $contrato->lote?->fraccionamiento?->propietario?->nombre_legal
                ?: ($contrato->lote?->fraccionamiento?->propietario?->nombre ?: '—')
            ),
            'cliente' => 'Parte adquirente: '.(
                $contrato->cliente?->nombre_legal ?: ($contrato->cliente?->nombre_completo ?: '—')
            ),
            default => 'Lote de este contrato',
        };
    }
}
