<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tipos_cobro', function (Blueprint $table) {
            $table->foreignId('propietario_contable_id')
                ->nullable()
                ->after('categoria')
                ->constrained('propietarios')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tipos_cobro', function (Blueprint $table) {
            $table->dropConstrainedForeignId('propietario_contable_id');
        });
    }
};