<?php

namespace App\Exports\Sheets;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class CustomerContractsSheet implements FromCollection, ShouldAutoSize, WithColumnFormatting, WithEvents, WithHeadings, WithTitle
{
    public function __construct(
        public string $clienteNombre,
        public Collection $resumen,
        public ?string $contratoFolio = null,
        public ?string $finca = null,
        public ?string $lote = null,
    ) {}

    public function title(): string
    {
        return 'Contratos';
    }

    public function headings(): array
    {
        return [
            'FINCA',
            'LOTE',
            'ESTATUS',
            'PRECIO TOTAL',
            'ENGANCHE',
            'TOTAL PAGADO',
            'SALDO RESTANTE (CALC)',
            'SALDO ACTUAL',
            'ULTIMO PAGO',
        ];
    }

    public function collection()
    {
        return $this->resumen->map(function ($c) {
            $get = fn ($key, $default = null) => is_array($c) ? ($c[$key] ?? $default) : ($c->{$key} ?? $default);

            return [
                (string) $get('finca', ''),
                (string) $get('lote', ''),
                (string) $get('estatus', ''),
                (float) $get('precio_total', 0),
                (float) $get('enganche', 0),
                (float) $get('total_pagado', 0),
                (float) $get('saldo_restante_calc', 0),
                (float) $get('saldo_actual', 0),
                (string) $get('ultimo_pago', ''),
            ];
        });
    }

    public function columnFormats(): array
    {
        return [
            'D' => NumberFormat::FORMAT_CURRENCY_USD_SIMPLE,
            'E' => NumberFormat::FORMAT_CURRENCY_USD_SIMPLE,
            'F' => NumberFormat::FORMAT_CURRENCY_USD_SIMPLE,
            'G' => NumberFormat::FORMAT_CURRENCY_USD_SIMPLE,
            'H' => NumberFormat::FORMAT_CURRENCY_USD_SIMPLE,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                $sheet->insertNewRowBefore(1, 2);

                $subtitle = "Cliente: {$this->clienteNombre}";
                if ($this->contratoFolio) {
                    $subtitle .= " | Contrato: {$this->contratoFolio}";
                }

                if ($this->finca || $this->lote) {
                    $subtitle .= ' | Finca: '.($this->finca ?? '—')
                        .' | Lote: '.($this->lote ?? '—');
                }

                $sheet->setCellValue('A1', 'RESUMEN DE CONTRATOS');
                $sheet->mergeCells('A1:I1');
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => [
                        'name' => 'Dubai Medium',
                        'bold' => true,
                        'size' => 14,
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                    ],
                ]);

                $sheet->setCellValue('A2', $subtitle);
                $sheet->mergeCells('A2:I2');
                $sheet->getStyle('A2')->applyFromArray([
                    'font' => [
                        'name' => 'Dubai Medium',
                        'size' => 11,
                        'color' => ['rgb' => '555555'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                    ],
                ]);

                $lastRow = $sheet->getHighestRow();

                // Fuerza la fuente en todo el rango usado
                $sheet->getStyle("A1:I{$lastRow}")->applyFromArray([
                    'font' => [
                        'name' => 'Dubai Medium',
                    ],
                ]);

                $sheet->getStyle('A3:I3')->applyFromArray([
                    'font' => [
                        'name' => 'Dubai Medium',
                        'bold' => true,
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'color' => ['rgb' => 'F3F4F6'],
                    ],
                    'borders' => [
                        'bottom' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => 'D1D5DB'],
                        ],
                    ],
                ]);

                $sheet->freezePane('A4');
                $sheet->setAutoFilter("A3:I{$lastRow}");

                $sheet->getStyle("A3:I{$lastRow}")
                    ->getBorders()
                    ->getAllBorders()
                    ->setBorderStyle(Border::BORDER_HAIR);

                foreach (['D', 'E', 'F', 'G', 'H'] as $col) {
                    $sheet->getStyle("{$col}4:{$col}{$lastRow}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                    $sheet->getStyle("{$col}4:{$col}{$lastRow}")->applyFromArray([
                        'font' => [
                            'name' => 'Dubai Medium',
                        ],
                    ]);
                }

                $sheet->setShowGridlines(false);
            },
        ];
    }
}
