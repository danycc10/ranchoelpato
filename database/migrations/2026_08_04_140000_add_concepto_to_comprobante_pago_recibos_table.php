<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comprobante_pago_recibos', function (Blueprint $table) {
            $table->string('concepto', 20)->default('principal')->after('cuota_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('comprobante_pago_recibos', function (Blueprint $table) {
            $table->dropIndex(['concepto']);
            $table->dropColumn('concepto');
        });
    }
};
