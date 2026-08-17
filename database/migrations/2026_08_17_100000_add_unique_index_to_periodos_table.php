<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los periodos mensuales ahora se crean solos cuando un recibo necesita un mes
 * que no estaba en el catálogo. El índice único evita que dos cobros
 * simultáneos den de alta el mismo mes dos veces.
 */
return new class extends Migration
{
    private const INDEX = 'periodos_tipo_anio_mes_unique';

    public function up(): void
    {
        if (Schema::hasIndex('periodos', self::INDEX)) {
            return;
        }

        Schema::table('periodos', function (Blueprint $table) {
            $table->unique(['tipo', 'anio', 'mes'], self::INDEX);
        });
    }

    public function down(): void
    {
        if (! Schema::hasIndex('periodos', self::INDEX)) {
            return;
        }

        Schema::table('periodos', function (Blueprint $table) {
            $table->dropUnique(self::INDEX);
        });
    }
};
