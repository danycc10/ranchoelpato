<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El contrato de cesión declara domicilio y RFC de ambas partes; el cliente ya
 * los tenía, el propietario no.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('propietarios', function (Blueprint $table) {
            $table->string('direccion')->nullable();
            $table->string('rfc')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('propietarios', function (Blueprint $table) {
            $table->dropColumn(['direccion', 'rfc']);
        });
    }
};
