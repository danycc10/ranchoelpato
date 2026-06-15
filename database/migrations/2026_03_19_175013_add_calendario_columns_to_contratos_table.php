<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            $table->string('calendario_tipo')->default('automatico')->after('anualidad_monto');
            $table->json('calendario_json')->nullable()->after('calendario_tipo');
        });
    }

    public function down(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            $table->dropColumn(['calendario_tipo', 'calendario_json']);
        });
    }
};