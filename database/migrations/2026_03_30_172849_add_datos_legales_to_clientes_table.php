<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            if (! Schema::hasColumn('clientes', 'nombre_legal')) {
                $table->string('nombre_legal')->nullable()->after('apellidos');
            }

            if (! Schema::hasColumn('clientes', 'curp')) {
                $table->string('curp', 30)->nullable()->after('rfc');
            }

            if (! Schema::hasColumn('clientes', 'ine_frente')) {
                $table->string('ine_frente')->nullable()->after('curp');
            }

            if (! Schema::hasColumn('clientes', 'ine_reverso')) {
                $table->string('ine_reverso')->nullable()->after('ine_frente');
            }

            if (! Schema::hasColumn('clientes', 'documentos_disk')) {
                $table->string('documentos_disk', 50)->nullable()->after('ine_reverso');
            }
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $cols = [];

            foreach ([
                'nombre_legal',
                'curp',
                'ine_frente',
                'ine_reverso',
                'documentos_disk',
            ] as $col) {
                if (Schema::hasColumn('clientes', $col)) {
                    $cols[] = $col;
                }
            }

            if (! empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};