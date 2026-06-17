<?php

namespace App\Imports;

use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Cuota;
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

class ContratosServiciosImport implements OnEachRow, WithChunkReading, WithStartRow
{
    public function __construct(
        private readonly int $propietarioId,
        private readonly ?int $capturadoPorUserId = null,

        private readonly float $recargoFijoDefault = 50.0,
        private readonly int $diasGraciaDefault = 0,

        private readonly int $tiposCobroIdDefault = 1,
        private readonly int $formaPagoIdDefault = 1,

        private readonly string $pagoMetodoDefault = 'efectivo',

        private readonly string $cuotaEstatusPagada = 'pagada',
        private readonly string $cuotaEstatusPendiente = 'pendiente',
        private readonly string $contratoEstatusActivo = 'activo',
        private readonly string $contratoEstatusLiquidado = 'liquidado',
        private readonly string $contratoEstatusCancelado = 'cancelado',

        private readonly bool $backfillHistorico = false,
        private readonly ?string $backfillCutoff = null,
    ) {}

    private array $cacheContratoBase = [];

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
         * Layout:
         * 0  NOMBRE
         * 1  FECHA DE CONTRATO
         * 2  PRECIO
         * 3  ABONO SEM.
         * 4  ABONO MENS.
         * 5  LOTE
         * 6  FINCA
         * 7  STATUS
         * 8  FRECUENCIA
         * 9  DIA_MES
         * 10 DIA_SEMANA
         * 11 FOLIO_CONTRATO_BASE
         */
        $nombreCompleto = trim((string) ($r[0] ?? ''));
        $fechaInicio = $this->parseFecha($r[1] ?? null) ?: now()->startOfDay();
        $precioTotal = $this->parseMoney($r[2] ?? 0);
        $abonoSemanal = $this->parseMoney($r[3] ?? 0);
        $abonoMensual = $this->parseMoney($r[4] ?? 0);
        $loteExcel = trim((string) ($r[5] ?? ''));
        $fincaExcel = $this->normUpper($r[6] ?? '');
        $estatusExcel = $this->normalizeEstatusServicio((string) ($r[7] ?? ''));
        $frecuencia = $this->normalizeFrecuencia($r[8] ?? '');
        $diaMesExcel = $this->parseIntOrNull($r[9] ?? null);
        $diaSemanaExcel = $this->parseDiaSemanaIso($r[10] ?? null);
        $folioContratoBase = trim((string) ($r[11] ?? ''));

        if ($nombreCompleto === '' || $folioContratoBase === '') {
            Log::warning('IMPORT SERVICIOS: fila omitida por datos mínimos faltantes', [
                'excel_row' => $excelRow,
                'nombre' => $nombreCompleto,
                'folio_contrato_base' => $folioContratoBase,
            ]);

            return;
        }

        [$nombres, $apellidos] = $this->splitNombreCompleto($nombreCompleto);

        $montoPago = 0.0;

        if ($frecuencia === 'semanal') {
            $montoPago = $abonoSemanal;
            if ($montoPago <= 0) {
                Log::warning('IMPORT SERVICIOS: frecuencia semanal sin ABONO SEM válido', [
                    'excel_row' => $excelRow,
                    'folio_contrato_base' => $folioContratoBase,
                    'abono_semanal' => $abonoSemanal,
                ]);

                return;
            }
        } elseif ($frecuencia === 'mensual') {
            $montoPago = $abonoMensual;
            if ($montoPago <= 0) {
                Log::warning('IMPORT SERVICIOS: frecuencia mensual sin ABONO MENS válido', [
                    'excel_row' => $excelRow,
                    'folio_contrato_base' => $folioContratoBase,
                    'abono_mensual' => $abonoMensual,
                ]);

                return;
            }
        } else {
            Log::warning('IMPORT SERVICIOS: frecuencia inválida', [
                'excel_row' => $excelRow,
                'frecuencia_raw' => $r[8] ?? null,
                'folio_contrato_base' => $folioContratoBase,
            ]);

            return;
        }

        if ($precioTotal <= 0) {
            Log::warning('IMPORT SERVICIOS: precio total inválido', [
                'excel_row' => $excelRow,
                'precio_total' => $precioTotal,
                'folio_contrato_base' => $folioContratoBase,
            ]);

            return;
        }

        $dia_mes = null;
        $dia_semana = null;

