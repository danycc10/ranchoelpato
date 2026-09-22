<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Liga temporal para que el cliente capture sus propios datos.
 *
 * Lo que llena no toca su registro: queda aquí hasta que alguien lo revisa y
 * lo aplica, para que una liga reenviada o un dedazo no pisen el catálogo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitudes_datos_cliente', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained()->cascadeOnDelete();
            $table->foreignId('creada_por_user_id')->nullable()->constrained('users')->nullOnDelete();

            // De la liga solo se guarda el hash: el token viaja en la URL.
            $table->char('token_hash', 64)->unique();

            $table->string('estatus', 20)->default('pendiente')->index();
            $table->timestamp('expira_en')->index();

            // Lo que capturó el cliente, en espera de revisión.
            $table->json('datos')->nullable();
            $table->string('ine_frente')->nullable();
            $table->string('ine_reverso')->nullable();
            $table->timestamp('capturada_en')->nullable();

            $table->timestamp('aplicada_en')->nullable();
            $table->foreignId('aplicada_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('nota_revision')->nullable();

            $table->timestamps();

            $table->index(['cliente_id', 'estatus']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes_datos_cliente');
    }
};
