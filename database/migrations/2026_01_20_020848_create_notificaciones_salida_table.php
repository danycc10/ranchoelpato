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
Schema::create('notificaciones_salida', function (Blueprint $table) {
    $table->id();
    $table->enum('canal', ['whatsapp','correo']);
    $table->string('tipo');
    $table->foreignId('cliente_id')->nullable()->constrained()->nullOnDelete();
    $table->foreignId('contrato_id')->nullable()->constrained()->nullOnDelete();
    $table->foreignId('cuota_id')->nullable()->constrained()->nullOnDelete();
    $table->string('destino');
    $table->json('payload');
    $table->enum('estatus', ['en_cola','enviado','fallido'])->default('en_cola');
    $table->timestamp('enviado_en')->nullable();
    $table->string('error')->nullable();
    $table->timestamps();
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notificaciones_salida');
    }
};
