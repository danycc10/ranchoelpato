<?php

namespace App\Console\Commands;

use App\Models\Contrato;
use App\Services\Contratos\ContratoWordService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Vuelve a generar los Word de los contratos con la plantilla actual.
 *
 * Los documentos se guardan una sola vez y se reusan, así que al cambiar una
 * plantilla los que ya existían seguirían saliendo con el texto viejo. La
 * descarga se encarga sola de los que alguien abra, pero esto los refresca
 * todos de golpe sin esperar a que se descarguen.
 */
class RegenerarDocumentosContratos extends Command
{
    protected $signature = 'contratos:regenerar-documentos
        {--tipo=* : Solo estos formatos (por omisión, todos los que apliquen)}
        {--uuid= : Solo este contrato}
        {--fraccionamiento= : Solo los contratos de este fraccionamiento (id)}
        {--todos : Regenera también los que están al día}
        {--dry-run : Solo dice qué haría, sin escribir}';

    protected $description = 'Regenera los documentos Word de los contratos con la plantilla actual.';

    public function handle(ContratoWordService $word): int
    {
        $tipos = array_filter((array) $this->option('tipo'));
        $simulacion = (bool) $this->option('dry-run');
        $todos = (bool) $this->option('todos');

        foreach ($tipos as $tipo) {
            if (! ContratoWordService::documentType($tipo)) {
                $this->error("Formato desconocido: {$tipo}");
                $this->line('Disponibles: '.implode(', ', ContratoWordService::validDocumentTypeKeys()));

                return self::FAILURE;
            }
        }

        $contratos = Contrato::query()
            ->with('lote.fraccionamiento')
            ->when($this->option('uuid'), fn ($q, $uuid) => $q->where('uuid', $uuid))
            ->when($this->option('fraccionamiento'), fn ($q, $id) => $q->whereHas(
                'lote',
                fn ($l) => $l->where('fraccionamiento_id', $id)
            ))
            ->orderBy('id')
            ->get();

        if ($contratos->isEmpty()) {
            $this->warn('No hay contratos que revisar.');

            return self::SUCCESS;
        }

        $regenerados = 0;
        $alDia = 0;
        $sinPlantilla = 0;
        $fallidos = 0;

        foreach ($contratos as $contrato) {
            $formatos = ContratoWordService::documentTypesFor($contrato);

            foreach ($formatos as $tipo => $documento) {
                if ($tipos && ! in_array($tipo, $tipos, true)) {
                    continue;
                }

                // Sin documento previo no hay nada que actualizar: se genera
                // cuando alguien lo descargue.
                if (! $todos && ! $contrato->{$documento['docx_field']}) {
                    continue;
                }

                if (! $todos && ! $word->estaDesactualizado($contrato, $tipo)) {
                    $alDia++;

                    continue;
                }

                if ($simulacion) {
                    $this->line("  regeneraría {$contrato->folio_contrato} · {$tipo}");
                    $regenerados++;

                    continue;
                }

                try {
                    $contrato->update([
                        $documento['docx_field'] => $word->generarDocx($contrato, $tipo),
                        'archivo_contrato_disk' => $contrato->archivo_contrato_disk ?: 'private',
                    ]);

                    $regenerados++;
                } catch (Throwable $e) {
                    // Una plantilla que no está instalada no es una falla: ese
                    // formato simplemente no aplica todavía.
                    if (str_contains($e->getMessage(), 'No se encontró la plantilla')) {
                        $sinPlantilla++;

                        continue;
                    }

                    $fallidos++;
                    $this->error("{$contrato->folio_contrato} · {$tipo}: {$e->getMessage()}");
                }
            }
        }

        $this->newLine();
        $this->info(($simulacion ? 'Se regenerarían' : 'Regenerados').": {$regenerados}");
        $this->line("Ya estaban al día: {$alDia}");

        if ($sinPlantilla > 0) {
            $this->line("Sin plantilla instalada: {$sinPlantilla}");
        }

        if ($fallidos > 0) {
            $this->warn("Con error: {$fallidos}");
        }

        return $fallidos > 0 ? self::FAILURE : self::SUCCESS;
    }
}
