<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Venta de contado: el contrato se paga completo al firmarse, así que nace
 * liquidado y sin calendario de cuotas, con su propio formato de Word y un
 * recibo por el total.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            $table->boolean('es_contado')->default(false)->after('solo_documentos')->index();
            $table->string('archivo_contrato_contado')->nullable()->after('archivo_contrato_docx');
            $table->string('archivo_contrato_contado_docx')->nullable()->after('archivo_contrato_contado');
        });

        if (! DB::table('tipos_cobro')->where('nombre', 'CONTADO')->exists()) {
            DB::table('tipos_cobro')->insert([
                'nombre' => 'CONTADO',
                'categoria' => 'contrato',
                // Se cobra una sola vez al firmar: no pertenece a un periodo.
                'requiere_periodo' => false,
                'activa' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            $table->dropColumn(['es_contado', 'archivo_contrato_contado', 'archivo_contrato_contado_docx']);
        });

        // El tipo de cobro se queda: borrarlo dejaría recibos sin concepto.
    }
};
