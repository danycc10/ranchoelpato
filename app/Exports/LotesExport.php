<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class LotesExport implements FromArray, ShouldAutoSize, WithEvents, WithTitle
{
    public function __construct(
        public Collection $rows
    ) {}

    public function title(): string
    {
        return 'Lotes';
    }

    public function array(): array
    {
        $data = [];

        $data[] = ['REPORTE DE LOTES'];
        $data[] = [''];
        $data[] = [
            'Fraccionamiento',
            'Propietario',
            'Manzana',
            'Lote',
            'Clave',
            'Área m²',
            'Precio lista',
            'Estatus',
            'Notas',
        ];

        foreach ($this->rows as $row) {
            $data[] = [
                $row->fraccionamiento?->nombre ?? '',
                $row->fraccionamiento?->propietario?->nombre ?? '',
                $row->manzana ?? '',
                $row->lote ?? '',
                $row->clave ?? '',
                (float) ($row->area_m2 ?? 0),
                (float) ($row->precio_lista ?? 0),
                $this->nombreEstatus((string) ($row->estatus ?? '')),
                $row->notas ?? '',
            ];
        }

        return $data;
    }

    protected function nombreEstatus(string $estatus): string
    {
        return match ($estatus) {
            'disponible' => 'Disponible',
            'apartado' => 'Apartado',
            'vendido' => 'Vendido',
            'donacion' => 'Donación',
            'cancelado' => 'Cancelado',
            default => ucfirst($estatus),
        };
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet;
                $lastRow = 3 + $this->rows->count();

                $sheet->mergeCells('A1:I1');

                $sheet->getStyle('A1')->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 15,
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                $sheet->getStyle('A3:I3')->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'color' => ['rgb' => 'FFFFFF'],
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'color' => ['rgb' => '111827'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => 'D1D5DB'],
                        ],
                    ],
                ]);

                if ($lastRow >= 4) {
                    $sheet->getStyle("A4:I{$lastRow}")->applyFromArray([
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['rgb' => 'E5E7EB'],
                            ],
                        ],
                        'alignment' => [
                            'vertical' => Alignment::VERTICAL_CENTER,
                        ],
                    ]);

                    $sheet->getStyle("F4:F{$lastRow}")
                        ->getNumberFormat()
                        ->setFormatCode('#,##0.00');

                    $sheet->getStyle("G4:G{$lastRow}")
                        ->getNumberFormat()
                        ->setFormatCode('$#,##0.00');
                }

                $sheet->freezePane('A4');
                $sheet->getRowDimension(3)->setRowHeight(28);

                $sheet->getColumnDimension('A')->setWidth(24);
                $sheet->getColumnDimension('B')->setWidth(24);
                $sheet->getColumnDimension('C')->setWidth(12);
                $sheet->getColumnDimension('D')->setWidth(12);
                $sheet->getColumnDimension('E')->setWidth(18);
                $sheet->getColumnDimension('F')->setWidth(12);
                $sheet->getColumnDimension('G')->setWidth(14);
                $sheet->getColumnDimension('H')->setWidth(14);
                $sheet->getColumnDimension('I')->setWidth(40);

                $sheet->getStyle("C4:H{$lastRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
            },
        ];
    }
}
