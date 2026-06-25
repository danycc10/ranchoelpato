<?php

namespace App\Exports;

use App\Exports\Sheets\DailyReceiptsDetailSheet;
use App\Exports\Sheets\DailyReceiptsSummarySheet;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class DailyReceiptsExport implements WithMultipleSheets
{
    public function __construct(
        public string $fecha,
        public ?string $propietarioNombre,
        public Collection $rows,
        public Collection $detailRows,
    ) {}

    public function sheets(): array
    {
        return [
            new DailyReceiptsSummarySheet(
                fecha: $this->fecha,
                propietarioNombre: $this->propietarioNombre,
                rows: $this->rows,
            ),
            new DailyReceiptsDetailSheet(
                fecha: $this->fecha,
                propietarioNombre: $this->propietarioNombre,
                detailRows: $this->detailRows,
            ),
        ];
    }
}
