<?php

namespace Tests\Unit;

use App\Exports\Sheets\MonthlyIncomePendingSheet;
use App\Services\Reports\MonthlyIncomeReportData;
use PHPUnit\Framework\TestCase;

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
}
