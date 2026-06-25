<?php

namespace App\Imports;

use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\CuentaBancaria;
use App\Models\Cuota;
use App\Models\FormaPago;
use App\Models\Fraccionamiento;
use App\Models\Lote;
use App\Models\Periodo;
use App\Models\TipoCobro;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Row;

class RecibosImport implements OnEachRow, WithChunkReading, WithStartRow
{
    public function __construct(
        private readonly int $propietarioId,
        private readonly ?int $capturadoPorUserId = null,
        private readonly bool $createMissing = true,
    ) {}

    // caches para no consultar lo mismo miles de veces
    private array $cacheFormaPago = [];    // key => ?int

    private array $cacheTipoCobro = [];    // key => ?int

    private array $cacheCuenta = [];       // key => ?int

    private array $cacheFracc = [];        // key => ?int

    private array $cacheLote = [];         // key => ?int

    private array $cacheCliente = [];      // key => ?int

    private array $cachePeriodo = [];      // key => ?int

    public function startRow(): int
    {
        return 2; // salta encabezados
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    public function onRow(Row $row): void
    {
        $r = $row->toArray(); // indices 0..n

        /**
         * Orden según tu excel:
         * 0  APELLIDO
         * 1  NOMBRE
         * 2  RESIDENCIAL
         * 3  MANZANA
         * 4  LOTE
         * 5  MONTO
         * 6  FECHA
         * 7  FORMA DE PAGO
         * 8  TIPO DE PAGO
         * 9  SEMANA (pago) (puede venir algo, lo ignoramos)
         * 10 MES
         * 11 MENSUALIDAD
         * 12 FOLIO
         * 13 SEMANA  ✅ (NÚMERO DE CUOTA PAGADA)
         * 14 CUENTA
         * 15 AÑO
         */
        $folio = trim((string) ($r[12] ?? ''));
        if ($folio === '') {
            return;
        }

        $fecha = $this->parseFecha($r[6] ?? null);
        if (! $fecha) {
            Log::warning('Recibo omitido: fecha inválida', [
                'folio' => $folio,
                'fecha_excel' => $r[6] ?? null,
            ]);

            return;
        }

        $anio = (int) ($r[15] ?? $fecha->year);
        $mes = (int) ($r[10] ?? $fecha->month);

        // ✅ índice 13 = número de cuota pagada
        $numeroCuotaPagada = is_numeric($r[13] ?? null) ? (int) $r[13] : null;

        $monto = $this->parseMoney($r[5] ?? 0);

        $apellido = $this->normUpper($r[0] ?? '');
        $nombre = $this->normUpper($r[1] ?? '');

        $residencial = $this->normUpper($r[2] ?? '');
        $manzana = trim((string) ($r[3] ?? ''));
        $loteNum = trim((string) ($r[4] ?? ''));

        // Normalizaciones
        $formaPagoTxt = $this->normalizeFormaPago($r[7] ?? '');
        $tipoPagoTxt = $this->normalizeCatalog($r[8] ?? '');
        $mensualidad = $this->normalizeCatalog($r[11] ?? ''); // ENERO, FEBRERO...

        $cuentaTxt = trim((string) ($r[14] ?? ''));

        DB::transaction(function () use (
            $folio, $fecha, $anio, $mes, $numeroCuotaPagada, $monto,
            $apellido, $nombre, $residencial, $manzana, $loteNum,
            $formaPagoTxt, $tipoPagoTxt, $mensualidad, $cuentaTxt
        ) {
            // 1) Catálogos
            $formaPagoId = $this->resolveFormaPagoId($formaPagoTxt);

            if (empty($formaPagoId)) {
                Log::error('Recibo omitido: forma de pago inválida/no encontrada', [
                    'folio' => $folio,
                    'forma_pago_excel' => $formaPagoTxt,
                ]);

                return;
            }

            // ✅ NUNCA NULL: tipos_cobro_id es NOT NULL en tu BD
            $tipoCobroId = $this->resolveTipoCobroIdOrDefault($tipoPagoTxt);
            $tipoCobro = $tipoCobroId ? TipoCobro::find($tipoCobroId) : null;

            // 2) Periodo (solo si requiere_periodo)
            $periodoId = null;
            if ($tipoCobro?->requiere_periodo) {
                $periodoId = $this->resolvePeriodoMensual(
                    $anio,
                    $mes,
                    $mensualidad ?: $this->mesNombre($mes)
                );

                if (empty($periodoId)) {
                    Log::error('Recibo omitido: requiere periodo pero no se pudo resolver', [
                        'folio' => $folio,
                        'anio' => $anio,
                        'mes' => $mes,
                        'mensualidad' => $mensualidad,
                    ]);

                    return;
                }
            }

            // 3) Cuenta bancaria (si viene texto)
            $cuentaId = null;
            if ($cuentaTxt !== '') {
                $cuentaId = $this->resolveCuenta($cuentaTxt);
            }

            // 4) Fraccionamiento + Lote
            $fraccId = $this->resolveFraccionamiento($residencial);
            if (empty($fraccId)) {
                Log::error('Recibo omitido: no se pudo resolver fraccionamiento', [
                    'folio' => $folio,
                    'residencial_excel' => $residencial,
                ]);

                return;
            }

            $loteId = $this->resolveLote($fraccId, $manzana, $loteNum);
            if (empty($loteId)) {
                Log::error('Recibo omitido: no se pudo resolver lote', [
                    'folio' => $folio,
                    'fraccionamiento_id' => $fraccId,
                    'manzana' => $manzana,
                    'lote' => $loteNum,
                ]);

                return;
            }

            // 5) Cliente
            $clienteId = $this->resolveCliente($nombre, $apellido);
            if (empty($clienteId)) {
                Log::error('Recibo omitido: no se pudo resolver cliente', [
                    'folio' => $folio,
                    'nombre' => $nombre,
                    'apellido' => $apellido,
                ]);

                return;
            }

            // 6) Contrato (opcional)
            $contratoId = Contrato::query()
                ->where('lote_id', $loteId)
                ->orderByDesc('id')
                ->value('id');

            // 7) Upsert Recibo por folio
            DB::table('recibos')->upsert([[
                'folio' => (string) $folio,
                'fecha' => $fecha->toDateString(),
                'anio' => $anio,

                // ✅ guardamos aquí el # de cuota pagada
                'semana_pago' => $numeroCuotaPagada,

                'mes_del_anio' => $mes,

                'cliente_id' => $clienteId,
                'contrato_id' => $contratoId,
                'lote_id' => $loteId,

                // ✅ YA NO PUEDE SER NULL
                'tipos_cobro_id' => $tipoCobroId,
                'forma_pago_id' => $formaPagoId,
                'cuentas_bancarias_id' => $cuentaId,
                'periodo_id' => $periodoId,

                'monto' => $monto,
                'observaciones' => null,
                'capturado_por_user_id' => $this->capturadoPorUserId,

                'created_at' => now(),
                'updated_at' => now(),
            ]], ['folio'], [
                'fecha', 'anio', 'semana_pago', 'mes_del_anio',
                'cliente_id', 'contrato_id', 'lote_id',
                'tipos_cobro_id', 'forma_pago_id', 'cuentas_bancarias_id', 'periodo_id',
                'monto', 'capturado_por_user_id', 'updated_at',
            ]);

            // =========================
            // ✅ Marcar cuota pagada
            // =========================
            if (empty($contratoId) || empty($numeroCuotaPagada)) {
                return;
            }

            // Si es recargo/multa/penal, no toca cuotas
            if ($this->tipoCobroNoAbonaACuota($tipoCobro)) {
                return;
            }

            /** @var Cuota|null $cuota */
            $cuota = Cuota::query()
                ->where('contrato_id', $contratoId)
                ->where('numero', $numeroCuotaPagada)
                ->lockForUpdate()
                ->first();

            if (! $cuota) {
                Log::warning('No se encontró cuota para marcar como pagada', [
                    'contrato_id' => $contratoId,
                    'numero_cuota' => $numeroCuotaPagada,
                    'folio' => $folio,
                ]);

                return;
            }

            $nuevoPagado = (float) ($cuota->pagado_total ?? 0) + (float) $monto;

            $update = [
                'pagado_total' => $nuevoPagado,
                'updated_at' => now(),
            ];

            $montoCuota = (float) ($cuota->monto ?? 0);

            if ($montoCuota > 0 && $nuevoPagado + 0.0001 >= $montoCuota) {
                $update['estatus'] = 'pagada';

                if (Schema::hasColumn('cuotas', 'pagada_at')) {
                    $update['pagada_at'] = $cuota->pagada_at ?: now();
                }
            } else {
                // si no existe "parcial" en tu enum, mejor no cambiamos a algo desconocido
                $update['estatus'] = $cuota->estatus ?: 'pendiente';
            }

            $cuota->update($update);
        });
    }

    private function tipoCobroNoAbonaACuota(?TipoCobro $tipoCobro): bool
    {
        if (! $tipoCobro) {
            return false;
        }

        $nombre = mb_strtoupper(trim((string) $tipoCobro->nombre));

        return str_contains($nombre, 'RECARGO')
            || str_contains($nombre, 'MULTA')
            || str_contains($nombre, 'PENAL');
    }

    private function normalizeFormaPago($s): string
    {
        $s = $this->normalizeCatalog($s);

        return match ($s) {
            'TRASFERENCIA' => 'TRANSFERENCIA',
            'TRANSF' => 'TRANSFERENCIA',
            default => $s,
        };
    }

    private function normalizeCatalog($s): string
    {
        $s = trim((string) $s);
        $s = preg_replace('/\s+/', ' ', $s);

        return mb_strtoupper($s);
    }

    private function resolveFormaPagoId(string $nombre): ?int
    {
        $nombre = trim($nombre);
        if ($nombre === '') {
            return null;
        }

        $key = mb_strtolower($nombre);

        if (array_key_exists($key, $this->cacheFormaPago)) {
            return $this->cacheFormaPago[$key];
        }

        $q = FormaPago::query()
            ->whereRaw('LOWER(nombre) = ?', [$key])
            ->first();

        if (! $q && $this->createMissing) {
            $q = FormaPago::query()->create([
                'nombre' => $nombre,
                'requiere_cuenta' => 0,
                'activa' => 1,
            ]);
        }

        $this->cacheFormaPago[$key] = $q?->id;

        return $this->cacheFormaPago[$key];
    }

    private function resolveTipoCobroId(string $nombre): ?int
    {
        $nombre = trim($nombre);
        if ($nombre === '') {
            return null;
        }

        $key = mb_strtolower($nombre);

        if (array_key_exists($key, $this->cacheTipoCobro)) {
            return $this->cacheTipoCobro[$key];
        }

        $q = TipoCobro::query()
            ->whereRaw('LOWER(nombre) = ?', [$key])
            ->first();

        if (! $q && $this->createMissing) {
            $q = TipoCobro::query()->create([
                'nombre' => $nombre,
                'categoria' => 'otro',
                'requiere_periodo' => 0,
                'activa' => 1,
            ]);
        }

        $this->cacheTipoCobro[$key] = $q?->id;

        return $this->cacheTipoCobro[$key];
    }

    /**
     * ✅ NUNCA regresa null: si viene vacío o no existe, usa/crea default "MENSUALIDAD"
     * Cambia el texto si quieres: "PAGO", "ABONO", etc.
     */
    private function resolveTipoCobroIdOrDefault(string $nombre): int
    {
        $nombre = trim((string) $nombre);

        if ($nombre === '') {
            $nombre = 'MENSUALIDAD';
        }

        $id = $this->resolveTipoCobroId($nombre);

        if (! empty($id)) {
            return (int) $id;
        }

        // fallback duro (por si createMissing=false)
        $q = TipoCobro::query()->firstOrCreate(
            ['nombre' => $nombre],
            [
                'categoria' => 'otro',
                'requiere_periodo' => 0,
                'activa' => 1,
            ]
        );

        return (int) $q->id;
    }

    private function resolveCuenta(string $alias): ?int
    {
        $key = mb_strtolower(trim($alias));
        if ($key === '') {
            return null;
        }

        if (array_key_exists($key, $this->cacheCuenta)) {
            return $this->cacheCuenta[$key];
        }

        $q = CuentaBancaria::query()
            ->where('propietario_id', $this->propietarioId)
            ->whereRaw('LOWER(alias) = ?', [$key])
            ->first();

        if (! $q && $this->createMissing) {
            $q = CuentaBancaria::create([
                'propietario_id' => $this->propietarioId,
                'alias' => trim($alias),
                'banco' => null,
                'tipo' => null,
                'numero' => null,
                'activa' => 1,
            ]);
        }

        $this->cacheCuenta[$key] = $q?->id;

        return $this->cacheCuenta[$key];
    }

    private function resolveFraccionamiento(string $nombre): ?int
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
        }