        if ($frecuencia === 'mensual') {
            if (! $diaMesExcel || $diaMesExcel < 1 || $diaMesExcel > 28) {
                Log::warning('IMPORT SERVICIOS: DIA_MES inválido para frecuencia mensual', [
                    'excel_row' => $excelRow,
                    'dia_mes' => $diaMesExcel,
                    'folio_contrato_base' => $folioContratoBase,
                ]);

                return;
            }
            $dia_mes = (int) $diaMesExcel;
        }

        if ($frecuencia === 'semanal') {
            if (! $diaSemanaExcel || $diaSemanaExcel < 1 || $diaSemanaExcel > 7) {
                Log::warning('IMPORT SERVICIOS: DIA_SEMANA inválido para frecuencia semanal', [
                    'excel_row' => $excelRow,
                    'dia_semana' => $diaSemanaExcel,
                    'folio_contrato_base' => $folioContratoBase,
                ]);

                return;
            }
            $dia_semana = (int) $diaSemanaExcel;
        }

        $tipoRecargo = 'fijo';
        $valorRecargo = $this->recargoFijoDefault;
        $diasGracia = $this->diasGraciaDefault;

        $estatusContrato = match ($estatusExcel) {
            'PAGADO', 'LIQUIDADO' => $this->contratoEstatusLiquidado,
            'CANCELADO' => $this->contratoEstatusCancelado,
            default => $this->contratoEstatusActivo,
        };

        $esPagado = in_array($estatusExcel, ['PAGADO', 'LIQUIDADO'], true);

        Log::info('IMPORT SERVICIOS: fila detectada', [
            'excel_row' => $excelRow,
            'nombre' => $nombreCompleto,
            'nombres' => $nombres,
            'apellidos' => $apellidos,
            'fecha_inicio' => $fechaInicio->toDateString(),
            'precio_total' => $precioTotal,
            'abono_semanal' => $abonoSemanal,
            'abono_mensual' => $abonoMensual,
            'frecuencia' => $frecuencia,
            'dia_mes' => $dia_mes,
            'dia_semana' => $dia_semana,
            'monto_pago' => $montoPago,
            'lote_excel' => $loteExcel,
            'finca_excel' => $fincaExcel,
            'estatus_excel' => $estatusExcel,
            'folio_contrato_base' => $folioContratoBase,
        ]);

