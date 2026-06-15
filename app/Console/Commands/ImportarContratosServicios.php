<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\ContratosServiciosImport;

class ImportarContratosServicios extends Command
{
    protected $signature = 'contratos:import-servicios
                            {archivo : Ruta del archivo Excel}
                            {propietario_id : ID del propietario}
                            {--capturado_por= : ID del usuario que captura}
                            {--recargo=0 : Recargo fijo default}
                            {--dias-gracia=0 : Días de gracia default}
                            {--backfill= : Fecha cutoff YYYY-MM-DD para marcar histórico pagado}';

    protected $description = 'Importa contratos de servicio (electricidad) desde archivo Excel';

    public function handle(): int
    {
        $archivo = $this->argument('archivo');
        $propietarioId = (int) $this->argument('propietario_id');

        if (! file_exists($archivo)) {
            $this->error("❌ El archivo no existe: {$archivo}");
            return self::FAILURE;
        }

        if ($propietarioId <= 0) {
            $this->error('❌ propietario_id inválido.');
            return self::FAILURE;
        }

        $capturadoPor = $this->option('capturado_por');
        $capturadoPor = $capturadoPor !== null && $capturadoPor !== ''
            ? (int) $capturadoPor
            : null;

        $recargo = (float) $this->option('recargo');
        $diasGracia = (int) $this->option('dias-gracia');
        $backfill = $this->option('backfill');

        $this->info('⚡ Iniciando importación de contratos de servicio...');
        $this->line("Archivo: {$archivo}");
        $this->line("Propietario ID: {$propietarioId}");
        $this->line("Capturado por: " . ($capturadoPor ?: 'null'));
        $this->line("Recargo default: {$recargo}");
        $this->line("Días de gracia default: {$diasGracia}");
        $this->line("Backfill: " . ($backfill ?: 'No'));

        try {
            Excel::import(
                new ContratosServiciosImport(
                    propietarioId: $propietarioId,
                    capturadoPorUserId: $capturadoPor,
                    recargoFijoDefault: $recargo,
                    diasGraciaDefault: $diasGracia,
                    backfillHistorico: filled($backfill),
                    backfillCutoff: filled($backfill) ? (string) $backfill : null,
                ),
                $archivo
            );

            $this->info('✅ Importación de contratos de servicio finalizada correctamente.');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('❌ Error durante la importación: ' . $e->getMessage());
            report($e);
            return self::FAILURE;
        }
    }
}