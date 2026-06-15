<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recibos_pagos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('recibo_id')
                ->constrained('recibos')
                ->cascadeOnDelete();

            $table->foreignId('forma_pago_id')
                ->constrained('formas_pago');

            $table->foreignId('cuenta_bancaria_id')
                ->nullable()
                ->constrained('cuentas_bancarias');

            $table->decimal('monto', 12, 2);

            $table->string('referencia', 255)->nullable();
            $table->string('observaciones', 255)->nullable();

            $table->string('evidencia_path', 255)->nullable();
            $table->string('evidencia_disk', 50)->nullable();
            $table->string('evidencia_mime', 100)->nullable();
            $table->unsignedBigInteger('evidencia_size')->nullable();

            $table->unsignedInteger('orden')->default(1);

            $table->foreignId('capturado_por_user_id')
                ->nullable()
                ->constrained('users');

            $table->timestamps();
            $table->softDeletes();
            $table->timestamp('anulado_at')->nullable();
            $table->unsignedBigInteger('anulado_por_user_id')->nullable();
            $table->string('anulado_motivo', 255)->nullable();
            $table->index(['recibo_id']);
            $table->index(['forma_pago_id']);
            $table->index(['cuenta_bancaria_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recibos_pagos');
    }
};
