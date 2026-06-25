<?php

namespace App\Console\Commands;

use App\Imports\ContratosDonacionImport;
use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;

class ImportarContratosDonacion extends Command
{
    protected $signature = 'contratos:import-donacion
                            {archivo : Ruta del archivo Excel}
                            {propietario_id : ID del propietario}';

    protected $description = 'Importa contratos informativos de donación desde archivo Excel';

    public function handle()
    {
        $archivo = $this->argument('archivo');
        $propietarioId = (int) $this->argument('propietario_id');

        if (! file_exists($archivo)) {
            $this->error("❌ El archivo no existe: {$archivo}");

            return 1;
        }

        if ($propietarioId <= 0) {
            $this->error('❌ propietario_id inválido.');

            return 1;
        }

        $this->info('📥 Importando informativos de donación...');

        Excel::import(
            new ContratosDonacionImport(
                propietarioId: $propietarioId,
                capturadoPorUserId: null
            ),
            $archivo
        );

        $this->info('✅ Importación de donación finalizada.');

        return 0;
    }
}
