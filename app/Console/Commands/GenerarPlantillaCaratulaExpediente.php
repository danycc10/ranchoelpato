<?php

namespace App\Console\Commands;

use App\Services\Contratos\ContratoWordService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\TblWidth;

/**
 * Genera la plantilla de la carátula de la operación y el control interno del
 * expediente: lo que se quitó del contrato porque no se firma, pero que sí se
 * imprime para archivar y llevar el control.
 */
class GenerarPlantillaCaratulaExpediente extends Command
{
    protected $signature = 'contratos:plantilla-caratula {--force : Sobrescribe la plantilla si ya existe}';

    protected $description = 'Genera la plantilla Word de la carátula y el control interno del expediente.';

    private const FONT = ['name' => 'Arial', 'size' => 10];

    private const FONT_BOLD = ['name' => 'Arial', 'size' => 10, 'bold' => true];

    public function handle(): int
    {
        $documento = ContratoWordService::documentType('caratula_expediente');

        if (! $documento) {
            $this->error('No existe el tipo de documento caratula_expediente.');

            return self::FAILURE;
        }

        $relativePath = $documento['template'];
        $disk = Storage::disk('private');

        if ($disk->exists($relativePath) && ! $this->option('force')) {
            $this->warn("La plantilla ya existe en {$relativePath}. Usa --force para regenerarla.");

            return self::SUCCESS;
        }

        Settings::setOutputEscapingEnabled(true);

        $phpWord = new PhpWord;
        $phpWord->setDefaultFontName('Arial');
        $phpWord->setDefaultFontSize(10);

        $section = $phpWord->addSection([
            'pageSizeW' => 12240,
            'pageSizeH' => 15840,
            'marginTop' => 1134,
            'marginBottom' => 1134,
            'marginLeft' => 1134,
            'marginRight' => 1134,
        ]);

        $section->addFooter()->addPreserveText(
            'Uso interno - No forma parte del contrato - Página {PAGE}',
            ['name' => 'Arial', 'size' => 8, 'color' => '666666'],
            ['alignment' => Jc::CENTER]
        );

        $this->caratula($section);
        $this->controlInterno($section);
        $this->anexos($section);

        $absolutePath = $disk->path($relativePath);
        $dir = dirname($absolutePath);

        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        IOFactory::createWriter($phpWord, 'Word2007')->save($absolutePath);

        $this->info("Plantilla generada en {$relativePath}");

        return self::SUCCESS;
    }

    private function caratula(Section $section): void
    {
        $section->addText(
            'CARÁTULA DE DATOS DE LA OPERACIÓN',
            ['name' => 'Arial', 'size' => 12, 'bold' => true],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 60]
        );

        $section->addText(
            'Documento de uso interno. No forma parte del contrato ni se entrega al cliente.',
            ['name' => 'Arial', 'size' => 9, 'italic' => true, 'color' => '666666'],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 200]
        );

        $tabla = $this->tabla($section);

        $filas = [
            'NÚMERO DE CONTRATO' => '${folio_contrato}',
            'FRACCIONAMIENTO / PROYECTO' => '${fraccionamiento}',
            'NÚCLEO AGRARIO / EJIDO' => '${ejido}',
            'MUNICIPIO Y ESTADO' => '${municipio}, Coahuila de Zaragoza',
            'LOTE / MANZANA' => '${lote} / ${manzana}',
            'SUPERFICIE' => '${superficie}',
            'PARTE CEDENTE' => '${cedente_nombre}',
            'PARTE ADQUIRENTE' => '${adquirente_nombre}',
            'PRECIO TOTAL' => '${precio_total_numero} (${precio_total_letra})',
            'ENGANCHE' => '${enganche_numero}',
            'SALDO FINANCIADO' => '${saldo_financiado_numero}',
            'FORMA DE PAGO' => '${periodicidad}',
            'PAGO PERIÓDICO' => '${monto_pago_numero}',
            'PLAZO' => '${numero_pagos} pagos',
            'FECHA DE FIRMA' => '${fecha_firma_larga}',
        ];

        foreach ($filas as $concepto => $valor) {
            $fila = $tabla->addRow();
            $fila->addCell(3400)->addText($concepto, self::FONT_BOLD, ['spaceAfter' => 0]);
            $fila->addCell(6100)->addText($valor, self::FONT, ['spaceAfter' => 0]);
        }

        $section->addTextBreak(1, self::FONT);
    }

    private function controlInterno(Section $section): void
    {
        $section->addText(
            'CONTROL INTERNO DEL EXPEDIENTE',
            ['name' => 'Arial', 'size' => 11, 'bold' => true],
            ['alignment' => Jc::START, 'spaceBefore' => 180, 'spaceAfter' => 100]
        );

        $tabla = $this->tabla($section);

        $filas = [
            'ASESOR / VENDEDOR' => '${asesor}',
            'FECHA DE ALTA' => '${fecha_alta}',
            'EXPEDIENTE COMPLETO' => '${expediente_completo}',
            'PLANO FIRMADO' => '${plano_firmado}',
            'IDENTIFICACIONES' => '${identificaciones}',
            'ANTECEDENTE AGRARIO' => '${antecedente_agrario}',
            'OBSERVACIONES' => '${observaciones}',
        ];

        foreach ($filas as $concepto => $valor) {
            $fila = $tabla->addRow();
            $fila->addCell(3400)->addText($concepto, self::FONT_BOLD, ['spaceAfter' => 0]);
            $fila->addCell(6100)->addText($valor, self::FONT, ['spaceAfter' => 0]);
        }

        $section->addTextBreak(1, self::FONT);
    }

    /**
     * Checklist de los anexos del contrato, para palomear al archivar.
     */
    private function anexos(Section $section): void
    {
        $section->addText(
            'ANEXOS DEL EXPEDIENTE',
            ['name' => 'Arial', 'size' => 11, 'bold' => true],
            ['alignment' => Jc::START, 'spaceBefore' => 180, 'spaceAfter' => 100]
        );

        $tabla = $this->tabla($section);

        $encabezado = $tabla->addRow();
        $encabezado->addCell(7500)->addText('Documento', self::FONT_BOLD, ['spaceAfter' => 0]);
        $encabezado->addCell(2000)->addText('Integrado', self::FONT_BOLD, ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);

        $anexos = [
            'A. Identificación oficial de la parte cedente',
            'B. Identificación oficial de la parte adquirente',
            'C. CURP, RFC y comprobante de domicilio de ambas partes',
            'D. Documento del antecedente de los derechos',
            'E. Croquis o plano con el lote señalado',
            'F. Medidas, colindancias y coordenadas',
            'G. Tabla de pagos y calendario',
            'H. Situación jurídica del predio',
            'I. Estado de servicios e infraestructura',
            'J. Reglamento interno, si existe',
            'K. Recibos y comprobantes de pago',
            'L. Documentos agrarios adicionales',
        ];

        foreach ($anexos as $anexo) {
            $fila = $tabla->addRow();
            $fila->addCell(7500)->addText($anexo, self::FONT, ['spaceAfter' => 0]);
            $fila->addCell(2000)->addText('SÍ / NO', self::FONT, ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
        }
    }

    private function tabla(Section $section): Table
    {
        return $section->addTable([
            'borderSize' => 6,
            'borderColor' => '999999',
            'cellMargin' => 60,
            'width' => 100 * 50,
            'unit' => TblWidth::PERCENT,
        ]);
    }
}
