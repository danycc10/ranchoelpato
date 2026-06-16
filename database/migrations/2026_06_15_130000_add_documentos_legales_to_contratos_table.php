<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            if (! Schema::hasColumn('contratos', 'archivo_constancia_terminacion_pago')) {
                $table->string('archivo_constancia_terminacion_pago')->nullable()->after('archivo_contrato_docx');
            }

            if (! Schema::hasColumn('contratos', 'archivo_constancia_terminacion_pago_docx')) {
                $table->string('archivo_constancia_terminacion_pago_docx')->nullable()->after('archivo_constancia_terminacion_pago');
            }

            if (! Schema::hasColumn('contratos', 'archivo_convenio_pago')) {
                $table->string('archivo_convenio_pago')->nullable()->after('archivo_constancia_terminacion_pago_docx');
            }

            if (! Schema::hasColumn('contratos', 'archivo_convenio_pago_docx')) {
                $table->string('archivo_convenio_pago_docx')->nullable()->after('archivo_convenio_pago');
            }

            if (! Schema::hasColumn('contratos', 'archivo_convenio_pago_reconocimiento_adeudo')) {
                $table->string('archivo_convenio_pago_reconocimiento_adeudo')->nullable()->after('archivo_convenio_pago_docx');
            }

            if (! Schema::hasColumn('contratos', 'archivo_convenio_pago_reconocimiento_adeudo_docx')) {
                $table->string('archivo_convenio_pago_reconocimiento_adeudo_docx')->nullable()->after('archivo_convenio_pago_reconocimiento_adeudo');
            }
        });
    }

    public function down(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            $drop = [];

            foreach ([
                'archivo_constancia_terminacion_pago',
                'archivo_constancia_terminacion_pago_docx',
                'archivo_convenio_pago',
                'archivo_convenio_pago_docx',
                'archivo_convenio_pago_reconocimiento_adeudo',
                'archivo_convenio_pago_reconocimiento_adeudo_docx',
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
