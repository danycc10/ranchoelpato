<?php

namespace App\Services\Comprobantes;

use App\Services\OpenAi\OpenAiUsageRecorder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use RuntimeException;

/**
 * Lee un comprobante de pago (imagen) con OpenAI y devuelve datos estructurados.
 *
 * Lo que devuelve es una PROPUESTA para que un cobrador la revise, no un hecho
 * contable. El servicio nunca escribe en pagos ni recibos.
 *
 * Se usa Structured Outputs (json_schema con strict) en vez de pedir "responde
 * en JSON": así el modelo no puede devolver prosa ni campos inventados, y no hay
 * que parsear texto libre con expresiones regulares.
 */
class ComprobanteExtractorService
{
    protected ?string $apiKey;

    protected string $baseUrl;

    protected string $model;

    protected int $maxLadoPx;

    protected int $timeout;

    protected OpenAiUsageRecorder $usageRecorder;

    public function __construct(?array $config = null, ?OpenAiUsageRecorder $usageRecorder = null)
    {
        $config ??= config('services.openai', []);

        $this->apiKey = $config['api_key'] ?? null;
        $this->baseUrl = rtrim((string) ($config['base_url'] ?? 'https://api.openai.com/v1'), '/');
        $this->model = (string) ($config['model'] ?? 'gpt-4o-mini');
        $this->maxLadoPx = max(256, (int) ($config['max_lado_px'] ?? 1568));
        $this->timeout = max(10, (int) ($config['timeout'] ?? 90));
        $this->usageRecorder = $usageRecorder ?? app(OpenAiUsageRecorder::class);
    }

    public function estaConfigurado(): bool
    {
        return ! empty($this->apiKey);
    }

    public function modelo(): string
    {
        return $this->model;
    }

