<?php

namespace App\Services\Contratos;

use App\Models\Contrato;
use App\Models\CuentaBancaria;
use App\Models\Propietario;
use App\Models\Recibo;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\TemplateProcessor;
use RuntimeException;
use ZipArchive;

class ContratoWordService
{
    private const DOCUMENT_TYPES = [
        'contrato' => [
            'label' => 'Contrato',
            'template' => 'plantillas/contrato_base.docx',
            'docx_field' => 'archivo_contrato_docx',
            'scan_field' => 'archivo_contrato',
            'filename_slug' => 'contrato',
            'scan_filename' => 'contrato.pdf',
            'uses_fraccionamiento_template' => true,
            // Es el contrato de compraventa del lote: no aplica a contratos de servicio.
            'tipos' => ['terreno'],
        ],
        'contrato_contado_terreno' => [
            'label' => 'Contrato de compraventa de contado',
            'template' => 'plantillas/CONTRATO_CONTADO_TERRENO.docx',
            // Comparte columnas con el contado de luz: a un contrato le aplica
            // uno o el otro, nunca los dos.
            'docx_field' => 'archivo_contrato_contado_docx',
            'scan_field' => 'archivo_contrato_contado',
            'filename_slug' => 'contrato-contado',
            'scan_filename' => 'contrato-contado.pdf',
            'uses_fraccionamiento_template' => false,
            'solo_contado' => true,
            'tipos' => ['terreno'],
        ],
        'contrato_contado' => [
            'label' => 'Contrato de infraestructura eléctrica pagado de contado',
            'template' => 'plantillas/CONTRATO_CONTADO.docx',
            'docx_field' => 'archivo_contrato_contado_docx',
            'scan_field' => 'archivo_contrato_contado',
            'filename_slug' => 'contrato-contado',
            'scan_filename' => 'contrato-contado.pdf',
            'uses_fraccionamiento_template' => false,
            // Reemplaza al contrato a plazos: uno u otro, nunca los dos.
            'solo_contado' => true,
            // La plantilla es la de aportación eléctrica: no sirve para un lote.
            'tipos' => ['servicio'],
            'servicio_tipos' => ['electricidad'],
        ],
        'constancia_terminacion_pago' => [
            'label' => 'Constancia de terminación de pago',
            'template' => 'plantillas/CONSTANCIA_DE_TERMINACION_DE_PAGO.docx',
            'docx_field' => 'archivo_constancia_terminacion_pago_docx',
            'scan_field' => 'archivo_constancia_terminacion_pago',
            'filename_slug' => 'constancia-terminacion-pago',
            'scan_filename' => 'constancia-terminacion-pago.pdf',
            'uses_fraccionamiento_template' => false,
        ],
        'convenio_pago' => [
            'label' => 'Convenio de pago',
            'template' => 'plantillas/CONVENIO_DE_PAGO_Mensual.docx',
            'templates_por_frecuencia' => [
                'semanal' => 'plantillas/CONVENIO_DE_PAGO_Semanal.docx',
                'mensual' => 'plantillas/CONVENIO_DE_PAGO_Mensual.docx',
            ],
            'docx_field' => 'archivo_convenio_pago_docx',
            'scan_field' => 'archivo_convenio_pago',
            'filename_slug' => 'convenio-pago',
            'scan_filename' => 'convenio-pago.pdf',
            'uses_fraccionamiento_template' => false,
        ],
        'convenio_pago_reconocimiento_adeudo' => [
            'label' => 'Convenio de pago y reconocimiento de adeudo',
            'template' => 'plantillas/CONVENIO_DE_PAGO_Y_RECONOCIMIENTO_DE_ADEUDO.docx',
            'docx_field' => 'archivo_convenio_pago_reconocimiento_adeudo_docx',
            'scan_field' => 'archivo_convenio_pago_reconocimiento_adeudo',
            'filename_slug' => 'convenio-pago-reconocimiento-adeudo',
            'scan_filename' => 'convenio-pago-reconocimiento-adeudo.pdf',
            'uses_fraccionamiento_template' => false,
        ],
        'anexo_cambio_dia_pago' => [
            'label' => 'Anexo: cambio de día de pago',
            'template' => 'plantillas/ANEXO_CAMBIO_DIA_PAGO.docx',
            'docx_field' => 'archivo_anexo_cambio_dia_pago_docx',
            'scan_field' => 'archivo_anexo_cambio_dia_pago',
            'filename_slug' => 'anexo-cambio-dia-pago',
            'scan_filename' => 'anexo-cambio-dia-pago.pdf',
            'uses_fraccionamiento_template' => false,
        ],
        'contrato_cesion_derechos' => [
            'label' => 'Contrato de cesión de derechos posesorios',
            'template' => 'plantillas/CONTRATO_CESION_DERECHOS_POSESORIOS.docx',
            'docx_field' => 'archivo_contrato_cesion_docx',
            'scan_field' => 'archivo_contrato_cesion',
            'filename_slug' => 'contrato-cesion-derechos',
            'scan_filename' => 'contrato-cesion-derechos.pdf',
            'uses_fraccionamiento_template' => false,
            // Es la cesión del lote: no aplica a contratos de servicio.
            'tipos' => ['terreno'],
        ],
        'caratula_expediente' => [
            'label' => 'Carátula y control del expediente (uso interno)',
            'template' => 'plantillas/CARATULA_CONTROL_EXPEDIENTE.docx',
            'docx_field' => 'archivo_caratula_expediente_docx',
            'scan_field' => 'archivo_caratula_expediente',
            'filename_slug' => 'caratula-expediente',
            'scan_filename' => 'caratula-expediente.pdf',
            'uses_fraccionamiento_template' => false,
            // Acompaña al contrato de cesión: no se firma, se archiva.
            'tipos' => ['terreno'],
            'uso_interno' => true,
        ],
        'contrato_financiamiento_electrico' => [
            'label' => 'Contrato de financiamiento eléctrico',
            'template' => 'plantillas/CONTRATO_FINANCIAMIENTO_INFRAESTRUCTURA_ELECTRICA.docx',
            'docx_field' => 'archivo_contrato_financiamiento_electrico_docx',
            'scan_field' => 'archivo_contrato_financiamiento_electrico',
            'filename_slug' => 'contrato-financiamiento-electrico',
            'scan_filename' => 'contrato-financiamiento-electrico.pdf',
            'uses_fraccionamiento_template' => false,
            // Solo aplica a contratos de servicio de luz.
            'servicio_tipos' => ['electricidad'],
        ],
    ];

    public static function documentTypes(): array
    {
        return collect(self::DOCUMENT_TYPES)
            ->map(fn (array $document, string $key) => ['key' => $key] + $document)
            ->all();
    }

