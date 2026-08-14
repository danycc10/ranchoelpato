<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            if (! Schema::hasColumn('contratos', 'archivo_contrato_financiamiento_electrico')) {
                $table->string('archivo_contrato_financiamiento_electrico')->nullable()->after('archivo_convenio_pago_reconocimiento_adeudo_docx');
            }

            if (! Schema::hasColumn('contratos', 'archivo_contrato_financiamiento_electrico_docx')) {
                $table->string('archivo_contrato_financiamiento_electrico_docx')->nullable()->after('archivo_contrato_financiamiento_electrico');
            }
        });
    }

    public function down(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            $drop = [];

            foreach ([
                'archivo_contrato_financiamiento_electrico',
                'archivo_contrato_financiamiento_electrico_docx',
            ] as $column) {
                if (Schema::hasColumn('contratos', $column)) {
                    $drop[] = $column;
                }
            }

            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });
    }
};
