<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clientes que no quieren su recibo en papel: al cobrarles no se imprime el
 * ticket y el PDF se les manda por WhatsApp.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->boolean('recibo_sin_imprimir')->default(false)->after('telefono_uso');
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn('recibo_sin_imprimir');
        });
    }
};
