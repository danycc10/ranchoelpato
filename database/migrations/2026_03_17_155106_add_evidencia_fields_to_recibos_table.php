<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('recibos', function (Blueprint $table) {
            $table->string('evidencia_path')->nullable()->after('observaciones');
            $table->string('evidencia_disk', 50)->nullable()->after('evidencia_path');
            $table->string('evidencia_mime', 100)->nullable()->after('evidencia_disk');
            $table->unsignedBigInteger('evidencia_size')->nullable()->after('evidencia_mime');
        });
    }

    public function down(): void
    {
        Schema::table('recibos', function (Blueprint $table) {
            $table->dropColumn([
                'evidencia_path',
                'evidencia_disk',
                'evidencia_mime',
                'evidencia_size',
            ]);
        });
    }
};