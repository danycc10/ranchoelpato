<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los telefonos de mas de un cliente, y para que sirve cada uno.
 *
 * Hay quien da un numero para llamadas y otro para WhatsApp. El `uso` dice
 * cual es cual; por defecto "ambos", que es el caso de quien solo tiene uno.
 *
 * El principal se queda en `clientes.telefono`: ahi lo leen los contratos,
 * las plantillas de Word, los reportes y el tablero de cobranza. Aqui viven
 * solo los adicionales, para que no haya dos lugares diciendo cual es el de
 * siempre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cliente_telefonos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();

            $table->string('telefono', 20);

            $table->enum('uso', ['ambos', 'llamadas', 'whatsapp'])->default('ambos');

            // "Casa", "Recados con su hija", "Trabajo"...
            $table->string('etiqueta', 60)->nullable();

            $table->timestamps();

            // Por aqui entra la busqueda de quien manda un WhatsApp.
            $table->index('telefono');
            $table->unique(['cliente_id', 'telefono']);
        });

        Schema::table('clientes', function (Blueprint $table) {
            // El principal tambien tiene uso: puede ser solo para llamar.
            $table->enum('telefono_uso', ['ambos', 'llamadas', 'whatsapp'])
                ->default('ambos')
                ->after('telefono');
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn('telefono_uso');
        });

        Schema::dropIfExists('cliente_telefonos');
    }
};
