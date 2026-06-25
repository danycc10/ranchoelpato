<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('propietarios', function (Blueprint $table) {
            if (! Schema::hasColumn('propietarios', 'nombre_legal')) {
                $table->string('nombre_legal')->nullable()->after('nombre');
            }

            if (! Schema::hasColumn('propietarios', 'curp')) {
                $table->string('curp', 30)->nullable()->after('nombre_legal');
            }

            if (! Schema::hasColumn('propietarios', 'ine_frente')) {
                $table->string('ine_frente')->nullable()->after('curp');
            }

            if (! Schema::hasColumn('propietarios', 'ine_reverso')) {
                $table->string('ine_reverso')->nullable()->after('ine_frente');
            }

            if (! Schema::hasColumn('propietarios', 'documentos_disk')) {
                $table->string('documentos_disk', 50)->nullable()->after('ine_reverso');
            }
        });
    }

    public function down(): void
    {
        Schema::table('propietarios', function (Blueprint $table) {
            $cols = [];

            foreach ([
                'nombre_legal',
                'curp',
                'ine_frente',
                'ine_reverso',
                'documentos_disk',
            ] as $col) {
                if (Schema::hasColumn('propietarios', $col)) {
                    $cols[] = $col;
                }
            }

            if (! empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
