<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class EarlyPayersExport implements FromCollection, WithColumnFormatting, WithEvents, WithHeadings
{
    public function __construct(
        public Collection $filas,
        public string $mesNombre,
    ) {}

    public function headings(): array
    {
        return [
            'FRECUENCIA',
            'CLIENTE',
            'FINCA(S)',
            'CONTRATOS',
            'PRIMER_PAGO',
            'DIA_PAGO',
            'MONTO',
            'CUOTAS_ANTICIPADAS',
        ];
    }

    public function collection(): Collection
    {
        return $this->filas->map(fn ($r) => [
            mb_strtoupper((string) ($r->frecuencia ?? '')),
            (string) ($r->cliente ?? ''),
            (string) ($r->fraccionamiento ?? ''),
            (int) ($r->contratos_count ?? 1),
            (string) ($r->fecha_pago ?? ''),
            (int) ($r->dia_pago ?? 0),
            (float) ($r->monto ?? 0),
            $r->cuotas_anticipadas !== null ? (int) $r->cuotas_anticipadas : '',
        ]);
    }

    public function columnFormats(): array
    {
        return [
            'G' => '"$"#,##0.00',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();
                $highestCol = $sheet->getHighestColumn();

                $sheet->insertNewRowBefore(1, 1);
                $sheet->mergeCells("A1:{$highestCol}1");
                $sheet->setCellValue('A1', 'PAGADORES ADELANTADOS · ' . $this->mesNombre);

                $sheet->getStyle("A1:{$highestCol}1")->applyFromArray([
                    'font' => ['name' => 'Dubai Medium', 'bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '065F46']],
                ]);

                $sheet->getRowDimension(1)->setRowHeight(26);

                $sheet->freezePane('A3');
                $sheet->setAutoFilter("A2:{$highestCol}2");

                $sheet->getStyle("A2:{$highestCol}2")->applyFromArray([
                    'font' => ['name' => 'Dubai Medium', 'bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '111827']],
                ]);

                $highestRow = $sheet->getHighestRow();

                for ($r = 3; $r <= $highestRow; $r++) {
                    if ($r % 2 === 0) {
                        $sheet->getStyle("A{$r}:{$highestCol}{$r}")->applyFromArray([
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'ECFDF5']],
                        ]);
                    }
                }

                $sheet->getStyle("G3:G{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                $sheet->getColumnDimension('A')->setWidth(12);
                $sheet->getColumnDimension('B')->setWidth(30);
                $sheet->getColumnDimension('C')->setWidth(30);
                $sheet->getColumnDimension('D')->setWidth(12);
                $sheet->getColumnDimension('E')->setWidth(14);
                $sheet->getColumnDimension('F')->setWidth(10);
                $sheet->getColumnDimension('G')->setWidth(14);
                $sheet->getColumnDimension('H')->setWidth(18);

                $sheet->setShowGridlines(false);
            },
        ];
    }
}
