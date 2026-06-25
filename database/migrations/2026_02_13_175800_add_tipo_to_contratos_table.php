<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contratos', function (Blueprint $table) {

            // 1) Tipo contrato: terreno o servicio
            if (! Schema::hasColumn('contratos', 'tipo')) {
                $table->enum('tipo', ['terreno', 'servicio'])->default('terreno')->index();
            }

            // 2) Subtipo si es servicio
            if (! Schema::hasColumn('contratos', 'servicio_tipo')) {
                $table->enum('servicio_tipo', ['agua', 'electricidad'])->nullable()->index();
            }

            // 3) Contrato base (el del lote/cliente) del cual tomaremos info
            if (! Schema::hasColumn('contratos', 'contrato_base_id')) {
                $table->foreignId('contrato_base_id')->nullable()
                    ->constrained('contratos')
                    ->nullOnDelete()
                    ->index();
            }

            // 4) Marca opcional para distinguir financiamiento de instalación
            if (! Schema::hasColumn('contratos', 'es_financiamiento_instalacion')) {
                $table->boolean('es_financiamiento_instalacion')->default(false)->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            if (Schema::hasColumn('contratos', 'contrato_base_id')) {
                $table->dropConstrainedForeignId('contrato_base_id');
            }
            if (Schema::hasColumn('contratos', 'tipo')) {
                $table->dropColumn('tipo');
            }
            if (Schema::hasColumn('contratos', 'servicio_tipo')) {
                $table->dropColumn('servicio_tipo');
            }
            if (Schema::hasColumn('contratos', 'es_financiamiento_instalacion')) {
                $table->dropColumn('es_financiamiento_instalacion');
            }
        });
    }
};
