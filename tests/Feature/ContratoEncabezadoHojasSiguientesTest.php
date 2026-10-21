<?php

namespace Tests\Feature;

use App\Services\Contratos\ContratoTemplateProcessor;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use ZipArchive;

/**
 * El identificador de las hojas siguientes del contrato.
 *
 * Una hoja suelta no dice de quién es: con el membrete solo, una pila de
 * contratos revueltos no se puede reordenar. La primera queda intacta porque
 * ahí va el detalle completo.
 *
 * Las plantillas reales no están versionadas, así que aquí se arma un .docx
 * con la misma estructura que usan: primera hoja aparte (`titlePg`) con su
 * propio encabezado.
 */
class ContratoEncabezadoHojasSiguientesTest extends TestCase
{
    private const IDENTIFICADOR = 'DORA CARDONA  ·  Finca Tres, Mz 1, Lote 7';

    private string $archivo = '';

    /** @var array<int, string> */
    private array $generados = [];

    protected function tearDown(): void
    {
        foreach ([...$this->generados, $this->archivo] as $ruta) {
            if ($ruta !== '' && is_file($ruta)) {
                unlink($ruta);
            }
        }

        parent::tearDown();
    }

    #[Test]
    public function el_identificador_va_en_las_hojas_siguientes_y_no_en_la_primera(): void
    {
        $plantilla = $this->docx(primeraHojaAparte: true);

        $procesador = new ContratoTemplateProcessor($plantilla);

        $this->assertTrue($procesador->identificarHojasSiguientes(self::IDENTIFICADOR));

        $generado = $this->guardar($procesador);

        // header1 es el "default": el de la segunda hoja en adelante.
        $this->assertStringContainsString(self::IDENTIFICADOR, $this->parte($generado, 'word/header1.xml'));

        // header2 es el "first": la hoja del detalle no se toca.
        $this->assertStringNotContainsString(self::IDENTIFICADOR, $this->parte($generado, 'word/header2.xml'));
    }

    #[Test]
    public function el_membrete_de_las_hojas_siguientes_se_conserva(): void
    {
        $procesador = new ContratoTemplateProcessor($this->docx(primeraHojaAparte: true));
        $procesador->identificarHojasSiguientes(self::IDENTIFICADOR);

        $encabezado = $this->parte($this->guardar($procesador), 'word/header1.xml');

        // Se agrega debajo del logo, no en su lugar.
        $this->assertStringContainsString('MEMBRETE', $encabezado);
        $this->assertStringContainsString('</w:hdr>', $encabezado);
    }

    #[Test]
    public function si_la_plantilla_no_separa_la_primera_hoja_no_se_toca_nada(): void
    {
        $plantilla = $this->docx(primeraHojaAparte: false);

        $procesador = new ContratoTemplateProcessor($plantilla);

        // Escribirlo ahí lo pondría también en la primera, que es lo que no
        // se quiere: mejor no tocar que poner el identificador de más.
        $this->assertFalse($procesador->identificarHojasSiguientes(self::IDENTIFICADOR));

        $encabezado = $this->parte($this->guardar($procesador), 'word/header1.xml');

        $this->assertStringNotContainsString(self::IDENTIFICADOR, $encabezado);
    }

    #[Test]
    public function un_identificador_vacio_no_deja_un_renglon_en_blanco(): void
    {
        $procesador = new ContratoTemplateProcessor($this->docx(primeraHojaAparte: true));

        $this->assertFalse($procesador->identificarHojasSiguientes('   '));
    }

    #[Test]
    public function los_acentos_y_simbolos_no_rompen_el_documento(): void
    {
        $procesador = new ContratoTemplateProcessor($this->docx(primeraHojaAparte: true));

        // Un XML mal escapado deja el .docx ilegible para Word.
        $procesador->identificarHojasSiguientes('AGUIÑAGA & ASOCIADOS <S.A.>  ·  Mz 1');

        $encabezado = $this->parte($this->guardar($procesador), 'word/header1.xml');

        $this->assertStringContainsString('AGUIÑAGA &amp; ASOCIADOS &lt;S.A.&gt;', $encabezado);
        $this->assertNotFalse(simplexml_load_string($encabezado), 'El encabezado dejó de ser XML válido.');
    }

    private function guardar(ContratoTemplateProcessor $procesador): string
    {
        $base = tempnam(sys_get_temp_dir(), 'docx');
        unlink($base);

        $destino = $base.'.docx';

        $procesador->saveAs($destino);

        $this->generados[] = $destino;

        return $destino;
    }

    private function parte(string $docx, string $nombre): string
    {
        $zip = new ZipArchive;
        $zip->open($docx);
        $contenido = (string) $zip->getFromName($nombre);
        $zip->close();

        return $contenido;
    }

    /** Un .docx mínimo con la misma estructura que las plantillas reales. */
    private function docx(bool $primeraHojaAparte): string
    {
        $base = tempnam(sys_get_temp_dir(), 'plantilla');
        unlink($base);

        $this->archivo = $base.'.docx';

        $ns = 'xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" '
            .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"';

        $seccion = '<w:headerReference w:type="default" r:id="rId7"/>';
        $relaciones = '<Relationship Id="rId7" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/header" Target="header1.xml"/>';
        $partes = ['word/header1.xml' => $this->encabezado('MEMBRETE')];

        if ($primeraHojaAparte) {
            $seccion .= '<w:headerReference w:type="first" r:id="rId9"/><w:titlePg/>';
            $relaciones .= '<Relationship Id="rId9" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/header" Target="header2.xml"/>';
            $partes['word/header2.xml'] = $this->encabezado('MEMBRETE PRIMERA');
        }

        $zip = new ZipArchive;
        $zip->open($this->archivo, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml',
            '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            .'<Override PartName="/word/header1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.header+xml"/>'
            .($primeraHojaAparte ? '<Override PartName="/word/header2.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.header+xml"/>' : '')
            .'</Types>'
        );

        $zip->addFromString('_rels/.rels',
            '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            .'</Relationships>'
        );

        $zip->addFromString('word/_rels/document.xml.rels',
            '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$relaciones.'</Relationships>'
        );

        $zip->addFromString('word/document.xml',
            '<?xml version="1.0" encoding="UTF-8"?>'
            .'<w:document '.$ns.'><w:body>'
            .'<w:p><w:r><w:t>DETALLE DEL CONTRATO</w:t></w:r></w:p>'
            .'<w:sectPr>'.$seccion.'</w:sectPr>'
            .'</w:body></w:document>'
        );

        foreach ($partes as $nombre => $contenido) {
            $zip->addFromString($nombre, $contenido);
        }

        $zip->close();

        return $this->archivo;
    }

    private function encabezado(string $texto): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<w:hdr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            .'<w:p><w:r><w:t>'.$texto.'</w:t></w:r></w:p>'
            .'</w:hdr>';
    }
}
