<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\MembreteEspinozaAsociados;
use App\Services\Contratos\ContratoWordService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\SimpleType\Jc;

/**
 * Genera la plantilla de la carta con la que el titular autoriza a su pareja
 * a hacer los pagos de sus lotes.
 *
 * El texto es el que entregó el despacho. El titular, sus lotes y la finca se
 * llenan al descargarla; el nombre de la persona autorizada se escribe a mano.
 */
class GenerarPlantillaCartaAutorizacion extends Command
{
    use MembreteEspinozaAsociados;

    protected $signature = 'contratos:plantilla-carta-autorizacion {--force : Sobrescribe la plantilla si ya existe}';

    protected $description = 'Genera la plantilla Word de la carta de autorización de pagos.';

    private const FONT = ['name' => 'Arial', 'size' => 13];

    private const PARRAFO = ['alignment' => Jc::BOTH, 'spaceBefore' => 240, 'spaceAfter' => 240, 'lineHeight' => 1.15];

    public function handle(): int
    {
        $documento = ContratoWordService::documentType('carta_autorizacion');

        if (! $documento) {
            $this->error('No existe el tipo de documento carta_autorizacion.');

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
        $phpWord->setDefaultFontSize(13);

        // Hoja oficio, como el formato original.
        $section = $phpWord->addSection([
            'pageSizeW' => 12240,
            'pageSizeH' => 20160,
            'marginTop' => 1440,
            'marginBottom' => 1440,
            'marginLeft' => 1440,
            'marginRight' => 1440,
            'headerHeight' => 720,
        ]);

        // 14 in menos 2 in de márgenes = 864 pt de área de texto.
        $this->membrete($section, 864);
        $this->cuerpo($section);
        $this->firmas($section);

        $absolutePath = $disk->path($relativePath);
        $dir = dirname($absolutePath);

        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        IOFactory::createWriter($phpWord, 'Word2007')->save($absolutePath);

        $this->info("Plantilla generada en {$relativePath}");

        return self::SUCCESS;
    }

    private function cuerpo(Section $section): void
    {
        $section->addText(
            'CARTA DE AUTORIZACIÓN',
            ['name' => 'Arial', 'size' => 14, 'bold' => true],
            ['alignment' => Jc::CENTER, 'spaceBefore' => 480, 'spaceAfter' => 480]
        );

        $section->addText(
            'Piedras Negras, Coahuila. A ${fecha_hoy}',
            ['name' => 'Arial', 'size' => 14, 'bold' => true, 'italic' => true],
            ['alignment' => Jc::END, 'spaceAfter' => 480]
        );

        $section->addText('A QUIEN CORRESPONDA:', ['name' => 'Arial', 'size' => 12], self::PARRAFO);

        $autoriza = $section->addTextRun(self::PARRAFO);
        $autoriza->addText(
            'Por medio de la presente, yo, ${titular_nombre}, en mi carácter de titular ${lotes_titularidad}, '
            .'en Finca ${lotes_finca}, manifiesto que autorizo a ',
            self::FONT
        );
        $autoriza->addText('mi pareja', ['name' => 'Arial', 'size' => 13, 'underline' => 'single']);
        $autoriza->addText(
            ', ___________________________________, para que realice en mi nombre y representación los pagos '
            .'correspondientes a ${dichos_lotes} ante la administración de Espinoza & Asociados.',
            self::FONT
        );

        $section->addText(
            'Asimismo, autorizo que se reciban de su parte los pagos correspondientes y se le proporcionen '
            .'los comprobantes o recibos de pago respectivos.',
            self::FONT,
            self::PARRAFO
        );

        $section->addText(
            'La presente autorización se otorga de manera voluntaria y únicamente para los fines antes mencionados. '
            .'Cabe aclarar que esta autorización no le confiere derechos de propiedad, posesión ni facultades para '
            .'realizar modificaciones al contrato, traspasos o cualquier otro trámite relacionado con ${los_lotes}.',
            self::FONT,
            self::PARRAFO
        );

        $section->addText(
            'Sin más por el momento, firmo la presente para los efectos correspondientes.',
            self::FONT,
            self::PARRAFO
        );
    }

    private function firmas(Section $section): void
    {
        $this->firma($section, 'C. ${titular_nombre}');

        $section->addTextBreak(3, self::FONT);

        $this->firma($section, 'NOMBRE DE LA PERSONA AUTORIZADA');
    }

    private function firma(Section $section, string $nombre): void
    {
        $section->addTextBreak(2, self::FONT);

        $section->addText('FIRMA', self::FONT, ['alignment' => Jc::CENTER, 'spaceAfter' => 720]);

        $section->addText(
            '_______________________________',
            ['name' => 'Arial', 'size' => 12],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 0]
        );

        $section->addText($nombre, ['name' => 'Arial', 'size' => 12, 'bold' => true], ['alignment' => Jc::CENTER]);
    }
}
