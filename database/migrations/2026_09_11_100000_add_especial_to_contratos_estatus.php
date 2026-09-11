<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "especial" marca contratos en una situación sin resolver: siguen existiendo
     * y se les puede cobrar, pero sus cuotas no cuentan para cobranza ni reportes.
     */
    public function up(): void
    {
        if (! $this->esMysql()) {
            return;
        }

        DB::statement(
            "ALTER TABLE contratos MODIFY estatus
             ENUM('activo','moroso','liquidado','cancelado','donacion','especial')
             NOT NULL DEFAULT 'activo'"
        );
    }

    public function down(): void
    {
        if (! $this->esMysql()) {
            return;
        }

        $enEspecial = DB::table('contratos')->where('estatus', 'especial')->count();

        if ($enEspecial > 0) {
            throw new RuntimeException(
                "No se puede revertir: hay {$enEspecial} contrato(s) en estatus 'especial'. Resuélvelos primero."
            );
        }

        DB::statement(
            "ALTER TABLE contratos MODIFY estatus
             ENUM('activo','moroso','liquidado','cancelado','donacion')
             NOT NULL DEFAULT 'activo'"
        );
    }

    protected function esMysql(): bool
    {
        return in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true);
    }
};
