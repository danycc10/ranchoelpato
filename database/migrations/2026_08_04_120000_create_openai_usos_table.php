<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('openai_usos', function (Blueprint $table) {
            $table->id();
            $table->string('request_id')->nullable()->unique();
            $table->string('modelo');
            $table->string('operacion');
            $table->string('endpoint', 50);
            $table->unsignedBigInteger('tokens_entrada')->default(0);
            $table->unsignedBigInteger('tokens_entrada_cache')->default(0);
            $table->unsignedBigInteger('tokens_salida')->default(0);
            $table->unsignedBigInteger('tokens_razonamiento')->default(0);
            $table->unsignedBigInteger('tokens_total')->default(0);
            $table->decimal('precio_entrada_millon', 12, 6)->nullable();
            $table->decimal('precio_cache_millon', 12, 6)->nullable();
            $table->decimal('precio_salida_millon', 12, 6)->nullable();
            $table->decimal('costo_estimado_usd', 16, 10)->nullable();
            $table->timestamp('consumido_en')->index();
            $table->timestamps();

            $table->index(['modelo', 'consumido_en']);
            $table->index(['operacion', 'consumido_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('openai_usos');
    }
};
