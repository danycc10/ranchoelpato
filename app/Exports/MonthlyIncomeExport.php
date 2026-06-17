<?php

namespace App\Exports;

use App\Exports\Sheets\MonthlyIncomeActiveContractsSheet;
use App\Exports\Sheets\MonthlyIncomeAdvanceDetailSheet;
use App\Exports\Sheets\MonthlyIncomeDetailSheet;
use App\Exports\Sheets\MonthlyIncomeHistoricDetailSheet;
use App\Exports\Sheets\MonthlyIncomeLateDetailSheet;
use App\Exports\Sheets\MonthlyIncomeLiquidatedContractsSheet;
use App\Exports\Sheets\MonthlyIncomePendingSheet;
use App\Exports\Sheets\MonthlyIncomeSummarySheet;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class MonthlyIncomeExport implements WithMultipleSheets
{
    public function __construct(
        public int $anio,
        public int $mes,
        public ?int $propietarioId,
        public string $modoVista,
        public Collection $metodosPago,
        public Collection $filas,
        public object $totales,
        public ?object $headerConceptos = null,
        public ?object $conceptosPorFinca = null,
    ) {}

    public function sheets(): array
    {
        $sheets = [
            new MonthlyIncomeSummarySheet(
                anio: $this->anio,
                mes: $this->mes,
                modoVista: $this->modoVista,
                metodosPago: $this->metodosPago,
                conceptosPorFinca: $this->conceptosPorFinca,
                filas: $this->filas,
                totales: $this->totales,
                headerConceptos: $this->headerConceptos,
                mesNombre: mb_strtoupper(now()->setMonth($this->mes)->translatedFormat('F')),
            ),

            new MonthlyIncomeDetailSheet(
                anio: $this->anio,
                mes: $this->mes,
                propietarioId: $this->propietarioId,
                modoVista: $this->modoVista,
            ),

            new MonthlyIncomeAdvanceDetailSheet(
                anio: $this->anio,
                mes: $this->mes,
                propietarioId: $this->propietarioId,
            ),

            new MonthlyIncomeLateDetailSheet(
                anio: $this->anio,
                mes: $this->mes,
                propietarioId: $this->propietarioId,
            ),
        ];

        if ($this->modoVista !== 'flujo_real') {
            $sheets[] = new MonthlyIncomeHistoricDetailSheet(
                anio: $this->anio,
                mes: $this->mes,
                propietarioId: $this->propietarioId,
            );
        }

        $sheets[] = new MonthlyIncomeLiquidatedContractsSheet(
            anio: $this->anio,
            mes: $this->mes,
            propietarioId: $this->propietarioId,
        );

        $sheets[] = new MonthlyIncomeActiveContractsSheet(
            anio: $this->anio,
            mes: $this->mes,
            propietarioId: $this->propietarioId,
        );

        $sheets[] = new MonthlyIncomePendingSheet(
            anio: $this->anio,
            mes: $this->mes,
            propietarioId: $this->propietarioId,
        );

        return $sheets;
    }
}
