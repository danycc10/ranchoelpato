<?php

namespace App\Services\Contratos;

use App\Models\Contrato;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\TemplateProcessor;
use RuntimeException;

class ContratoWordService
{
    private const DOCUMENT_TYPES = [
        'contrato' => [
            'label' => 'Contrato',
            'template' => 'plantillas/contrato_base.docx',
            'docx_field' => 'archivo_contrato_docx',
            'scan_field' => 'archivo_contrato',
            'filename_slug' => 'contrato',
            'scan_filename' => 'contrato.pdf',
            'uses_fraccionamiento_template' => true,
        ],
        'constancia_terminacion_pago' => [
            'label' => 'Constancia de terminación de pago',
            'template' => 'plantillas/CONSTANCIA_DE_TERMINACION_DE_PAGO.docx',
            'docx_field' => 'archivo_constancia_terminacion_pago_docx',
            'scan_field' => 'archivo_constancia_terminacion_pago',
            'filename_slug' => 'constancia-terminacion-pago',
            'scan_filename' => 'constancia-terminacion-pago.pdf',
            'uses_fraccionamiento_template' => false,
        ],
        'convenio_pago' => [
            'label' => 'Convenio de pago',
            'template' => 'plantillas/CONVENIO_DE_PAGO_Mensual.docx',
            'templates_por_frecuencia' => [
                'semanal' => 'plantillas/CONVENIO_DE_PAGO_Semanal.docx',
                'mensual' => 'plantillas/CONVENIO_DE_PAGO_Mensual.docx',
            ],
            'docx_field' => 'archivo_convenio_pago_docx',
            'scan_field' => 'archivo_convenio_pago',
            'filename_slug' => 'convenio-pago',
            'scan_filename' => 'convenio-pago.pdf',
            'uses_fraccionamiento_template' => false,
        ],
        'convenio_pago_reconocimiento_adeudo' => [
            'label' => 'Convenio de pago y reconocimiento de adeudo',
            'template' => 'plantillas/CONVENIO_DE_PAGO_Y_RECONOCIMIENTO_DE_ADEUDO.docx',
            'docx_field' => 'archivo_convenio_pago_reconocimiento_adeudo_docx',
            'scan_field' => 'archivo_convenio_pago_reconocimiento_adeudo',
            'filename_slug' => 'convenio-pago-reconocimiento-adeudo',
            'scan_filename' => 'convenio-pago-reconocimiento-adeudo.pdf',
            'uses_fraccionamiento_template' => false,
        ],
    ];

    public static function documentTypes(): array
    {
        return collect(self::DOCUMENT_TYPES)
            ->map(fn (array $document, string $key) => ['key' => $key] + $document)
            ->all();
    }

    public static function documentType(string $key): ?array
    {
        $document = self::DOCUMENT_TYPES[$key] ?? null;

        return $document ? ['key' => $key] + $document : null;
    }

    public static function validDocumentTypeKeys(): array
    {
        return array_keys(self::DOCUMENT_TYPES);
    }

    public function generarDocx(Contrato $contrato, string $tipo = 'contrato'): string
    {
        $documento = self::documentType($tipo);

        if (! $documento) {
            throw new RuntimeException('Tipo de documento no válido.');
        }

        $contrato->loadMissing([
            'cliente',
            'lote.fraccionamiento',
        ]);

        $template = new TemplateProcessor(
            $this->resolveTemplatePath($contrato, $documento)
        );

        $this->setCommonValues($template, $contrato);

        $this->setImageIfExists($template, 'vendedor_ine_frente', $contrato->vendedor_ine_frente);
        $this->setImageIfExists($template, 'vendedor_ine_reverso', $contrato->vendedor_ine_reverso);
        $this->setImageIfExists($template, 'comprador_ine_frente', $contrato->comprador_ine_frente);
        $this->setImageIfExists($template, 'comprador_ine_reverso', $contrato->comprador_ine_reverso);

        $relativePath = $this->buildRelativePath($contrato, $documento);
        $absolutePath = Storage::disk('private')->path($relativePath);

        $dir = dirname($absolutePath);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $template->saveAs($absolutePath);

        return $relativePath;
    }

    public function generarTodosDocx(Contrato $contrato): array
    {
        $paths = [];

        foreach (self::documentTypes() as $key => $documento) {
            $paths[$documento['docx_field']] = $this->generarDocx($contrato, $key);
        }

        return $paths;
    }

