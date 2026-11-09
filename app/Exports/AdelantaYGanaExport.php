<?php

namespace App\Exports;

use App\Models\AbonoCapital;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class AdelantaYGanaExport implements FromCollection, WithColumnFormatting, WithEvents, WithHeadings
{
    public function __construct(
        public Collection $abonos,
        public string $titulo,
    ) {}

    public function headings(): array
    {
        return [
            'FECHA',
            'RECIBO',
            'CLIENTE',
            'CONTRATO',
            'FINCA',
            'LOTE',
            'ABONO_EFECTIVO',
            'BONIFICACION',
            'BAJO_SALDO',
            'SALDO_ANTERIOR',
            'SALDO_NUEVO',
            'CAPTURO',
            'ESTADO',
        ];
    }

    public function collection(): Collection
    {
        return $this->abonos->map(fn (AbonoCapital $a) => [
            $a->created_at?->format('d/m/Y H:i') ?? '',
            (string) ($a->recibo?->folio ?? ''),
            (string) ($a->contrato?->cliente?->nombre_completo ?? ''),
            (string) ($a->contrato?->folio_contrato ?? ''),
            (string) ($a->contrato?->lote?->fraccionamiento?->nombre ?? ''),
            'MZ '.($a->contrato?->lote?->manzana ?? '—').' LT '.($a->contrato?->lote?->lote ?? '—'),
            (float) $a->monto_abono,
            (float) $a->monto_bonificacion,
            $a->totalAplicado(),
            (float) $a->saldo_anterior,
            (float) $a->saldo_nuevo,
            (string) ($a->capturadoPor?->name ?? ''),
            $a->anulado_at ? 'ANULADO' : 'APLICADO',
        ]);
    }

    public function columnFormats(): array
    {
        return [
            'G' => '"$"#,##0.00',
            'H' => '"$"#,##0.00',
            'I' => '"$"#,##0.00',
            'J' => '"$"#,##0.00',
            'K' => '"$"#,##0.00',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestCol = $sheet->getHighestColumn();

                $sheet->insertNewRowBefore(1, 1);
                $sheet->mergeCells("A1:{$highestCol}1");
                $sheet->setCellValue('A1', $this->titulo);

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

                foreach (['A' => 16, 'B' => 14, 'C' => 30, 'D' => 20, 'E' => 24, 'F' => 14, 'G' => 16, 'H' => 14, 'I' => 14, 'J' => 16, 'K' => 14, 'L' => 20, 'M' => 12] as $col => $ancho) {
                    $sheet->getColumnDimension($col)->setWidth($ancho);
                }

                $sheet->setShowGridlines(false);
            },
        ];
    }
}
