<?php

namespace App\Exports\Sheets;

use Carbon\Carbon;
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

class CustomerPaymentsSheet implements FromCollection, ShouldAutoSize, WithColumnFormatting, WithEvents, WithHeadings, WithTitle
{
    public function __construct(
        public string $clienteNombre,
        public Collection $pagos,
        public ?string $contratoFolio = null,
        public ?string $finca = null,
        public ?string $lote = null,
    ) {}

    public function title(): string
    {
        return 'Pagos';
    }

    public function headings(): array
    {
        return [
            'FECHA PAGO',
            'FINCA',
            'LOTE',
            'CUOTA',
            'RECIBO',
            'CONCEPTO',
            'METODO',
            'CUENTA',
            'ESTATUS',
            'MONTO',
        ];
    }

    public function collection()
    {
        return $this->pagos->map(function ($p) {
            $concepto = strtoupper(trim((string) ($p->recibo?->tipoCobro?->nombre ?? '')));

            $metodoReal = strtoupper(trim((string) (
                $p->formaPago?->nombre
                ?? $p->recibo?->formaPago?->nombre
                ?? $p->metodo
                ?? ''
            )));

            $cuentaObj = $p->cuentaBancaria;

            $cuenta = $cuentaObj?->alias ?? '';

            if (! $cuenta && $cuentaObj) {
                $cuenta = trim(
                    ($cuentaObj->banco ?? '').
                    (($cuentaObj->numero ?? null) ? ' - '.$cuentaObj->numero : '')
                );
            }

            $cuotaNum = $p->recibo?->cuota?->numero
                ?? $p->recibo?->cuota?->no_cuota
                ?? $p->recibo?->cuota?->numero_cuota
                ?? $p->recibo?->cuota_id
                ?? $p->cuota_id
                ?? '';

            $finca = $p->recibo?->contrato?->lote?->fraccionamiento?->nombre ?? '';

            $lote = $p->recibo?->contrato?->lote?->lote
                ?? $p->recibo?->contrato?->lote?->numero
                ?? $p->recibo?->contrato?->lote?->num_lote
                ?? '';

            $fechaPago = $p->created_at
                ? Carbon::parse($p->created_at)->format('Y-m-d')
                : '';

            return [
                (string) $fechaPago,
                (string) $finca,
                (string) $lote,
                (string) $cuotaNum,
                (string) ($p->recibo?->folio ?? $p->recibo_id ?? ''),
                (string) ($concepto !== '' ? $concepto : ''),
                (string) ($metodoReal !== '' ? $metodoReal : ''),
                (string) ($cuenta !== '' ? $cuenta : ''),
                (string) ($p->estatus ?? ''),
                (float) ($p->monto ?? 0),
            ];
        });
    }

    public function columnFormats(): array
    {
        return [
            'J' => NumberFormat::FORMAT_CURRENCY_USD_SIMPLE,
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

                $sheet->setCellValue('A1', 'HISTORIAL DE PAGOS');
                $sheet->mergeCells('A1:J1');
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => [
                        'name' => 'Dubai Medium',
                        'bold' => true,
                        'size' => 14,
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                $sheet->setCellValue('A2', $subtitle);
                $sheet->mergeCells('A2:J2');
                $sheet->getStyle('A2')->applyFromArray([
                    'font' => [
                        'name' => 'Dubai Medium',
                        'size' => 11,
                        'color' => ['rgb' => '555555'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                $lastRow = $sheet->getHighestRow();

                $sheet->getStyle("A1:J{$lastRow}")->applyFromArray([
                    'font' => [
                        'name' => 'Dubai Medium',
                    ],
                ]);

                $sheet->getStyle('A3:J3')->applyFromArray([
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
                $sheet->setAutoFilter("A3:J{$lastRow}");

                $sheet->getStyle("A3:J{$lastRow}")
                    ->getBorders()
                    ->getAllBorders()
                    ->setBorderStyle(Border::BORDER_HAIR);

                $sheet->getStyle("J4:J{$lastRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                $sheet->getStyle("J4:J{$lastRow}")->applyFromArray([
                    'font' => [
                        'name' => 'Dubai Medium',
                    ],
                ]);

                $sheet->setShowGridlines(false);
            },
        ];
    }
}
