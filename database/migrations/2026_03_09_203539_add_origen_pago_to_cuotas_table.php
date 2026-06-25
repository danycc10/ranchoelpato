<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cuotas', function (Blueprint $table) {
            if (! Schema::hasColumn('cuotas', 'origen_pago')) {
                $table->string('origen_pago', 30)->nullable();
                // normal | historico | condonacion | ajuste
            }

            if (! Schema::hasColumn('cuotas', 'observaciones_pago')) {
                $table->text('observaciones_pago')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('cuotas', function (Blueprint $table) {
            $cols = [];

            if (Schema::hasColumn('cuotas', 'origen_pago')) {
                $cols[] = 'origen_pago';
            }

            if (Schema::hasColumn('cuotas', 'observaciones_pago')) {
                $cols[] = 'observaciones_pago';
            }

            if (! empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
