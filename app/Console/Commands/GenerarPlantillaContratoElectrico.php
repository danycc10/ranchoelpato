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
use PhpOffice\PhpWord\SimpleType\TblWidth;

/**
 * Genera la plantilla Word del contrato de financiamiento de infraestructura
 * eléctrica con los placeholders ${...} que llena ContratoWordService.
 *
 * La carpeta storage/app/private no está versionada, por eso la plantilla se
 * construye desde código y puede regenerarse en cualquier ambiente.
 */
class GenerarPlantillaContratoElectrico extends Command
{
    protected $signature = 'contratos:plantilla-electrica {--force : Sobrescribe la plantilla si ya existe}';

    protected $description = 'Genera la plantilla Word del contrato de financiamiento de infraestructura eléctrica.';

    private const FONT = ['name' => 'Arial', 'size' => 10];

    private const FONT_BOLD = ['name' => 'Arial', 'size' => 10, 'bold' => true];

    private const PAR = ['alignment' => Jc::BOTH, 'spaceAfter' => 120];

    private const PAR_CAMPO = ['alignment' => Jc::START, 'spaceAfter' => 20];

    public function handle(): int
    {
        $documento = ContratoWordService::documentType('contrato_financiamiento_electrico');

        if (! $documento) {
            $this->error('No existe el tipo de documento contrato_financiamiento_electrico.');

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
            'Contrato de Financiamiento de Infraestructura Eléctrica - Página {PAGE}',
            ['name' => 'Arial', 'size' => 8, 'color' => '666666'],
            ['alignment' => Jc::CENTER]
        );

        $this->encabezado($section);
        $this->declaraciones($section);
        $this->clausulas($section);
        $this->tablaFinanciamiento($section);
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

    private function encabezado(Section $section): void
    {
        $section->addText(
            'CONTRATO DE FINANCIAMIENTO PARA APORTACIÓN E INSTALACIÓN DE INFRAESTRUCTURA ELÉCTRICA',
            ['name' => 'Arial', 'size' => 12, 'bold' => true],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 240]
        );

        $run = $section->addTextRun(self::PAR);
        $run->addText(
            'CONTRATO DE RECONOCIMIENTO DE ADEUDO Y FINANCIAMIENTO PARA LA APORTACIÓN, GESTIÓN E '
            .'INSTALACIÓN DE INFRAESTRUCTURA ELÉCTRICA, que celebran por una parte ',
            self::FONT
        );
        $run->addText('${proveedor_nombre}', self::FONT_BOLD);
        $run->addText(', a quien en lo sucesivo se le denominará ', self::FONT);
        $run->addText('“EL PROVEEDOR”', self::FONT_BOLD);
        $run->addText(', y por la otra ', self::FONT);
        $run->addText('${cliente_nombre}', self::FONT_BOLD);
        $run->addText(', a quien en lo sucesivo se le denominará ', self::FONT);
        $run->addText('“EL CLIENTE”', self::FONT_BOLD);
        $run->addText(', respecto del lote o lotes identificados como ', self::FONT);
        $run->addText('${lote_identificacion}', self::FONT_BOLD);
        $run->addText(', sujetándose a las siguientes declaraciones y cláusulas:', self::FONT);
    }

