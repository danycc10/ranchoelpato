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
Schema::create('lotes', function (Blueprint $table) {
    $table->id();
    $table->foreignId('fraccionamiento_id')->constrained()->cascadeOnDelete();
    $table->foreignId('propietario_id')->nullable()->constrained()->nullOnDelete();
    $table->string('manzana')->nullable();
    $table->string('lote');
    $table->string('clave')->unique();
    $table->decimal('area_m2', 10, 2)->nullable();
    $table->decimal('precio_lista', 12, 2)->nullable();
    $table->enum('estatus', ['disponible','apartado','vendido','cancelado','donacion'])->default('disponible');
    $table->text('notas')->nullable();
    $table->timestamps();
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lotes');
    }
};
