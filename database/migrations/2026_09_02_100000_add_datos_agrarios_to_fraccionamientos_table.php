<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Datos que el contrato de cesión de derechos posesorios declara del predio:
 * el núcleo agrario, el municipio y el antecedente del que derivan los
 * derechos que transmite el propietario.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fraccionamientos', function (Blueprint $table) {
            $table->string('ejido')->nullable()->after('ubicacion');
            $table->string('municipio')->nullable()->after('ejido');
            $table->string('antecedente_tipo')->nullable()->after('municipio');
            $table->text('antecedente_datos')->nullable()->after('antecedente_tipo');
        });
    }

    public function down(): void
    {
        Schema::table('fraccionamientos', function (Blueprint $table) {
            $table->dropColumn([
                'ejido',
                'municipio',
                'antecedente_tipo',
                'antecedente_datos',
            ]);
        });
    }
};
