<?php

namespace App\Imports;

use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Fraccionamiento;
use App\Models\Lote;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Row;

class ContratosDonacionImport implements OnEachRow, WithChunkReading, WithStartRow
{
    public function __construct(
        private readonly int $propietarioId,
        private readonly ?int $capturadoPorUserId = null,
        private readonly bool $createMissing = true,

        private readonly string $contratoEstatusActivo = 'donacion',
        private readonly string $loteEstatusDonacion = 'donacion',
        private readonly string $tipoContratoDonacion = 'terreno',
    ) {}

    private array $cacheFracc = [];

    private array $cacheLote = [];

    private array $cacheCliente = [];

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

        $col0 = $this->normalizeCatalog($r[0] ?? '');
        $col1 = $this->normalizeCatalog($r[1] ?? '');
        $col2 = $this->normalizeCatalog($r[2] ?? '');

        if ($col0 === 'APELLIDO' && $col1 === 'NOMBRE' && $col2 === 'RESIDENCIAL') {
            Log::info('IMPORT DONACION: header detectado, se omite', [
                'excel_row' => $excelRow,
            ]);

            return;
        }

        if ($this->propietarioId <= 0) {
            throw new \RuntimeException('Import donación: propietarioId inválido (0). Debes pasar un propietario_id existente.');
        }

        /**
         * Layout esperado:
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
         * 10 MENSUALIDAD
         * 11 RECARGO
         * 12 MONTO
         * 13 DIAS GRACIA
         * 14 ESTATUS
         * 15 FRECUENCIA
         * 16 DIA_MES
         * 17 DIA_SEMANA
         * 18 tiene_anualidad
         * 19 anualidad_fecha
         * 20 anualidad_monto
         *
         * Para donación realmente usaremos sólo lo necesario.
         */
        $apellidos = $this->normUpper($r[0] ?? '');
        $nombres = $this->normUpper($r[1] ?? '');
        $residencial = $this->normUpper($r[2] ?? '');
        $manzana = trim((string) ($r[3] ?? ''));
        $loteNum = trim((string) ($r[4] ?? ''));
        $telefono = $this->normPhone($r[5] ?? '');
        $fecha = $this->parseFecha($r[6] ?? null);

        $precio = $this->parseMoney($r[7] ?? 0);
        $estatusRaw = (string) ($r[14] ?? '');
        $estatusNorm = $this->normalizeEstatus($estatusRaw);

        if ($residencial === '' || $loteNum === '') {
            Log::warning('IMPORT DONACION omitido: faltan datos mínimos', [
                'excel_row' => $excelRow,
                'residencial' => $residencial,
                'manzana' => $manzana,
                'lote' => $loteNum,
                'cliente' => trim("$apellidos $nombres"),
            ]);

            return;
        }

        $fechaContrato = ($fecha ?: now())->startOfDay();

        Log::info('DEBUG IMPORT DONACION', [
            'excel_row' => $excelRow,
            'cliente' => trim("$apellidos $nombres"),
            'residencial' => $residencial,
            'manzana' => $manzana,
            'lote' => $loteNum,
            'telefono' => $telefono,
            'fecha' => $fechaContrato->toDateString(),
            'precio' => $precio,
            'estatus_raw' => $estatusRaw,
            'estatus_norm' => $estatusNorm,
            'propietario_id' => $this->propietarioId,
        ]);

