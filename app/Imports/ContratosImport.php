<?php

namespace App\Imports;

use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Cuota;
use App\Models\Fraccionamiento;
use App\Models\Lote;
use App\Models\Pago;
use App\Models\Recibo;
use App\Services\Contratos\ContratoPlanService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Row;

class ContratosImport implements OnEachRow, WithChunkReading, WithStartRow
{
    public function __construct(
        private readonly int $propietarioId,
        private readonly ?int $capturadoPorUserId = null,
        private readonly bool $createMissing = true,

        private readonly float $recargoFijoDefault = 50.0,
        private readonly int $diasGraciaDefault = 0,

        // ✅ Defaults para RECIBO (cuotas)
        private readonly int $tiposCobroIdDefault = 1,
        private readonly int $formaPagoIdDefault = 1,

        // ✅ tipo de cobro para ENGANCHE
        private readonly int $tiposCobroIdEnganche = 2,

        // ✅ Defaults para PAGO
        private readonly string $pagoMetodoDefault = 'efectivo',

        private readonly string $cuotaEstatusPagada = 'pagada',
        private readonly string $cuotaEstatusPendiente = 'pendiente',
        private readonly string $contratoEstatusActivo = 'activo',
        private readonly string $contratoEstatusLiquidado = 'liquidado',
        private readonly string $loteEstatusDisponible = 'disponible',
        private readonly string $loteEstatusVendido = 'vendido',

        // ✅ estatus lote donación (ajusta a tu catálogo si difiere)
        private readonly string $loteEstatusDonacion = 'donacion',

        // ✅ Backfill histórico (opcional)
        private readonly bool $backfillHistorico = true,
        private readonly string $backfillCutoff = '2025-12-31',

        // ✅ Blindar donación (recomendado)
        private readonly bool $blindarDonacion = true
    ) {}

    private array $cacheFracc = [];

    private array $cacheLote = [];

    private array $cacheCliente = [];

    /**
     * ✅ CORRECCIÓN:
     * - Tu Excel trae header en la fila 2 (según tus logs).
     * - Arrancamos en la 3 para no procesar encabezados como datos.
     */
    public function startRow(): int
    {
        return 2;
    }

    public function chunkSize(): int
    {
        return 500;
    }

