<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El modo de impresión dejó de ser un ajuste del sistema: ahora el cajero
 * elige navegador o agente en la misma vista del ticket. La fila queda sin
 * quien la lea, así que se va.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ajustes_sistema')) {
            return;
        }

        DB::table('ajustes_sistema')->where('clave', 'modo_impresion')->delete();
    }

    public function down(): void
    {
        // Nada que restaurar: el ajuste ya no lo consulta nadie.
    }
};
