<?php

namespace Tests\Unit;

use App\Exports\Sheets\MonthlyIncomePendingSheet;
use App\Services\Reports\MonthlyIncomeReportData;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MonthlyIncomeReportDataTest extends TestCase
{
    public function test_los_contratos_morosos_forman_parte_del_reporte_mensual(): void
    {
        $this->assertContains('moroso', MonthlyIncomeReportData::REPORTABLE_CONTRACT_STATUSES);
        $this->assertNotContains('cancelado', MonthlyIncomeReportData::REPORTABLE_CONTRACT_STATUSES);
    }

    public function test_la_exportacion_identifica_claramente_la_cuota_pendiente(): void
    {
        $sheet = new MonthlyIncomePendingSheet(
            anio: 2026,
            mes: 4,
            propietarioId: null,
        );

        $this->assertSame('CUOTA PENDIENTE', $sheet->headings()[0]);
    }

    public function test_los_ingresos_no_dependen_del_estatus_actual_del_contrato(): void
    {
        $report = new class(2026, 4, null, 'como_va_mes') extends MonthlyIncomeReportData
        {
            public function consultasDeIngresosSql(): array
            {
                return [
                    $this->baseJoinRecibos()->toSql(),
                    $this->baseJoinRecibosFlujoReal()->toSql(),
                    $this->baseJoinRecibosHistoricosComoVaMes()->toSql(),
                    $this->baseJoinRecibosMensualidadPorFechaPago()->toSql(),
                ];
            }
        };

        foreach ($report->consultasDeIngresosSql() as $sql) {
            $this->assertStringNotContainsString('contratos`.`estatus', $sql);
        }
    }

    public function test_el_esperado_incluye_cuotas_anteriores_a_la_cancelacion(): void
    {
        $query = DB::table('cuotas')
            ->join('contratos', 'contratos.id', '=', 'cuotas.contrato_id')
            ->where(fn ($q) => MonthlyIncomeReportData::aplicarVigenciaHistoricaContrato($q));

        $sql = str_replace(['`', '"'], '', $query->toSql());

        $this->assertStringContainsString('contratos.deleted_at >= cuotas.fecha_vencimiento', $sql);
        $this->assertContains('cancelado', $query->getBindings());
    }
}
