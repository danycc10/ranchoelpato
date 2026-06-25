<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fraccionamientos', function (Blueprint $table) {
            $table->string('logo_path', 255)->nullable()->after('ubicacion');
            $table->index(['propietario_id', 'nombre']);
        });
    }

    public function down(): void
    {
        Schema::table('fraccionamientos', function (Blueprint $table) {
            $table->dropIndex(['propietario_id', 'nombre']);
            $table->dropColumn('logo_path');
        });
    }
};
