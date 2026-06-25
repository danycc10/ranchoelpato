<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recibos_pagos', function (Blueprint $table) {

            $table->date('fecha_efectiva')
                ->nullable()
                ->after('monto');

        });

        // Rellenar históricos usando la fecha del recibo
        DB::statement('
            UPDATE recibos_pagos rp
            INNER JOIN recibos r ON r.id = rp.recibo_id
            SET rp.fecha_efectiva = r.fecha
            WHERE rp.fecha_efectiva IS NULL
        ');
    }

    public function down(): void
    {
        Schema::table('recibos_pagos', function (Blueprint $table) {

            $table->dropColumn('fecha_efectiva');

        });
    }
};
