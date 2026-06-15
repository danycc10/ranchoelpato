<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            if (!Schema::hasColumn('contratos', 'promocion_id')) {
                $table->unsignedBigInteger('promocion_id')->nullable()->after('lote_id');

                // Si quieres FK (recomendado) y tu tabla promociones existe:
                if (Schema::hasTable('promociones')) {
                    $table->foreign('promocion_id')
                        ->references('id')
                        ->on('promociones')
                        ->nullOnDelete();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            if (Schema::hasColumn('contratos', 'promocion_id')) {

                // drop FK si existe
                try { $table->dropForeign(['promocion_id']); } catch (\Throwable $e) {}

                $table->dropColumn('promocion_id');
            }
        });
    }
};
