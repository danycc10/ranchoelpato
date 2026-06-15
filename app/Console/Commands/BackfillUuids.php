<?php

// app/Console/Commands/BackfillUuids.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BackfillUuids extends Command
{
    protected $signature = 'app:backfill-uuids';
    protected $description = 'Rellena UUIDs en tablas principales';

    public function handle(): int
    {
        $tables = [
            'fraccionamientos','lotes','propietarios','contratos','cuotas','recibos','pagos',
        ];

        foreach ($tables as $t) {
            $this->info("Procesando: {$t}");

            DB::table($t)
                ->whereNull('uuid')
                ->orderBy('id')
                ->chunkById(500, function ($rows) use ($t) {
                    foreach ($rows as $r) {
                        DB::table($t)->where('id', $r->id)->update([
                            'uuid' => (string) Str::uuid(),
                        ]);
                    }
                });
        }

        $this->info("Listo ✅");
        return self::SUCCESS;
    }
}