        $this->cacheFracc[$key] = $q?->id;

        return $this->cacheFracc[$key];
    }

    private function resolveLote(int $fraccId, string $manzana, string $lote): ?int
    {
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
            ->where('manzana', $manzana !== '' ? $manzana : null)
            ->where('lote', $lote)
            ->first();

        if (! $q && $this->createMissing) {
            $clave = $this->buildClaveLote($fraccId, $manzana, $lote);

            $q = Lote::create([
                'fraccionamiento_id' => $fraccId,
                'manzana' => $manzana !== '' ? $manzana : null,
                'lote' => $lote,
                'clave' => $clave,
                'estatus' => 'vendido',
            ]);
        }

        $this->cacheLote[$key] = $q?->id;

        return $this->cacheLote[$key];
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

    private function resolveCliente(string $nombres, string $apellidos): ?int
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

        $this->cacheCliente[$key] = $q?->id;

        return $this->cacheCliente[$key];
    }

    private function resolvePeriodoMensual(int $anio, int $mes, string $nombre): ?int
    {
        $key = "mensual|{$anio}|{$mes}";

        if (array_key_exists($key, $this->cachePeriodo)) {
            return $this->cachePeriodo[$key];
        }

        $q = Periodo::query()
            ->where('tipo', 'mensual')
            ->where('anio', $anio)
            ->where('mes', $mes)
            ->first();

        if (! $q && $this->createMissing) {
            $q = Periodo::create([
                'tipo' => 'mensual',
                'anio' => $anio,
                'mes' => $mes,
                'nombre' => $nombre ?: $this->mesNombre($mes),
            ]);
        }

        $this->cachePeriodo[$key] = $q?->id;

        return $this->cachePeriodo[$key];
    }

    private function parseFecha($value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return Carbon::createFromTimestampUTC(((int) $value - 25569) * 86400)->startOfDay();
        }

        $s = trim((string) $value);

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
        $s = (string) $value;
        $s = str_replace(['$', ',', ' '], '', $s);

        return is_numeric($s) ? (float) $s : 0.0;
    }

    private function normUpper($s): string
    {
        $s = trim((string) $s);
        $s = preg_replace('/\s+/', ' ', $s);

        return mb_strtoupper($s);
    }

    private function mesNombre(int $mes): string
    {
        return [
            1 => 'ENERO', 2 => 'FEBRERO', 3 => 'MARZO', 4 => 'ABRIL', 5 => 'MAYO', 6 => 'JUNIO',
            7 => 'JULIO', 8 => 'AGOSTO', 9 => 'SEPTIEMBRE', 10 => 'OCTUBRE', 11 => 'NOVIEMBRE', 12 => 'DICIEMBRE',
        ][$mes] ?? 'MES';
    }
}
