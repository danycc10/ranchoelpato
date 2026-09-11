<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cola de impresión del agente local. El agente vive en la computadora del
 * usuario y jala los trabajos por HTTPS, así que el servidor solo guarda la
 * cola y el estado de cada equipo emparejado.
 *
 * El esquema es el mismo que el del POS a propósito: así el agente ya
 * compilado funciona contra este proyecto sin recompilarse.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('print_agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 80);
            $table->char('token_hash', 64)->unique();
            $table->char('token_last_four', 4);
            $table->boolean('is_active')->default(true)->index();
            $table->string('version', 32)->nullable();
            $table->string('ticket_printer')->nullable();
            // El agente del POS también reporta su impresora Carta/A4; aquí no
            // se usa, pero la columna existe para no romper su heartbeat.
            $table->string('quote_printer')->nullable();
            $table->json('available_printers')->nullable();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('print_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('print_agent_id')->constrained()->restrictOnDelete();
            $table->foreignId('requested_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('document_type', 30);
            $table->unsignedBigInteger('document_id');
            $table->string('document_label', 80);
            $table->string('status', 20)->index();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedTinyInteger('copies')->default(1);
            // Los recibos de recargo que se imprimen dentro del mismo ticket.
            $table->json('adjunto_ids')->nullable();
            $table->timestamp('available_at')->index();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('lease_expires_at')->nullable()->index();
            $table->timestamp('completed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['print_agent_id', 'status', 'available_at'], 'print_jobs_agent_queue_index');
            $table->index(['document_type', 'document_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_jobs');
        Schema::dropIfExists('print_agents');
    }
};
