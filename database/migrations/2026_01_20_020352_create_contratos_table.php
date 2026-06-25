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
        Schema::create('contratos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lote_id')->constrained()->cascadeOnDelete();
            $table->string('folio_contrato')->unique();
            $table->date('fecha_inicio');
            $table->enum('frecuencia', ['semanal', 'mensual']);
            $table->unsignedTinyInteger('dia_semana')->nullable(); // 1-7
            $table->unsignedTinyInteger('dia_mes')->nullable();    // 1-28
            $table->decimal('precio_total', 12, 2);
            $table->decimal('enganche', 12, 2)->default(0);
            $table->decimal('saldo_inicial', 12, 2);
            $table->decimal('saldo_actual', 12, 2);
            $table->decimal('monto_pago', 12, 2);
            $table->enum('tipo_recargo', ['fijo', 'porcentaje']);
            $table->decimal('valor_recargo', 8, 2)->default(0);
            $table->integer('dias_gracia')->default(0);
            $table->enum('estatus', ['activo', 'moroso', 'liquidado', 'cancelado'])->default('activo');
            $table->string('archivo_contrato')->nullable();
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contratos');
    }
};
