<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fraccionamientos', function (Blueprint $table) {
            $table->string('contrato_base_path')->nullable()->after('logo_path');
        });
    }

    public function down(): void
    {
        Schema::table('fraccionamientos', function (Blueprint $table) {
            $table->dropColumn('contrato_base_path');
        });
    }
};
