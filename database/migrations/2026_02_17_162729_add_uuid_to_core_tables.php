<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'fraccionamientos',
            'lotes',
            'propietarios',
            'contratos',
            'cuotas',
            'recibos',
            'pagos',
        ];

        foreach ($tables as $tableName) {

            if (Schema::hasTable($tableName)) {

                Schema::table($tableName, function (Blueprint $table) use ($tableName) {

                    if (!Schema::hasColumn($tableName, 'uuid')) {

                        $table->uuid('uuid')
                              ->nullable()
                              ->unique()
                              ->after('id');

                    }

                });

            }

        }
    }

    public function down(): void
    {
        $tables = [
            'fraccionamientos',
            'lotes',
            'propietarios',
            'contratos',
            'cuotas',
            'recibos',
            'pagos',
        ];

        foreach ($tables as $tableName) {

            if (Schema::hasTable($tableName)) {

                Schema::table($tableName, function (Blueprint $table) use ($tableName) {

                    if (Schema::hasColumn($tableName, 'uuid')) {

                        $table->dropColumn('uuid');

                    }

                });

            }

        }
    }
};
