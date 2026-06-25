<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            $table->boolean('tiene_anualidad')->default(false)->after('dias_gracia');
            $table->date('anualidad_fecha')->nullable()->after('tiene_anualidad');
            $table->decimal('anualidad_monto', 12, 2)->default(0)->after('anualidad_fecha');
        });
    }

    public function down(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            $table->dropColumn(['tiene_anualidad', 'anualidad_fecha', 'anualidad_monto']);
        });
    }
};