    private function declaraciones(Section $section): void
    {
        $this->titulo($section, 'DECLARACIONES');
        $this->titulo($section, 'I. DECLARA EL PROVEEDOR:');

        $this->parrafo($section, 'a) Que cuenta con capacidad legal para celebrar el presente contrato.');
        $this->parrafo(
            $section,
            'b) Que realizará, directamente o mediante terceros, las gestiones, trabajos, aportaciones y/o '
            .'infraestructura necesaria para hacer llegar el servicio o infraestructura de energía eléctrica al '
            .'desarrollo donde se encuentra ubicado el terreno de EL CLIENTE, sujeto a las autorizaciones, '
            .'especificaciones y procedimientos de las autoridades, organismos, contratistas y empresas que correspondan.'
        );
        $this->parrafo(
            $section,
            'c) Que, con la finalidad de facilitar a EL CLIENTE el pago de la aportación que le corresponde por dichos '
            .'trabajos, acepta otorgarle un esquema de financiamiento en los términos establecidos en este contrato.'
        );

        $this->titulo($section, 'II. DECLARA EL CLIENTE:');

        $this->parrafo($section, 'a) Que sus datos son:');
        $this->campo($section, 'Nombre completo: ', '${cliente_nombre}');
        $this->campo($section, 'CURP: ', '${cliente_curp}');
        $this->campo($section, 'RFC: ', '${cliente_rfc}');
        $this->campo($section, 'Domicilio: ', '${cliente_domicilio}');
        $this->campo($section, 'Teléfono: ', '${cliente_telefono}');
        $this->campo($section, 'Identificación oficial: ', '${cliente_identificacion}', true);

        $this->parrafo($section, 'b) Que tiene derechos respecto del lote o lotes identificados como:');
        $this->campo($section, 'Desarrollo/Fraccionamiento: ', '${lotes_fraccionamiento}');
        $this->campo($section, 'Número de lote: ', '${lotes_numero}');
        $this->campo($section, 'Manzana: ', '${lotes_manzana}');
        $this->campo($section, 'Medidas del lote principal: ', '${medidas}');
        $this->campo($section, 'Contrato relacionado No.: ', '${contrato_relacionado}', true);

        $this->parrafo(
            $section,
            'c) Que conoce y acepta el proyecto para hacer llegar infraestructura eléctrica al sector donde se '
            .'encuentra ubicado su terreno.'
        );
        $this->parrafo(
            $section,
            'd) Que reconoce expresamente que el costo correspondiente a su aportación será cubierto mediante '
            .'financiamiento y que dicha obligación económica es independiente de los pagos que, en su caso, deba '
            .'realizar por la adquisición del terreno.'
        );
        $this->parrafo(
            $section,
            'e) Que celebra el presente contrato libremente y reconoce como líquido y exigible, conforme a los '
            .'vencimientos pactados, el monto financiado señalado en este instrumento.'
        );
    }

