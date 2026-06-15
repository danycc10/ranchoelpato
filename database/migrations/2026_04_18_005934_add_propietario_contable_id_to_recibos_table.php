<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recibos', function (Blueprint $table) {
            $table->foreignId('propietario_contable_id')
                ->nullable()
                ->after('lote_id')
                ->constrained('propietarios')
                ->nullOnDelete();
        });

        /**
         * Backfill inicial:
         * Para recibos ya existentes, tomar el propietario real
         * desde lote -> fraccionamiento -> propietario_id
         */
        DB::statement("
            UPDATE recibos r
            INNER JOIN lotes l
                ON l.id = r.lote_id
            INNER JOIN fraccionamientos f
                ON f.id = l.fraccionamiento_id
            SET r.propietario_contable_id = f.propietario_id
            WHERE r.propietario_contable_id IS NULL
        ");
    }

    public function down(): void
    {
        Schema::table('recibos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('propietario_contable_id');
        });
    }
};