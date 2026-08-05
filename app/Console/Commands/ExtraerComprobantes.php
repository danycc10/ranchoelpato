<?php

namespace App\Console\Commands;

use App\Models\ComprobantePago;
use App\Services\Clientes\ClientePorTelefonoService;
use App\Services\Comprobantes\ComprobanteExtractorService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Pasa por la IA los comprobantes que llegaron por WhatsApp y los deja listos
 * para revisión humana.
 *
 * Va aparte del webhook a propósito: leer una imagen tarda segundos y OpenWA
 * corta la entrega por timeout y reintenta. El webhook solo guarda; este comando
 * hace el trabajo caro, y se agenda por cron.
 */
class ExtraerComprobantes extends Command
{
    protected $signature = 'comprobantes:extraer
                            {--limit=10 : Máximo de comprobantes a procesar en esta corrida}
                            {--id= : Procesa únicamente el comprobante con este id}';

    protected $description = 'Lee con IA los comprobantes de pago recibidos por WhatsApp y los deja por validar';

    public function handle(
        ComprobanteExtractorService $extractor,
        ClientePorTelefonoService $clientesPorTelefono,
    ): int {
        if (! $extractor->estaConfigurado()) {
            $this->error('Falta OPENAI_API_KEY en el .env.');

            return self::FAILURE;
        }

        $query = ComprobantePago::query()->pendientesDeExtraer();

        if ($id = $this->option('id')) {
            $query = ComprobantePago::query()->whereKey($id);
        }

        $comprobantes = $query->orderBy('id')->limit((int) $this->option('limit'))->get();

        if ($comprobantes->isEmpty()) {
            $this->info('No hay comprobantes pendientes de leer.');

            return self::SUCCESS;
        }

        $ok = 0;
        $fallidos = 0;

        foreach ($comprobantes as $comprobante) {
            try {
                $this->procesar($comprobante, $extractor, $clientesPorTelefono);
                $ok++;
                $this->line("#{$comprobante->id} leído (confianza ".number_format((float) $comprobante->confianza, 2).')');
            } catch (\Throwable $e) {
                $fallidos++;

                $comprobante->estatus = ComprobantePago::ESTATUS_ERROR;
                $comprobante->error = mb_substr($e->getMessage(), 0, 1000);
                $comprobante->save();

                Log::error('Error leyendo comprobante de pago', [
                    'comprobante_id' => $comprobante->id,
                    'error' => $e->getMessage(),
                ]);

                $this->error("#{$comprobante->id}: {$e->getMessage()}");
            }
        }

        $this->info("Procesados: {$comprobantes->count()} (leídos: {$ok}, fallidos: {$fallidos})");

        return self::SUCCESS;
    }

    protected function procesar(
        ComprobantePago $comprobante,
        ComprobanteExtractorService $extractor,
        ClientePorTelefonoService $clientesPorTelefono,
    ): void {
        $contenido = $comprobante->contenidoArchivo();

        if ($contenido === null) {
            throw new \RuntimeException('El archivo del comprobante no está disponible en disco.');
        }

        $datos = $extractor->extraer($contenido, $comprobante->archivo_mime ?: 'image/jpeg');

        $comprobante->fill([
            'monto_detectado' => $datos['monto'],
            'fecha_detectada' => $datos['fecha'],
            'banco_detectado' => $datos['banco'],
            'referencia_detectada' => $datos['referencia'],
            'ultimos_digitos' => $datos['ultimos_digitos'],
            'confianza' => round($datos['confianza'] * 100, 2),
            'extraccion' => $datos,
            'extraccion_modelo' => $extractor->modelo(),
            'extraido_en' => now(),
            'error' => null,
            // Siempre queda esperando revisión: la IA propone, la persona decide.
            'estatus' => ComprobantePago::ESTATUS_POR_VALIDAR,
        ]);

        $this->vincularCliente($comprobante, $clientesPorTelefono);

        $comprobante->save();
    }

    /**
     * Intenta amarrar el comprobante a un cliente por su teléfono.
     *
     * Normaliza la lada de México (52/521) y conserva el prefijo 1 de EE. UU.
     * La relación solo se hace cuando existe una coincidencia única.
     *
     * El contrato solo se asigna cuando NO hay ambigüedad: si el cliente tiene
     * varios contratos vigentes, elegir uno sería adivinar, y esa decisión es del
     * cobrador en la pantalla de validación.
     */
    protected function vincularCliente(
        ComprobantePago $comprobante,
        ClientePorTelefonoService $clientesPorTelefono,
    ): void {
        $cliente = $clientesPorTelefono->buscarUnico($comprobante->telefono);

        if (! $cliente) {
            return;
        }

        $comprobante->cliente_id = $cliente->id;

        $contratos = $cliente->contratos()
            ->whereIn('estatus', ['activo', 'moroso'])
            ->limit(2)
            ->get();

        if ($contratos->count() === 1) {
            $comprobante->contrato_id = $contratos->first()->id;
        }
    }
}