    private function clausulas(Section $section): void
    {
        $this->titulo($section, 'PRIMERA. OBJETO');
        $this->parrafo(
            $section,
            'EL PROVEEDOR realizará o coordinará las gestiones, aportaciones, adquisición de materiales, contratación '
            .'de trabajos y demás actos necesarios para la introducción o ampliación de infraestructura eléctrica '
            .'hacia el área donde se encuentra el terreno de EL CLIENTE.'
        );
        $this->parrafo($section, 'EL CLIENTE acepta cubrir la aportación económica que le corresponde conforme al presente contrato.');
        $this->parrafo(
            $section,
            'La energización definitiva, instalación de medidor individual, contratación del servicio, tiempos de '
            .'conexión y demás actos que dependan de autoridades, Comisión Federal de Electricidad, suministradores, '
            .'contratistas autorizados o terceros estarán sujetos a sus respectivos procedimientos, autorizaciones y '
            .'requisitos.'
        );

        $this->titulo($section, 'SEGUNDA. MONTO DEL FINANCIAMIENTO');
        $run = $section->addTextRun(self::PAR);
        $run->addText('El costo correspondiente a EL CLIENTE asciende a ', self::FONT);
        $run->addText('${precio_total_numero}', self::FONT_BOLD);
        $run->addText(' M.N. (', self::FONT);
        $run->addText('${precio_total_letra}', self::FONT_BOLD);
        $run->addText('). EL CLIENTE reconoce expresamente dicho monto como adeudo a su cargo.', self::FONT);

        $this->titulo($section, 'TERCERA. ENGANCHE');
        $run = $section->addTextRun(self::PAR);
        $run->addText('EL CLIENTE pagará ', self::FONT);
        $run->addText('${enganche_numero}', self::FONT_BOLD);
        $run->addText(' M.N. por concepto de enganche o pago inicial. El saldo financiado será de ', self::FONT);
        $run->addText('${saldo_financiado_numero}', self::FONT_BOLD);
        $run->addText(' M.N.', self::FONT);

        $this->titulo($section, 'CUARTA. PLAZO Y FORMA DE PAGO');
        $run = $section->addTextRun(self::PAR);
        $run->addText('EL CLIENTE se obliga a liquidar el saldo mediante ', self::FONT);
        $run->addText('${numero_pagos}', self::FONT_BOLD);
        $run->addText(' pagos de ', self::FONT);
        $run->addText('${monto_pago_numero}', self::FONT_BOLD);
        $run->addText(' M.N. cada uno.', self::FONT);

        $this->campo($section, 'Periodicidad: ', '${periodicidad}');
        $this->campo($section, 'Día(s) de pago: ', '${dias_pago}');
        $this->campo($section, 'Primer pago: ', '${fecha_primer_pago}');
        $this->campo($section, 'Último pago: ', '${fecha_ultimo_pago}');
        $this->campo($section, 'Forma de pago: ', '${forma_pago}', true);

        $this->parrafo(
            $section,
            'Todo pago deberá acreditarse mediante recibo, comprobante de transferencia, depósito o documento '
            .'expedido o reconocido por EL PROVEEDOR.'
        );

        $this->titulo($section, 'QUINTA. OBLIGACIÓN INDEPENDIENTE');
        $this->parrafo(
            $section,
            'Este financiamiento constituye una obligación económica independiente del precio y esquema de pago del '
            .'terreno. Cualquier pago efectuado respecto del terreno no se entenderá aplicado al presente '
            .'financiamiento, salvo convenio expreso y por escrito.'
        );

        $this->titulo($section, 'SEXTA. FECHAS DE PAGO');
        $this->parrafo(
            $section,
            'EL CLIENTE deberá efectuar cada pago en la fecha establecida. La falta de recordatorio, mensaje, llamada '
            .'o aviso de cobro por parte de EL PROVEEDOR no libera a EL CLIENTE de su obligación de pagar puntualmente.'
        );

        $this->titulo($section, 'SÉPTIMA. MORA');
        $run = $section->addTextRun(self::PAR);
        $run->addText(
            'Si EL CLIENTE no cubre una parcialidad en la fecha establecida, incurrirá en mora a partir del día '
            .'siguiente de su vencimiento, hasta donde legalmente resulte procedente. Sobre las cantidades vencidas '
            .'se generará el recargo por mora pactado en este contrato: ',
            self::FONT
        );
        $run->addText('${recargo_mora}', self::FONT_BOLD);
        $run->addText(', sin exceder los límites permitidos por la legislación aplicable.', self::FONT);

        $this->titulo($section, 'OCTAVA. INCUMPLIMIENTO REITERADO');
        $run = $section->addTextRun(self::PAR);
        $run->addText(
            'Se considerará incumplimiento relevante cuando EL CLIENTE: (1) acumule dos parcialidades vencidas, '
            .'consecutivas o no; (2) deje transcurrir más de ',
            self::FONT
        );
        $run->addText('${dias_incumplimiento}', self::FONT_BOLD);
        $run->addText(
            ' días naturales desde el vencimiento de una parcialidad sin regularizarla; (3) proporcione comprobantes '
            .'falsos, alterados o de operaciones rechazadas o canceladas; o (4) incumpla cualquier convenio de '
            .'regularización celebrado por escrito.',
            self::FONT
        );

        $this->titulo($section, 'NOVENA. VENCIMIENTO ANTICIPADO');
        $this->parrafo(
            $section,
            'Ante incumplimiento relevante, EL PROVEEDOR podrá, previa notificación cuando legalmente corresponda, '
            .'declarar vencido anticipadamente el financiamiento y exigir el saldo insoluto, cantidades vencidas, '
            .'intereses moratorios y demás conceptos legalmente exigibles.'
        );

        $this->titulo($section, 'DÉCIMA. GASTOS DE COBRANZA');
        $this->parrafo(
            $section,
            'Cuando el incumplimiento haga necesaria la intervención de abogados, gestores o procedimientos '
            .'judiciales, EL CLIENTE cubrirá los gastos y costas que legalmente sean procedentes y, en su caso, '
            .'determine la autoridad competente.'
        );

        $this->titulo($section, 'DÉCIMA PRIMERA. PAGARÉS');
        $this->parrafo(
            $section,
            'Como garantía adicional, EL CLIENTE podrá suscribir los pagarés correspondientes a las parcialidades '
            .'financiadas o al saldo reconocido, conforme a la legislación aplicable. La existencia de pagarés no '
            .'implicará duplicidad del adeudo.'
        );

        $this->titulo($section, 'DÉCIMA SEGUNDA. PAGOS ANTICIPADOS');
        $this->parrafo(
            $section,
            'EL CLIENTE podrá efectuar pagos anticipados o liquidar el saldo antes del vencimiento, sin penalización, '
            .'salvo condición distinta pactada por escrito y permitida por la legislación aplicable.'
        );

        $this->titulo($section, 'DÉCIMA TERCERA. MODIFICACIONES');
        $this->parrafo(
            $section,
            'Ningún acuerdo verbal modificará cantidades, fechas, plazos o condiciones. Cualquier prórroga, '
            .'reestructura o modificación deberá constar por escrito y estar firmada por ambas partes. La tolerancia '
            .'ocasional de retrasos no constituirá renuncia de derechos.'
        );

        $this->titulo($section, 'DÉCIMA CUARTA. INFRAESTRUCTURA ELÉCTRICA');
        $this->parrafo(
            $section,
            'Las instalaciones generales de la red, líneas, postes, transformadores, registros, cableado u otra '
            .'infraestructura común estarán sujetas a las disposiciones técnicas, administrativas y patrimoniales '
            .'aplicables. La aportación pagada no implica necesariamente propiedad individual sobre dichos elementos.'
        );

        $this->titulo($section, 'DÉCIMA QUINTA. MEDIDOR Y CONTRATO INDIVIDUAL');
        $this->parrafo(
            $section,
            'Salvo indicación expresa en un anexo, el precio no incluye necesariamente depósitos, contratos '
            .'individuales, medidores, instalaciones internas, centros de carga, acometidas particulares u otros '
            .'conceptos que deban cubrirse directamente ante la empresa suministradora, autoridad o contratista.'
        );

        $this->titulo($section, 'DÉCIMA SEXTA. CASO FORTUITO, FUERZA MAYOR Y ACTOS DE TERCEROS');
        $this->parrafo(
            $section,
            'Los plazos de ejecución podrán modificarse por causas ajenas al control razonable de EL PROVEEDOR, '
            .'incluyendo retrasos de autoridades, permisos, disponibilidad de materiales, modificaciones técnicas '
            .'exigidas por terceros, condiciones climatológicas extraordinarias, caso fortuito o fuerza mayor.'
        );

        $this->titulo($section, 'DÉCIMA SÉPTIMA. DOMICILIOS Y NOTIFICACIONES');
        $this->campo($section, 'Domicilio de EL CLIENTE: ', '${cliente_domicilio}');
        $this->campo($section, 'Teléfono/WhatsApp: ', '${cliente_telefono}');
        $this->campo($section, 'Correo electrónico: ', '${cliente_correo}', true);
        $this->parrafo(
            $section,
            'EL CLIENTE se obliga a informar por escrito cualquier modificación a sus datos. Las partes podrán '
            .'utilizar medios electrónicos para comunicaciones ordinarias, sin perjuicio de las formalidades '
            .'legalmente necesarias.'
        );

        $this->titulo($section, 'DÉCIMA OCTAVA. INTEGRIDAD CONTRACTUAL');
        $this->parrafo(
            $section,
            'El presente documento y sus anexos contienen los acuerdos relacionados con este financiamiento. '
            .'Cualquier modificación deberá realizarse por escrito y estar firmada por ambas partes.'
        );

        $this->titulo($section, 'DÉCIMA NOVENA. LEGISLACIÓN Y JURISDICCIÓN');
        $this->parrafo(
            $section,
            'Para la interpretación y cumplimiento del presente contrato, las partes se someten a las leyes '
            .'aplicables de los Estados Unidos Mexicanos y, cuando legalmente resulte procedente, a la competencia de '
            .'los tribunales correspondientes a Piedras Negras, Coahuila de Zaragoza, sin perjuicio de los derechos '
            .'irrenunciables que la legislación aplicable otorgue a EL CLIENTE.'
        );
    }