    public function onRow(Row $row): void
    {
        $excelRow = method_exists($row, 'getIndex') ? (int) $row->getIndex() : null;
        $r = $row->toArray();

        if ($this->allEmpty($r)) {
            return;
        }

        /**
         * ✅ CORRECCIÓN:
         * - Si por alguna razón vuelve a entrar una fila de header, la brincamos.
         */
        $col0 = $this->normalizeCatalog($r[0] ?? '');
        $col1 = $this->normalizeCatalog($r[1] ?? '');
        $col2 = $this->normalizeCatalog($r[2] ?? '');
        if ($col0 === 'APELLIDO' && $col1 === 'NOMBRE' && $col2 === 'RESIDENCIAL') {
            Log::info('IMPORT: header detectado, se omite', ['excel_row' => $excelRow]);

            return;
        }

        /**
         * ✅ CORRECCIÓN CRÍTICA:
         * - Tu error de FK venía de propietario_id = 0.
         * - Mejor fallar claro aquí que generar 1000 errores.
         */
        if ($this->propietarioId <= 0) {
            throw new \RuntimeException('Import: propietarioId inválido (0). Debes pasar un propietario_id existente.');
        }

        /**
         * Layout NUEVO:
         * 0  APELLIDO
         * 1  NOMBRE
         * 2  RESIDENCIAL
         * 3  MANZANA
         * 4  LOTE
         * 5  TELEFONO
         * 6  FECHA
         * 7  PRECIO
         * 8  ENGANCHE
         * 9  RESTAN
         * 10 MENSUALIDAD (monto pago)
         * 11 RECARGO (SI/NO)
         * 12 MONTO (recargo)
         * 13 DIAS GRACIA
         * 14 ESTATUS (LIBRE/PAGADO/DONACION/ACTIVO)
         * 15 FRECUENCIA (MENSUAL/SEMANAL)
         * 16 DIA_MES
         * 17 DIA_SEMANA
         * 18 tiene_anualidad (SI/NO/1/0)
         * 19 anualidad_fecha
         * 20 anualidad_monto
         */
        $apellidos = $this->normUpper($r[0] ?? '');
        $nombres = $this->normUpper($r[1] ?? '');
        $residencial = $this->normUpper($r[2] ?? '');
        $manzana = trim((string) ($r[3] ?? ''));
        $loteNum = trim((string) ($r[4] ?? ''));

        // Si viene "DONACION" en teléfono → se limpia a ''
        $telefono = $this->normPhone($r[5] ?? '');
        $fecha = $this->parseFecha($r[6] ?? null);

        $precio = $this->parseMoney($r[7] ?? 0);
        $enganche = $this->parseMoney($r[8] ?? 0);
        $restan = $this->parseMoney($r[9] ?? 0);

        $mensualidadRaw = $r[10] ?? '';
        $mensTxt = $this->normalizeCatalog($mensualidadRaw);

        $recargoTxt = $this->normalizeCatalog($r[11] ?? '');
        $aplicaRecargo = in_array($recargoTxt, ['SI', 'SÍ', 'YES', 'Y'], true);

        $montoRecargoExcel = $this->parseMoney($r[12] ?? null);
        $diasGraciaExcel = (int) ($r[13] ?? $this->diasGraciaDefault);

        $estatusExcelRaw = (string) ($r[14] ?? '');
        $estatusExcel = $this->normalizeEstatus($estatusExcelRaw);

        // ✅ NUEVO: FRECUENCIA + DIAS
        $frecuenciaRaw = $r[15] ?? '';
        $frecuencia = $this->normalizeFrecuencia($frecuenciaRaw); // 'mensual' | 'semanal' | null

        $diaMesExcel = $this->parseIntOrNull($r[16] ?? null);
        $diaSemanaExcel = $this->parseDiaSemanaIso($r[17] ?? null); // 1..7 o null

        // ✅ NUEVO: ANUALIDAD
        $tieneAnualidad = $this->parseBool($r[18] ?? null);
        $anualidadFecha = $this->parseFecha($r[19] ?? null);
        $anualidadMonto = $this->parseMoney($r[20] ?? null);

        $esPagado = ($estatusExcel === 'PAGADO') || ($mensTxt === 'PAGADO');

        // ✅ robusto: DONACION/LIBRE aunque venga con texto extra
        $soloCrearLote =
            str_contains($estatusExcel, 'DONACION')
            || str_contains($estatusExcel, 'LIBRE')
            || $this->isLibreRow($apellidos, $nombres, $residencial);

        Log::info('DEBUG IMPORT', [
            'excel_row' => $excelRow,
            'estatus_raw' => $estatusExcelRaw,
            'estatus_norm' => $estatusExcel,
            'solo_crear_lote' => $soloCrearLote,
            'es_pagado' => $esPagado,
            'residencial' => $residencial,
            'manzana' => $manzana,
            'lote' => $loteNum,
            'precio' => $precio,
            'enganche' => $enganche,
            'restan' => $restan,
            'mensualidad_raw' => $mensualidadRaw,
            'frecuencia_raw' => $frecuenciaRaw,
            'frecuencia_norm' => $frecuencia,
            'dia_mes_excel' => $diaMesExcel,
            'dia_semana_excel' => $diaSemanaExcel,
            'tiene_anualidad' => $tieneAnualidad,
            'anualidad_fecha' => $anualidadFecha?->toDateString(),
            'anualidad_monto' => $anualidadMonto,
            'create_missing' => $this->createMissing,
            'propietario_id' => $this->propietarioId,
        ]);

        $tipoRecargo = 'fijo';
        $valorRecargo = $aplicaRecargo
            ? (float) (($montoRecargoExcel > 0) ? $montoRecargoExcel : $this->recargoFijoDefault)
            : 0.0;

        $diasGracia = ($diasGraciaExcel >= 0) ? $diasGraciaExcel : $this->diasGraciaDefault;

        // ==================== SOLO CREAR LOTE (LIBRE/DONACION) ====================
        if ($soloCrearLote) {
            if ($loteNum === '') {
                Log::warning('SOLO LOTE omitido: lote vacío', ['excel_row' => $excelRow]);

                return;
            }

            try {
                DB::transaction(function () use (
                    $excelRow,
                    $estatusExcel,
                    $residencial,
                    $manzana,
                    $loteNum,
                    $precio
                ) {
                    $fraccId = $this->resolveFraccionamiento($residencial, $excelRow);
                    if (! $fraccId) {
                        return;
                    }

                    $nuevoEstatus = str_contains($estatusExcel, 'DONACION')
                        ? $this->loteEstatusDonacion
                        : $this->loteEstatusDisponible;

                    $loteId = $this->resolveLote(
                        $fraccId,
                        $manzana,
                        $loteNum,
                        $excelRow,
                        $nuevoEstatus,
                        $precio
                    );

                    if (! $loteId) {
                        return;
                    }

                    $update = [
                        'estatus' => $nuevoEstatus,
                        'updated_at' => now(),
                    ];

                    if ((float) $precio > 0) {
                        $lot = Lote::find($loteId);
                        if ($lot && ((float) ($lot->precio_lista ?? 0) <= 0)) {
                            $update['precio_lista'] = (float) $precio;
                        }
                    }

                    Lote::where('id', $loteId)->update($update);

                    Log::info('Import SOLO LOTE OK', [
                        'excel_row' => $excelRow,
                        'lote_id' => $loteId,
                        'nuevo_estatus' => $nuevoEstatus,
                        'precio' => (float) $precio,
                    ]);
                });
            } catch (\Throwable $e) {
                Log::error('Import error (SOLO LOTE)', [
                    'excel_row' => $excelRow,
                    'estatus_excel' => $estatusExcel,
                    'residencial' => $residencial,
                    'manzana' => $manzana,
                    'lote' => $loteNum,
                    'message' => $e->getMessage(),
                ]);
            }

            return;
        }
        // ========================================================================

        // mínimos (para contrato)
        if ($residencial === '' || $loteNum === '' || $precio <= 0) {
            Log::warning('Contrato omitido: faltan datos mínimos', [
                'excel_row' => $excelRow,
                'estatus_norm' => $estatusExcel,
                'residencial' => $residencial,
                'manzana' => $manzana,
                'lote' => $loteNum,
                'precio' => $precio,
                'cliente' => trim("$apellidos $nombres"),
            ]);

            return;
        }

        $fechaInicio = ($fecha ?: now())->startOfDay();

        $saldoInicial = max(0, $precio - $enganche);
        if ($restan <= 0) {
            $restan = $saldoInicial;
        }

        // monto pago
        $montoPago = $this->parseMoney($mensualidadRaw);
        if ($montoPago <= 0) {
            // fallback por si viene vacío
            $montoPago = max(1, (float) ceil($restan / 12));
            Log::warning('Mensualidad inválida: se calcula montoPago = restan/12', [
                'excel_row' => $excelRow,
                'mensualidad_raw' => $mensualidadRaw,
                'restan' => $restan,
                'montoPago_calculado' => $montoPago,
            ]);
        }

        // ✅ FRECUENCIA: si no viene, fallback (para no tronar import)
        if (! $frecuencia) {
            $frecuencia = ($montoPago < 100) ? 'semanal' : 'mensual';
            Log::warning('FRECUENCIA vacía: se infiere por heurística', [
                'excel_row' => $excelRow,
                'monto_pago' => $montoPago,
                'frecuencia_inferida' => $frecuencia,
            ]);
        }

        // ✅ DÍAS: si no vienen, fallback al día de fechaInicio
        $diaMesDefault = min(28, (int) $fechaInicio->day);
        $diaSemanaDefaultIso = (int) $fechaInicio->dayOfWeekIso;

        $dia_mes = null;
        $dia_semana = null;

        if ($frecuencia === 'mensual') {
            $dia_mes = $diaMesExcel ?: $diaMesDefault;
            $dia_mes = max(1, min(28, (int) $dia_mes));
        } else {
            $dia_semana = $diaSemanaExcel ?: $diaSemanaDefaultIso;
            $dia_semana = max(1, min(7, (int) $dia_semana));
        }

        try {
            DB::transaction(function () use (
                $excelRow,
                $apellidos,
                $nombres,
                $residencial,
                $manzana,
                $loteNum,
                $telefono,
                $fechaInicio,
                $precio,
                $enganche,
                $restan,
                $saldoInicial,
                $montoPago,
                $esPagado,
                $frecuencia,
                $dia_semana,
                $dia_mes,
                $tipoRecargo,
                $valorRecargo,
                $diasGracia,
                $tieneAnualidad,
                $anualidadFecha,
                $anualidadMonto
            ) {
                $fraccId = $this->resolveFraccionamiento($residencial, $excelRow);
                if (! $fraccId) {
                    return;
                }

                $loteId = $this->resolveLote(
                    $fraccId,
                    $manzana,
                    $loteNum,
                    $excelRow,
                    null,
                    $precio
                );
                if (! $loteId) {
                    return;
                }

                // backfill precio_lista si faltaba
                if ((float) $precio > 0) {
                    $lot = Lote::find($loteId);
                    if ($lot && ((float) ($lot->precio_lista ?? 0) <= 0)) {
                        $lot->precio_lista = (float) $precio;
                        $lot->save();
                    }
                }

                $clienteId = $this->resolveCliente($nombres, $apellidos, $excelRow);
                if (! $clienteId) {
                    return;
                }

                if ($telefono !== '') {
                    $c = Cliente::find($clienteId);
                    if ($c && empty($c->telefono)) {
                        $c->telefono = $telefono;
                        $c->save();
                    }
                }

                $contrato = Contrato::query()
                    ->where('lote_id', $loteId)
                    ->orderByDesc('id')
                    ->first();

                if (! $contrato) {
                    $contrato = new Contrato;
                    $contrato->uuid = (string) Str::uuid();
                    $contrato->folio_contrato = $this->generarFolioSeguro();
                }

                $contrato->fill([
                    'cliente_id' => $clienteId,
                    'lote_id' => $loteId,

                    'fecha_inicio' => $fechaInicio->toDateString(),
                    'frecuencia' => $frecuencia,
                    'dia_semana' => $dia_semana,
                    'dia_mes' => $dia_mes,

                    'precio_total' => $precio,
                    'enganche' => $enganche,
                    'saldo_inicial' => $saldoInicial,
                    'saldo_actual' => max(0, $restan),

                    'monto_pago' => $montoPago,

                    'tipo_recargo' => $tipoRecargo,
                    'valor_recargo' => $valorRecargo,
                    'dias_gracia' => $diasGracia,

                    // ✅ NUEVO: anualidad
                    'tiene_anualidad' => $tieneAnualidad ? 1 : 0,
                    'anualidad_fecha' => $tieneAnualidad ? ($anualidadFecha?->toDateString()) : null,
                    'anualidad_monto' => $tieneAnualidad ? (((float) $anualidadMonto) > 0 ? (float) $anualidadMonto : null) : null,

                    'estatus' => $esPagado ? $this->contratoEstatusLiquidado : $this->contratoEstatusActivo,
                ]);

                $contrato->save();

                // Blindar: si lote es DONACION no lo marques vendido
                $loteActual = Lote::find($loteId);
                $esDonacionLote = $loteActual && $loteActual->estatus === $this->loteEstatusDonacion;

                if (! ($this->blindarDonacion && $esDonacionLote)) {
                    Lote::where('id', $loteId)->update([
                        'estatus' => $this->loteEstatusVendido,
                        'updated_at' => now(),
                    ]);
                } else {
                    Log::info('Lote DONACION: no se marca como vendido', [
                        'excel_row' => $excelRow,
                        'lote_id' => $loteId,
                        'estatus' => $loteActual?->estatus,
                    ]);
                }

                // Recibo de enganche (idempotente) con tipos_cobro_id = 2
                if ((float) $enganche > 0) {
                    $this->crearReciboEngancheSiNoExiste($contrato, $clienteId, $fechaInicio, $excelRow);
                }

                // Solo bloquea cuotas si ya hay PAGOS de CUOTAS
                $hayPagosDeCuotas = Pago::query()
                    ->where('contrato_id', $contrato->id)
                    ->whereNotNull('cuota_id')
                    ->exists();

                if (! $hayPagosDeCuotas) {
                    Cuota::where('contrato_id', $contrato->id)->delete();

                    $data = [
                        'fecha_inicio' => $fechaInicio->toDateString(),
                        'frecuencia' => $frecuencia,
                        'dia_semana' => $dia_semana,
                        'dia_mes' => $dia_mes,

                        'precio_total' => $precio,
                        'enganche' => $enganche,

                        'saldo_inicial' => $restan,
                        'saldo_actual' => $restan,

                        'monto_pago' => $montoPago,

                        'tipo_recargo' => $tipoRecargo,
                        'valor_recargo' => $valorRecargo,
                        'dias_gracia' => $diasGracia,

                        // ✅ AQUI (NUEVO)
                        'tiene_anualidad' => $tieneAnualidad ? 1 : 0,
                        'anualidad_fecha' => $tieneAnualidad ? ($anualidadFecha?->toDateString()) : null,
                        'anualidad_monto' => $tieneAnualidad ? (float) ($anualidadMonto ?? 0) : 0,
                    ];

                    $plan = ContratoPlanService::generarCuotas($data, null);

                    foreach ($plan as $rowPlan) {
                        Cuota::create([
                            'uuid' => (string) Str::uuid(),
                            'contrato_id' => $contrato->id,
                            'numero' => (int) $rowPlan['numero'],
                            'fecha_vencimiento' => $rowPlan['fecha_vencimiento'],
                            'monto' => (float) $rowPlan['monto'],
                            'pagado_total' => 0,
                            'recargo_aplicado' => 0,
                            'estatus' => $this->cuotaEstatusPendiente,
                        ]);
                    }
                }

                if ($this->backfillHistorico) {
                    $cutoff = Carbon::parse($this->backfillCutoff)->endOfDay();
                    $this->backfillPagosHasta($contrato, $clienteId, $cutoff, $excelRow);
                }

                if ($esPagado) {
                    $this->pagarTodasLasCuotasYGenerarRecibos($contrato, $clienteId, $excelRow);
                }

                $this->recalcularSaldoContratoDesdePagos($contrato);
            });
        } catch (\Throwable $e) {
            Log::error('Import error en fila', [
                'excel_row' => $excelRow,
                'residencial' => $residencial,
                'manzana' => $manzana,
                'lote' => $loteNum,
                'cliente' => trim("$apellidos $nombres"),
                'message' => $e->getMessage(),
            ]);
        }
    }

