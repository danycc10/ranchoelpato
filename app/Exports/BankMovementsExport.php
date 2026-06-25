<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class BankMovementsExport implements FromArray, ShouldAutoSize, WithColumnFormatting, WithEvents, WithTitle
{
    protected int $lastRow = 1;

    protected int $headerRow = 4;

    protected int $dataStartRow = 5;

    protected int $totalRow = 0;

    public function __construct(
        public string $desde,
        public string $hasta,
        public ?string $cuentaNombre,
        public Collection $rows
    ) {}

    public function title(): string
    {
        return 'Movimientos Bancarios';
    }

    public function array(): array
    {
        $out = [];

        $out[] = ['REPORTE DE MOVIMIENTOS BANCARIOS'];
        $out[] = ["Rango: {$this->desde} a {$this->hasta}"];
        $out[] = ['Cuenta: '.($this->cuentaNombre ?: 'Todas')];

        $out[] = [
            'Fecha',
            'Folio',
            'Cliente',
            'Finca',
            'Manzana',
            'Lote',
            'Concepto',
            'Cuota',
            'Monto',
            'Forma de pago',
            'Cuenta bancaria',
        ];

        $total = 0;

        foreach ($this->rows as $r) {
            $cliente = trim(
                ($r->recibo?->cliente?->nombres ?? '').' '.
                ($r->recibo?->cliente?->apellidos ?? '')
            );

            $finca = $r->recibo?->contrato?->lote?->fraccionamiento?->nombre ?? '';
            $manzana = $r->recibo?->contrato?->lote?->manzana ?? '';
            $lote = $r->recibo?->contrato?->lote?->lote ?? '';

            $cuentaTxt = $r->cuentaBancaria?->alias ?? '';

            $cuotaTxt = '';

            if ($r->recibo?->cuota) {
                $cuotaTxt = $r->recibo->cuota->numero;
            }

            $monto = (float) ($r->monto ?? 0);
            $total += $monto;

            $out[] = [
                $r->created_at ? Carbon::parse($r->created_at)->format('Y-m-d') : '',
                $r->recibo?->folio ?? '',
                $cliente,
                $finca,
                $manzana,
                $lote,
                $r->recibo?->tipoCobro?->nombre ?? '',
                $cuotaTxt,
                $monto,
                $r->formaPago?->nombre ?? '',
                $cuentaTxt,
            ];
        }

        $out[] = ['', '', '', '', '', '', '', 'TOTAL', $total, '', ''];

        $this->lastRow = count($out);
        $this->totalRow = $this->lastRow;

        return $out;
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_DATE_YYYYMMDD2,
            'I' => NumberFormat::FORMAT_CURRENCY_USD_SIMPLE,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastCol = 'K';

                $sheet->getStyle("A1:{$lastCol}{$this->lastRow}")->applyFromArray([
                    'font' => [
                        'name' => 'Dubai Medium',
                    ],
                ]);

                $sheet->mergeCells("A1:{$lastCol}1");
                $sheet->mergeCells("A2:{$lastCol}2");
                $sheet->mergeCells("A3:{$lastCol}3");

                $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
                    'font' => [
                        'name' => 'Dubai Medium',
                        'bold' => true,
                        'size' => 15,
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                $sheet->getStyle("A2:{$lastCol}3")->applyFromArray([
                    'font' => [
                        'name' => 'Dubai Medium',
                        'size' => 11,
                        'color' => ['rgb' => '4B5563'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                $sheet->getStyle("A{$this->headerRow}:{$lastCol}{$this->headerRow}")->applyFromArray([
                    'font' => [
                        'name' => 'Dubai Medium',
                        'bold' => true,
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'F3F4F6'],
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => 'E5E7EB'],
                        ],
                    ],
                ]);

                $sheet->getStyle("A{$this->headerRow}:{$lastCol}{$this->totalRow}")->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => 'E5E7EB'],
                        ],
                    ],
                    'alignment' => [
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                    'font' => [
                        'name' => 'Dubai Medium',
                    ],
                ]);

                $sheet->getStyle("H{$this->totalRow}:I{$this->totalRow}")->applyFromArray([
                    'font' => [
                        'name' => 'Dubai Medium',
                        'bold' => true,
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'FEF3C7'],
                    ],
                ]);

                $sheet->getStyle("I{$this->dataStartRow}:I{$this->totalRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                $sheet->getStyle("A{$this->dataStartRow}:B{$this->totalRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->getStyle("E{$this->dataStartRow}:H{$this->totalRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->getStyle("I{$this->dataStartRow}:I{$this->totalRow}")->applyFromArray([
                    'font' => [
                        'name' => 'Dubai Medium',
                    ],
                ]);

                $sheet->freezePane('A5');
                $sheet->setShowGridlines(false);

                $sheet->getRowDimension(1)->setRowHeight(24);
                $sheet->getRowDimension(2)->setRowHeight(20);
                $sheet->getRowDimension(3)->setRowHeight(20);
                $sheet->getRowDimension(4)->setRowHeight(22);
            },
        ];
    }
}
