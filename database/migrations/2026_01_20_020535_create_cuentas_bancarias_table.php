<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cuentas_bancarias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('propietario_id')->constrained()->cascadeOnDelete();
            $table->string('alias');
            $table->string('banco')->nullable();
            $table->string('tipo')->nullable(); // clabe, tarjeta, efectivo
            $table->string('numero')->nullable();
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cuentas_bancarias');
    }
};
