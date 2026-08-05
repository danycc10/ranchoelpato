<?php

namespace App\Services\OpenAi;

use App\Models\OpenAiUso;
use Illuminate\Support\Facades\Log;
use Throwable;

class OpenAiUsageRecorder
{
    /**
     * Registra el uso que devuelve OpenAI. Un fallo del monitor nunca debe
     * impedir que continúe el bot o la lectura de un comprobante.
     */
    public function registrar(
        array $respuesta,
        string $operacion,
        string $endpoint,
        ?string $modeloSolicitado = null,
    ): ?OpenAiUso {
        try {
            $uso = $this->normalizarUso($respuesta['usage'] ?? null);

            if ($uso === null) {
                Log::warning('OpenAI respondió sin datos de uso de tokens.', [
                    'request_id' => $respuesta['id'] ?? null,
                    'modelo' => $respuesta['model'] ?? $modeloSolicitado,
                    'operacion' => $operacion,
                    'endpoint' => $endpoint,
                ]);

                return null;
            }

            $modelo = (string) ($respuesta['model'] ?? $modeloSolicitado ?? 'desconocido');
            $tarifa = $this->tarifaPara($modelo);
            $costo = $this->calcularCosto($uso, $tarifa);
            $requestId = isset($respuesta['id']) && is_string($respuesta['id'])
                ? $respuesta['id']
                : null;

            $atributos = [
                'modelo' => $modelo,
                'operacion' => $operacion,
                'endpoint' => $endpoint,
                ...$uso,
                'precio_entrada_millon' => $tarifa['input'] ?? null,
                'precio_cache_millon' => $tarifa['cached_input'] ?? null,
                'precio_salida_millon' => $tarifa['output'] ?? null,
                'costo_estimado_usd' => $costo,
                'consumido_en' => now(),
            ];

            $registro = $requestId
                ? OpenAiUso::firstOrCreate(['request_id' => $requestId], $atributos)
                : OpenAiUso::create(['request_id' => null, ...$atributos]);

            if ($registro->wasRecentlyCreated) {
                Log::info('Consumo de tokens de OpenAI registrado.', [
                    'openai_uso_id' => $registro->id,
                    'request_id' => $requestId,
                    'modelo' => $modelo,
                    'operacion' => $operacion,
                    'endpoint' => $endpoint,
                    'tokens_entrada' => $uso['tokens_entrada'],
                    'tokens_entrada_cache' => $uso['tokens_entrada_cache'],
                    'tokens_salida' => $uso['tokens_salida'],
                    'tokens_razonamiento' => $uso['tokens_razonamiento'],
                    'tokens_total' => $uso['tokens_total'],
                    'costo_estimado_usd' => $costo,
                ]);
            }

            return $registro;
        } catch (Throwable $e) {
            Log::error('No se pudo registrar el consumo de tokens de OpenAI.', [
                'request_id' => $respuesta['id'] ?? null,
                'operacion' => $operacion,
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Acepta tanto Responses API como Chat Completions API.
     *
     * @return array{tokens_entrada:int,tokens_entrada_cache:int,tokens_salida:int,tokens_razonamiento:int,tokens_total:int}|null
     */
    protected function normalizarUso(mixed $uso): ?array
    {
        if (! is_array($uso)) {
            return null;
        }

        $entrada = max(0, (int) ($uso['input_tokens'] ?? $uso['prompt_tokens'] ?? 0));
        $salida = max(0, (int) ($uso['output_tokens'] ?? $uso['completion_tokens'] ?? 0));
        $cache = max(0, (int) ($uso['input_tokens_details']['cached_tokens']
            ?? $uso['prompt_tokens_details']['cached_tokens']
            ?? 0));
        $razonamiento = max(0, (int) ($uso['output_tokens_details']['reasoning_tokens']
            ?? $uso['completion_tokens_details']['reasoning_tokens']
            ?? 0));

        $cache = min($cache, $entrada);
        $total = max(0, (int) ($uso['total_tokens'] ?? ($entrada + $salida)));

        return [
            'tokens_entrada' => $entrada,
            'tokens_entrada_cache' => $cache,
            'tokens_salida' => $salida,
            'tokens_razonamiento' => $razonamiento,
            'tokens_total' => $total,
        ];
    }

    /** @return array{input:float,cached_input:float,output:float}|null */
    protected function tarifaPara(string $modelo): ?array
    {
        $tarifas = config('services.openai.pricing', []);

        foreach ($tarifas as $nombre => $tarifa) {
            if ($modelo === $nombre || str_starts_with($modelo, $nombre.'-')) {
                return is_array($tarifa) ? $tarifa : null;
            }
        }

        return null;
    }

    /**
     * Los tokens de razonamiento ya forman parte de tokens_salida y no deben
     * cobrarse dos veces.
     */
    protected function calcularCosto(array $uso, ?array $tarifa): ?float
    {
        if ($tarifa === null) {
            return null;
        }

        $entradaSinCache = max(0, $uso['tokens_entrada'] - $uso['tokens_entrada_cache']);
        $costo = ($entradaSinCache * (float) $tarifa['input'])
            + ($uso['tokens_entrada_cache'] * (float) $tarifa['cached_input'])
            + ($uso['tokens_salida'] * (float) $tarifa['output']);

        return round($costo / 1_000_000, 10);
    }
}
