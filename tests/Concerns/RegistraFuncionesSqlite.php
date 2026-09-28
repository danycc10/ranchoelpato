<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Algunas consultas de reportes usan funciones de MySQL que SQLite no trae.
 * Se registran como funciones de usuario para poder probar esas consultas.
 */
trait RegistraFuncionesSqlite
{
    protected function registrarFuncionesMysqlEnSqlite(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            return;
        }

        $pdo = DB::connection()->getPdo();

        $pdo->sqliteCreateFunction(
            'CONCAT_WS',
            function ($separador, ...$partes) {
                return implode(
                    (string) $separador,
                    array_filter($partes, fn ($parte) => $parte !== null)
                );
            }
        );

        $pdo->sqliteCreateFunction('YEAR', fn ($fecha) => $this->parteDeFecha($fecha, 'Y'));
        $pdo->sqliteCreateFunction('MONTH', fn ($fecha) => $this->parteDeFecha($fecha, 'n'));
    }

    private function parteDeFecha(?string $fecha, string $formato): ?int
    {
        if (blank($fecha)) {
            return null;
        }

        return (int) date($formato, strtotime($fecha));
    }
}