    public static function documentType(string $key): ?array
    {
        $document = self::DOCUMENT_TYPES[$key] ?? null;

        return $document ? ['key' => $key] + $document : null;
    }

    public static function validDocumentTypeKeys(): array
    {
        return array_keys(self::DOCUMENT_TYPES);
    }

    /**
     * Documentos disponibles para un contrato concreto.
     *
     * Algunos formatos solo aplican a cierto tipo de servicio (por ejemplo, el
     * contrato de financiamiento eléctrico solo existe para contratos de luz).
     */
    public static function documentTypesFor(?Contrato $contrato): array
    {
        return array_filter(
            self::documentTypes(),
            fn (array $documento) => self::aplicaAContrato($documento, $contrato)
        );
    }

    public static function documentTypeFor(string $key, ?Contrato $contrato): ?array
    {
        $documento = self::documentType($key);

        if (! $documento || ! self::aplicaAContrato($documento, $contrato)) {
            return null;
        }

        return $documento;
    }

    public static function aplicaAContrato(array $documento, ?Contrato $contrato): bool
    {
        $tipos = $documento['tipos'] ?? null;
        $servicioTipos = $documento['servicio_tipos'] ?? null;
        $soloContado = (bool) ($documento['solo_contado'] ?? false);

        if (empty($tipos) && empty($servicioTipos) && ! $soloContado) {
            return $contrato === null || ! $contrato->es_contado || ! self::esDeFinanciamiento($documento);
        }

        if (! $contrato) {
            return false;
        }

        // Un contrato de contado lleva su propio formato en vez del de plazos.
        if ($soloContado !== (bool) $contrato->es_contado) {
            return false;
        }

        $tipoContrato = (string) ($contrato->tipo ?: 'terreno');

        if (! empty($tipos) && ! in_array($tipoContrato, $tipos, true)) {
            return false;
        }

        if (empty($servicioTipos)) {
            return true;
        }

        return $tipoContrato === 'servicio'
            && in_array((string) $contrato->servicio_tipo, $servicioTipos, true);
    }

    /**
     * Formatos que solo tienen sentido con un saldo a plazos: en una venta de
     * contado no hay calendario que convenir ni adeudo que reconocer.
     */
    protected static function esDeFinanciamiento(array $documento): bool
    {
        return in_array(
            $documento['docx_field'] ?? '',
            [
                'archivo_contrato_docx',
                'archivo_convenio_pago_docx',
                'archivo_convenio_pago_reconocimiento_adeudo_docx',
                'archivo_anexo_cambio_dia_pago_docx',
            ],
            true
        );
    }

    public function generarDocx(Contrato $contrato, string $tipo = 'contrato'): string
    {
        $documento = self::documentType($tipo);

        if (! $documento) {
            throw new RuntimeException('Tipo de documento no válido.');
        }

        if (! self::aplicaAContrato($documento, $contrato)) {
            throw new RuntimeException(
                'El documento '.($documento['label'] ?? $tipo).' no aplica para este contrato.'
            );
        }

        $contrato->loadMissing([
            'cliente',
            'lote.fraccionamiento',
        ]);

        $template = new ContratoTemplateProcessor(
            $this->resolveTemplatePath($contrato, $documento)
        );

        $this->setCommonValues($template, $contrato);

        if ($tipo === 'contrato_financiamiento_electrico') {
            $this->setFinanciamientoElectricoValues($template, $contrato);
        }

        if ($tipo === 'contrato_contado') {
            $this->setContadoValues($template, $contrato);
        }

        if ($tipo === 'contrato_contado_terreno') {
            // El resto del formato es el del contrato a plazos, que ya llena
            // setCommonValues; solo falta decir cómo se pagó.
            $template->setValue('forma_pago', $this->linea($this->formaPagoDeContado($contrato), 30));
        }

        if ($tipo === 'contrato_cesion_derechos') {
            $this->setCesionValues($template, $contrato);
            $template->ajustarPaginacionCesion();
            $template->agregarAnexosIdentificacion(app(AnexosIdentificacionService::class)->preparar($contrato));
            $template->agregarAnexoPlano(app(AnexoPlanoService::class)->preparar($contrato));
        }

        if ($tipo === 'caratula_expediente') {
            $this->setCaratulaValues($template, $contrato);
        }

        if ($tipo === 'anexo_cambio_dia_pago') {
            $this->setAnexoCambioDiaPagoValues($template, $contrato);
        }

        // Las hojas sueltas no dicen de quien son: de la segunda en adelante
        // llevan arriba el nombre y el lote.
        $template->identificarHojasSiguientes($this->identificadorDeHoja($contrato));

        $this->setImageIfExists($template, 'vendedor_ine_frente', $contrato->vendedor_ine_frente);
        $this->setImageIfExists($template, 'vendedor_ine_reverso', $contrato->vendedor_ine_reverso);
        $this->setImageIfExists($template, 'comprador_ine_frente', $contrato->comprador_ine_frente);
        $this->setImageIfExists($template, 'comprador_ine_reverso', $contrato->comprador_ine_reverso);

        $relativePath = $this->buildRelativePath($contrato, $documento);
        $absolutePath = Storage::disk('private')->path($relativePath);

        $dir = dirname($absolutePath);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $template->saveAs($absolutePath);
        $this->limpiarMetadatosPersonales($absolutePath);

        return $relativePath;
    }

    /**
     * Lo que identifica al contrato en el encabezado: quien lo firma y el
     * numero de lote.
     *
     * Con el folio bastaria para el sistema, pero quien acomoda papeles busca
     * por nombre.
     */
    private function identificadorDeHoja(Contrato $contrato): string
    {
        $cliente = $contrato->cliente;

        $nombre = trim((string) (
            $contrato->comprador_nombre_legal
            ?: ($cliente?->nombre_legal ?: $cliente?->nombre_completo)
        ));

        $numero = trim((string) $contrato->lote?->lote);

        return collect([$nombre, $numero !== '' ? 'Lote '.$numero : null])
            ->filter()
            ->implode('  ·  ');
    }

