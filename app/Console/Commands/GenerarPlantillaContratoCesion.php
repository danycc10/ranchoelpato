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
 * Genera la plantilla Word del contrato de cesión onerosa a plazos de derechos
 * posesorios y/o de uso sobre lote, con los placeholders ${...} que llena
 * ContratoWordService.
 *
 * Sigue el formato maestro para lotes de origen ejidal de Coahuila, sin la
 * carátula ni el control interno del expediente: esos son de control interno y
 * no van en el contrato que se firma.
 */
class GenerarPlantillaContratoCesion extends Command
{
    protected $signature = 'contratos:plantilla-cesion {--force : Sobrescribe la plantilla si ya existe}';

    protected $description = 'Genera la plantilla Word del contrato de cesión de derechos posesorios.';

    private const FONT = ['name' => 'Arial', 'size' => 10];

    private const FONT_BOLD = ['name' => 'Arial', 'size' => 10, 'bold' => true];

    private const PAR = ['alignment' => Jc::BOTH, 'spaceAfter' => 120];

    public function handle(): int
    {
        $documento = ContratoWordService::documentType('contrato_cesion_derechos');

        if (! $documento) {
            $this->error('No existe el tipo de documento contrato_cesion_derechos.');

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
            'Contrato de Cesión de Derechos Posesorios - Página {PAGE}',
            ['name' => 'Arial', 'size' => 8, 'color' => '666666'],
            ['alignment' => Jc::CENTER]
        );

        $this->encabezado($section);
        $this->declaraciones($section);
        $this->identificacionLote($section);
        $this->clausulas($section);
        $this->anexos($section);
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
            'CONTRATO DE CESIÓN ONEROSA A PLAZOS DE DERECHOS POSESORIOS Y/O DE USO SOBRE LOTE',
            ['name' => 'Arial', 'size' => 12, 'bold' => true],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 240]
        );

        $run = $section->addTextRun(self::PAR);
        $run->addText('CONTRATO que celebran, por una parte, ', self::FONT);
        $run->addText('${cedente_nombre}', self::FONT_BOLD);
        $run->addText(', a quien en lo sucesivo se denominará ', self::FONT);
        $run->addText('“LA PARTE CEDENTE”', self::FONT_BOLD);
        $run->addText('; y por la otra ', self::FONT);
        $run->addText('${adquirente_nombre}', self::FONT_BOLD);
        $run->addText(', a quien en lo sucesivo se denominará ', self::FONT);
        $run->addText('“LA PARTE ADQUIRENTE”', self::FONT_BOLD);
        $run->addText(
            '; conjuntamente “LAS PARTES”, respecto del lote identificado como ',
            self::FONT
        );
        $run->addText('${lote_identificacion}', self::FONT_BOLD);
        $run->addText(', al tenor de las siguientes declaraciones y cláusulas.', self::FONT);
    }

    private function declaraciones(Section $section): void
    {
        $this->titulo($section, 'DECLARACIONES');
        $this->titulo($section, 'I. DECLARA LA PARTE CEDENTE:');

        $this->datosPersonales($section, 'cedente');

        $run = $section->addTextRun(self::PAR);
        $run->addText(
            'b) Que cuenta con capacidad legal para celebrar el presente contrato y que, bajo protesta de decir '
            .'verdad, la fuente y naturaleza de los derechos que transmite respecto del predio matriz derivan de: ',
            self::FONT
        );
        $run->addText('${antecedente_tipo}', self::FONT_BOLD);
        $run->addText(', identificado con ', self::FONT);
        $run->addText('${antecedente_datos}', self::FONT_BOLD);
        $run->addText(', cuya copia se agrega como ANEXO.', self::FONT);

        $run = $section->addTextRun(self::PAR);
        $run->addText(
            'c) Que el lote materia de este contrato forma parte del proyecto denominado ',
            self::FONT
        );
        $run->addText('${fraccionamiento}', self::FONT_BOLD);
        $run->addText(', localizado en ', self::FONT);
        $run->addText('${proyecto_ubicacion}', self::FONT_BOLD);
        $run->addText(', dentro de ', self::FONT);
        $run->addText('${ejido}', self::FONT_BOLD);
        $run->addText(', Municipio de ', self::FONT);
        $run->addText('${municipio}', self::FONT_BOLD);
        $run->addText(', Coahuila de Zaragoza.', self::FONT);

        $this->parrafo(
            $section,
            'd) Que únicamente transmite los derechos que legítimamente tenga y pueda transmitir conforme a su '
            .'título o antecedente y a la legislación aplicable, sin ampliar por este contrato la naturaleza '
            .'jurídica de tales derechos.'
        );
        $this->parrafo(
            $section,
            'e) Que, salvo lo expresamente señalado en este instrumento y sus anexos, no ha celebrado con tercero '
            .'acto incompatible respecto del mismo lote y que informará por escrito cualquier limitación, gravamen, '
            .'controversia o afectación de la que tenga conocimiento.'
        );

        $this->titulo($section, 'II. DECLARA LA PARTE ADQUIRENTE:');

        $this->datosPersonales($section, 'adquirente');

        $this->parrafo(
            $section,
            'b) Que previamente a la firma tuvo oportunidad de revisar la ubicación, dimensiones, acceso, '
            .'condiciones físicas y situación documental del lote; recibió explicación suficiente sobre la '
            .'naturaleza de los derechos objeto de la operación y manifiesta comprender que el origen ejidal del '
            .'predio no equivale, por sí mismo, a propiedad privada individual ni garantiza escritura pública.'
        );
        $this->parrafo(
            $section,
            'c) Que los recursos utilizados para el pago son de procedencia lícita y que los datos y documentos '
            .'proporcionados son auténticos.'
        );

        $this->titulo($section, 'III. DECLARAN LAS PARTES:');

        $this->parrafo(
            $section,
            'a) Que se reconocen mutuamente personalidad y capacidad; que celebran el contrato libremente y sin '
            .'error, dolo, mala fe, violencia, lesión o cualquier otro vicio del consentimiento.'
        );
        $this->parrafo(
            $section,
            'b) Que el croquis, plano, tabla de pagos, recibos, identificaciones y documentos de antecedente que se '
            .'agreguen forman parte integrante del contrato.'
        );
        $this->parrafo(
            $section,
            'c) Que cualquier referencia comercial a “venta”, “lote”, “fraccionamiento” o “propiedad” deberá '
            .'interpretarse conforme a la naturaleza jurídica real de los derechos expresamente descritos en este '
            .'instrumento.'
        );
    }

    /**
     * Declaración a) de cada parte: los datos con los que se identifica.
     */
    private function datosPersonales(Section $section, string $parte): void
    {
        $run = $section->addTextRun(self::PAR);
        $run->addText('a) Ser ', self::FONT);
        $run->addText('${'.$parte.'_persona_tipo}', self::FONT_BOLD);
        $run->addText(', de nacionalidad ', self::FONT);
        $run->addText('${'.$parte.'_nacionalidad}', self::FONT_BOLD);
        $run->addText(', estado civil ', self::FONT);
        $run->addText('${'.$parte.'_estado_civil}', self::FONT_BOLD);
        $run->addText(', ocupación ', self::FONT);
        $run->addText('${'.$parte.'_ocupacion}', self::FONT_BOLD);
        $run->addText(', con domicilio en ', self::FONT);
        $run->addText('${'.$parte.'_domicilio}', self::FONT_BOLD);
        $run->addText(', CURP ', self::FONT);
        $run->addText('${'.$parte.'_curp}', self::FONT_BOLD);
        $run->addText(', RFC ', self::FONT);
        $run->addText('${'.$parte.'_rfc}', self::FONT_BOLD);
        $run->addText(', teléfono ', self::FONT);
        $run->addText('${'.$parte.'_telefono}', self::FONT_BOLD);
        $run->addText(', correo electrónico ', self::FONT);
        $run->addText('${'.$parte.'_correo}', self::FONT_BOLD);
        $run->addText(', e identificarse con ', self::FONT);
        $run->addText('${'.$parte.'_identificacion}', self::FONT_BOLD);
        $run->addText(' número ', self::FONT);
        $run->addText('${'.$parte.'_identificacion_numero}', self::FONT_BOLD);
        $run->addText('.', self::FONT);
    }

    private function identificacionLote(Section $section): void
    {
        $this->titulo($section, 'IDENTIFICACIÓN DEL LOTE');

        $tabla = $section->addTable([
            'borderSize' => 6,
            'borderColor' => '999999',
            'cellMargin' => 60,
            'width' => 100 * 50,
            'unit' => TblWidth::PERCENT,
        ]);

        $filas = [
            'PROYECTO' => '${fraccionamiento}',
            'LOTE' => '${lote}',
            'MANZANA' => '${manzana}',
            'SUPERFICIE APROX.' => '${superficie}',
            'NORTE' => '${lote_norte}',
            'SUR' => '${lote_sur}',
            'ORIENTE' => '${lote_este}',
            'PONIENTE' => '${lote_oeste}',
        ];

        foreach ($filas as $concepto => $valor) {
            $fila = $tabla->addRow();
            $fila->addCell(3200)->addText($concepto, self::FONT_BOLD, ['spaceAfter' => 0]);
            $fila->addCell(6300)->addText($valor, self::FONT, ['spaceAfter' => 0]);
        }

        $section->addTextBreak(1, self::FONT);
    }

    private function clausulas(Section $section): void
    {
        $this->titulo($section, 'CLÁUSULAS');

        $this->titulo($section, 'PRIMERA. OBJETO');
        $this->parrafo(
            $section,
            'LA PARTE CEDENTE cede onerosamente a LA PARTE ADQUIRENTE, quien acepta, los derechos posesorios y/o de '
            .'uso que legalmente correspondan sobre el lote descrito en este contrato y sus anexos, con las '
            .'limitaciones inherentes al antecedente jurídico del predio. La operación no se interpretará como '
            .'transmisión de dominio pleno si éste no existe o no ha sido legalmente adquirido.'
        );

        $this->titulo($section, 'SEGUNDA. NATURALEZA AGRARIA Y ALCANCE');
        $this->parrafo(
            $section,
            'Si el predio se encuentra sujeto al régimen ejidal, LAS PARTES reconocen que la validez, oponibilidad y '
            .'formalización de actos sobre derechos parcelarios, tierras de uso común o derechos agrarios se '
            .'sujetará prioritariamente a la Ley Agraria y a las formalidades que resulten aplicables. Ninguna '
            .'cláusula sustituye autorizaciones, notificaciones, calidad agraria, derechos del tanto, inscripción '
            .'ante el Registro Agrario Nacional o resolución de asamblea cuando legalmente sean necesarias.'
        );

        $this->titulo($section, 'TERCERA. PRECIO');
        $run = $section->addTextRun(self::PAR);
        $run->addText('El precio convenido es de ', self::FONT);
        $run->addText('${precio_total_numero}', self::FONT_BOLD);
        $run->addText(' M.N. (', self::FONT);
        $run->addText('${precio_total_letra}', self::FONT_BOLD);
        $run->addText('). LA PARTE ADQUIRENTE entrega en este acto ', self::FONT);
        $run->addText('${enganche_numero}', self::FONT_BOLD);
        $run->addText(
            ' M.N. por concepto de enganche, cantidad que LA PARTE CEDENTE reconoce recibir, sirviendo el presente '
            .'instrumento como recibo únicamente por la cantidad efectivamente entregada.',
            self::FONT
        );

        $this->titulo($section, 'CUARTA. FORMA Y CALENDARIO DE PAGO');
        $run = $section->addTextRun(self::PAR);
        $run->addText('El saldo de ', self::FONT);
        $run->addText('${saldo_financiado_numero}', self::FONT_BOLD);
        $run->addText(' M.N. será cubierto mediante ', self::FONT);
        $run->addText('${numero_pagos}', self::FONT_BOLD);
        $run->addText(' pagos ', self::FONT);
        $run->addText('${periodicidad_texto}', self::FONT_BOLD);
        $run->addText(' de ', self::FONT);
        $run->addText('${monto_pago_numero}', self::FONT_BOLD);
        $run->addText(' M.N. cada uno, comenzando el ', self::FONT);
        $run->addText('${fecha_primer_pago}', self::FONT_BOLD);
        $run->addText(' y continuando ', self::FONT);
        $run->addText('${dias_pago}', self::FONT_BOLD);
        $run->addText(
            '. Los pagos se realizarán mediante transferencia, depósito o efectivo en los datos autorizados por LA '
            .'PARTE CEDENTE: ',
            self::FONT
        );
        $run->addText('${datos_pago}', self::FONT_BOLD);
        $run->addText(
            '. El comprobante bancario o recibo firmado acreditará el pago. Todo pago se aplicará primero a gastos y '
            .'accesorios legalmente exigibles y después al principal, salvo pacto escrito distinto.',
            self::FONT
        );

        $this->titulo($section, 'QUINTA. PAGO ANTICIPADO');
        $this->parrafo(
            $section,
            'LA PARTE ADQUIRENTE podrá realizar pagos anticipados o liquidar el saldo sin penalización, salvo que '
            .'LAS PARTES pacten expresamente por escrito una condición diferente permitida por la ley. La '
            .'liquidación sólo se tendrá por concluida cuando el precio y demás conceptos exigibles se encuentren '
            .'totalmente cubiertos.'
        );

        $this->titulo($section, 'SEXTA. MORA Y REQUERIMIENTO');
        $run = $section->addTextRun(self::PAR);
        $run->addText(
            'El retraso en una parcialidad generará únicamente el recargo pactado de ',
            self::FONT
        );
        $run->addText('${recargo_mora}', self::FONT_BOLD);
        $run->addText(
            ', siempre dentro de los límites legales. Antes de ejercer rescisión por incumplimiento, LA PARTE '
            .'CEDENTE notificará a LA PARTE ADQUIRENTE y le concederá un plazo de gracia de ',
            self::FONT
        );
        $run->addText('${dias_gracia}', self::FONT_BOLD);
        $run->addText(
            ' días naturales para regularizarse, salvo incumplimiento grave que legalmente permita proceder de otra '
            .'manera.',
            self::FONT
        );

        $this->titulo($section, 'SÉPTIMA. INCUMPLIMIENTO Y RESCISIÓN');
        $run = $section->addTextRun(self::PAR);
        $run->addText(
            'Si, transcurrido el plazo de gracia, LA PARTE ADQUIRENTE no cubre ',
            self::FONT
        );
        $run->addText('${parcialidades_rescision}', self::FONT_BOLD);
        $run->addText(
            ' parcialidades vencidas o incurre en un incumplimiento esencial, LA PARTE CEDENTE podrá exigir el '
            .'cumplimiento o promover la rescisión por las vías legalmente procedentes. Las cantidades pagadas no se '
            .'perderán automáticamente: al practicarse la liquidación se considerarán principal pagado, uso o '
            .'disfrute efectivamente recibido, daños acreditados, gastos procedentes y demás conceptos que '
            .'legalmente correspondan. Cualquier saldo a favor de una parte deberá ser determinado y, en su caso, '
            .'restituido.',
            self::FONT
        );

        $this->titulo($section, 'OCTAVA. POSESIÓN MATERIAL');
        $run = $section->addTextRun(self::PAR);
        $run->addText('La posesión material del lote se entregará ', self::FONT);
        $run->addText('${posesion_entrega}', self::FONT_BOLD);
        $run->addText(
            '. La entrega material no modifica por sí sola la naturaleza jurídica de los derechos transmitidos. LA '
            .'PARTE ADQUIRENTE se obliga a respetar accesos, servidumbres, vialidades proyectadas, áreas comunes y '
            .'restricciones que consten en el plano o reglamento anexo.',
            self::FONT
        );

        $this->titulo($section, 'NOVENA. CONSTRUCCIONES Y MEJORAS');
        $this->parrafo(
            $section,
            'Las construcciones, cercos, instalaciones o mejoras que LA PARTE ADQUIRENTE pretenda realizar serán '
            .'bajo su responsabilidad y deberán cumplir la normativa agraria, urbana, ambiental, municipal y de '
            .'seguridad aplicable. Antes de la liquidación total, no podrá realizar actos que comprometan '
            .'jurídicamente el lote frente a terceros sin autorización escrita de LA PARTE CEDENTE.'
        );

        $this->titulo($section, 'DÉCIMA. SERVICIOS E INFRAESTRUCTURA');
        $run = $section->addTextRun(self::PAR);
        $run->addText(
            'Salvo que en el ANEXO DE SERVICIOS se establezca expresamente una obligación con fecha determinada, '
            .'cualquier referencia a electricidad, agua, drenaje, caminos, internet, alumbrado u otra '
            .'infraestructura tendrá el carácter de ',
            self::FONT
        );
        $run->addText('${servicios_estado}', self::FONT_BOLD);
        $run->addText(
            '. No se entenderá garantizada una fecha de instalación que no conste expresamente por escrito. Los '
            .'derechos, contratos, medidores, conexiones y consumos individuales serán a cargo de ',
            self::FONT
        );
        $run->addText('${servicios_a_cargo}', self::FONT_BOLD);
        $run->addText('.', self::FONT);

        $this->titulo($section, 'DÉCIMA PRIMERA. MEDIDAS, COLINDANCIAS Y CROQUIS');
        $run = $section->addTextRun(self::PAR);
        $run->addText(
            'El lote se identifica por las medidas, colindancias y croquis anexos. Si las medidas son preliminares, '
            .'LAS PARTES aceptan una tolerancia técnica de ',
            self::FONT
        );
        $run->addText('${tolerancia_medidas}', self::FONT_BOLD);
        $run->addText(
            '% derivada del levantamiento definitivo, siempre que no se altere sustancialmente la ubicación o '
            .'funcionalidad del lote. Cualquier ajuste material deberá documentarse mediante convenio firmado.',
            self::FONT
        );

        $this->titulo($section, 'DÉCIMA SEGUNDA. CESIÓN A TERCEROS');
        $this->parrafo(
            $section,
            'Mientras exista saldo pendiente, LA PARTE ADQUIRENTE no podrá ceder su posición contractual a tercero '
            .'sin consentimiento previo y escrito de LA PARTE CEDENTE, quien no podrá negarlo arbitrariamente cuando '
            .'el cesionario acredite identidad, capacidad y aceptación de las obligaciones pendientes. La cesión '
            .'deberá formalizarse por escrito.'
        );

        $this->titulo($section, 'DÉCIMA TERCERA. FALLECIMIENTO O INCAPACIDAD');
        $this->parrafo(
            $section,
            'El fallecimiento de cualquiera de LAS PARTES no extinguirá las obligaciones transmisibles. Los '
            .'sucesores deberán acreditar legalmente su carácter. Tratándose de derechos agrarios, se observarán '
            .'además las disposiciones sucesorias especiales de la legislación agraria, por lo que este contrato no '
            .'sustituye lista de sucesión, juicio sucesorio agrario ni determinación de autoridad competente.'
        );

        $this->titulo($section, 'DÉCIMA CUARTA. DERECHO DEL TANTO Y FORMALIDADES AGRARIAS');
        $this->parrafo(
            $section,
            'Cuando la operación, por su naturaleza, implique enajenación de derechos parcelarios u otro supuesto '
            .'sujeto a derecho del tanto o requisitos especiales, su consumación quedará condicionada al '
            .'cumplimiento de dichas formalidades. LAS PARTES se obligan a proporcionar la documentación y firmas '
            .'razonablemente necesarias para ello.'
        );

        $this->titulo($section, 'DÉCIMA QUINTA. SANEAMIENTO Y CONTROVERSIAS PREEXISTENTES');
        $this->parrafo(
            $section,
            'LA PARTE CEDENTE responderá, en la medida que legalmente corresponda a la naturaleza del derecho '
            .'transmitido, por actos propios anteriores incompatibles con este contrato y por la legitimidad del '
            .'derecho que declara tener. No responderá por circunstancias expresamente reveladas y aceptadas por LA '
            .'PARTE ADQUIRENTE en el ANEXO DE SITUACIÓN JURÍDICA.'
        );

        $this->titulo($section, 'DÉCIMA SEXTA. GASTOS, IMPUESTOS Y FORMALIZACIÓN');
        $this->parrafo(
            $section,
            'Cada parte cubrirá los impuestos y obligaciones que legalmente le correspondan. Los gastos de '
            .'certificaciones, ratificaciones, avalúos, levantamientos, trámites agrarios, notariales, registrales o '
            .'municipales necesarios para una formalización posterior serán cubiertos conforme a la ley, salvo '
            .'disposición legal imperativa distinta o pacto escrito de LAS PARTES.'
        );

        $this->titulo($section, 'DÉCIMA SÉPTIMA. DOMICILIOS Y NOTIFICACIONES');
        $this->parrafo(
            $section,
            'Para todo aviso relacionado con el contrato, LAS PARTES señalan los domicilios, teléfonos y correos '
            .'asentados en sus declaraciones. Serán válidas las notificaciones por escrito entregadas personalmente, '
            .'mensajería con constancia, correo electrónico o mensaje electrónico cuando pueda acreditarse su '
            .'recepción. Todo cambio de datos deberá notificarse dentro de los 5 días hábiles siguientes.'
        );

        $this->titulo($section, 'DÉCIMA OCTAVA. INTEGRIDAD CONTRACTUAL');
        $this->parrafo(
            $section,
            'Este contrato y sus anexos contienen el acuerdo entre LAS PARTES respecto del lote. Promesas, '
            .'descuentos, obras, servicios, tolerancias o modificaciones posteriores sólo serán exigibles cuando '
            .'consten por escrito y estén firmadas por quienes tengan facultades. La tolerancia ocasional de un '
            .'incumplimiento no implica renuncia permanente a exigir el cumplimiento futuro.'
        );

        $this->titulo($section, 'DÉCIMA NOVENA. PROTECCIÓN DE DATOS Y EXPEDIENTE');
        $this->parrafo(
            $section,
            'LAS PARTES autorizan la integración de un expediente contractual con identificaciones, CURP, RFC, '
            .'comprobantes de domicilio, recibos y documentos necesarios para acreditar la operación, debiendo '
            .'utilizarse dichos datos únicamente para fines contractuales, administrativos, fiscales, agrarios o '
            .'legales relacionados con la misma.'
        );

        $this->titulo($section, 'VIGÉSIMA. NULIDAD PARCIAL');
        $this->parrafo(
            $section,
            'Si alguna estipulación resultara inválida o inaplicable, las restantes conservarán su eficacia en '
            .'cuanto legalmente sea posible. LAS PARTES procurarán sustituir la disposición afectada por otra válida '
            .'que preserve razonablemente la finalidad económica y jurídica del acuerdo.'
        );

        $this->titulo($section, 'VIGÉSIMA PRIMERA. LEGISLACIÓN Y COMPETENCIA');
        $run = $section->addTextRun(self::PAR);
        $run->addText(
            'La relación se regirá, según corresponda a la naturaleza del derecho, por la Constitución Política de '
            .'los Estados Unidos Mexicanos, la Ley Agraria y demás legislación federal aplicable, así como por la '
            .'legislación civil vigente en Coahuila de Zaragoza en aquello que resulte procedente y no contravenga '
            .'normas agrarias de orden público. Para controversias agrarias serán competentes las autoridades y '
            .'tribunales agrarios que legalmente correspondan; para cuestiones civiles o contractuales no reservadas '
            .'a la jurisdicción agraria, LAS PARTES se someten a los tribunales competentes de ',
            self::FONT
        );
        $run->addText('${municipio}', self::FONT_BOLD);
        $run->addText(
            ', Coahuila de Zaragoza, sin perjuicio de competencias irrenunciables.',
            self::FONT
        );

        $this->titulo($section, 'VIGÉSIMA SEGUNDA. RATIFICACIÓN');
        $this->parrafo(
            $section,
            'LAS PARTES podrán ratificar firmas ante fedatario público o autoridad competente cuando resulte '
            .'conveniente o legalmente necesario. Dicha ratificación no subsanará por sí misma la falta de '
            .'requisitos agrarios sustantivos que deban cumplirse conforme a la Ley Agraria.'
        );
    }

    private function anexos(Section $section): void
    {
        $this->titulo($section, 'ANEXOS QUE DEBERÁN INTEGRAR EL EXPEDIENTE');

        $anexos = [
            'ANEXO A. Identificación oficial vigente de LA PARTE CEDENTE.',
            'ANEXO B. Identificación oficial vigente de LA PARTE ADQUIRENTE.',
            'ANEXO C. CURP y, cuando corresponda, RFC y comprobante de domicilio de ambas partes.',
            'ANEXO D. Documento que acredita el origen o antecedente de los derechos del predio matriz.',
            'ANEXO E. Croquis/plano del fraccionamiento con el lote claramente señalado.',
            'ANEXO F. Medidas, colindancias y, de ser posible, coordenadas del lote.',
            'ANEXO G. Tabla de pagos y calendario.',
            'ANEXO H. Situación jurídica y revelaciones específicas del predio.',
            'ANEXO I. Estado de servicios e infraestructura ofrecida.',
            'ANEXO J. Reglamento interno, si existe.',
            'ANEXO K. Recibos y comprobantes de pago.',
            'ANEXO L. Documentos agrarios adicionales que resulten aplicables (certificado parcelario, constancias, '
                .'notificaciones, derechos del tanto, actas o inscripciones).',
        ];

        foreach ($anexos as $anexo) {
            $section->addListItem($anexo, 0, self::FONT, null, ['spaceAfter' => 40]);
        }
    }

    private function firmas(Section $section): void
    {
        $this->titulo($section, 'FIRMAS');

        $run = $section->addTextRun(self::PAR);
        $run->addText(
            'Leído el presente contrato y enteradas LAS PARTES de su contenido, alcance y consecuencias, lo firman '
            .'por duplicado en ',
            self::FONT
        );
        $run->addText('${municipio}', self::FONT_BOLD);
        $run->addText(', Coahuila de Zaragoza, a ', self::FONT);
        $run->addText('${fecha_firma_larga}', self::FONT_BOLD);
        $run->addText(
            '. Se recomienda rubricar cada hoja y firmar también los anexos esenciales.',
            self::FONT
        );

        $section->addTextBreak(2, self::FONT);

        $centrado = ['alignment' => Jc::CENTER, 'spaceAfter' => 0];

        $tabla = $section->addTable([
            'width' => 100 * 50,
            'unit' => TblWidth::PERCENT,
        ]);

        $fila = $tabla->addRow();

        $celda = $fila->addCell(4500);
        $celda->addText('__________________________________', self::FONT, $centrado);
        $celda->addText('${cedente_nombre}', self::FONT_BOLD, $centrado);
        $celda->addText('LA PARTE CEDENTE', self::FONT, $centrado);
        $celda->addText('Huella (opcional): ______________', self::FONT, $centrado);

        $celda = $fila->addCell(4500);
        $celda->addText('__________________________________', self::FONT, $centrado);
        $celda->addText('${adquirente_nombre}', self::FONT_BOLD, $centrado);
        $celda->addText('LA PARTE ADQUIRENTE', self::FONT, $centrado);
        $celda->addText('Huella (opcional): ______________', self::FONT, $centrado);

        $section->addTextBreak(2, self::FONT);

        $tabla = $section->addTable([
            'width' => 100 * 50,
            'unit' => TblWidth::PERCENT,
        ]);

        $fila = $tabla->addRow();

        foreach (['TESTIGO 1 (OPCIONAL)', 'TESTIGO 2 (OPCIONAL)'] as $testigo) {
            $celda = $fila->addCell(4500);
            $celda->addText('__________________________________', self::FONT, $centrado);
            $celda->addText($testigo, self::FONT, $centrado);
            $celda->addText('Identificación: ______________', self::FONT, $centrado);
        }
    }

    private function titulo(Section $section, string $texto): void
    {
        $section->addText($texto, self::FONT_BOLD, ['alignment' => Jc::START, 'spaceBefore' => 180, 'spaceAfter' => 80]);
    }

    private function parrafo(Section $section, string $texto): void
    {
        $section->addText($texto, self::FONT, self::PAR);
    }
}
