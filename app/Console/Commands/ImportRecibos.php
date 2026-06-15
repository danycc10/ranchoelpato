<?php

namespace App\Console\Commands;

use App\Imports\RecibosImport;
use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;

class ImportRecibos extends Command
{
    protected $signature = 'recibos:import {file}
        {--propietario_id=1}
        {--capturado_por_user_id=}
        {--create-missing=1}
    ';

    public function handle(): int
    {
        $file = $this->argument('file');
        $propietarioId = (int)$this->option('propietario_id');
        $capturado = $this->option('capturado_por_user_id');
        $capturado = $capturado !== null ? (int)$capturado : null;
        $createMissing = (int)$this->option('create-missing') === 1;

        if (! file_exists($file)) {
            $this->error("No existe: {$file}");
            return self::FAILURE;
        }

        Excel::import(new RecibosImport(
            propietarioId: $propietarioId,
            capturadoPorUserId: $capturado,
            createMissing: $createMissing,
        ), $file);

        $this->info('Import terminado.');
        return self::SUCCESS;
    }
}
