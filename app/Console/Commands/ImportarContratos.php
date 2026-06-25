<?php

namespace App\Console\Commands;

use App\Imports\ContratosImport;
use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;

class ImportarContratos extends Command
{
    protected $signature = 'contratos:import
                            {archivo : Ruta del archivo Excel}
                            {propietario_id : ID del propietario}';

    protected $description = 'Importa contratos desde archivo Excel';

    public function handle()
    {
        $archivo = $this->argument('archivo');
        $propietarioId = (int) $this->argument('propietario_id');

        if (! file_exists($archivo)) {
            $this->error("❌ El archivo no existe: {$archivo}");

            return 1;
        }

        $this->info('📥 Analizando archivo...');

        // ⚠️ Esto lee el sheet en memoria para contar. Si tu Excel es MUY grande, dímelo y te paso versión streaming.
        $sheet = Excel::toCollection(null, $archivo)[0];
        $totalRows = max(0, $sheet->count() - 1); // -1 por header

        $this->info("📊 Filas detectadas: {$totalRows}");
        $this->newLine();

        $bar = $this->output->createProgressBar($totalRows);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%%');
        $bar->start();

        $import = new ContratosImport(
            propietarioId: $propietarioId,
            capturadoPorUserId: null
        );

        // Registrar la barra en el container para que el import la use
        app()->instance('importProgressBar', $bar);

        Excel::import($import, $archivo);

        $bar->finish();
        $this->newLine(2);

        $this->info('✅ Importación finalizada.');

        return 0;
    }
}
