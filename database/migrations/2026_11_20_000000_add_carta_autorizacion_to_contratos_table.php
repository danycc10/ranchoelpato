<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Carta con la que el titular autoriza a otra persona a hacer sus pagos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            $table->string('archivo_carta_autorizacion')->nullable();
            $table->string('archivo_carta_autorizacion_docx')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            $table->dropColumn([
                'archivo_carta_autorizacion',
                'archivo_carta_autorizacion_docx',
            ]);
        });
    }
};
