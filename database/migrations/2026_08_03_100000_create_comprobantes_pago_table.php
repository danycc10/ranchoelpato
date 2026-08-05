<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comprobantes_pago', function (Blueprint $table) {
            $table->id();

            // ── Origen (WhatsApp) ────────────────────────────────────────────
            // wa_message_id es único: OpenWA reintenta las entregas fallidas, así
            // que sin esto una sola foto podría convertirse en varios comprobantes.
            $table->string('wa_message_id')->unique();
            $table->string('wa_idempotency_key')->nullable()->index();
            $table->string('wa_chat_id');
            $table->string('telefono', 20)->index();
            $table->string('nombre_whatsapp')->nullable();
            $table->text('texto_mensaje')->nullable();
            $table->timestamp('recibido_en');

            // ── Archivo ──────────────────────────────────────────────────────
            // Nulo cuando WhatsApp/OpenWA no entregó la imagen (excedió el límite
            // de incrustación); en ese caso hay que pedirle al cliente que reenvíe.
            $table->string('archivo_path')->nullable();
            $table->string('archivo_disk', 40)->nullable();
            $table->string('archivo_mime', 120)->nullable();
            $table->unsignedBigInteger('archivo_size')->nullable();

            // ── Estado ───────────────────────────────────────────────────────
            // recibido    -> llegó, falta pasarlo por la IA
            // por_validar -> la IA ya extrajo datos, espera revisión humana
            // validado    -> un cobrador lo aprobó y generó recibo
            // rechazado   -> un cobrador lo descartó
            // sin_archivo -> llegó sin imagen utilizable
            // error       -> la extracción falló
            $table->enum('estatus', [
                'recibido',
                'por_validar',
                'validado',
                'rechazado',
                'sin_archivo',
                'error',
            ])->default('recibido')->index();

            // ── Datos extraídos por la IA (nunca se aplican solos) ───────────
            $table->decimal('monto_detectado', 12, 2)->nullable();
            $table->date('fecha_detectada')->nullable();
            $table->string('banco_detectado')->nullable();
            $table->string('referencia_detectada')->nullable();
            $table->string('ultimos_digitos', 8)->nullable();
            $table->decimal('confianza', 5, 2)->nullable();
            $table->json('extraccion')->nullable();
            $table->string('extraccion_modelo')->nullable();
            $table->timestamp('extraido_en')->nullable();
            $table->text('error')->nullable();

            // ── Vinculación ──────────────────────────────────────────────────
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->foreignId('contrato_id')->nullable()->constrained('contratos')->nullOnDelete();
            $table->foreignId('cuota_id')->nullable()->constrained('cuotas')->nullOnDelete();
            $table->foreignId('recibo_id')->nullable()->constrained('recibos')->nullOnDelete();

            // ── Auditoría de la validación manual ────────────────────────────
            $table->foreignId('validado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validado_en')->nullable();
            $table->text('nota_validacion')->nullable();

            $table->timestamps();

            // La bandeja de trabajo se ordena por estatus + llegada.
            $table->index(['estatus', 'recibido_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comprobantes_pago');
    }
};