    protected function setCommonValues(TemplateProcessor $template, Contrato $contrato): void
    {
        $template->setValue('folio_contrato', $this->safe($contrato->folio_contrato));

        $template->setValue('vendedor_nombre', $this->safe($contrato->vendedor_nombre_legal));
        $template->setValue('vendedor_nombre_legal', $this->safe($contrato->vendedor_nombre_legal));
        $template->setValue('vendedor_curp', $this->safe($contrato->vendedor_curp));
        $template->setValue('comprador_nombre', $this->safe($contrato->comprador_nombre_legal));
        $template->setValue('comprador_nombre_legal', $this->safe($contrato->comprador_nombre_legal));
        $template->setValue('comprador_curp', $this->safe($contrato->comprador_curp));

        $template->setValue('fraccionamiento', $this->safe(optional($contrato->lote?->fraccionamiento)->nombre));
        // Alias para una plantilla (CONSTANCIA) cuyo placeholder trae un typo: ${francionamiento}.
        $template->setValue('francionamiento', $this->safe(optional($contrato->lote?->fraccionamiento)->nombre));
        $template->setValue('lote', $this->safe($contrato->lote?->lote));
        $template->setValue('manzana', $this->safe($contrato->lote?->manzana));
        $template->setValue('clave_lote', $this->safe($contrato->lote?->clave));

        $template->setValue('area_m2', number_format((float) $contrato->area_m2_snapshot, 2));

        $template->setValue('medida_norte', $this->formatMedida($contrato->medida_norte_snapshot));
        $template->setValue('medida_sur', $this->formatMedida($contrato->medida_sur_snapshot));
        $template->setValue('medida_este', $this->formatMedida($contrato->medida_este_snapshot));
        $template->setValue('medida_oeste', $this->formatMedida($contrato->medida_oeste_snapshot));

        $template->setValue('colindancia_norte', $this->safe($contrato->colindancia_norte_snapshot));
        $template->setValue('colindancia_sur', $this->safe($contrato->colindancia_sur_snapshot));
        $template->setValue('colindancia_este', $this->safe($contrato->colindancia_este_snapshot));
        $template->setValue('colindancia_oeste', $this->safe($contrato->colindancia_oeste_snapshot));

        $template->setValue('precio_total', $this->formatMoneyWithText($contrato->precio_total));
        $template->setValue('enganche', $this->formatMoneyWithText($contrato->enganche));
        $template->setValue('saldo_inicial', $this->formatMoneyWithText($contrato->saldo_inicial));
        $template->setValue('saldo_actual', $this->formatMoneyWithText($contrato->saldo_actual));
        $template->setValue('monto_pago', $this->formatMoneyWithText($contrato->monto_pago));
        $template->setValue('valor_recargo', $this->formatMoneyWithText($contrato->valor_recargo));

        // Convenio de pago: deuda vencida y número de cuotas atrasadas a la fecha.
        [$deudaVencida, $numCuotasVencidas] = $this->calcularDeudaVencida($contrato);
        $template->setValue('deuda', $this->formatMoneyWithText($deudaVencida));
        $template->setValue('n_recibos', (string) $numCuotasVencidas);
        // La plantilla CONVENIO_DE_PAGO trae el placeholder con espacios: ${ n_recibos }.
        $template->setValue(' n_recibos ', (string) $numCuotasVencidas);

        $template->setValue(
            'frecuencia_texto',
            $contrato->frecuencia === 'semanal' ? 'semanales' : 'mensuales'
        );

        $template->setValue(
            'fecha_inicio',
            $contrato->fecha_inicio ? $contrato->fecha_inicio->format('d/m/Y') : ''
        );

        $template->setValue(
            'fecha_firma',
            mb_strtoupper(now()->translatedFormat('d \\d\\e F \\d\\e Y'), 'UTF-8')
        );

        $template->setValue('fecha_actual', now()->format('d/m/Y'));

        $template->setValue(
            'fecha_completa',
            now()->day.' del mes de '.now()->translatedFormat('F').' del año '.now()->year
        );

        $template->setValue(
            'fecha_hoy',
            now()->day.' de '.now()->translatedFormat('F').' del '.now()->year
        );
    }