    // ===================== NUEVOS HELPERS =====================

    private function normalizeFrecuencia($value): ?string
    {
        $s = $this->normalizeCatalog($value);

        if ($s === '') {
            return null;
        }
        if (str_contains($s, 'MENS')) {
            return 'mensual';
        }
        if (str_contains($s, 'SEMAN')) {
            return 'semanal';
        }
        if (in_array($s, ['MES', 'M'], true)) {
            return 'mensual';
        }
        if (in_array($s, ['SEM', 'S'], true)) {
            return 'semanal';
        }

        return null;
    }

    private function parseIntOrNull($value): ?int
    {
        if ($value === null) {
            return null;
        }
        $s = trim((string) $value);
        if ($s === '') {
            return null;
        }

        // soporta "15.0" (a veces Excel lo manda así)
        if (is_numeric($s)) {
            return (int) round((float) $s);
        }

        if (! preg_match('/^-?\d+$/', $s)) {
            return null;
        }

        return (int) $s;
    }

    /**
     * Acepta:
     * - 1..7 (ISO)
     * - LUNES..DOMINGO (con/ sin acento)
     * - MONDAY..SUNDAY (por si acaso)
     */
    private function parseDiaSemanaIso($value): ?int
    {
        if ($value === null) {
            return null;
        }

        // numérico directo
        if (is_numeric($value)) {
            $n = (int) round((float) $value);
            if ($n >= 1 && $n <= 7) {
                return $n;
            }
        }

        $s = $this->normalizeCatalog($value);
        if ($s === '') {
            return null;
        }

        // quitar acentos
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        if (is_string($ascii) && $ascii !== '') {
            $s = $this->normalizeCatalog($ascii);
        }

        // normalizar vocales acentuadas a ASCII
        $s = str_replace(['Á', 'É', 'Í', 'Ó', 'Ú'], ['A', 'E', 'I', 'O', 'U'], $s);

        // soportar abreviaciones
        if (str_starts_with($s, 'LUN')) {
            $s = 'LUNES';
        }
        if (str_starts_with($s, 'MAR')) {
            $s = 'MARTES';
        }
        if (str_starts_with($s, 'MIE')) {
            $s = 'MIERCOLES';
        }
        if (str_starts_with($s, 'JUE')) {
            $s = 'JUEVES';
        }
        if (str_starts_with($s, 'VIE')) {
            $s = 'VIERNES';
        }
        if (str_starts_with($s, 'SAB')) {
            $s = 'SABADO';
        }
        if (str_starts_with($s, 'DOM')) {
            $s = 'DOMINGO';
        }

        $map = [
            'LUNES' => 1,
            'MONDAY' => 1,
            'MARTES' => 2,
            'TUESDAY' => 2,
            'MIERCOLES' => 3,
            'WEDNESDAY' => 3,
            'JUEVES' => 4,
            'THURSDAY' => 4,
            'VIERNES' => 5,
            'FRIDAY' => 5,
            'SABADO' => 6,
            'SATURDAY' => 6,
            'DOMINGO' => 7,
            'SUNDAY' => 7,
        ];

        return $map[$s] ?? null;
    }

