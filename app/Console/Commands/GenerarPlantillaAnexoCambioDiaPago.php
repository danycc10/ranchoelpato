<?php

namespace App\Console\Commands;

use App\Services\Contratos\ContratoWordService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\SimpleType\Jc;

/**
 * Genera la plantilla del anexo que firma el cliente cuando pide cambiar su
 * día de pago: el día nuevo y el recargo de $50.00 por cada día de atraso.
 *
 * El texto es el que entregó el despacho. Lo que cambia por contrato (fecha,
 * día de pago y quien firma) se llena al descargarlo.
 */
class GenerarPlantillaAnexoCambioDiaPago extends Command
{
    protected $signature = 'contratos:plantilla-anexo-dia-pago {--force : Sobrescribe la plantilla si ya existe}';

    protected $description = 'Genera la plantilla Word del anexo de cambio de día de pago y recargos por atraso.';

    private const FONT = ['name' => 'Arial', 'size' => 12];

    private const FONT_BOLD = ['name' => 'Arial', 'size' => 12, 'bold' => true];

    private const PARRAFO = ['alignment' => Jc::BOTH, 'spaceAfter' => 240, 'lineHeight' => 1.15];

    public function handle(): int
    {
        $documento = ContratoWordService::documentType('anexo_cambio_dia_pago');

        if (! $documento) {
            $this->error('No existe el tipo de documento anexo_cambio_dia_pago.');

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
        $phpWord->setDefaultFontSize(12);

        $section = $phpWord->addSection([
            'pageSizeW' => 12240,
            'pageSizeH' => 15840,
            'marginTop' => 1440,
            'marginBottom' => 1440,
            'marginLeft' => 1440,
            'marginRight' => 1440,
            'headerHeight' => 720,
        ]);

        $this->membrete($section);
        $this->cuerpo($section);
        $this->firma($section);

        $absolutePath = $disk->path($relativePath);
        $dir = dirname($absolutePath);

        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        IOFactory::createWriter($phpWord, 'Word2007')->save($absolutePath);

        $this->info("Plantilla generada en {$relativePath}");

        return self::SUCCESS;
    }

    /**
     * El membrete del despacho, igual al de los convenios de pago.
     */
    private function membrete(Section $section): void
    {
        $header = $section->addHeader();
        $logo = resource_path('images/logo-espinoza-asociados.jpg');

        $primera = $header->addTextRun(['alignment' => Jc::CENTER, 'spaceAfter' => 0]);

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

    private function cuerpo(Section $section): void
    {
        $section->addTextBreak(1, self::FONT);

        $section->addText(
            'ANEXO AL CONTRATO',
            ['name' => 'Arial', 'size' => 12, 'bold' => true],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 120]
        );

        $section->addText(
            'MODIFICACIÓN DE DÍA DE PAGO Y RECARGOS POR ATRASO',
            ['name' => 'Arial', 'size' => 9, 'bold' => true],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 480]
        );

        $section->addText(
            'Piedras Negras, Coahuila. A ${fecha_hoy}',
            ['name' => 'Arial', 'size' => 12, 'bold' => true, 'italic' => true],
            ['alignment' => Jc::END, 'spaceAfter' => 480]
        );

        $section->addText(
            'Por medio del presente anexo, se hace constar que el cliente solicita voluntariamente el cambio '
            .'del día establecido para realizar sus pagos, por lo que ambas partes acuerdan las siguientes condiciones:',
            self::FONT,
            self::PARRAFO
        );

        $primera = $section->addTextRun(self::PARRAFO);
        $primera->addText('PRIMERA. CAMBIO DE DÍA DE PAGO.', self::FONT_BOLD);
        $primera->addText(' A solicitud del cliente, se establece como nuevo día de pago el día ', self::FONT);
        $primera->addText('${nuevo_dia_pago}', ['name' => 'Arial', 'size' => 12, 'bold' => true, 'underline' => 'single']);
        $primera->addText(
            ' de cada ${periodo_pago}, comprometiéndose a realizar puntualmente sus pagos en la fecha acordada.',
            self::FONT
        );

        $segunda = $section->addTextRun(self::PARRAFO);
        $segunda->addText('SEGUNDA. RECARGO POR ATRASO.', self::FONT_BOLD);
        $segunda->addText(
            ' En caso de que el cliente no realice el pago correspondiente en la fecha establecida, se aplicará un recargo de ',
            self::FONT
        );
        $segunda->addText(
            '$50.00 (cincuenta pesos 00/100 M.N.) por cada día natural de atraso',
            self::FONT_BOLD
        );
        $segunda->addText(
            ', comenzando a contabilizarse a partir del día siguiente a la fecha de pago acordada y hasta que se liquide el pago pendiente.',
            self::FONT
        );

        $tercera = $section->addTextRun(self::PARRAFO);
        $tercera->addText('TERCERA. ACEPTACIÓN DE LAS CONDICIONES.', self::FONT_BOLD);
        $tercera->addText(
            ' El cliente manifiesta estar enterado y de acuerdo con las condiciones establecidas en el presente anexo, '
            .'reconociendo que el cambio de día de pago no modifica ni elimina las demás obligaciones y responsabilidades '
            .'estipuladas en el contrato original.',
            self::FONT
        );

        $section->addText(
            'Leído el presente anexo y estando de acuerdo con su contenido, se firma de conformidad.',
            self::FONT,
            self::PARRAFO
        );
    }

    private function firma(Section $section): void
    {
        $section->addTextBreak(3, self::FONT);

        $section->addText('FIRMA', self::FONT, ['alignment' => Jc::CENTER, 'spaceAfter' => 720]);

        $section->addText(
            '_______________________________',
            self::FONT,
            ['alignment' => Jc::CENTER, 'spaceAfter' => 0]
        );

        $section->addText(
            'C. ${firmante_nombre}',
            ['name' => 'Arial', 'size' => 11, 'bold' => true],
            ['alignment' => Jc::CENTER]
        );
    }
}
