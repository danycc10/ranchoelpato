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
Schema::create('cuotas', function (Blueprint $table) {
    $table->id();
    $table->foreignId('contrato_id')->constrained()->cascadeOnDelete();
    $table->integer('numero');
    $table->date('fecha_vencimiento');
    $table->decimal('monto', 12, 2);
    $table->decimal('pagado_total', 12, 2)->default(0);
    $table->decimal('recargo_aplicado', 12, 2)->default(0);
    $table->enum('estatus', ['pendiente','parcial','pagada','vencida','anulada'])->default('pendiente');
    $table->timestamps();
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cuotas');
    }
};