    private function parseBool($value): bool
    {
        $s = $this->normalizeCatalog($value);
        if ($s === '') {
            return false;
        }

        return in_array($s, ['1', 'SI', 'SÍ', 'YES', 'Y', 'TRUE', 'VERDADERO'], true);
    }

    // ===================== TU CÓDIGO EXISTENTE (SIN CAMBIOS IMPORTANTES) =====================

    private function crearReciboEngancheSiNoExiste(
        Contrato $contrato,
        int $clienteId,
        Carbon $fechaInicio,
        ?int $excelRow = null
    ): void {
        $existe = Pago::query()
            ->where('contrato_id', $contrato->id)
            ->whereNull('cuota_id')
            ->where('estatus', 'confirmado')
            ->where('referencia', 'IMPORT-ENGANCHE')
            ->exists();

        if ($existe) {
            return;
        }

        $monto = (float) $contrato->enganche;
        if ($monto <= 0) {
            return;
        }

        $fecha = $fechaInicio->copy()->startOfDay();

        $recibo = Recibo::create([
            'uuid' => (string) Str::uuid(),
            'folio' => $this->generarFolioReciboSeguro(),
            'fecha' => $fecha->toDateString(),
            'anio' => (int) $fecha->year,
            'semana_pago' => (int) $fecha->weekOfMonth,
            'semana_del_anio' => (int) $fecha->weekOfYear,
            'mes_del_anio' => (int) $fecha->month,

            'cliente_id' => $clienteId,
            'contrato_id' => $contrato->id,
            'lote_id' => $contrato->lote_id,

            'tipos_cobro_id' => $this->tiposCobroIdEnganche,
            'forma_pago_id' => $this->formaPagoIdDefault,

            'cuentas_bancarias_id' => null,
            'periodo_id' => null,

            'monto' => $monto,
            'observaciones' => 'Enganche (generado por importación)',
            'capturado_por_user_id' => $this->capturadoPorUserId,
            'afecta_reportes' => false,
            'tipo_movimiento' => 'historico',
            'es_historico' => true,
        ]);

        Pago::create([
            'uuid' => (string) Str::uuid(),
            'contrato_id' => $contrato->id,
            'cuota_id' => null,
            'recibo_id' => $recibo->id,
            'monto' => $monto,
            'metodo' => $this->pagoMetodoDefault,
            'referencia' => 'IMPORT-ENGANCHE',
            'estatus' => 'confirmado',
            'fecha_pago' => $fecha->copy()->setTime(12, 0, 0),
        ]);

        Log::info('Enganche: recibo+pago creado', [
            'excel_row' => $excelRow,
            'contrato_id' => $contrato->id,
            'monto' => $monto,
            'tipos_cobro_id' => $this->tiposCobroIdEnganche,
        ]);
    }

