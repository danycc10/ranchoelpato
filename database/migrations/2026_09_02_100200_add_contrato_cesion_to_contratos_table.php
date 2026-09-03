<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contrato de cesión onerosa de derechos posesorios: su archivo generado y
 * las condiciones que el formato deja a elección de las partes.
 *
 * Las condiciones en null usan el valor por defecto de ajustes del sistema,
 * para que los contratos que ya existen no queden en blanco.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            $table->string('archivo_contrato_cesion')->nullable();
            $table->string('archivo_contrato_cesion_docx')->nullable();

            $table->string('posesion_entrega')->nullable();
            $table->string('servicios_estado')->nullable();
            $table->string('servicios_a_cargo')->nullable();
            $table->unsignedSmallInteger('parcialidades_rescision')->nullable();
            $table->decimal('tolerancia_medidas', 5, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            $table->dropColumn([
                'archivo_contrato_cesion',
                'archivo_contrato_cesion_docx',
                'posesion_entrega',
                'servicios_estado',
                'servicios_a_cargo',
                'parcialidades_rescision',
                'tolerancia_medidas',
            ]);
        });
    }
};