    private function tablaFinanciamiento(Section $section): void
    {
        $this->titulo($section, 'TABLA DE FINANCIAMIENTO');

        $tabla = $section->addTable([
            'borderSize' => 6,
            'borderColor' => '999999',
            'cellMargin' => 60,
            'width' => 100 * 50,
            'unit' => TblWidth::PERCENT,
        ]);

        $filas = [
            'Costo total de aportación' => '${precio_total_numero}',
            'Enganche' => '${enganche_numero}',
            'Saldo financiado' => '${saldo_financiado_numero}',
            'Número de pagos' => '${numero_pagos}',
            'Monto de cada pago' => '${monto_pago_numero}',
            'Periodicidad' => '${periodicidad}',
            'Fecha del primer pago' => '${fecha_primer_pago}',
            'Fecha del último pago' => '${fecha_ultimo_pago}',
        ];

        foreach ($filas as $concepto => $valor) {
            $fila = $tabla->addRow();
            $fila->addCell(5000)->addText($concepto, self::FONT, ['spaceAfter' => 0]);
            $fila->addCell(4000)->addText($valor, self::FONT_BOLD, ['spaceAfter' => 0]);
        }

        $section->addTextBreak(1, self::FONT);

        $this->titulo($section, 'RECONOCIMIENTO EXPRESO');
        $this->parrafo(
            $section,
            'EL CLIENTE manifiesta que ha leído el presente contrato, conoce el monto de su obligación, número de '
            .'parcialidades, fechas de vencimiento y consecuencias del incumplimiento. Asimismo, reconoce haber '
            .'recibido un ejemplar del presente documento y de la tabla de financiamiento correspondiente.'
        );

        $run = $section->addTextRun(self::PAR);
        $run->addText('Firmado por duplicado en ', self::FONT);
        $run->addText('${ciudad_firma}', self::FONT_BOLD);
        $run->addText(', Coahuila de Zaragoza, a ', self::FONT);
        $run->addText('${fecha_firma_larga}', self::FONT_BOLD);
        $run->addText('.', self::FONT);
    }