    private function backfillPagosHasta(Contrato $contrato, int $clienteId, Carbon $cutoff, ?int $excelRow = null): void
    {
        $cuotas = Cuota::query()
            ->where('contrato_id', $contrato->id)
            ->whereDate('fecha_vencimiento', '<=', $cutoff->toDateString())
            ->orderBy('numero')
            ->get();

        foreach ($cuotas as $cuota) {
            $ya = Pago::query()
                ->where('contrato_id', $contrato->id)
                ->where('cuota_id', $cuota->id)
                ->where('estatus', 'confirmado')
                ->exists();

            if ($ya) {
                if ($cuota->estatus !== $this->cuotaEstatusPagada || (float) $cuota->pagado_total < (float) $cuota->monto) {
                    $cuota->pagado_total = (float) $cuota->monto;
                    $cuota->estatus = $this->cuotaEstatusPagada;
                    $cuota->save();
                }

                continue;
            }

            $fecha = Carbon::parse($cuota->fecha_vencimiento)->startOfDay();

            $recibo = Recibo::create([
                'uuid' => (string) Str::uuid(),
                'folio' => $this->generarFolioReciboSeguro(),
                'fecha' => $fecha->toDateString(),
                'anio' => (int) $fecha->year,
                'semana_pago' => (int) $fecha->weekOfMonth,
                'semana_del_anio' => (int) $fecha->weekOfYear,
                'mes_del_anio' => (int) $fecha->month,

                'cliente_id' => $clienteId,
                'contrato_id' => $contrato->id,
                'lote_id' => $contrato->lote_id,
                'cuota_id' => $cuota->id,

                'tipos_cobro_id' => $this->tiposCobroIdDefault,
                'forma_pago_id' => $this->formaPagoIdDefault,

                'cuentas_bancarias_id' => null,
                'periodo_id' => null,

                'monto' => (float) $cuota->monto,
                'observaciones' => 'Backfill por import (<= '.$cutoff->toDateString().')',
                'capturado_por_user_id' => $this->capturadoPorUserId,
                'afecta_reportes' => false,
                'tipo_movimiento' => 'historico',
                'es_historico' => true,
            ]);

            Pago::create([
                'uuid' => (string) Str::uuid(),
                'contrato_id' => $contrato->id,
                'cuota_id' => $cuota->id,
                'recibo_id' => $recibo->id,
                'monto' => (float) $cuota->monto,
                'metodo' => $this->pagoMetodoDefault,
                'referencia' => 'IMPORT-BACKFILL-'.$cutoff->format('Y'),
                'estatus' => 'confirmado',
                'fecha_pago' => $fecha->copy()->setTime(12, 0, 0),
            ]);

            $cuota->pagado_total = (float) $cuota->monto;
            $cuota->estatus = $this->cuotaEstatusPagada;
            $cuota->save();
        }
    }

