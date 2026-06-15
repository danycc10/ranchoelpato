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
Schema::create('pagos', function (Blueprint $table) {
    $table->id();
    $table->foreignId('contrato_id')->constrained()->cascadeOnDelete();
    $table->foreignId('cuota_id')->nullable()->constrained()->nullOnDelete();
    $table->foreignId('recibo_id')->constrained()->cascadeOnDelete();
    $table->decimal('monto', 12, 2);
    $table->enum('metodo', ['efectivo','transferencia','oxxo','stripe']);
    $table->string('referencia')->nullable();
    $table->enum('estatus', ['borrador','confirmado','rechazado','anulado'])->default('confirmado');
    $table->timestamp('fecha_pago');
    $table->timestamps();
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pagos');
    }
};
