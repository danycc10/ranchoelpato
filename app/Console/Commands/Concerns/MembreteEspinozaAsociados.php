<?php

namespace App\Console\Commands\Concerns;

use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\SimpleType\Jc;

/**
 * Membrete del despacho para los formatos que entregó: logo a la izquierda,
 * nombre y teléfono al centro y la marca de agua de logos en la esquina de
 * abajo a la derecha.
 */
trait MembreteEspinozaAsociados
{
    /**
     * La marca de agua mide lo mismo en cualquier hoja; lo que cambia es qué
     * tan abajo empieza, según lo alto del área de texto de la hoja (en pt).
     */
    protected function membrete(Section $section, float $altoAreaTexto): void
    {
        $header = $section->addHeader();

        $primera = $header->addTextRun(['alignment' => Jc::CENTER, 'spaceAfter' => 0]);

        // Va como imagen flotante detrás del texto y no con addWatermark(),
        // que mete un renglón vacío al encabezado y empuja el texto hacia abajo.
        $marcaAgua = resource_path('images/marca-agua-espinoza-asociados.png');

        if (is_file($marcaAgua)) {
            $primera->addImage($marcaAgua, [
                'width' => 558,
                'height' => 558,
                'pos' => 'absolute',
                'hPos' => 'absolute',
                'hPosRelTo' => 'margin',
                'left' => 38,
                'vPos' => 'absolute',
                'vPosRelTo' => 'margin',
                'top' => $altoAreaTexto - 446,
                'wrap' => 'behind',
            ]);
        }

        $logo = resource_path('images/logo-espinoza-asociados.jpg');

        if (is_file($logo)) {
            $primera->addImage($logo, [
                'width' => 66,
                'height' => 66,
                'pos' => 'absolute',
                'hPos' => 'left',
                'hPosRelTo' => 'margin',
                'vPos' => 'top',
                'vPosRelTo' => 'line',
                'wrap' => 'infront',
            ]);
        }

        $primera->addText('ESPINOZA & ASOCIADOS', ['name' => 'Arial', 'size' => 18, 'bold' => true]);

        $header->addText(
            'DESPACHO LEGAL',
            ['name' => 'Arial', 'size' => 18],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 0]
        );

        $header->addText(
            '(878)-785-1461',
            ['name' => 'Arial', 'size' => 14, 'bold' => true],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 240]
        );
    }
}