        try {
            DB::transaction(function () use (
                $excelRow,
                $fechaInicio,
                $precioTotal,
                $montoPago,
                $frecuencia,
                $dia_mes,
                $dia_semana,
                $tipoRecargo,
                $valorRecargo,
                $diasGracia,
                $estatusContrato,
                $esPagado,
                $folioContratoBase,
                $loteExcel,
                $fincaExcel
            ) {
                $contratoBase = $this->resolveContratoBasePorFolio($folioContratoBase);

                if (! $contratoBase) {
                    Log::warning('IMPORT SERVICIOS: no se encontró contrato base por folio', [
                        'excel_row' => $excelRow,
                        'folio_contrato_base' => $folioContratoBase,
                    ]);

                    return;
                }

                $clienteId = $contratoBase->cliente_id;
                $loteId = $contratoBase->lote_id;

                if (! $clienteId || ! $loteId) {
                    Log::warning('IMPORT SERVICIOS: contrato base incompleto', [
                        'excel_row' => $excelRow,
                        'contrato_base_id' => $contratoBase->id,
                        'cliente_id' => $clienteId,
                        'lote_id' => $loteId,
                    ]);

                    return;
                }

                $cliente = Cliente::find($clienteId);
                if (! $cliente) {
                    Log::warning('IMPORT SERVICIOS: cliente del contrato base no existe', [
                        'excel_row' => $excelRow,
                        'contrato_base_id' => $contratoBase->id,
                        'cliente_id' => $clienteId,
                    ]);

                    return;
                }

                $contratoServicio = Contrato::query()
                    ->where('tipo', 'servicio')
                    ->where('servicio_tipo', 'electricidad')
                    ->where('contrato_base_id', $contratoBase->id)
                    ->orderByDesc('id')
                    ->first();

                if (! $contratoServicio) {
                    $contratoServicio = new Contrato;
                    $contratoServicio->uuid = (string) Str::uuid();
                    $contratoServicio->folio_contrato = $this->generarFolioServicioSeguro();
                }

                $contratoServicio->fill([
                    'cliente_id' => $clienteId,
                    'lote_id' => $loteId,
                    'contrato_base_id' => $contratoBase->id,

                    'fecha_inicio' => $fechaInicio->toDateString(),
                    'frecuencia' => $frecuencia,
                    'dia_semana' => $dia_semana,
                    'dia_mes' => $dia_mes,

                    'tipo' => 'servicio',
                    'servicio_tipo' => 'electricidad',

                    'precio_total' => $precioTotal,
                    'enganche' => 0,
                    'saldo_inicial' => $precioTotal,
                    'saldo_actual' => $precioTotal,
                    'monto_pago' => $montoPago,

                    'tipo_recargo' => $tipoRecargo,
                    'valor_recargo' => $valorRecargo,
                    'dias_gracia' => $diasGracia,

                    'tiene_anualidad' => 0,
                    'anualidad_fecha' => null,
                    'anualidad_monto' => 0,

                    'estatus' => $estatusContrato,
                ]);

                $contratoServicio->save();

                $hayPagosDeCuotas = Pago::query()
                    ->where('contrato_id', $contratoServicio->id)
                    ->whereNotNull('cuota_id')
                    ->exists();

                if (! $hayPagosDeCuotas) {
                    Cuota::where('contrato_id', $contratoServicio->id)->delete();

                    $data = [
                        'fecha_inicio' => $fechaInicio->toDateString(),
                        'frecuencia' => $frecuencia,
                        'dia_semana' => $dia_semana,
                        'dia_mes' => $dia_mes,

                        'precio_total' => $precioTotal,
                        'enganche' => 0,

                        'saldo_inicial' => $precioTotal,
                        'saldo_actual' => $precioTotal,

                        'monto_pago' => $montoPago,

                        'tipo_recargo' => $tipoRecargo,
                        'valor_recargo' => $valorRecargo,
                        'dias_gracia' => $diasGracia,

                        'tiene_anualidad' => 0,
                        'anualidad_fecha' => null,
                        'anualidad_monto' => 0,
                    ];

                    $plan = ContratoPlanService::generarCuotas($data, null);

                    foreach ($plan as $rowPlan) {
                        Cuota::create([
                            'uuid' => (string) Str::uuid(),
                            'contrato_id' => $contratoServicio->id,
                            'numero' => (int) $rowPlan['numero'],
                            'fecha_vencimiento' => $rowPlan['fecha_vencimiento'],
                            'monto' => (float) $rowPlan['monto'],
                            'pagado_total' => 0,
                            'recargo_aplicado' => 0,
                            'estatus' => $this->cuotaEstatusPendiente,
                        ]);
                    }
                }

                if ($this->backfillHistorico && $this->backfillCutoff) {
                    $cutoff = Carbon::parse($this->backfillCutoff)->endOfDay();
                    $this->backfillPagosHasta($contratoServicio, $clienteId, $cutoff);
                }

                if ($esPagado) {
                    $this->pagarTodasLasCuotasYGenerarRecibos($contratoServicio, $clienteId);
                }

                $this->recalcularSaldoContratoDesdePagos($contratoServicio);

                Log::info('IMPORT SERVICIOS OK', [
                    'excel_row' => $excelRow,
                    'contrato_servicio_id' => $contratoServicio->id,
                    'contrato_base_id' => $contratoBase->id,
                    'cliente_id' => $clienteId,
                    'lote_id' => $loteId,
                    'folio_contrato_base' => $folioContratoBase,
                    'lote_excel' => $loteExcel,
                    'finca_excel' => $fincaExcel,
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('IMPORT SERVICIOS: error en fila', [
                'excel_row' => $excelRow,
                'nombre' => $nombreCompleto,
                'folio_contrato_base' => $folioContratoBase,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function resolveContratoBasePorFolio(string $folioContratoBase): ?Contrato
    {
        $folioContratoBase = trim($folioContratoBase);

        if ($folioContratoBase === '') {
            return null;
        }

        if (array_key_exists($folioContratoBase, $this->cacheContratoBase)) {
            return $this->cacheContratoBase[$folioContratoBase];
        }

        $contrato = Contrato::query()
            ->where('folio_contrato', $folioContratoBase)
            ->where(function ($q) {
                $q->whereNull('tipo')
                    ->orWhere('tipo', 'terreno');
            })
            ->first();

        return $this->cacheContratoBase[$folioContratoBase] = $contrato;
    }

    private function backfillPagosHasta(Contrato $contrato, int $clienteId, Carbon $cutoff): void
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
                'observaciones' => 'Backfill por import servicio (<= '.$cutoff->toDateString().')',
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
                'referencia' => 'IMPORT-SERVICIO-BACKFILL-'.$cutoff->format('Y'),
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
        $contrato->estatus = ($saldo <= 0.00001)
            ? $this->contratoEstatusLiquidado
            : ($contrato->estatus === $this->contratoEstatusCancelado ? $this->contratoEstatusCancelado : $this->contratoEstatusActivo);
        $contrato->liquidado_at = ($saldo <= 0.00001)
            ? (Pago::query()
                ->where('contrato_id', $contrato->id)
                ->where('estatus', 'confirmado')
                ->whereNotNull('cuota_id')
                ->max('fecha_pago') ?: $contrato->liquidado_at)
            : null;

        $contrato->save();
    }

    private function pagarTodasLasCuotasYGenerarRecibos(Contrato $contrato, int $clienteId): void
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
                'observaciones' => 'Generado por importación servicio (PAGADO)',
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
                'referencia' => 'IMPORT-SERVICIO-PAGADO',
                'estatus' => 'confirmado',
                'fecha_pago' => $fecha->copy()->setTime(12, 0, 0),
            ]);

            $cuota->pagado_total = (float) $cuota->monto;
            $cuota->estatus = $this->cuotaEstatusPagada;
            $cuota->save();
        }
    }

    private function generarFolioServicioSeguro(): string
    {
        return 'CS-'.now()->format('YmdHis').'-'.strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    }

    private function generarFolioReciboSeguro(): string
    {
        return 'R-'.now()->format('YmdHis').'-'.strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    }

    private function splitNombreCompleto(string $nombreCompleto): array
    {
        $nombreCompleto = trim(preg_replace('/\s+/', ' ', $nombreCompleto));

        if ($nombreCompleto === '') {
            return ['', ''];
        }

        $partes = preg_split('/\s+/', $nombreCompleto) ?: [];

        if (count($partes) === 1) {
            return [$partes[0], ''];
        }

        if (count($partes) === 2) {
            return [$partes[0], $partes[1]];
        }

        $apellidos = implode(' ', array_slice($partes, -2));
        $nombres = implode(' ', array_slice($partes, 0, -2));

        return [$nombres, $apellidos];
    }

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
            return Carbon::createFromFormat('d-m-Y', $s)->startOfDay();
        } catch (\Throwable) {
        }

        try {
            return Carbon::createFromFormat('d/m/Y', $s)->startOfDay();
        } catch (\Throwable) {
        }

        try {
            return Carbon::createFromFormat('j-M-Y', $s)->startOfDay();
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

    private function normalizeEstatusServicio(string $value): string
    {
        $s = $this->normalizeCatalog($value);

        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        if (is_string($ascii) && $ascii !== '') {
            $s = $this->normalizeCatalog($ascii);
        }

        $s = preg_replace('/[^A-Z0-9\s]/', ' ', $s);
        $s = preg_replace('/\s+/', ' ', $s);
        $s = trim($s);

        if (str_contains($s, 'PAGAD')) {
            return 'PAGADO';
        }
        if (str_contains($s, 'LIQUID')) {
            return 'LIQUIDADO';
        }
        if (str_contains($s, 'CANCEL')) {
            return 'CANCELADO';
        }
        if (str_contains($s, 'MOROS')) {
            return 'MOROSO';
        }
        if (str_contains($s, 'ACTIV')) {
            return 'ACTIVO';
        }

        return $s !== '' ? $s : 'ACTIVO';
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

        if (is_numeric($s)) {
            return (int) round((float) $s);
        }
        if (! preg_match('/^-?\d+$/', $s)) {
            return null;
        }

        return (int) $s;
    }

    private function parseDiaSemanaIso($value): ?int
    {
        if ($value === null) {
            return null;
        }

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

        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        if (is_string($ascii) && $ascii !== '') {
            $s = $this->normalizeCatalog($ascii);
        }

        $s = str_replace(['Á', 'É', 'Í', 'Ó', 'Ú'], ['A', 'E', 'I', 'O', 'U'], $s);

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

    private function normalizeCatalog($s): string
    {
        $s = trim((string) $s);
        $s = preg_replace('/\s+/', ' ', $s);

        return mb_strtoupper($s);
    }

    private function normUpper($s): string
    {
        $s = trim((string) $s);
        $s = preg_replace('/\s+/', ' ', $s);

        return mb_strtoupper($s);
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
