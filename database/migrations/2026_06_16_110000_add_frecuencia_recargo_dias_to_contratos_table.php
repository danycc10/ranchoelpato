<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            if (! Schema::hasColumn('contratos', 'frecuencia_recargo_dias')) {
                $table->unsignedInteger('frecuencia_recargo_dias')
                    ->nullable()
                    ->default(7)
                    ->after('dias_gracia');
            }
        });
    }

    public function down(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            if (Schema::hasColumn('contratos', 'frecuencia_recargo_dias')) {
                $table->dropColumn('frecuencia_recargo_dias');
            }
        });
    }
};
