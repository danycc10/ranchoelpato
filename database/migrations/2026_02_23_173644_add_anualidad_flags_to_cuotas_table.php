<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('cuotas', function (Blueprint $table) {
            $table->boolean('es_anualidad')->default(false)->after('numero');
            $table->string('concepto', 60)->nullable()->after('es_anualidad');
        });
    }

    public function down(): void
    {
        Schema::table('cuotas', function (Blueprint $table) {
            $table->dropColumn(['es_anualidad', 'concepto']);
        });
    }
};