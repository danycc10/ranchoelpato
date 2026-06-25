<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cuotas', function (Blueprint $table) {
            $table->boolean('condonada')->default(false)->after('estatus');
            $table->timestamp('condonada_at')->nullable()->after('condonada');
            $table->decimal('condonado_total', 12, 2)->default(0)->after('pagado_total');

            $table->index(['condonada', 'estatus']);
        });
    }

    public function down(): void
    {
        Schema::table('cuotas', function (Blueprint $table) {
            $table->dropIndex(['condonada', 'estatus']);
            $table->dropColumn(['condonada', 'condonada_at', 'condonado_total']);
        });
    }
};