        try {
            DB::transaction(function () use (
                $excelRow,
                $apellidos,
                $nombres,
                $residencial,
                $manzana,
                $loteNum,
                $telefono,
                $fechaContrato,
                $precio
            ) {
                $fraccId = $this->resolveFraccionamiento($residencial, $excelRow);
                if (! $fraccId) {
                    return;
                }

                $loteId = $this->resolveLote(
                    fraccId: $fraccId,
                    manzana: $manzana,
                    lote: $loteNum,
                    excelRow: $excelRow,
                    estatusOnCreate: $this->loteEstatusDonacion,
                    precioListaOnCreate: $precio > 0 ? $precio : null
                );

                if (! $loteId) {
                    return;
                }

                $clienteId = $this->resolveCliente($nombres, $apellidos, $excelRow);
                if (! $clienteId) {
                    return;
                }

                if ($telefono !== '') {
                    $cliente = Cliente::find($clienteId);
                    if ($cliente && empty($cliente->telefono)) {
                        $cliente->telefono = $telefono;
                        $cliente->save();
                    }
                }

                $lote = Lote::find($loteId);
                if (! $lote) {
                    Log::warning('IMPORT DONACION: lote no encontrado después de resolver', [
                        'excel_row' => $excelRow,
                        'lote_id' => $loteId,
                    ]);

                    return;
                }

                // siempre dejar el lote como donación
                $updateLote = [
                    'estatus' => $this->loteEstatusDonacion,
                    'updated_at' => now(),
                ];

                if ((float) $precio > 0 && (float) ($lote->precio_lista ?? 0) <= 0) {
                    $updateLote['precio_lista'] = (float) $precio;
                }

                Lote::where('id', $loteId)->update($updateLote);

                /**
                 * Evitar duplicados:
                 * si ya existe un contrato de informativo_donacion para ese lote, se actualiza
                 */
                $contrato = Contrato::query()
                    ->where('lote_id', $loteId)
                    ->where('tipo', $this->tipoContratoDonacion)
                    ->orderByDesc('id')
                    ->first();

                if (! $contrato) {
                    $contrato = new Contrato;
                    $contrato->uuid = (string) Str::uuid();
                    $contrato->folio_contrato = $this->generarFolioSeguro();
                }

                $payload = [
                    'cliente_id' => $clienteId,
                    'lote_id' => $loteId,

                    // tipo especial
                    'tipo' => $this->tipoContratoDonacion,

                    // fecha base
                    'fecha_inicio' => $fechaContrato->toDateString(),

                    // sin plan de pagos
                    'frecuencia' => 'mensual',
                    'dia_semana' => null,
                    'dia_mes' => 1,

                    'precio_total' => (float) $precio,
                    'enganche' => 0,
                    'saldo_inicial' => 0,
                    'saldo_actual' => 0,
                    'monto_pago' => 0,

                    'tipo_recargo' => 'fijo',
                    'valor_recargo' => 0,
                    'dias_gracia' => 0,

                    'tiene_anualidad' => 0,
                    'anualidad_fecha' => null,
                    'anualidad_monto' => null,

                    'estatus' => $this->contratoEstatusActivo,
                    'liquidado_at' => $fechaContrato->copy()->startOfDay(),
                ];

                $contrato->fill($payload);

                // Si tu tabla tiene capturado_por_user_id, se asigna
                if ($this->hasColumn('contratos', 'capturado_por_user_id')) {
                    $contrato->capturado_por_user_id = $this->capturadoPorUserId;
                }

                // Si tienes observaciones o descripción en otra columna, aquí puedes guardarla
                if ($this->hasColumn('contratos', 'observaciones') && empty($contrato->observaciones)) {
                    $contrato->observaciones = 'Informativo de donación generado por importación';
                }

                $contrato->save();

                Log::info('IMPORT DONACION OK', [
                    'excel_row' => $excelRow,
                    'contrato_id' => $contrato->id,
                    'folio_contrato' => $contrato->folio_contrato,
                    'cliente_id' => $clienteId,
                    'lote_id' => $loteId,
                    'fraccionamiento_id' => $fraccId,
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('IMPORT DONACION error en fila', [
                'excel_row' => $excelRow,
                'residencial' => $residencial,
                'manzana' => $manzana,
                'lote' => $loteNum,
                'cliente' => trim("$apellidos $nombres"),
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function generarFolioSeguro(): string
    {
        return 'DON-'.now()->format('YmdHis').'-'.strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
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

            Log::notice('Fraccionamiento creado por import donación', [
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
                'estatus' => $estatusOnCreate ?: $this->loteEstatusDonacion,
            ];

            if ($precioListaOnCreate !== null && (float) $precioListaOnCreate > 0) {
                $payload['precio_lista'] = (float) $precioListaOnCreate;
            }

            $q = Lote::create($payload);

            Log::notice('Lote creado por import donación', [
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

            Log::notice('Cliente creado por import donación', [
                'excel_row' => $excelRow,
                'cliente_id' => $q->id,
                'nombres' => $q->nombres,
                'apellidos' => $q->apellidos,
            ]);
        }

        return $this->cacheCliente[$key] = $q?->id;
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

    private function allEmpty(array $row): bool
    {
        foreach ($row as $v) {
            if (trim((string) $v) !== '') {
                return false;
            }
        }

        return true;
    }

    private function hasColumn(string $table, string $column): bool
    {
        static $cache = [];

        $key = "{$table}.{$column}";

        if (! array_key_exists($key, $cache)) {
            $cache[$key] = \Schema::hasColumn($table, $column);
        }

        return $cache[$key];
    }
}
