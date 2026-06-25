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
        Schema::create('recibos', function (Blueprint $table) {
            $table->id();
            $table->string('folio')->unique();
            $table->date('fecha');
            $table->integer('anio');
            $table->integer('semana_pago')->nullable();
            $table->integer('semana_del_anio')->nullable();
            $table->integer('mes_del_anio')->nullable();
            $table->foreignId('cliente_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contrato_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lote_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tipos_cobro_id')
                ->constrained('tipos_cobro')
                ->cascadeOnDelete();

            $table->foreignId('forma_pago_id')
                ->constrained('formas_pago')
                ->cascadeOnDelete();
            $table->foreignId('cuentas_bancarias_id')->nullable()->constrained('cuentas_bancarias')->nullOnDelete();
            $table->foreignId('periodo_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('monto', 12, 2);
            $table->string('observaciones')->nullable();
            $table->foreignId('capturado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recibos');
    }
};
