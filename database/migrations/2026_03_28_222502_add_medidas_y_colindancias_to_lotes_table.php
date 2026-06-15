<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lotes', function (Blueprint $table) {
            if (!Schema::hasColumn('lotes', 'medida_norte')) {
                $table->decimal('medida_norte', 10, 2)->nullable()->after('area_m2');
            }

            if (!Schema::hasColumn('lotes', 'medida_sur')) {
                $table->decimal('medida_sur', 10, 2)->nullable()->after('medida_norte');
            }

            if (!Schema::hasColumn('lotes', 'medida_este')) {
                $table->decimal('medida_este', 10, 2)->nullable()->after('medida_sur');
            }

            if (!Schema::hasColumn('lotes', 'medida_oeste')) {
                $table->decimal('medida_oeste', 10, 2)->nullable()->after('medida_este');
            }

            if (!Schema::hasColumn('lotes', 'colindancia_norte')) {
                $table->string('colindancia_norte')->nullable()->after('medida_oeste');
            }

            if (!Schema::hasColumn('lotes', 'colindancia_sur')) {
                $table->string('colindancia_sur')->nullable()->after('colindancia_norte');
            }

            if (!Schema::hasColumn('lotes', 'colindancia_este')) {
                $table->string('colindancia_este')->nullable()->after('colindancia_sur');
            }

            if (!Schema::hasColumn('lotes', 'colindancia_oeste')) {
                $table->string('colindancia_oeste')->nullable()->after('colindancia_este');
            }
        });
    }

    public function down(): void
    {
        Schema::table('lotes', function (Blueprint $table) {
            $drop = [];

            foreach ([
                'medida_norte',
                'medida_sur',
                'medida_este',
                'medida_oeste',
                'colindancia_norte',
                'colindancia_sur',
                'colindancia_este',
                'colindancia_oeste',
            ] as $col) {
                if (Schema::hasColumn('lotes', $col)) {
                    $drop[] = $col;
                }
            }

            if (!empty($drop)) {
                $table->dropColumn($drop);
            }
        });
    }
};