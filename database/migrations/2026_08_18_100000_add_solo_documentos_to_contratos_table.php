<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contratos que ya se terminaron antes del sistema y solo se dan de alta para
 * resguardar sus documentos digitales: no llevan cuotas, saldos ni pagos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            if (! Schema::hasColumn('contratos', 'solo_documentos')) {
                $table->boolean('solo_documentos')->default(false)->index()->after('es_financiamiento_instalacion');
            }
        });
    }

    public function down(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            if (Schema::hasColumn('contratos', 'solo_documentos')) {
                $table->dropColumn('solo_documentos');
            }
        });
    }
};
