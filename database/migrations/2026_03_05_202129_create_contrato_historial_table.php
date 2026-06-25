<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contrato_historial', function (Blueprint $table) {
            $table->id();

            $table->foreignId('contrato_id')->constrained('contratos');
            $table->foreignId('user_id')->nullable()->constrained('users');

            // cambio_cliente | reestructura | ambos
            $table->string('tipo', 50);

            // JSON con snapshot de "antes" y "después"
            $table->json('antes')->nullable();
            $table->json('despues')->nullable();

            // opcional pero útil para auditoría financiera
            $table->decimal('saldo_anterior', 14, 2)->nullable();
            $table->decimal('saldo_nuevo', 14, 2)->nullable();

            $table->unsignedInteger('cuotas_eliminadas')->default(0);
            $table->unsignedInteger('cuotas_creadas')->default(0);

            $table->text('nota')->nullable();

            $table->timestamps();

            $table->index(['contrato_id', 'tipo']);
            $table->index(['user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contrato_historial');
    }
};