    /**
     * Extrae los datos de un comprobante.
     *
     * @param  string  $contenido  Bytes crudos de la imagen.
     * @return array{es_comprobante:bool,monto:?float,fecha:?string,banco:?string,referencia:?string,ultimos_digitos:?string,beneficiario:?string,confianza:float,notas:?string}
     */
    public function extraer(string $contenido, string $mime = 'image/jpeg'): array
    {
        if (! $this->estaConfigurado()) {
            throw new RuntimeException('Falta OPENAI_API_KEY en el .env; no se puede leer el comprobante.');
        }

        if ($contenido === '') {
            throw new RuntimeException('El comprobante no tiene contenido que leer.');
        }

        [$contenido, $mime] = $this->reducir($contenido, $mime);

        $respuesta = Http::withToken($this->apiKey)
            ->timeout($this->timeout)
            ->acceptJson()
            ->asJson()
            ->post($this->baseUrl.'/chat/completions', [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => $this->instrucciones()],
                    ['role' => 'user', 'content' => [
                        ['type' => 'text', 'text' => 'Extrae los datos de este comprobante de pago.'],
                        ['type' => 'image_url', 'image_url' => [
                            'url' => 'data:'.$mime.';base64,'.base64_encode($contenido),
                            'detail' => 'high',
                        ]],
                    ]],
                ],
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => [
                        'name' => 'comprobante_pago',
                        'strict' => true,
                        'schema' => $this->esquema(),
                    ],
                ],
            ]);

        if ($respuesta->failed()) {
            $cuerpo = $respuesta->json();
            $detalle = $cuerpo['error']['message'] ?? 'HTTP '.$respuesta->status();

            throw new RuntimeException('OpenAI rechazó la lectura del comprobante: '.$detalle);
        }

        $cuerpo = $respuesta->json();
        $this->usageRecorder->registrar($cuerpo, 'comprobante_extraccion', 'chat_completions', $this->model);

        $json = data_get($cuerpo, 'choices.0.message.content');

        if (! is_string($json) || $json === '') {
            throw new RuntimeException('OpenAI no devolvió contenido para el comprobante.');
        }

        $datos = json_decode($json, true);

        if (! is_array($datos)) {
            throw new RuntimeException('La respuesta de OpenAI no es un JSON válido.');
        }

        return $this->normalizar($datos);
    }

    /**
     * Reduce la imagen antes de subirla.
     *
     * Una foto de celular a resolución completa cuesta bastantes más tokens sin
     * mejorar la lectura de un comprobante, que es texto grande sobre fondo liso.
     * Si la reducción falla (formato raro, PDF), se manda el original.
     */
    protected function reducir(string $contenido, string $mime): array
    {
        if (! str_starts_with($mime, 'image/')) {
            return [$contenido, $mime];
        }

        try {
            $imagen = (new ImageManager(new Driver))->read($contenido);

            if (max($imagen->width(), $imagen->height()) > $this->maxLadoPx) {
                $imagen->scaleDown($this->maxLadoPx, $this->maxLadoPx);
            }

            return [(string) $imagen->toJpeg(82), 'image/jpeg'];
        } catch (\Throwable $e) {
            return [$contenido, $mime];
        }
    }

    protected function instrucciones(): string
    {
        return <<<'TXT'
        Eres un asistente que lee comprobantes de pago mexicanos: transferencias
        SPEI, depósitos bancarios, pagos en OXXO y capturas de banca en línea.

        Reglas:
        - Transcribe SOLO lo que se ve en la imagen. No adivines ni completes
          datos que no aparecen: si un dato no está visible, devuélvelo como null.
        - "monto" es la cantidad pagada, como número, sin símbolo ni comas.
          Si aparecen varias cifras, toma la del importe de la operación, no el
          saldo de la cuenta ni la comisión.
        - "fecha" es la fecha de la operación en formato YYYY-MM-DD.
        - "ultimos_digitos" son los últimos dígitos visibles de la cuenta o tarjeta
          destino (normalmente 4).
        - "referencia" es el folio, clave de rastreo o número de operación.
        - "beneficiario" es a quién se le pagó, si aparece.
        - "confianza" va de 0 a 1 y refleja qué tan legible está la imagen y qué
          tan seguro estás del monto. Si la foto está borrosa, cortada o no es un
          comprobante, usa un valor bajo.
        - Si la imagen no es un comprobante de pago, pon es_comprobante en false
          y todo lo demás en null.
        TXT;
    }

    /**
     * Esquema estricto. OpenAI exige que TODAS las propiedades estén en required
     * y additionalProperties en false; los campos opcionales se modelan como
     * uniones con "null".
     */
    protected function esquema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => [
                'es_comprobante', 'monto', 'fecha', 'banco',
                'referencia', 'ultimos_digitos', 'beneficiario', 'confianza', 'notas',
            ],
            'properties' => [
                'es_comprobante' => ['type' => 'boolean'],
                'monto' => ['type' => ['number', 'null']],
                'fecha' => ['type' => ['string', 'null'], 'description' => 'YYYY-MM-DD'],
                'banco' => ['type' => ['string', 'null']],
                'referencia' => ['type' => ['string', 'null']],
                'ultimos_digitos' => ['type' => ['string', 'null']],
                'beneficiario' => ['type' => ['string', 'null']],
                'confianza' => ['type' => 'number'],
                'notas' => ['type' => ['string', 'null']],
            ],
        ];
    }

    protected function normalizar(array $d): array
    {
        return [
            'es_comprobante' => (bool) ($d['es_comprobante'] ?? false),
            'monto' => isset($d['monto']) && is_numeric($d['monto']) ? round((float) $d['monto'], 2) : null,
            'fecha' => $this->fecha($d['fecha'] ?? null),
            'banco' => $this->texto($d['banco'] ?? null),
            'referencia' => $this->texto($d['referencia'] ?? null),
            'ultimos_digitos' => $this->digitos($d['ultimos_digitos'] ?? null),
            'beneficiario' => $this->texto($d['beneficiario'] ?? null),
            'confianza' => max(0.0, min(1.0, (float) ($d['confianza'] ?? 0))),
            'notas' => $this->texto($d['notas'] ?? null),
        ];
    }

    protected function texto(mixed $v): ?string
    {
        if (! is_string($v)) {
            return null;
        }

        $v = trim($v);

        return $v !== '' ? mb_substr($v, 0, 255) : null;
    }

    protected function digitos(mixed $v): ?string
    {
        if (! is_string($v)) {
            return null;
        }

        $v = preg_replace('/\D+/', '', $v) ?? '';

        return $v !== '' ? mb_substr($v, -8) : null;
    }

    protected function fecha(mixed $v): ?string
    {
        if (! is_string($v) || trim($v) === '') {
            return null;
        }

        try {
            return Carbon::parse($v)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
