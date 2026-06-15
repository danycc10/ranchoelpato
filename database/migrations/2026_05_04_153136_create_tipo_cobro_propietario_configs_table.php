<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipo_cobro_propietario_configs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tipo_cobro_id')
                ->constrained('tipos_cobro')
                ->cascadeOnDelete();

            $table->foreignId('fraccionamiento_id')
                ->nullable()
                ->constrained('fraccionamientos')
                ->nullOnDelete();

            $table->foreignId('forma_pago_id')
                ->nullable()
                ->constrained('formas_pago')
                ->nullOnDelete();

            $table->foreignId('propietario_id')
                ->constrained('propietarios')
                ->restrictOnDelete();

            $table->unsignedInteger('prioridad')->default(0);
            $table->boolean('activo')->default(true);

            $table->timestamps();

            $table->unique(
                ['tipo_cobro_id', 'fraccionamiento_id', 'forma_pago_id'],
                'tipo_cobro_config_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipo_cobro_propietario_configs');
    }
};