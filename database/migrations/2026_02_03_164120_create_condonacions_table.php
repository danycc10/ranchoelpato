<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // No-op: la tabla correcta es "condonaciones".
        // Se conserva el archivo para no reintroducir la tabla mal nombrada en instalaciones nuevas.
    }

    public function down(): void
    {
        //
    }
};