    /**
     * ¿El documento guardado se quedó atrás de su plantilla?
     *
     * Los documentos se generan una sola vez y se reusan, así que al cambiar
     * una plantilla los que ya existían seguirían saliendo con el texto viejo.
     * Se compara contra la plantilla que le toca a este contrato, que puede ser
     * la del fraccionamiento y no la general.
     */
    public function estaDesactualizado(Contrato $contrato, string $tipo): bool
    {
        $documento = self::documentTypeFor($tipo, $contrato);

        if (! $documento) {
            return false;
        }

        $disk = $contrato->archivo_contrato_disk ?: 'private';
        $relativePath = $contrato->{$documento['docx_field']};

        // Sin archivo no hay nada que comparar: hay que generarlo.
        if (! $relativePath || ! Storage::disk($disk)->exists($relativePath)) {
            return true;
        }

        try {
            $plantilla = $this->resolveTemplatePath($contrato, $documento);
        } catch (RuntimeException) {
            // Sin plantilla instalada no se puede regenerar: se deja como está.
            return false;
        }

        $generado = Storage::disk($disk)->path($relativePath);

        $fechaPlantilla = @filemtime($plantilla);
        $fechaGenerado = @filemtime($generado);

        if ($fechaPlantilla === false || $fechaGenerado === false) {
            return false;
        }

        return $fechaPlantilla > $fechaGenerado;
    }

    public function limpiarMetadatosPersonales(string $absolutePath): void
    {
        $zip = new ZipArchive;
        $resultado = $zip->open($absolutePath);

        if ($resultado !== true) {
            throw new RuntimeException('No se pudo abrir el documento Word para limpiar sus metadatos.');
        }

        try {
            $coreXml = $zip->getFromName('docProps/core.xml');

            if ($coreXml === false) {
                return;
            }

            $coreXmlLimpio = preg_replace(
                [
                    '#<dc:creator(?:\s[^>]*)?>.*?</dc:creator>#si',
                    '#<dc:creator(?:\s[^>]*)?/>#si',
                    '#<cp:lastModifiedBy(?:\s[^>]*)?>.*?</cp:lastModifiedBy>#si',
                    '#<cp:lastModifiedBy(?:\s[^>]*)?/>#si',
                ],
                '',
                $coreXml
            );

            if ($coreXmlLimpio === null || ! $zip->addFromString('docProps/core.xml', $coreXmlLimpio)) {
                throw new RuntimeException('No se pudieron limpiar los metadatos del documento Word.');
            }
        } finally {
            $zip->close();
        }
    }

    /**
     * Genera todos los documentos que aplican al contrato.
     *
     * Un formato sin plantilla instalada se omite: dar de alta el contrato no
     * debe fallar por eso, y el documento se puede generar después desde la
     * pantalla del contrato.
     */
    public function generarTodosDocx(Contrato $contrato): array
    {
        $paths = [];

        foreach (self::documentTypesFor($contrato) as $key => $documento) {
            if (! $this->tienePlantilla($contrato, $documento)) {
                continue;
            }

            $paths[$documento['docx_field']] = $this->generarDocx($contrato, $key);
        }

        return $paths;
    }

