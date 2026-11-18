<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anexo que firma el cliente cuando pide cambiar su día de pago.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            $table->string('archivo_anexo_cambio_dia_pago')->nullable();
            $table->string('archivo_anexo_cambio_dia_pago_docx')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            $table->dropColumn([
                'archivo_anexo_cambio_dia_pago',
                'archivo_anexo_cambio_dia_pago_docx',
            ]);
        });
    }
};
