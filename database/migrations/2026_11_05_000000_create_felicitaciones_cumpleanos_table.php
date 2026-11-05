<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A quién ya se felicitó por su cumpleaños, cada año y por qué medio.
 *
 * El correo sale solo una vez aunque el comando corra de nuevo, y en el
 * tablero se ve quién ya le mandó WhatsApp para que no lo feliciten dos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('felicitaciones_cumpleanos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();

            $table->unsignedSmallInteger('anio');

            $table->enum('canal', ['correo', 'whatsapp']);

            // Quién le escribió por WhatsApp; el correo lo manda el sistema.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['cliente_id', 'anio', 'canal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('felicitaciones_cumpleanos');
    }
};
