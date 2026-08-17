<?php

namespace Tests\Feature;

use App\Exports\Sheets\MonthlyIncomeActiveContractsSheet;
use App\Models\Cliente;
use App\Models\Cuota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MonthlyIncomeActiveContractsSheetTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function la_hoja_expone_las_columnas_de_dia_de_cobro_y_semanas(): void
    {
        $sheet = new MonthlyIncomeActiveContractsSheet(anio: 2026, mes: 8, propietarioId: null);

        $this->assertSame('DÍA DE COBRO', $sheet->headings()[4]);
        $this->assertSame('SEMANAS DEL MES', $sheet->headings()[5]);
    }

    #[Test]
    public function los_contratos_semanales_muestran_su_dia_de_cobro_y_las_semanas_del_mes(): void
    {
        $this->registrarConcatWsEnSqlite();

        // Agosto 2026 tiene 5 lunes: el semanal cubre 5 semanas y el mensual una sola cuota.
        $semanal = $this->contrato('SEMANAL', 'semanal', [
            '2026-08-03', '2026-08-10', '2026-08-17', '2026-08-24', '2026-08-31',
        ], diaSemana: 1);
        $mensual = $this->contrato('MENSUAL', 'mensual', ['2026-08-10']);

        $filas = $this->filasDelMes(2026, 8);

        $this->assertSame('LUNES', $filas[$semanal][4]);
        $this->assertSame(5, $filas[$semanal][5]);

        $this->assertSame('', $filas[$mensual][4]);
        $this->assertSame('', $filas[$mensual][5]);
    }

    #[Test]
    public function las_semanas_reflejan_las_cuotas_reales_del_mes(): void
    {
        $this->registrarConcatWsEnSqlite();

        // Un mes con solo tres vencimientos semanales (por ejemplo, tras reprogramar).
        $semanal = $this->contrato('PARCIAL', 'semanal', [
            '2026-09-05', '2026-09-12', '2026-09-19', '2026-10-03',
        ], diaSemana: 6);

        $filas = $this->filasDelMes(2026, 9);

        $this->assertSame('SÁBADO', $filas[$semanal][4]);
        $this->assertSame(3, $filas[$semanal][5]);
    }

    #[Test]
    public function el_dia_de_cobro_se_deduce_del_vencimiento_cuando_el_contrato_no_lo_tiene(): void
    {
        $this->registrarConcatWsEnSqlite();

        // Contratos importados que llegaron sin dia_semana capturado.
        $semanal = $this->contrato('SIN-DIA', 'semanal', [
            '2026-10-07', '2026-10-14', '2026-10-21', '2026-10-28',
        ], diaSemana: null);

        $filas = $this->filasDelMes(2026, 10);

        $this->assertSame('MIÉRCOLES', $filas[$semanal][4]);
        $this->assertSame(4, $filas[$semanal][5]);
    }

    /**
     * Filas de la hoja indexadas por lote.
     */
    private function filasDelMes(int $anio, int $mes)
    {
        return (new MonthlyIncomeActiveContractsSheet(anio: $anio, mes: $mes, propietarioId: null))
            ->collection()
            ->keyBy(fn (array $fila) => $fila[1]);
    }

    /**
     * La hoja arma el nombre del cliente con CONCAT_WS, que SQLite no trae.
     */
    private function registrarConcatWsEnSqlite(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            return;
        }

        DB::connection()->getPdo()->sqliteCreateFunction(
            'CONCAT_WS',
            function ($separador, ...$partes) {
                return implode(
                    (string) $separador,
                    array_filter($partes, fn ($parte) => $parte !== null)
                );
            }
        );
    }

    /**
     * Crea contrato de terreno con sus cuotas y devuelve la clave del lote,
     * que es la columna con la que se identifican las filas de la hoja.
     */
    private function contrato(
        string $etiqueta,
        string $frecuencia,
        array $vencimientos,
        ?int $diaSemana = null
    ): string {
        $ahora = now();

        $cliente = Cliente::create([
            'nombres' => 'Cliente',
            'apellidos' => $etiqueta,
            'telefono' => null,
        ]);

        $propietarioId = DB::table('propietarios')->insertGetId([
            'nombre' => 'Propietario '.$etiqueta, 'created_at' => $ahora, 'updated_at' => $ahora,
        ]);
        $fraccionamientoId = DB::table('fraccionamientos')->insertGetId([
            'propietario_id' => $propietarioId,
            'nombre' => 'Fraccionamiento '.$etiqueta,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);
        $loteId = DB::table('lotes')->insertGetId([
            'fraccionamiento_id' => $fraccionamientoId,
            'lote' => 'LOTE-'.$etiqueta,
            'clave' => 'CLAVE-'.$etiqueta,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);
        $contratoId = DB::table('contratos')->insertGetId([
            'cliente_id' => $cliente->id,
            'lote_id' => $loteId,
            'folio_contrato' => 'CONTRATO-'.$etiqueta,
            'fecha_inicio' => '2026-08-01',
            'frecuencia' => $frecuencia,
            'dia_semana' => $diaSemana,
            'precio_total' => 1000,
            'enganche' => 0,
            'saldo_inicial' => 1000,
            'saldo_actual' => 1000,
            'monto_pago' => 200,
            'tipo_recargo' => 'fijo',
            'valor_recargo' => 0,
            'dias_gracia' => 0,
            'estatus' => 'activo',
            'tipo' => 'terreno',
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);

        foreach ($vencimientos as $numero => $vencimiento) {
            // Insert directo para guardar la fecha sin hora: el cast del modelo le
            // agrega 00:00:00 y SQLite, que compara como texto, dejaría fuera el
            // último día del mes (en MySQL la columna es DATE y no se nota).
            DB::table('cuotas')->insert([
                'contrato_id' => $contratoId,
                'numero' => $numero + 1,
                'fecha_vencimiento' => $vencimiento,
                'monto' => 200,
                'estatus' => 'pendiente',
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }

        return 'LOTE-'.$etiqueta;
    }
}
