<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Carátula y control interno del expediente: se imprimen aparte del contrato,
 * porque son de control interno y no forman parte de lo que se firma.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            $table->string('archivo_caratula_expediente')->nullable();
            $table->string('archivo_caratula_expediente_docx')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            $table->dropColumn([
                'archivo_caratula_expediente',
                'archivo_caratula_expediente_docx',
            ]);
        });
    }
};
