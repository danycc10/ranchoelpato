<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Datos personales que declaran las partes en el contrato de cesión:
 * si es persona física o moral, nacionalidad, estado civil, ocupación y
 * el número de la identificación oficial con la que se identifican.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['clientes', 'propietarios'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->string('persona_tipo')->nullable();
                $table->string('nacionalidad')->nullable();
                $table->string('estado_civil')->nullable();
                $table->string('ocupacion')->nullable();
                $table->string('identificacion_tipo')->nullable();
                $table->string('identificacion_numero')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['clientes', 'propietarios'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->dropColumn([
                    'persona_tipo',
                    'nacionalidad',
                    'estado_civil',
                    'ocupacion',
                    'identificacion_tipo',
                    'identificacion_numero',
                ]);
            });
        }
    }
};
