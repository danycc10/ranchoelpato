<?php

namespace App\Services\Contratos;

use App\Models\Contrato;
use PhpOffice\PhpWord\TemplateProcessor;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ContratoWordService
{
    public function generarDocx(Contrato $contrato): string
    {
        $contrato->loadMissing([
            'cliente',
            'lote.fraccionamiento',
        ]);

        $templatePath = $this->resolveTemplatePath($contrato);

        $template = new TemplateProcessor($templatePath);

        $template->setValue('vendedor_nombre', $this->safe($contrato->vendedor_nombre_legal));
        $template->setValue('vendedor_curp', $this->safe($contrato->vendedor_curp));
        $template->setValue('comprador_nombre', $this->safe($contrato->comprador_nombre_legal));
        $template->setValue('comprador_curp', $this->safe($contrato->comprador_curp));

        $template->setValue('fraccionamiento', $this->safe(optional($contrato->lote?->fraccionamiento)->nombre));
        $template->setValue('lote', $this->safe($contrato->lote?->lote));
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
        $template->setValue('monto_pago', $this->formatMoneyWithText($contrato->monto_pago));
        $template->setValue('valor_recargo', $this->formatMoneyWithText($contrato->valor_recargo));

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

        $this->setImageIfExists($template, 'vendedor_ine_frente', $contrato->vendedor_ine_frente);
        $this->setImageIfExists($template, 'vendedor_ine_reverso', $contrato->vendedor_ine_reverso);
        $this->setImageIfExists($template, 'comprador_ine_frente', $contrato->comprador_ine_frente);
        $this->setImageIfExists($template, 'comprador_ine_reverso', $contrato->comprador_ine_reverso);

        // ✅ Guardar como: contratos/fraccionamiento/lote/contrato-folio.docx
        $fraccionamientoNombre = $contrato->lote?->fraccionamiento?->nombre ?: 'sin-fraccionamiento';
        $fraccionamientoSlug = \Illuminate\Support\Str::slug($fraccionamientoNombre);

        $loteNombre = $contrato->lote?->lote
            ?: ($contrato->lote?->lote ? 'lote-' . $contrato->lote->lote : 'sin-lote');

        $loteSlug = \Illuminate\Support\Str::slug($loteNombre);
        $folioSlug = \Illuminate\Support\Str::slug((string) $contrato->folio_contrato);

        $relativePath = 'contratos/'
            . $fraccionamientoSlug
            . '/'
            . $loteSlug
            . '/'
            . $contrato->uuid
            . '/contrato-'
            . $folioSlug
            . '.docx';

        $absolutePath = Storage::disk('private')->path($relativePath);

        $dir = dirname($absolutePath);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $template->saveAs($absolutePath);

        return $relativePath;
    }

    protected function resolveTemplatePath(Contrato $contrato): string
    {
        $fraccionamiento = $contrato->lote?->fraccionamiento;

        if (
            $fraccionamiento &&
            ! empty($fraccionamiento->contrato_base_path) &&
            Storage::disk('private')->exists($fraccionamiento->contrato_base_path)
        ) {
            return Storage::disk('private')->path($fraccionamiento->contrato_base_path);
        }

        return Storage::disk('private')->path('plantillas/contrato_base.docx');
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

        return number_format($valor, 2) . " m ({$texto} metros)";
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

        return $textoEntero . ' punto ' . $textoDecimal;
    }

    protected function formatMoneyWithText($amount): string
    {
        $amount = (float) $amount;

        $formatted = '$' . number_format($amount, 2);

        $integer = (int) floor($amount);
        $text = $this->numeroATexto($integer);
        $text = mb_strtoupper($text, 'UTF-8');

        return $formatted . ' (' . $text . ' PESOS MEXICANOS)';
    }

    protected function setImageIfExists(TemplateProcessor $template, string $key, ?string $path): void
    {
        if (! $path) {
            return;
        }

        if (! Storage::disk('private')->exists($path)) {
            return;
        }

        $absolute = Storage::disk('private')->path($path);

        if (! file_exists($absolute)) {
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