    protected function buildRelativePath(Contrato $contrato, array $documento): string
    {
        $fraccionamientoNombre = $contrato->lote?->fraccionamiento?->nombre ?: 'sin-fraccionamiento';
        $fraccionamientoSlug = Str::slug($fraccionamientoNombre);

        $loteNombre = $contrato->lote?->lote
            ?: ($contrato->lote?->clave ?: 'sin-lote');

        $loteSlug = Str::slug($loteNombre);
        $folioSlug = Str::slug((string) ($contrato->folio_contrato ?: $contrato->uuid));

        return 'contratos/'
            .$fraccionamientoSlug
            .'/'
            .$loteSlug
            .'/'
            .$contrato->uuid
            .'/'
            .$documento['filename_slug']
            .'-'
            .$folioSlug
            .'.docx';
    }

    protected function resolveTemplatePath(Contrato $contrato, array $documento): string
    {
        $fraccionamiento = $contrato->lote?->fraccionamiento;

        if (
            ($documento['uses_fraccionamiento_template'] ?? false) &&
            $fraccionamiento &&
            ! empty($fraccionamiento->contrato_base_path) &&
            Storage::disk('private')->exists($fraccionamiento->contrato_base_path)
        ) {
            return Storage::disk('private')->path($fraccionamiento->contrato_base_path);
        }

        $template = (string) ($documento['template'] ?? '');

        // Algunos documentos tienen plantilla distinta por frecuencia (semanal/mensual).
        if (! empty($documento['templates_por_frecuencia'])) {
            $frecuencia = $contrato->frecuencia === 'semanal' ? 'semanal' : 'mensual';
            $template = (string) ($documento['templates_por_frecuencia'][$frecuencia] ?? $template);
        }

        if ($template === '' || ! Storage::disk('private')->exists($template)) {
            throw new RuntimeException('No se encontró la plantilla para '.($documento['label'] ?? 'el documento').'.');
        }

        return Storage::disk('private')->path($template);
    }

    /**
     * Deuda vencida y número de cuotas atrasadas a la fecha.
     * Considera cuotas con vencimiento hasta hoy y saldo pendiente
     * (monto - pagado - condonado) mayor a cero.
     *
     * @return array{0: float, 1: int} [deuda, numero_de_cuotas]
     */
    protected function calcularDeudaVencida(Contrato $contrato): array
    {
        $hoy = now()->toDateString();

        $cuotas = $contrato->cuotas()
            ->whereDate('fecha_vencimiento', '<=', $hoy)
            ->get(['monto', 'pagado_total', 'condonado_total']);

        $deuda = 0.0;
        $numero = 0;

        foreach ($cuotas as $cuota) {
            $pendiente = max(
                0,
                (float) $cuota->monto - (float) $cuota->pagado_total - (float) $cuota->condonado_total
            );

            if ($pendiente > 0) {
                $deuda += $pendiente;
                $numero++;
            }
        }

        return [round($deuda, 2), $numero];
    }

    protected function numeroATexto(int $numero): string
    {
        $formatter = new \NumberFormatter('es', \NumberFormatter::SPELLOUT);

        return (string) $formatter->format($numero);
    }

    protected function formatMedida($valor): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }

        $valor = (float) $valor;
        $texto = $this->numeroDecimalATexto($valor);

        return number_format($valor, 2)." m ({$texto} metros)";
    }

    protected function numeroDecimalATexto(float $numero): string
    {
        $entero = (int) floor($numero);
        $decimal = (int) round(($numero - $entero) * 100);

        $textoEntero = $this->numeroATexto($entero);

        if ($decimal <= 0) {
            return $textoEntero;
        }

        $textoDecimal = $this->numeroATexto($decimal);

        return $textoEntero.' punto '.$textoDecimal;
    }

    protected function formatMoneyWithText($amount): string
    {
        $amount = (float) $amount;

        $formatted = '$'.number_format($amount, 2);

        $integer = (int) floor($amount);
        $text = $this->numeroATexto($integer);
        $text = mb_strtoupper($text, 'UTF-8');

        return $formatted.' ('.$text.' PESOS MEXICANOS)';
    }

    protected function setImageIfExists(TemplateProcessor $template, string $key, ?string $path): void
    {
        $absolute = ($path && Storage::disk('private')->exists($path))
            ? Storage::disk('private')->path($path)
            : null;

        if (! $absolute || ! file_exists($absolute)) {
            // Sin imagen: limpiar el placeholder para que no quede ${...} literal.
            $template->setValue($key, '');

            return;
        }

        $template->setImageValue($key, [
            'path' => $absolute,
            'width' => 382,
            'height' => 243,
            'ratio' => false,
        ]);
    }

    protected function safe($value): string
    {
        return trim((string) $value);
    }
}
