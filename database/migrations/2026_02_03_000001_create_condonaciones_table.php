<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('condonaciones', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cliente_id')->constrained('clientes');
            $table->foreignId('contrato_id')->constrained('contratos');

            $table->foreignId('cuota_id')->nullable()->constrained('cuotas');

            $table->decimal('monto', 12, 2)->default(0);
            $table->string('motivo', 255)->nullable();

            $table->foreignId('recibo_id')->nullable()->constrained('recibos');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users');

            $table->timestamps();

            $table->index(['cliente_id', 'contrato_id']);
            $table->index(['cuota_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('condonaciones');
    }
};
