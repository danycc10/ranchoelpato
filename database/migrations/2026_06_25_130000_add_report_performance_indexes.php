<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recibos_pagos', function (Blueprint $table) {
            $table->index(
                ['deleted_at', 'fecha_efectiva', 'recibo_id'],
                'recibos_pagos_report_date_idx'
            );

            $table->index(
                ['cuenta_bancaria_id', 'deleted_at', 'fecha_efectiva'],
                'recibos_pagos_bank_date_idx'
            );
        });

        Schema::table('cuotas', function (Blueprint $table) {
            $table->index(
                ['fecha_vencimiento', 'estatus', 'contrato_id'],
                'cuotas_report_due_status_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('recibos_pagos', function (Blueprint $table) {
            $table->dropIndex('recibos_pagos_report_date_idx');
            $table->dropIndex('recibos_pagos_bank_date_idx');
        });

        Schema::table('cuotas', function (Blueprint $table) {
            $table->dropIndex('cuotas_report_due_status_idx');
        });
    }
};
