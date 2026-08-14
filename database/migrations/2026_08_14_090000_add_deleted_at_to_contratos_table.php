<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El modelo Contrato usa SoftDeletes desde hace tiempo y varias consultas
 * llaman withTrashed(), pero la columna nunca quedó en las migraciones.
 * Se agrega de forma idempotente para que el esquema coincida con el modelo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            if (! Schema::hasColumn('contratos', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            if (Schema::hasColumn('contratos', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
