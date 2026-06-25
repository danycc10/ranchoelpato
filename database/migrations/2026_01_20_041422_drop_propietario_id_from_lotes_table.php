<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lotes', function (Blueprint $table) {
            if (Schema::hasColumn('lotes', 'propietario_id')) {
                // Si existe FK, hay que tumbarla
                try {
                    $table->dropForeign(['propietario_id']);
                } catch (Throwable $e) {
                }
                $table->dropColumn('propietario_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('lotes', function (Blueprint $table) {
            $table->foreignId('propietario_id')->nullable()->after('fraccionamiento_id')
                ->constrained('propietarios')
                ->nullOnDelete();
        });
    }
};
