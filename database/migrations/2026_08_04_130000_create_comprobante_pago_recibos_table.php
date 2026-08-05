<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comprobante_pago_recibos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comprobante_pago_id')->constrained('comprobantes_pago')->cascadeOnDelete();
            $table->foreignId('recibo_id')->constrained('recibos')->cascadeOnDelete();
            $table->foreignId('cuota_id')->nullable()->constrained('cuotas')->nullOnDelete();
            $table->timestamps();

            $table->unique(['comprobante_pago_id', 'recibo_id'], 'comprobante_recibo_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comprobante_pago_recibos');
    }
};
