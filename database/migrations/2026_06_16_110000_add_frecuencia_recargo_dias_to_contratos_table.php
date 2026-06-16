<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            if (! Schema::hasColumn('contratos', 'frecuencia_recargo_dias')) {
                $table->unsignedInteger('frecuencia_recargo_dias')
                    ->nullable()
                    ->default(1)
                    ->after('dias_gracia');
            }
        });

        DB::table('contratos')
            ->where('dias_gracia', 0)
            ->update(['frecuencia_recargo_dias' => 1]);

        DB::table('contratos')
            ->where('dias_gracia', 3)
            ->update(['frecuencia_recargo_dias' => 4]);

        DB::table('contratos')
            ->where('dias_gracia', 7)
            ->update(['frecuencia_recargo_dias' => 7]);
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
