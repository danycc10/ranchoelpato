<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('recibos', function (Blueprint $table) {
            $table->string('firma_path')->nullable()->after('evidencia_size');
            $table->string('firma_disk')->nullable()->after('firma_path');
            $table->string('firma_mime')->nullable()->after('firma_disk');
            $table->unsignedBigInteger('firma_size')->nullable()->after('firma_mime');
            $table->timestamp('firmado_en')->nullable()->after('firma_size');
            $table->string('firmado_por')->nullable()->after('firmado_en');
        });
    }

    public function down(): void
    {
        Schema::table('recibos', function (Blueprint $table) {
            $table->dropColumn([
                'firma_path',
                'firma_disk',
                'firma_mime',
                'firma_size',
                'firmado_en',
                'firmado_por',
            ]);
        });
    }
};