<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recibos', function (Blueprint $table) {

            if (! Schema::hasColumn('recibos', 'afecta_reportes')) {
                $table->boolean('afecta_reportes')->default(true);
            }

            if (! Schema::hasColumn('recibos', 'tipo_movimiento')) {
                $table->string('tipo_movimiento', 30)->default('normal');
                // normal | historico | condonacion | ajuste
            }

            if (! Schema::hasColumn('recibos', 'es_historico')) {
                $table->boolean('es_historico')->default(false);
            }

        });
    }

    public function down(): void
    {
        Schema::table('recibos', function (Blueprint $table) {
            $cols = [];

            if (Schema::hasColumn('recibos', 'afecta_reportes')) {
                $cols[] = 'afecta_reportes';
            }
            if (Schema::hasColumn('recibos', 'tipo_movimiento')) {
                $cols[] = 'tipo_movimiento';
            }
            if (Schema::hasColumn('recibos', 'es_historico')) {
                $cols[] = 'es_historico';
            }

            if (! empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
