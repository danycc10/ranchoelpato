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

        DB::connection()->getPdo()->sqliteCreateFunction(
            'CONCAT_WS',
            function ($separador, ...$partes) {
                return implode(
                    (string) $separador,
                    array_filter($partes, fn ($parte) => $parte !== null)
                );
            }
        );
    }
}
