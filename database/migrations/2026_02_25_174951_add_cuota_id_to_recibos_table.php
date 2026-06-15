<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recibos', function (Blueprint $table) {
            $table->unsignedBigInteger('cuota_id')
                  ->nullable()
                  ->after('contrato_id');

            $table->foreign('cuota_id')
                  ->references('id')
                  ->on('cuotas')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('recibos', function (Blueprint $table) {
            // Primero eliminar la foreign key
            $table->dropForeign(['cuota_id']);

            // Luego eliminar la columna
            $table->dropColumn('cuota_id');
        });
    }
};