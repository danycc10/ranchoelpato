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
Schema::create('periodos', function (Blueprint $table) {
    $table->id();
    $table->enum('tipo', ['mensual','anual']);
    $table->integer('anio');
    $table->integer('mes')->nullable();
    $table->string('nombre');
    $table->timestamps();
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('periodos');
    }
};
