<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('condonacions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cliente_id')
                ->constrained('clientes')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('contrato_id')
                ->constrained('contratos')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('cuota_id')
                ->constrained('cuotas')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->decimal('monto', 12, 2);

            $table->string('motivo', 255)->nullable();

            // opcional: si generas un recibo de condonación
            $table->foreignId('recibo_id')
                ->nullable()
                ->constrained('recibos')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->timestamps();

            $table->index(['cliente_id', 'contrato_id']);
            $table->index(['cuota_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('condonacions');
    }
};
