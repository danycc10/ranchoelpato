<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promociones', function (Blueprint $table) {
            $table->id();

            $table->string('nombre');
            $table->string('codigo')->unique();

            $table->enum('tipo', [
                'diferir_primer_pago',
                'cuotas_fijas',
                'descuento_saldo',
                'descuento_monto_pago',
            ]);

            $table->integer('dias_diferidos')->nullable();
            $table->integer('numero_cuotas')->nullable();
            $table->decimal('porcentaje', 5, 2)->nullable();
            $table->decimal('monto_fijo', 12, 2)->nullable();

            $table->boolean('activa')->default(true);
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promociones');
    }
};
