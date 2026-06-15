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
 Schema::create('fraccionamientos', function (Blueprint $table) {
    $table->id();
    $table->foreignId('propietario_id')->constrained()->cascadeOnDelete();
    $table->string('nombre');
    $table->string('ubicacion')->nullable();
    $table->timestamps();
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fraccionamientos');
    }
};
