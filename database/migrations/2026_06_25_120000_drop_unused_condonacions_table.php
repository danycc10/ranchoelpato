<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('condonacions')) {
            return;
        }

        if (! Schema::hasTable('condonaciones')) {
            Schema::rename('condonacions', 'condonaciones');

            return;
        }

        DB::table('condonacions')
            ->orderBy('id')
            ->get()
            ->each(function (object $row): void {
                $payload = [
                    'cliente_id' => $row->cliente_id,
                    'contrato_id' => $row->contrato_id,
                    'cuota_id' => $row->cuota_id,
                    'monto' => $row->monto,
                    'motivo' => $row->motivo,
                    'recibo_id' => $row->recibo_id,
                    'created_by_user_id' => $row->created_by_user_id,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ];

                $query = DB::table('condonaciones');

                foreach ($payload as $column => $value) {
                    $value === null
                        ? $query->whereNull($column)
                        : $query->where($column, $value);
                }

                if (! $query->exists()) {
                    DB::table('condonaciones')->insert($payload);
                }
            });

        Schema::dropIfExists('condonacions');
    }

    public function down(): void
    {
        // No re-crear la tabla mal nombrada.
    }
};
