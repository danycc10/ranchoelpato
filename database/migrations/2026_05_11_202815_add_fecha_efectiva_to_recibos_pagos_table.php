<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('
                UPDATE recibos_pagos
                SET fecha_efectiva = (
                    SELECT r.fecha FROM recibos r WHERE r.id = recibos_pagos.recibo_id
                )
                WHERE fecha_efectiva IS NULL
            ');
        } else {
            DB::statement('
                UPDATE recibos_pagos rp
                INNER JOIN recibos r ON r.id = rp.recibo_id
                SET rp.fecha_efectiva = r.fecha
                WHERE rp.fecha_efectiva IS NULL
            ');
        }
    }

    public function down(): void
    {
        Schema::table('recibos_pagos', function (Blueprint $table) {

            $table->dropColumn('fecha_efectiva');

        });
    }
};
