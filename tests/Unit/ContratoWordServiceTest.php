<?php

namespace Tests\Unit;

use App\Services\Contratos\ContratoWordService;
use PHPUnit\Framework\TestCase;
use ZipArchive;

class ContratoWordServiceTest extends TestCase
{
    public function test_expone_los_tipos_de_documentos_soportados(): void
    {
        $this->assertSame(
            [
                'contrato',
                'constancia_terminacion_pago',
                'convenio_pago',
                'convenio_pago_reconocimiento_adeudo',
                'contrato_financiamiento_electrico',
            ],
            ContratoWordService::validDocumentTypeKeys()
        );
    }

    public function test_resuelve_la_configuracion_de_un_tipo_de_documento(): void
    {
        $documento = ContratoWordService::documentType('convenio_pago');

        $this->assertIsArray($documento);
        $this->assertSame('convenio_pago', $documento['key']);
        $this->assertSame('archivo_convenio_pago_docx', $documento['docx_field']);
        $this->assertSame('archivo_convenio_pago', $documento['scan_field']);
        $this->assertArrayHasKey('templates_por_frecuencia', $documento);
    }

    public function test_rechaza_tipos_de_documento_desconocidos(): void
    {
        $this->assertNull(ContratoWordService::documentType('documento_inexistente'));
    }

    public function test_limpia_autor_y_ultimo_editor_de_los_documentos_word(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'contrato-word-');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString(
            'docProps/core.xml',
            '<?xml version="1.0" encoding="UTF-8"?>'
            .'<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" '
            .'xmlns:dc="http://purl.org/dc/elements/1.1/">'
            .'<dc:creator>Daniel Casas</dc:creator>'
            .'<cp:lastModifiedBy>daniel de la cruz casas</cp:lastModifiedBy>'
            .'<cp:revision>8</cp:revision>'
            .'</cp:coreProperties>'
        );
        $zip->close();

        try {
            (new ContratoWordService)->limpiarMetadatosPersonales($path);

            $zip->open($path);
            $coreXml = $zip->getFromName('docProps/core.xml');
            $zip->close();

            $this->assertStringNotContainsStringIgnoringCase('daniel', $coreXml);
            $this->assertStringNotContainsString('dc:creator', $coreXml);
            $this->assertStringNotContainsString('cp:lastModifiedBy', $coreXml);
            $this->assertStringContainsString('<cp:revision>8</cp:revision>', $coreXml);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}
