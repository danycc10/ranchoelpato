<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contratos', function (Blueprint $table) {

            if (! Schema::hasColumn('contratos', 'archivo_contrato_disk')) {
                $table->string('archivo_contrato_disk')->nullable()->after('archivo_contrato');
            }
            if (! Schema::hasColumn('contratos', 'archivo_contrato_docx')) {
                $table->string('archivo_contrato_docx')->nullable()->after('archivo_contrato');
            }

            if (! Schema::hasColumn('contratos', 'comprador_ine_frente')) {
                $table->string('comprador_ine_frente')->nullable()->after('archivo_contrato_disk');
            }

            if (! Schema::hasColumn('contratos', 'comprador_ine_reverso')) {
                $table->string('comprador_ine_reverso')->nullable()->after('comprador_ine_frente');
            }

            if (! Schema::hasColumn('contratos', 'vendedor_ine_frente')) {
                $table->string('vendedor_ine_frente')->nullable()->after('comprador_ine_reverso');
            }

            if (! Schema::hasColumn('contratos', 'vendedor_ine_reverso')) {
                $table->string('vendedor_ine_reverso')->nullable()->after('vendedor_ine_frente');
            }

            if (! Schema::hasColumn('contratos', 'credenciales_disk')) {
                $table->string('credenciales_disk')->nullable()->after('vendedor_ine_reverso');
            }

            if (! Schema::hasColumn('contratos', 'vendedor_nombre_legal')) {
                $table->string('vendedor_nombre_legal')->nullable()->after('credenciales_disk');
            }

            if (! Schema::hasColumn('contratos', 'vendedor_curp')) {
                $table->string('vendedor_curp', 30)->nullable()->after('vendedor_nombre_legal');
            }

            if (! Schema::hasColumn('contratos', 'comprador_nombre_legal')) {
                $table->string('comprador_nombre_legal')->nullable()->after('vendedor_curp');
            }

            if (! Schema::hasColumn('contratos', 'comprador_curp')) {
                $table->string('comprador_curp', 30)->nullable()->after('comprador_nombre_legal');
            }

            if (! Schema::hasColumn('contratos', 'area_m2_snapshot')) {
                $table->decimal('area_m2_snapshot', 10, 2)->nullable()->after('comprador_curp');
            }

            if (! Schema::hasColumn('contratos', 'medida_norte_snapshot')) {
                $table->decimal('medida_norte_snapshot', 10, 2)->nullable()->after('area_m2_snapshot');
            }

            if (! Schema::hasColumn('contratos', 'medida_sur_snapshot')) {
                $table->decimal('medida_sur_snapshot', 10, 2)->nullable()->after('medida_norte_snapshot');
            }

            if (! Schema::hasColumn('contratos', 'medida_este_snapshot')) {
                $table->decimal('medida_este_snapshot', 10, 2)->nullable()->after('medida_sur_snapshot');
            }

            if (! Schema::hasColumn('contratos', 'medida_oeste_snapshot')) {
                $table->decimal('medida_oeste_snapshot', 10, 2)->nullable()->after('medida_este_snapshot');
            }

            if (! Schema::hasColumn('contratos', 'colindancia_norte_snapshot')) {
                $table->string('colindancia_norte_snapshot')->nullable()->after('medida_oeste_snapshot');
            }

            if (! Schema::hasColumn('contratos', 'colindancia_sur_snapshot')) {
                $table->string('colindancia_sur_snapshot')->nullable()->after('colindancia_norte_snapshot');
            }

            if (! Schema::hasColumn('contratos', 'colindancia_este_snapshot')) {
                $table->string('colindancia_este_snapshot')->nullable()->after('colindancia_sur_snapshot');
            }

            if (! Schema::hasColumn('contratos', 'colindancia_oeste_snapshot')) {
                $table->string('colindancia_oeste_snapshot')->nullable()->after('colindancia_este_snapshot');
            }
        });
    }

    public function down(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            $drop = [];

            foreach ([

                'archivo_contrato_disk',
                'archivo_contrato_docx',
                'comprador_ine_frente',
                'comprador_ine_reverso',
                'vendedor_ine_frente',
                'vendedor_ine_reverso',
                'credenciales_disk',
                'vendedor_nombre_legal',
                'vendedor_curp',
                'comprador_nombre_legal',
                'comprador_curp',
                'area_m2_snapshot',
                'medida_norte_snapshot',
                'medida_sur_snapshot',
                'medida_este_snapshot',
                'medida_oeste_snapshot',
                'colindancia_norte_snapshot',
                'colindancia_sur_snapshot',
                'colindancia_este_snapshot',
                'colindancia_oeste_snapshot',
            ] as $col) {
                if (Schema::hasColumn('contratos', $col)) {
                    $drop[] = $col;
                }
            }

            if (! empty($drop)) {
                $table->dropColumn($drop);
            }
        });
    }
};