    private function recalcularSaldoContratoDesdePagos(Contrato $contrato): void
    {
        $totalPagadoCuotas = (float) Pago::query()
            ->where('contrato_id', $contrato->id)
            ->where('estatus', 'confirmado')
            ->whereNotNull('cuota_id')
            ->sum('monto');

        $saldo = max(0, (float) $contrato->saldo_inicial - $totalPagadoCuotas);

        $contrato->saldo_actual = $saldo;
        $contrato->estatus = ($saldo <= 0.00001) ? $this->contratoEstatusLiquidado : $this->contratoEstatusActivo;
        $contrato->liquidado_at = ($saldo <= 0.00001)
            ? (Pago::query()
                ->where('contrato_id', $contrato->id)
                ->where('estatus', 'confirmado')
                ->whereNotNull('cuota_id')
                ->max('fecha_pago') ?: $contrato->liquidado_at)
            : null;
        $contrato->save();
    }

    private function pagarTodasLasCuotasYGenerarRecibos(Contrato $contrato, int $clienteId, ?int $excelRow = null): void
    {
        $cuotas = Cuota::query()
            ->where('contrato_id', $contrato->id)
            ->orderBy('numero')
            ->get();

        foreach ($cuotas as $cuota) {
            $ya = Pago::query()
                ->where('contrato_id', $contrato->id)
                ->where('cuota_id', $cuota->id)
                ->where('estatus', 'confirmado')
                ->exists();

            if ($ya) {
                if ($cuota->estatus !== $this->cuotaEstatusPagada || (float) $cuota->pagado_total < (float) $cuota->monto) {
                    $cuota->pagado_total = (float) $cuota->monto;
                    $cuota->estatus = $this->cuotaEstatusPagada;
                    $cuota->save();
                }

                continue;
            }

            $fecha = Carbon::parse($cuota->fecha_vencimiento)->startOfDay();

            $recibo = Recibo::create([
                'uuid' => (string) Str::uuid(),
                'folio' => $this->generarFolioReciboSeguro(),
                'fecha' => $fecha->toDateString(),
                'anio' => (int) $fecha->year,
                'semana_pago' => (int) $fecha->weekOfMonth,
                'semana_del_anio' => (int) $fecha->weekOfYear,
                'mes_del_anio' => (int) $fecha->month,

                'cliente_id' => $clienteId,
                'contrato_id' => $contrato->id,
                'lote_id' => $contrato->lote_id,
                'cuota_id' => $cuota->id,

                'tipos_cobro_id' => $this->tiposCobroIdDefault,
                'forma_pago_id' => $this->formaPagoIdDefault,

                'cuentas_bancarias_id' => null,
                'periodo_id' => null,

                'monto' => (float) $cuota->monto,
                'observaciones' => 'Generado por importación (PAGADO)',
                'capturado_por_user_id' => $this->capturadoPorUserId,
                'afecta_reportes' => false,
                'tipo_movimiento' => 'historico',
                'es_historico' => true,
            ]);

            Pago::create([
                'uuid' => (string) Str::uuid(),
                'contrato_id' => $contrato->id,
                'cuota_id' => $cuota->id,
                'recibo_id' => $recibo->id,
                'monto' => (float) $cuota->monto,
                'metodo' => $this->pagoMetodoDefault,
                'referencia' => 'IMPORT-PAGADO',
                'estatus' => 'confirmado',
                'fecha_pago' => $fecha->copy()->setTime(12, 0, 0),
            ]);

            $cuota->pagado_total = (float) $cuota->monto;
            $cuota->estatus = $this->cuotaEstatusPagada;
            $cuota->save();
        }
    }