    protected function tienePlantilla(Contrato $contrato, array $documento): bool
    {
        try {
            $this->resolveTemplatePath($contrato, $documento);

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    protected function setCommonValues(TemplateProcessor $template, Contrato $contrato): void
    {
        $template->setValue('folio_contrato', $this->safe($contrato->folio_contrato));

        $template->setValue('vendedor_nombre', $this->safe($contrato->vendedor_nombre_legal));
        $template->setValue('vendedor_nombre_legal', $this->safe($contrato->vendedor_nombre_legal));
        $template->setValue('vendedor_curp', $this->safe($contrato->vendedor_curp));
        $template->setValue('comprador_nombre', $this->safe($contrato->comprador_nombre_legal));
        $template->setValue('comprador_nombre_legal', $this->safe($contrato->comprador_nombre_legal));
        $template->setValue('comprador_curp', $this->safe($contrato->comprador_curp));

        $template->setValue('fraccionamiento', $this->safe(optional($contrato->lote?->fraccionamiento)->nombre));
        // Alias para una plantilla (CONSTANCIA) cuyo placeholder trae un typo: ${francionamiento}.
        $template->setValue('francionamiento', $this->safe(optional($contrato->lote?->fraccionamiento)->nombre));
        $template->setValue('lote', $this->safe($contrato->lote?->lote));
        $template->setValue('manzana', $this->safe($contrato->lote?->manzana));
        $template->setValue('clave_lote', $this->safe($contrato->lote?->clave));

        $area = $this->areaDelLote($contrato);

        // Sin superficie capturada se deja la raya para llenarla a mano: un
        // 0.00 en un documento legal seria un dato falso.
        $template->setValue('area_m2', $area > 0 ? number_format($area, 2) : '');
        $template->setValue('superficie', $this->linea($area > 0 ? number_format($area, 2).' m²' : null, 20));

        $template->setValue('medida_norte', $this->formatMedida($contrato->medida_norte_snapshot));
        $template->setValue('medida_sur', $this->formatMedida($contrato->medida_sur_snapshot));
        $template->setValue('medida_este', $this->formatMedida($contrato->medida_este_snapshot));
        $template->setValue('medida_oeste', $this->formatMedida($contrato->medida_oeste_snapshot));

        $template->setValue('colindancia_norte', $this->safe($contrato->colindancia_norte_snapshot));
        $template->setValue('colindancia_sur', $this->safe($contrato->colindancia_sur_snapshot));
        $template->setValue('colindancia_este', $this->safe($contrato->colindancia_este_snapshot));
        $template->setValue('colindancia_oeste', $this->safe($contrato->colindancia_oeste_snapshot));

        $template->setValue('precio_total', $this->formatMoneyWithText($contrato->precio_total));
        $template->setValue('enganche', $this->formatMoneyWithText($contrato->enganche));
        $template->setValue('saldo_inicial', $this->formatMoneyWithText($contrato->saldo_inicial));
        $template->setValue('saldo_actual', $this->formatMoneyWithText($contrato->saldo_actual));
        $template->setValue('monto_pago', $this->formatMoneyWithText($contrato->monto_pago));
        $template->setValue('valor_recargo', $this->formatMoneyWithText($contrato->valor_recargo));

        // Convenio de pago: deuda vencida y número de cuotas atrasadas a la fecha.
        [$deudaVencida, $numCuotasVencidas] = $this->calcularDeudaVencida($contrato);
        $template->setValue('deuda', $this->formatMoneyWithText($deudaVencida));
        $template->setValue('n_recibos', (string) $numCuotasVencidas);
        // La plantilla CONVENIO_DE_PAGO trae el placeholder con espacios: ${ n_recibos }.
        $template->setValue(' n_recibos ', (string) $numCuotasVencidas);

        $template->setValue(
            'frecuencia_texto',
            $contrato->frecuencia === 'semanal' ? 'semanales' : 'mensuales'
        );

        $template->setValue(
            'fecha_inicio',
            $contrato->fecha_inicio ? $contrato->fecha_inicio->format('d/m/Y') : ''
        );

        $template->setValue(
            'fecha_firma',
            mb_strtoupper(now()->translatedFormat('d \\d\\e F \\d\\e Y'), 'UTF-8')
        );

        $template->setValue('fecha_actual', now()->format('d/m/Y'));

        $template->setValue(
            'fecha_completa',
            now()->day.' del mes de '.now()->translatedFormat('F').' del año '.now()->year
        );

        $template->setValue(
            'fecha_hoy',
            now()->day.' de '.now()->translatedFormat('F').' del '.now()->year
        );
    }

    /**
     * Datos exclusivos del contrato de financiamiento de infraestructura eléctrica.
     *
     * Lo que no existe en el sistema (forma de pago, identificación oficial, etc.)
     * se deja como línea en blanco para llenarse a mano.
     */
    /**
     * Quiénes firman y sobre qué lotes. Lo comparten el contrato de
     * financiamiento eléctrico y el de contado, que salieron del mismo formato.
     */
    protected function setDatosDeLasPartes(TemplateProcessor $template, Contrato $contrato): void
    {
        $contrato->loadMissing([
            'cliente',
            'lote.fraccionamiento.propietario',
            'contratoBase',
        ]);

        $propietario = $contrato->lote?->fraccionamiento?->propietario;
        $cliente = $contrato->cliente;

        $template->setValue('proveedor_nombre', $this->linea(
            $propietario?->nombre_legal ?: ($propietario?->nombre ?: $contrato->vendedor_nombre_legal)
        ));

        $template->setValue('cliente_nombre', $this->linea(
            $contrato->comprador_nombre_legal ?: ($cliente?->nombre_legal ?: $cliente?->nombre_completo)
        ));

        // El contrato ampara todos los lotes del cliente, no solo el que se usó para darlo de alta.
        $this->setLotesValues($template, $contrato);

        $template->setValue('cliente_curp', $this->linea($cliente?->curp ?: $contrato->comprador_curp));
        $template->setValue('cliente_rfc', $this->linea($cliente?->rfc));
        $template->setValue('cliente_domicilio', $this->linea($cliente?->direccion, 45));
        $template->setValue('cliente_telefono', $this->linea($cliente?->telefono, 20));
        $template->setValue('cliente_correo', $this->linea($cliente?->correo, 35));
        $template->setValue('cliente_identificacion', $this->linea(
            $contrato->comprador_ine_frente ? 'Credencial para votar (INE)' : null,
            35
        ));
    }

    /**
     * Contrato pagado de contado.
     *
     * Usa el mismo vocabulario que el de financiamiento porque salió de ese
     * formato, pero las cláusulas de crédito se llenan como lo que realmente
     * pasó: un solo pago por el total, sin enganche ni plazos.
     */
    protected function setContadoValues(TemplateProcessor $template, Contrato $contrato): void
    {
        $this->setDatosDeLasPartes($template, $contrato);

        $precioTotal = (float) $contrato->precio_total;

        $template->setValue('precio_total_numero', $this->formatMoney($precioTotal));
        $template->setValue('precio_total_letra', $this->montoEnLetra($precioTotal));

        // No hay crédito: nada de enganche y el total se cubre de una vez.
        $template->setValue('enganche_numero', $this->formatMoney(0));
        $template->setValue('saldo_financiado_numero', $this->formatMoney($precioTotal));
        $template->setValue('numero_pagos', '1');
        $template->setValue('monto_pago_numero', $this->formatMoney($precioTotal));

        $template->setValue('forma_pago', $this->linea($this->formaPagoDeContado($contrato), 40));

        $template->setValue('ciudad_firma', 'Piedras Negras');
        $template->setValue('fecha_firma_larga', $this->fechaLarga($contrato->liquidado_at ?: now()));
    }

    /**
     * Cómo se pagó, tomado del recibo de contado que emitió el contrato.
     */
    protected function formaPagoDeContado(Contrato $contrato): ?string
    {
        return Recibo::query()
            ->where('contrato_id', $contrato->id)
            ->whereHas('tipoCobro', fn ($q) => $q->where('nombre', VentaContadoService::TIPO_COBRO))
            ->with('formaPago')
            ->latest('id')
            ->first()?->formaPago?->nombre;
    }

    protected function setFinanciamientoElectricoValues(TemplateProcessor $template, Contrato $contrato): void
    {
        $this->setDatosDeLasPartes($template, $contrato);

        $precioTotal = (float) $contrato->precio_total;

        $template->setValue('precio_total_numero', $this->formatMoney($precioTotal));
        $template->setValue('precio_total_letra', $this->montoEnLetra($precioTotal));
        $template->setValue('enganche_numero', $this->formatMoney((float) $contrato->enganche));
        $template->setValue('saldo_financiado_numero', $this->formatMoney((float) $contrato->saldo_inicial));
        $template->setValue('monto_pago_numero', $this->formatMoney((float) $contrato->monto_pago));

        [$numeroPagos, $primerPago, $ultimoPago] = $this->resumenCalendario($contrato);

        $template->setValue('numero_pagos', $this->linea($numeroPagos > 0 ? (string) $numeroPagos : null, 8));
        $template->setValue('fecha_primer_pago', $this->linea($this->fechaLarga($primerPago), 28));
        $template->setValue('fecha_ultimo_pago', $this->linea($this->fechaLarga($ultimoPago), 28));

        $template->setValue('periodicidad', $contrato->frecuencia === 'semanal' ? 'Semanal' : 'Mensual');
        $template->setValue('dias_pago', $this->linea($this->diasPagoTexto($contrato), 30));
        $template->setValue('forma_pago', $this->linea(null, 40));

        $template->setValue('recargo_mora', $this->recargoMoraTexto($contrato));
        $template->setValue('dias_incumplimiento', $this->linea(
            ((int) $contrato->dias_gracia) > 0 ? (string) (int) $contrato->dias_gracia : null,
            6
        ));

        $template->setValue('ciudad_firma', 'Piedras Negras');
        $template->setValue('fecha_firma_larga', $this->fechaLarga(now()));
    }

    /**
     * Carátula de la operación y control interno del expediente.
     *
     * Lo que el sistema puede saber del expediente se responde solo; lo que
     * depende de la revisión física queda como línea para palomear a mano.
     */
    protected function setCaratulaValues(TemplateProcessor $template, Contrato $contrato): void
    {
        $contrato->loadMissing(['cliente', 'lote.fraccionamiento.propietario']);

        $fraccionamiento = $contrato->lote?->fraccionamiento;
        $propietario = $fraccionamiento?->propietario;
        $cliente = $contrato->cliente;

        $template->setValue('cedente_nombre', $this->linea(
            $contrato->vendedor_nombre_legal ?: ($propietario?->nombre_legal ?: $propietario?->nombre)
        ));

        $template->setValue('adquirente_nombre', $this->linea(
            $contrato->comprador_nombre_legal ?: ($cliente?->nombre_legal ?: $cliente?->nombre_completo)
        ));

        $template->setValue('ejido', $this->linea($fraccionamiento?->ejido, 30));
        $template->setValue('municipio', $this->linea($fraccionamiento?->municipio, 25));

        $precioTotal = (float) $contrato->precio_total;

        $template->setValue('precio_total_numero', $this->formatMoney($precioTotal));
        $template->setValue('precio_total_letra', $this->montoEnLetra($precioTotal));
        $template->setValue('enganche_numero', $this->formatMoney((float) $contrato->enganche));
        $template->setValue('saldo_financiado_numero', $this->formatMoney((float) $contrato->saldo_inicial));
        $template->setValue('monto_pago_numero', $this->montoPagoResumen($contrato));
        $template->setValue('periodicidad', $contrato->frecuencia === 'semanal' ? 'Semanal' : 'Mensual');

        [$numeroPagos] = $this->resumenCalendario($contrato);

        $template->setValue('numero_pagos', $this->linea($numeroPagos > 0 ? (string) $numeroPagos : null, 6));
        $template->setValue('fecha_firma_larga', $this->fechaLarga(now()));

        // Control interno: lo que el sistema sí puede contestar.
        $template->setValue('fecha_alta', $this->linea(
            $contrato->created_at?->format('d/m/Y'),
            18
        ));
        $template->setValue('expediente_completo', $this->siNo($this->expedienteCompleto($contrato)));
        $template->setValue('identificaciones', $this->siNo(
            app(AnexosIdentificacionService::class)->completos($contrato)
        ));
        $template->setValue('antecedente_agrario', $this->antecedenteAgrarioTexto($contrato));

        // Lo que depende de revisar el expediente a mano.
        $template->setValue('asesor', $this->linea(null, 30));
        $template->setValue('plano_firmado', 'SÍ / NO');
        $template->setValue('observaciones', $this->linea(null, 60));
    }

    /**
     * Anexo que firma el cliente al pedir otro día de pago.
     *
     * Se imprime después de cambiar el día en el contrato, así que el día que
     * tiene guardado ya es el nuevo. Si no lo tiene, queda la raya para
     * llenarlo a mano.
     */
    protected function setAnexoCambioDiaPagoValues(TemplateProcessor $template, Contrato $contrato): void
    {
        if ($contrato->frecuencia === 'semanal') {
            $dia = ContratoPlanService::diasSemana()[(int) $contrato->dia_semana] ?? null;
            $periodo = 'semana';
        } else {
            $dia = $contrato->dia_mes ? (string) (int) $contrato->dia_mes : null;
            $periodo = 'mes';
        }

        $template->setValue('nuevo_dia_pago', $this->linea($dia ? mb_strtoupper($dia, 'UTF-8') : null, 10));
        $template->setValue('periodo_pago', $periodo);

        $this->setRecargoAnexoValues($template, $contrato);

        $cliente = $contrato->cliente;

        $template->setValue('firmante_nombre', mb_strtoupper($this->linea(
            $contrato->comprador_nombre_legal ?: ($cliente?->nombre_legal ?: $cliente?->nombre_completo)
        ), 'UTF-8'));
    }

    /**
     * El recargo como lo cobra el sistema (RecargoCuotaCalculator): el primero
     * cae al día siguiente de que terminan los días de gracia y se repite cada
     * tantos días como diga el contrato.
     *
     * Un contrato sin recargo capturado deja la raya para llenarla a mano.
     */
    protected function setRecargoAnexoValues(TemplateProcessor $template, Contrato $contrato): void
    {
        $valor = (float) $contrato->valor_recargo;

        if ($valor <= 0) {
            $monto = '$__________';
        } elseif ($contrato->tipo_recargo === 'porcentaje') {
            $monto = rtrim(rtrim(number_format($valor, 2), '0'), '.').'% sobre el monto del pago vencido';
        } else {
            $entero = (int) floor($valor);
            $centavos = (int) round(($valor - $entero) * 100);

            $monto = $this->formatMoney($valor)
                .' ('.mb_strtolower($this->numeroATexto($entero), 'UTF-8').' pesos '
                .str_pad((string) $centavos, 2, '0', STR_PAD_LEFT).'/100 M.N.)';
        }

        $cadaCuantos = max(1, (int) ($contrato->frecuencia_recargo_dias ?: 1));
        $diasGracia = max(0, (int) $contrato->dias_gracia);

        $template->setValue('recargo_monto', $monto);
        $template->setValue('recargo_periodo', $cadaCuantos === 1
            ? 'cada día natural de atraso'
            : 'cada '.$cadaCuantos.' días naturales de atraso');
        $template->setValue('recargo_inicio', $diasGracia === 0
            ? 'a partir del día siguiente a la fecha de pago acordada'
            : 'a partir del día siguiente a que se cumplan '.$diasGracia
                .($diasGracia === 1 ? ' día natural' : ' días naturales')
                .' de gracia posteriores a la fecha de pago acordada');
    }

    /**
     * Todos los documentos que aplican al contrato tienen su PDF escaneado.
     */
    protected function expedienteCompleto(Contrato $contrato): bool
    {
        if (self::documentTypeFor('contrato_cesion_derechos', $contrato)
            && ! app(AnexosIdentificacionService::class)->completos($contrato)) {
            return false;
        }

        $documentos = array_filter(
            self::documentTypesFor($contrato),
            fn (array $documento) => empty($documento['uso_interno'])
        );

        if ($documentos === []) {
            return false;
        }

        foreach ($documentos as $documento) {
            if (blank($contrato->{$documento['scan_field']})) {
                return false;
            }
        }

        return true;
    }

    protected function antecedenteAgrarioTexto(Contrato $contrato): string
    {
        $fraccionamiento = $contrato->lote?->fraccionamiento;

        if (blank($fraccionamiento?->ejido)) {
            return 'NO APLICA';
        }

        return $this->siNo(filled($fraccionamiento?->antecedente_tipo));
    }

    protected function siNo(bool $valor): string
    {
        return $valor ? 'SÍ' : 'NO';
    }

    /**
     * Contrato de cesión onerosa de derechos posesorios sobre el lote.
     *
     * Lo que el sistema no tiene capturado se deja como línea en blanco para
     * llenarse a mano, igual que en el contrato de luz.
     */
    protected function setCesionValues(TemplateProcessor $template, Contrato $contrato): void
    {
        $contrato->loadMissing([
            'cliente',
            'lote.fraccionamiento.propietario.cuentasBancarias',
        ]);

        $fraccionamiento = $contrato->lote?->fraccionamiento;
        $propietario = $fraccionamiento?->propietario;
        $cliente = $contrato->cliente;

        // Partes: el propietario cede, el cliente adquiere.
        $this->setParteValues($template, 'cedente', [
            'nombre' => $contrato->vendedor_nombre_legal
                ?: ($propietario?->nombre_legal ?: $propietario?->nombre),
            'curp' => $contrato->vendedor_curp ?: $propietario?->curp,
            'rfc' => $propietario?->rfc,
            'domicilio' => $propietario?->direccion,
            'telefono' => $propietario?->telefono,
            'correo' => $propietario?->correo,
            'persona' => $propietario,
            'ine' => $contrato->vendedor_ine_frente,
        ]);

        $this->setParteValues($template, 'adquirente', [
            'nombre' => $contrato->comprador_nombre_legal
                ?: ($cliente?->nombre_legal ?: $cliente?->nombre_completo),
            'curp' => $contrato->comprador_curp ?: $cliente?->curp,
            'rfc' => $cliente?->rfc,
            'domicilio' => $cliente?->direccion,
            'telefono' => $cliente?->telefono,
            'correo' => $cliente?->correo,
            'persona' => $cliente,
            'ine' => $contrato->comprador_ine_frente,
        ]);

        // Predio: de dónde vienen los derechos y dónde está.
        $template->setValue('antecedente_tipo', $this->linea($fraccionamiento?->antecedente_tipo, 35));
        $template->setValue('antecedente_datos', $this->linea($fraccionamiento?->antecedente_datos, 35));
        $template->setValue('proyecto_ubicacion', $this->linea($fraccionamiento?->ubicacion, 35));
        $template->setValue('ejido', $this->linea($fraccionamiento?->ejido, 30));
        $template->setValue('municipio', $this->linea($fraccionamiento?->municipio, 25));

        $template->setValue('lote_identificacion', $this->linea($this->descripcionLoteCesion($contrato)));

        foreach (['norte', 'sur', 'este', 'oeste'] as $lado) {
            $template->setValue('lote_'.$lado, $this->linea($this->ladoLote($contrato, $lado), 40));
        }

        // Precio y calendario.
        $precioTotal = (float) $contrato->precio_total;

        $template->setValue('precio_total_numero', $this->formatMoney($precioTotal));
        $template->setValue('precio_total_letra', $this->montoEnLetra($precioTotal));
        $template->setValue('enganche_numero', $this->formatMoney((float) $contrato->enganche));
        $template->setValue('saldo_financiado_numero', $this->formatMoney((float) $contrato->saldo_inicial));
        $template->setValue('monto_pago_numero', $this->montoPagoEnClausula($contrato));

        [$numeroPagos, $primerPago] = $this->resumenCalendario($contrato);

        $template->setValue('numero_pagos', $this->linea($numeroPagos > 0 ? (string) $numeroPagos : null, 8));
        $template->setValue('fecha_primer_pago', $this->linea($this->fechaLarga($primerPago), 28));
        $template->setValue(
            'periodicidad_texto',
            $contrato->frecuencia === 'semanal' ? 'semanales' : 'mensuales'
        );
        $template->setValue('dias_pago', $this->linea($this->diasPagoFrase($contrato), 30));
        $template->setValue('datos_pago', $this->linea($this->datosPago($propietario), 40));

        $template->setValue('recargo_mora', $this->recargoMoraTexto($contrato));
        $template->setValue('dias_gracia', $this->linea(
            ((int) $contrato->dias_gracia) > 0 ? (string) (int) $contrato->dias_gracia : null,
            6
        ));

        // Condiciones elegidas para este contrato (o las de ajustes del sistema).
        $condiciones = CondicionesCesionService::paraContrato($contrato);

        $template->setValue('posesion_entrega', $this->safe($condiciones['posesion_entrega']));
        $template->setValue('servicios_estado', $this->safe($condiciones['servicios_estado']));
        $template->setValue('servicios_a_cargo', $this->safe($condiciones['servicios_a_cargo']));
        $template->setValue('parcialidades_rescision', $this->safe($condiciones['parcialidades_rescision']));
        $template->setValue('tolerancia_medidas', $this->safe($condiciones['tolerancia_medidas']));

        $template->setValue('fecha_firma_larga', $this->fechaLarga(now()));
    }

    /**
     * Datos con los que se identifica una de las partes en su declaración a).
     *
     * @param  array<string, mixed>  $datos
     */
    protected function setParteValues(TemplateProcessor $template, string $parte, array $datos): void
    {
        $persona = $datos['persona'] ?? null;

        $template->setValue($parte.'_nombre', $this->linea($datos['nombre']));
        $template->setValue($parte.'_persona_tipo', $this->linea(
            $this->personaTipoTexto($persona?->persona_tipo),
            16
        ));
        $template->setValue($parte.'_nacionalidad', $this->linea($persona?->nacionalidad, 14));
        $template->setValue($parte.'_estado_civil', $this->linea($persona?->estado_civil, 14));
        $template->setValue($parte.'_ocupacion', $this->linea($persona?->ocupacion, 14));
        $template->setValue($parte.'_domicilio', $this->linea($datos['domicilio'] ?? null, 40));
        $template->setValue($parte.'_curp', $this->linea($datos['curp'] ?? null, 20));
        $template->setValue($parte.'_rfc', $this->linea($datos['rfc'] ?? null, 16));
        $template->setValue($parte.'_telefono', $this->linea($datos['telefono'] ?? null, 14));
        $template->setValue($parte.'_correo', $this->linea($datos['correo'] ?? null, 24));

        // Si no se capturó el tipo, se toma INE cuando hay credencial en el expediente.
        $identificacion = $persona?->identificacion_tipo
            ?: (($datos['ine'] ?? null) ? 'INE' : null);

        $template->setValue($parte.'_identificacion', $this->linea($identificacion, 14));
        $template->setValue($parte.'_identificacion_numero', $this->linea($persona?->identificacion_numero, 18));
    }

    protected function personaTipoTexto(?string $tipo): ?string
    {
        return match (mb_strtolower(trim((string) $tipo))) {
            'fisica' => 'persona física',
            'moral' => 'persona moral',
            default => null,
        };
    }

    /**
     * «Lote 12, Manzana 5, Rancho El Pato»: el lote de este contrato.
     */
    protected function descripcionLoteCesion(Contrato $contrato): string
    {
        $partes = array_filter([
            $contrato->lote?->lote ? 'Lote '.$contrato->lote->lote : '',
            $contrato->lote?->manzana ? 'Manzana '.$contrato->lote->manzana : '',
            $this->safe($contrato->lote?->fraccionamiento?->nombre),
        ]);

        return $partes === []
            ? $this->safe($contrato->lote?->clave)
            : implode(', ', $partes);
    }

    /**
     * Medida y colindancia de un lado del lote, como quedaron en el contrato.
     */
    protected function ladoLote(Contrato $contrato, string $lado): string
    {
        $medida = $contrato->{'medida_'.$lado.'_snapshot'} ?: $contrato->lote?->{'medida_'.$lado};
        $colindancia = $contrato->{'colindancia_'.$lado.'_snapshot'} ?: $contrato->lote?->{'colindancia_'.$lado};

        $partes = [];

        if ($medida !== null && (float) $medida > 0) {
            $partes[] = number_format((float) $medida, 2).' m';
        }

        if ($this->safe($colindancia) !== '') {
            $partes[] = 'colinda con '.$this->safe($colindancia);
        }

        return implode(', ', $partes);
    }

    /**
     * Cómo continúan los pagos después del primero.
     */
    protected function diasPagoFrase(Contrato $contrato): string
    {
        if ($contrato->frecuencia === 'semanal') {
            $dia = ContratoPlanService::diasSemana()[(int) $contrato->dia_semana] ?? null;

            return $dia ? 'cada '.mb_strtolower($dia, 'UTF-8') : '';
        }

        return $contrato->dia_mes
            ? 'los días '.(int) $contrato->dia_mes.' de cada mes'
            : '';
    }

    /**
     * Cuentas donde el propietario recibe los pagos.
     */
    protected function datosPago(?Propietario $propietario): string
    {
        if (! $propietario) {
            return '';
        }

        return $propietario->cuentasBancarias
            ->where('activa', true)
            ->map(fn (CuentaBancaria $cuenta) => trim(implode(' ', array_filter([
                $this->safe($cuenta->banco),
                $this->safe($cuenta->tipo),
                $this->safe($cuenta->numero),
            ]))))
            ->filter()
            ->implode('; ');
    }

    /**
     * Menciona en el contrato eléctrico todos los lotes del cliente.
     *
     * No se detallan uno por uno: los números van juntos («Lotes 19 y 20») igual
     * que las manzanas, los desarrollos y los contratos de terreno relacionados.
     */
    protected function setLotesValues(TemplateProcessor $template, Contrato $contrato): void
    {
        $lotes = LotesClienteService::resumen($contrato);

        $template->setValue('lote_identificacion', $this->linea($lotes['identificacion']));

        $template->setValue('lotes_numero', $this->linea($lotes['numeros'], 20));
        $template->setValue('lotes_manzana', $this->linea($lotes['manzanas'], 20));
        $template->setValue('lotes_fraccionamiento', $this->linea($lotes['fraccionamientos'], 30));
        $template->setValue('contrato_relacionado', $this->linea($lotes['contratos'], 25));

        // Las medidas son solo las del lote principal: el del propio contrato.
        $template->setValue('medidas', $this->linea($this->descripcionMedidas($contrato), 45));
    }

    /**
     * Medidas y superficie del lote del contrato, como quedaron al firmarse.
     */
    protected function descripcionMedidas(Contrato $contrato): string
    {
        $medidas = [
            'Norte' => $contrato->medida_norte_snapshot ?: $contrato->lote?->medida_norte,
            'Sur' => $contrato->medida_sur_snapshot ?: $contrato->lote?->medida_sur,
            'Este' => $contrato->medida_este_snapshot ?: $contrato->lote?->medida_este,
            'Oeste' => $contrato->medida_oeste_snapshot ?: $contrato->lote?->medida_oeste,
        ];

        $partes = [];

        foreach ($medidas as $lado => $valor) {
            if ($valor === null || (float) $valor <= 0) {
                continue;
            }

            $partes[] = $lado.' '.number_format((float) $valor, 2).' m';
        }

        $area = (float) ($contrato->area_m2_snapshot ?: $contrato->lote?->area_m2);

        if ($area > 0) {
            $partes[] = 'superficie '.number_format($area, 2).' m²';
        }

        return implode(', ', $partes);
    }

    /**
     * Número de pagos y fechas de la primera y última parcialidad.
     *
     * @return array{0: int, 1: ?CarbonInterface, 2: ?CarbonInterface}
     */
    protected function resumenCalendario(Contrato $contrato): array
    {
        $cuotas = $contrato->cuotas()
            ->orderBy('numero')
            ->get(['fecha_vencimiento']);

        if ($cuotas->isNotEmpty()) {
            return [
                $cuotas->count(),
                $cuotas->first()?->fecha_vencimiento,
                $cuotas->last()?->fecha_vencimiento,
            ];
        }

        $montoPago = (float) $contrato->monto_pago;
        $numeroPagos = $montoPago > 0
            ? (int) ceil((float) $contrato->saldo_inicial / $montoPago)
            : 0;

        return [$numeroPagos, $contrato->fecha_inicio, null];
    }

    /**
     * El monto de cada pago como lo dice la cláusula: "... pagos semanales de
     * ${monto_pago_numero} M.N. cada uno". Con pago escalonado son dos montos,
     * y poner solo uno dejaría en el contrato un pago que no es.
     */
    protected function montoPagoEnClausula(Contrato $contrato): string
    {
        $iniciales = (int) $contrato->promocion?->pagosConMontoInicial((string) $contrato->frecuencia);

        if ($iniciales <= 0) {
            return $this->formatMoney((float) $contrato->monto_pago);
        }

        return $this->formatMoney((float) $contrato->promocion->monto_pago_inicial)
            .' M.N. cada uno los primeros '.$iniciales.' pagos, y de '
            .$this->formatMoney((float) $contrato->monto_pago);
    }

    /** Lo mismo para la carátula, que lo muestra en una celda. */
    protected function montoPagoResumen(Contrato $contrato): string
    {
        $iniciales = (int) $contrato->promocion?->pagosConMontoInicial((string) $contrato->frecuencia);

        if ($iniciales <= 0) {
            return $this->formatMoney((float) $contrato->monto_pago);
        }

        return $this->formatMoney((float) $contrato->promocion->monto_pago_inicial)
            .' los primeros '.$iniciales.' pagos; después '
            .$this->formatMoney((float) $contrato->monto_pago);
    }

    protected function diasPagoTexto(Contrato $contrato): string
    {
        if ($contrato->frecuencia === 'semanal') {
            $dia = ContratoPlanService::diasSemana()[(int) $contrato->dia_semana] ?? null;

            return $dia ? 'Cada '.mb_strtolower($dia, 'UTF-8') : '';
        }

        return $contrato->dia_mes
            ? 'Día '.(int) $contrato->dia_mes.' de cada mes'
            : '';
    }

    protected function recargoMoraTexto(Contrato $contrato): string
    {
        $valor = (float) $contrato->valor_recargo;

        if ($valor <= 0) {
            return $this->linea(null, 30);
        }

        if ($contrato->tipo_recargo === 'porcentaje') {
            return number_format($valor, 2).'% sobre la parcialidad vencida';
        }

        return $this->formatMoney($valor).' M.N. por dia de cada parcialidad vencida';
    }

    protected function fechaLarga($fecha): string
    {
        if (! $fecha) {
            return '';
        }

        return $fecha->translatedFormat('d \d\e F \d\e Y');
    }

    protected function formatMoney($amount): string
    {
        return '$'.number_format((float) $amount, 2);
    }

    protected function montoEnLetra($amount): string
    {
        $amount = (float) $amount;

        $entero = (int) floor($amount);
        $centavos = (int) round(($amount - $entero) * 100);

        $texto = mb_strtoupper($this->numeroATexto($entero), 'UTF-8');

        return $texto.' PESOS '.str_pad((string) $centavos, 2, '0', STR_PAD_LEFT).'/100 MONEDA NACIONAL';
    }

    /**
     * Valor listo para imprimir: el dato o una línea para llenarse a mano.
     */
    /**
     * Superficie del lote: la que quedó congelada al firmarse y, para los
     * contratos viejos que no la traen, la que tenga el lote.
     */
    protected function areaDelLote(Contrato $contrato): float
    {
        return (float) ($contrato->area_m2_snapshot ?: $contrato->lote?->area_m2);
    }

    protected function linea($valor, int $largo = 30): string
    {
        $valor = $this->safe($valor);

        return $valor !== '' ? $valor : str_repeat('_', $largo);
    }

    protected function buildRelativePath(Contrato $contrato, array $documento): string
    {
        $fraccionamientoNombre = $contrato->lote?->fraccionamiento?->nombre ?: 'sin-fraccionamiento';
        $fraccionamientoSlug = Str::slug($fraccionamientoNombre);

        $loteNombre = $contrato->lote?->lote
            ?: ($contrato->lote?->clave ?: 'sin-lote');

        $loteSlug = Str::slug($loteNombre);
        $folioSlug = Str::slug((string) ($contrato->folio_contrato ?: $contrato->uuid));

        return 'contratos/'
            .$fraccionamientoSlug
            .'/'
            .$loteSlug
            .'/'
            .$contrato->uuid
            .'/'
            .$documento['filename_slug']
            .'-'
            .$folioSlug
            .'.docx';
    }

    protected function resolveTemplatePath(Contrato $contrato, array $documento): string
    {
        $fraccionamiento = $contrato->lote?->fraccionamiento;

        if (
            ($documento['uses_fraccionamiento_template'] ?? false) &&
            $fraccionamiento &&
            ! empty($fraccionamiento->contrato_base_path) &&
            Storage::disk('private')->exists($fraccionamiento->contrato_base_path)
        ) {
            return Storage::disk('private')->path($fraccionamiento->contrato_base_path);
        }

        $template = (string) ($documento['template'] ?? '');

        // Algunos documentos tienen plantilla distinta por frecuencia (semanal/mensual).
        if (! empty($documento['templates_por_frecuencia'])) {
            $frecuencia = $contrato->frecuencia === 'semanal' ? 'semanal' : 'mensual';
            $template = (string) ($documento['templates_por_frecuencia'][$frecuencia] ?? $template);
        }

        if ($template === '' || ! Storage::disk('private')->exists($template)) {
            throw new RuntimeException('No se encontró la plantilla para '.($documento['label'] ?? 'el documento').'.');
        }

        return Storage::disk('private')->path($template);
    }

    /**
     * Deuda vencida y número de cuotas atrasadas a la fecha.
     * Considera cuotas con vencimiento hasta hoy y saldo pendiente
     * (monto - pagado - condonado) mayor a cero.
     *
     * @return array{0: float, 1: int} [deuda, numero_de_cuotas]
     */
    protected function calcularDeudaVencida(Contrato $contrato): array
    {
        $hoy = now()->toDateString();

        $cuotas = $contrato->cuotas()
            ->whereDate('fecha_vencimiento', '<=', $hoy)
            ->get(['monto', 'pagado_total', 'condonado_total']);

        $deuda = 0.0;
        $numero = 0;

        foreach ($cuotas as $cuota) {
            $pendiente = max(
                0,
                (float) $cuota->monto - (float) $cuota->pagado_total - (float) $cuota->condonado_total
            );

            if ($pendiente > 0) {
                $deuda += $pendiente;
                $numero++;
            }
        }

        return [round($deuda, 2), $numero];
    }

    protected function numeroATexto(int $numero): string
    {
        $formatter = new \NumberFormatter('es', \NumberFormatter::SPELLOUT);

        return (string) $formatter->format($numero);
    }

    protected function formatMedida($valor): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }

        $valor = (float) $valor;
        $texto = $this->numeroDecimalATexto($valor);

        return number_format($valor, 2)." m ({$texto} metros)";
    }