    private function firmas(Section $section): void
    {
        $section->addTextBreak(2, self::FONT);

        $tabla = $section->addTable([
            'width' => 100 * 50,
            'unit' => TblWidth::PERCENT,
        ]);

        $centrado = ['alignment' => Jc::CENTER, 'spaceAfter' => 0];

        $fila = $tabla->addRow();
        $fila->addCell(4500)->addText('EL PROVEEDOR', self::FONT_BOLD, $centrado);
        $fila->addCell(4500)->addText('EL CLIENTE', self::FONT_BOLD, $centrado);

        $fila = $tabla->addRow();
        $celda = $fila->addCell(4500);
        $celda->addTextBreak(2, self::FONT);
        $celda->addText('${proveedor_nombre}', self::FONT, $centrado);
        $celda->addText('Nombre y firma', self::FONT, $centrado);

        $celda = $fila->addCell(4500);
        $celda->addTextBreak(2, self::FONT);
        $celda->addText('${cliente_nombre}', self::FONT, $centrado);
        $celda->addText('Nombre y firma', self::FONT, $centrado);
        $celda->addText('Huella digital: ______________', self::FONT, $centrado);

        $section->addTextBreak(1, self::FONT);

        $run = $section->addTextRun(self::PAR);
        $run->addText('ANEXOS: ', self::FONT_BOLD);
        $run->addText(
            'A) Tabla y calendario completo de pagos. B) Identificación oficial de EL CLIENTE. C) Documentos que '
            .'identifican los lotes o contratos relacionados. D) Pagaré o pagarés que, en su caso, garanticen el '
            .'financiamiento. E) Descripción del alcance de la infraestructura eléctrica financiada.',
            ['name' => 'Arial', 'size' => 9]
        );
    }

    private function titulo(Section $section, string $texto): void
    {
        $section->addText($texto, self::FONT_BOLD, ['alignment' => Jc::START, 'spaceBefore' => 180, 'spaceAfter' => 80]);
    }

    private function parrafo(Section $section, string $texto): void
    {
        $section->addText($texto, self::FONT, self::PAR);
    }

    private function campo(Section $section, string $etiqueta, string $placeholder, bool $ultimo = false): void
    {
        $par = self::PAR_CAMPO;

        if ($ultimo) {
            $par['spaceAfter'] = 160;
        }

        $run = $section->addTextRun($par);
        $run->addText($etiqueta, self::FONT);
        $run->addText($placeholder, self::FONT_BOLD);
    }
}
