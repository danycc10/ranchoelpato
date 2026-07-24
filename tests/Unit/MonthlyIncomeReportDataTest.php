<?php

namespace Tests\Unit;

use App\Exports\Sheets\MonthlyIncomePendingSheet;
use App\Services\Reports\MonthlyIncomeReportData;
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
}
