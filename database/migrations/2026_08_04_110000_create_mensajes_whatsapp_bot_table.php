<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mensajes_whatsapp_bot', function (Blueprint $table) {
            $table->id();
            $table->string('wa_message_id')->unique();
            $table->string('wa_chat_id')->index();
            $table->string('telefono', 20)->nullable()->index();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->foreignId('comprobante_id')->nullable()->constrained('comprobantes_pago')->nullOnDelete();
            $table->string('tipo', 30); // texto | comprobante
            $table->text('contenido')->nullable();
            $table->text('respuesta')->nullable();
            $table->string('modelo')->nullable();
            $table->string('estatus', 30)->default('pendiente')->index();
            $table->text('error')->nullable();
            $table->timestamp('recibido_en');
            $table->timestamp('respondido_en')->nullable();
            $table->timestamps();

            $table->index(['wa_chat_id', 'recibido_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mensajes_whatsapp_bot');
    }
};