    // ===================== Resolvers =====================

    private function generarFolioSeguro(): string
    {
        return 'CT-'.now()->format('YmdHis').'-'.strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    }

    private function generarFolioReciboSeguro(): string
    {
        return 'R-'.now()->format('YmdHis').'-'.strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    }

    private function resolveFraccionamiento(string $nombre, ?int $excelRow = null): ?int
    {
        $nombre = trim($nombre);
        if ($nombre === '') {
            $nombre = 'SIN NOMBRE';
        }

        $key = mb_strtolower($nombre);
        if (array_key_exists($key, $this->cacheFracc)) {
            return $this->cacheFracc[$key];
        }

        $q = Fraccionamiento::query()
            ->where('propietario_id', $this->propietarioId)
            ->whereRaw('LOWER(nombre) = ?', [$key])
            ->first();

        if (! $q && $this->createMissing) {
            $q = Fraccionamiento::create([
                'propietario_id' => $this->propietarioId,
                'nombre' => $nombre,
            ]);

            Log::notice('Fraccionamiento creado por import', [
                'excel_row' => $excelRow,
                'propietario_id' => $this->propietarioId,
                'nombre' => $nombre,
                'id' => $q->id,
            ]);
        }

        return $this->cacheFracc[$key] = $q?->id;
    }

    private function resolveLote(
        int $fraccId,
        string $manzana,
        string $lote,
        ?int $excelRow = null,
        ?string $estatusOnCreate = null,
        ?float $precioListaOnCreate = null
    ): ?int {
        $manzana = trim($manzana);
        $lote = trim($lote);
        if ($lote === '') {
            return null;
        }

        $key = "{$fraccId}|".mb_strtolower($manzana).'|'.mb_strtolower($lote);
        if (array_key_exists($key, $this->cacheLote)) {
            return $this->cacheLote[$key];
        }

        $q = Lote::query()
            ->where('fraccionamiento_id', $fraccId)
            ->when(
                $manzana !== '',
                fn ($qq) => $qq->where('manzana', $manzana),
                fn ($qq) => $qq->whereNull('manzana')
            )
            ->where('lote', $lote)
            ->first();

        if (! $q && $this->createMissing) {
            $payload = [
                'fraccionamiento_id' => $fraccId,
                'manzana' => $manzana !== '' ? $manzana : null,
                'lote' => $lote,
                'clave' => $this->buildClaveLote($fraccId, $manzana, $lote),
                'estatus' => $estatusOnCreate ?: $this->loteEstatusDisponible,
            ];

            if ($precioListaOnCreate !== null && (float) $precioListaOnCreate > 0) {
                $payload['precio_lista'] = (float) $precioListaOnCreate;
            }

            $q = Lote::create($payload);

            Log::notice('Lote creado por import', [
                'excel_row' => $excelRow,
                'fraccionamiento_id' => $fraccId,
                'manzana' => $manzana !== '' ? $manzana : null,
                'lote' => $lote,
                'clave' => $q->clave,
                'estatus' => $q->estatus,
                'precio_lista' => $q->precio_lista,
                'id' => $q->id,
            ]);
        }

        return $this->cacheLote[$key] = $q?->id;
    }

