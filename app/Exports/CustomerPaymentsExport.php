<?php

namespace App\Exports;

use App\Exports\Sheets\CustomerContractsSheet;
use App\Exports\Sheets\CustomerPaymentsSheet;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class CustomerPaymentsExport implements WithMultipleSheets
{
    public function __construct(
        public string $clienteNombre,
        public Collection $pagos,
        public Collection $resumen,
        public ?string $contratoFolio = null,
        public ?string $finca = null,
        public ?string $manzana = null,
        public ?string $lote = null,
    ) {}

    public function sheets(): array
    {
        return [
            new CustomerPaymentsSheet(
                clienteNombre: $this->clienteNombre,
                pagos: $this->pagos,
                contratoFolio: $this->contratoFolio,
                finca: $this->finca,
           
                lote: $this->lote,
            ),
            new CustomerContractsSheet(
                clienteNombre: $this->clienteNombre,
                resumen: $this->resumen,
                contratoFolio: $this->contratoFolio,
                finca: $this->finca,
          
                lote: $this->lote,
            ),
        ];
    }
}