    protected function numeroDecimalATexto(float $numero): string
    {
        $entero = (int) floor($numero);
        $decimal = (int) round(($numero - $entero) * 100);

        $textoEntero = $this->numeroATexto($entero);

        if ($decimal <= 0) {
            return $textoEntero;
        }

        $textoDecimal = $this->numeroATexto($decimal);

        return $textoEntero.' punto '.$textoDecimal;
    }

    protected function formatMoneyWithText($amount): string
    {
        $amount = (float) $amount;

        $formatted = '$'.number_format($amount, 2);

        $integer = (int) floor($amount);
        $text = $this->numeroATexto($integer);
        $text = mb_strtoupper($text, 'UTF-8');

        return $formatted.' ('.$text.' PESOS MEXICANOS)';
    }

    protected function setImageIfExists(TemplateProcessor $template, string $key, ?string $path): void
    {
        $absolute = ($path && Storage::disk('private')->exists($path))
            ? Storage::disk('private')->path($path)
            : null;

        if (! $absolute || ! file_exists($absolute)) {
            // Sin imagen: limpiar el placeholder para que no quede ${...} literal.
            $template->setValue($key, '');

            return;
        }

        $template->setImageValue($key, [
            'path' => $absolute,
            'width' => 382,
            'height' => 243,
            'ratio' => false,
        ]);
    }

    protected function safe($value): string
    {
        return trim((string) $value);
    }
}
