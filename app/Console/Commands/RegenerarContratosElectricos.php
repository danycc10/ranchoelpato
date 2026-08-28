<?php

namespace App\Console\Commands;

use App\Models\Contrato;
use App\Services\Contratos\ContratoWordService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Vuelve a generar el Word del contrato de financiamiento eléctrico de los
 * contratos de luz que ya existen.
 *
 * La descarga solo genera el documento cuando falta, así que al cambiar la
 * plantilla hay que regenerar los que ya estaban guardados.
 */
class RegenerarContratosElectricos extends Command
{
    protected $signature = 'contratos:regenerar-electricos {--uuid= : Regenera solo el contrato indicado}';

    protected $description = 'Regenera el Word del contrato de financiamiento eléctrico de los contratos de luz existentes.';

    public function handle(ContratoWordService $contratoWordService): int
    {
        $contratos = Contrato::query()
            ->where('tipo', 'servicio')
            ->where('servicio_tipo', 'electricidad')
            ->when($this->option('uuid'), fn ($query, $uuid) => $query->where('uuid', $uuid))
            ->orderBy('id')
            ->get();

        if ($contratos->isEmpty()) {
            $this->warn('No hay contratos de luz que regenerar.');

            return self::SUCCESS;
        }

        $regenerados = 0;
        $fallidos = 0;

        foreach ($contratos as $contrato) {
            try {
                $contrato->update([
                    'archivo_contrato_financiamiento_electrico_docx' => $contratoWordService->generarDocx(
                        $contrato,
                        'contrato_financiamiento_electrico'
                    ),
                    'archivo_contrato_disk' => $contrato->archivo_contrato_disk ?: 'private',
                ]);

                $regenerados++;
            } catch (Throwable $e) {
                $fallidos++;
                $this->error("Contrato {$contrato->folio_contrato}: {$e->getMessage()}");
            }
        }

        $this->info("Contratos regenerados: {$regenerados}.");

        return $fallidos > 0 ? self::FAILURE : self::SUCCESS;
    }
}
