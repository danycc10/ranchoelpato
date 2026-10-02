<?php

namespace App\Services\Contratos;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\TemplateProcessor;

class ContratoTemplateProcessor extends TemplateProcessor
{
    /** Añade páginas sin alterar la plantilla original ni sus estilos y secciones. */
    public function agregarAnexosIdentificacion(array $anexos): void
    {
        $xml = new DOMDocument;
        $xml->loadXML($this->tempDocumentMainPart);
        $xpath = new DOMXPath($xml);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $body = $xpath->query('/w:document/w:body')->item(0);
        $seccion = $xpath->query('./w:sectPr', $body)->item(0);

        foreach ($anexos as $letra => $anexo) {
            $parte = $letra === 'A' ? 'PARTE CEDENTE' : 'PARTE ADQUIRENTE';
            $parrafos = [
                // Reserva espacio bajo el membrete, también al comenzar una página.
                '<w:p xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:pPr><w:pageBreakBefore/><w:keepNext/><w:spacing w:before="0" w:after="0" w:line="720" w:lineRule="exact"/></w:pPr><w:r><w:t xml:space="preserve"> </w:t></w:r></w:p>',
                $this->parrafo('ANEXO '.$letra.' IDENTIFICACIÓN DE LA '.$parte, true),
                $this->parrafo('Contrato: '.$anexo['folio']),
                $this->parrafo('Proyecto: '.$anexo['proyecto'].' · Manzana: '.$anexo['manzana'].' · Lote: '.$anexo['lote']),
                $this->parrafo('Nombre: '.$anexo['nombre']),
                $this->parrafo('Identificación: '.$anexo['tipo'].' · Número: '.($anexo['numero'] ?: 'No capturado')),
            ];

            if ($anexo['faltantes']) {
                $parrafos[] = $this->parrafo('ANEXO PENDIENTE: falta cargar o revisar una imagen válida del '.implode(' y ', $anexo['faltantes']).'.', true);
                $parrafos[] = $this->parrafo('Expediente incompleto. Integre la identificación antes de la firma.');
            } else {
                foreach ($anexo['imagenes'] as $lado => $imagen) {
                    if ($imagen['path']) {
                        $parrafos[] = $this->parrafo(ucfirst($lado), true);
                        $parrafos[] = $this->parrafo('${anexo_'.$letra.'_'.$lado.'}');
                    }
                }
            }

            foreach ($parrafos as $parrafo) {
                $fragmento = $xml->createDocumentFragment();
                $fragmento->appendXML($parrafo);
                $body->insertBefore($fragmento, $seccion);
            }
        }

        $this->tempDocumentMainPart = $xml->saveXML($xml->documentElement);
        foreach ($anexos as $letra => $anexo) {
            if ($anexo['faltantes']) {
                continue;
            }
            foreach ($anexo['imagenes'] as $lado => $imagen) {
                if ($imagen['path']) {
                    $this->setImageValue('anexo_'.$letra.'_'.$lado, [
                        'path' => Storage::disk($imagen['disk'])->path($imagen['path']),
                        'width' => 440,
                        'height' => 220,
                        'ratio' => true,
                    ]);
                }
            }
        }
    }

    private function parrafo(string $texto, bool $negrita = false): string
    {
        return '<w:p xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:pPr>'
            .'<w:spacing w:after="160"/><w:jc w:val="center"/></w:pPr><w:r><w:rPr>'
            .'<w:rFonts w:ascii="Arial" w:hAnsi="Arial"/><w:sz w:val="20"/>'
            .($negrita ? '<w:b/>' : '').'</w:rPr><w:t xml:space="preserve">'
            .htmlspecialchars($texto, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</w:t></w:r></w:p>';
    }
}
