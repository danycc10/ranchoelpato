<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('recibos', function (Blueprint $table) {
            $table->softDeletes(); // deleted_at

            $table->timestamp('anulado_at')->nullable()->after('deleted_at');
            $table->unsignedBigInteger('anulado_por_user_id')->nullable()->after('anulado_at');
            $table->string('anulado_motivo', 255)->nullable()->after('anulado_por_user_id');

            $table->index('anulado_at');
            $table->foreign('anulado_por_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('recibos', function (Blueprint $table) {
            $table->dropForeign(['anulado_por_user_id']);
            $table->dropColumn(['deleted_at','anulado_at','anulado_por_user_id','anulado_motivo']);
        });
    }
};