    private function buildClaveLote(int $fraccId, string $manzana, string $lote): string
    {
        $base = "F{$fraccId}-M".($manzana !== '' ? $manzana : '0')."-L{$lote}";
        $clave = $base;
        $i = 1;

        while (Lote::where('clave', $clave)->exists()) {
            $i++;
            $clave = "{$base}-{$i}";
        }

        return $clave;
    }

    private function resolveCliente(string $nombres, string $apellidos, ?int $excelRow = null): ?int
    {
        $nombres = trim($nombres);
        $apellidos = trim($apellidos);

        $key = mb_strtolower($apellidos.'|'.$nombres);
        if ($key === '|') {
            $key = 'sin-nombre';
        }

        if (array_key_exists($key, $this->cacheCliente)) {
            return $this->cacheCliente[$key];
        }

        $q = Cliente::query()
            ->whereRaw('LOWER(nombres) = ?', [mb_strtolower($nombres)])
            ->whereRaw('LOWER(apellidos) = ?', [mb_strtolower($apellidos)])
            ->first();

        if (! $q && $this->createMissing) {
            $q = Cliente::create([
                'nombres' => $nombres ?: 'SIN NOMBRE',
                'apellidos' => $apellidos ?: 'SIN APELLIDO',
                'estatus' => 'activo',
            ]);
        }

        return $this->cacheCliente[$key] = $q?->id;
    }

    // ===================== Helpers base =====================

    private function parseFecha($value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            $v = (float) $value;

            return Carbon::createFromTimestampUTC(((int) round($v) - 25569) * 86400)->startOfDay();
        }

        $s = trim((string) $value);

        try {
            return Carbon::createFromFormat('j-M-Y', $s)->startOfDay();
        } catch (\Throwable) {
        }
        try {
            return Carbon::createFromFormat('d/m/Y', $s)->startOfDay();
        } catch (\Throwable) {
        }
        try {
            return Carbon::parse($s)->startOfDay();
        } catch (\Throwable) {
        }

        return null;
    }

    private function parseMoney($value): float
    {
        $s = trim((string) $value);
        if ($s === '') {
            return 0.0;
        }

        // DONACION / texto => 0
        if (! preg_match('/\d/', $s)) {
            return 0.0;
        }

        $s = str_replace(['$', ' '], '', $s);

        $hasDot = str_contains($s, '.');
        $hasComma = str_contains($s, ',');

        if ($hasDot && $hasComma) {
            $lastDot = strrpos($s, '.');
            $lastComma = strrpos($s, ',');

            if ($lastComma > $lastDot) {
                $s = str_replace('.', '', $s);
                $s = str_replace(',', '.', $s);
            } else {
                $s = str_replace(',', '', $s);
            }
        } elseif ($hasComma && ! $hasDot) {
            if (preg_match('/,\d{2}$/', $s)) {
                $s = str_replace('.', '', $s);
                $s = str_replace(',', '.', $s);
            } else {
                $s = str_replace(',', '', $s);
            }
        } elseif ($hasDot && ! $hasComma) {
            if (preg_match('/\.\d{3}(\.|$)/', $s) && ! preg_match('/\.\d{1,2}$/', $s)) {
                $s = str_replace('.', '', $s);
            }
        }

        return is_numeric($s) ? (float) $s : 0.0;
    }

    private function normalizeCatalog($s): string
    {
        $s = trim((string) $s);
        $s = preg_replace('/\s+/', ' ', $s);

        return mb_strtoupper($s);
    }

    private function normalizeEstatus(string $value): string
    {
        $s = $this->normalizeCatalog($value);

        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        if (is_string($ascii) && $ascii !== '') {
            $s = $this->normalizeCatalog($ascii);
        }

        $s = preg_replace('/[^A-Z0-9\s]/', ' ', $s);
        $s = preg_replace('/\s+/', ' ', $s);
        $s = trim($s);

        if (str_contains($s, 'DONAC') || str_contains($s, 'DONAT') || str_contains($s, 'DONADO')) {
            return 'DONACION';
        }
        if (str_contains($s, 'LIBRE')) {
            return 'LIBRE';
        }
        if (str_contains($s, 'PAGAD')) {
            return 'PAGADO';
        }

        return $s;
    }

    private function normUpper($s): string
    {
        $s = trim((string) $s);
        $s = preg_replace('/\s+/', ' ', $s);

        return mb_strtoupper($s);
    }

    private function normPhone($s): string
    {
        $s = preg_replace('/\D+/', '', (string) $s);

        return $s ?: '';
    }

    private function isLibreRow(string $apellidos, string $nombres, string $residencial): bool
    {
        $a = $this->normalizeCatalog($apellidos);
        $n = $this->normalizeCatalog($nombres);
        $r = $this->normalizeCatalog($residencial);

        return $a === 'LIBRE' || $n === 'LIBRE' || $r === 'LIBRE';
    }

    private function allEmpty(array $row): bool
    {
        foreach ($row as $v) {
            if (trim((string) $v) !== '') {
                return false;
            }
        }

        return true;
    }
}
