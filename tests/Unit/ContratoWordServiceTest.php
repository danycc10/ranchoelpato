<?php

namespace Tests\Unit;

use App\Services\Contratos\ContratoWordService;
use PHPUnit\Framework\TestCase;

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
}
