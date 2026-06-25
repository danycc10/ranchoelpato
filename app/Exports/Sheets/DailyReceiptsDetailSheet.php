<?php

namespace App\Exports\Sheets;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class DailyReceiptsDetailSheet implements FromArray, ShouldAutoSize, WithEvents, WithTitle
{
    protected array $rowsOut = [];

    protected int $lastColIndex = 8;

    protected int $lastRow = 1;

    protected ?string $detailMontoRange = null;

    public function __construct(
        public string $fecha,
        public ?string $propietarioNombre,
        public Collection $detailRows,
    ) {}

    public function title(): string
    {
        return 'Detalle';
    }

    protected function norm(?string $s, bool $upper = false): string
    {
        $s = (string) ($s ?? '');

        $s = str_replace("\xC2\xA0", ' ', $s);
        $s = str_replace("\u{00A0}", ' ', $s);
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
        $s = trim($s);

        if ($upper) {
            $s = mb_strtoupper($s);
        }

        return $s;
    }

    public function array(): array
    {
        $detailRows = $this->detailRows->map(function ($r) {
            return (object) [
                'folio' => $this->norm($r->folio ?? ''),
                'persona' => $this->norm($r->persona ?? '—'),
                'lote' => $this->norm($r->lote ?? '—'),
                'finca' => $this->norm($r->finca ?? 'Sin finca'),
                'concepto' => $this->norm($r->concepto ?? 'Sin concepto'),
                'forma' => $this->norm($r->forma ?? 'Sin forma'),
                'cuenta_bancaria' => $this->norm($r->cuenta_bancaria ?? '—'),
                'monto' => (float) ($r->monto ?? 0),
                'fecha' => $this->norm($r->fecha_recibo ?? $this->fecha),
            ];
        });

        $heads = [
            'FOLIO',
            'CLIENTE',
            'LOTE',
            'FINCA',
            'CONCEPTO',
            'MONTO',
            'FORMA',
            'CUENTA BANCARIA',
        ];

        $this->rowsOut = [];
        $this->rowsOut[] = ['DETALLE DE RECIBOS'];
        $this->rowsOut[] = ['Fecha: '.$this->fecha.' | Propietario: '.($this->propietarioNombre ?: 'Todos')];
        $this->rowsOut[] = [];
        $this->rowsOut[] = $heads;

        $startRow = 5;

        foreach ($detailRows as $r) {
            $this->rowsOut[] = [
                $r->folio,
                $r->persona,
                $r->lote,
                $r->finca,
                mb_strtoupper($r->concepto),
                (float) $r->monto,
                mb_strtoupper($r->forma),
                $r->cuenta_bancaria,
            ];
        }

        $this->lastRow = count($this->rowsOut);

        if ($this->lastRow >= $startRow) {
            // MONTO ahora está en la columna F
            $this->detailMontoRange = "F{$startRow}:F{$this->lastRow}";
        }

        return $this->rowsOut;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastColLetter = Coordinate::stringFromColumnIndex($this->lastColIndex);

                $sheet->setShowGridlines(false);

                // Fuerza fuente en todo el rango usado
                $sheet->getStyle("A1:{$lastColLetter}{$this->lastRow}")->applyFromArray([
                    'font' => [
                        'name' => 'Dubai Medium',
                    ],
                ]);

                $sheet->mergeCells("A1:{$lastColLetter}1");
                $sheet->mergeCells("A2:{$lastColLetter}2");

                $sheet->getStyle("A1:{$lastColLetter}1")->applyFromArray([
                    'font' => [
                        'name' => 'Dubai Medium',
                        'bold' => true,
                        'size' => 16,
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                    ],
                ]);

                $sheet->getStyle("A2:{$lastColLetter}2")->applyFromArray([
                    'font' => [
                        'name' => 'Dubai Medium',
                        'size' => 11,
                        'color' => ['rgb' => '555555'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                    ],
                ]);

                // Header está en fila 3
                $sheet->getStyle("A3:{$lastColLetter}3")->applyFromArray([
                    'font' => [
                        'name' => 'Dubai Medium',
                        'bold' => true,
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'color' => ['rgb' => 'F3F4F6'],
                    ],
                ]);

                $sheet->freezePane('A4');

                $sheet->getStyle("A1:{$lastColLetter}{$this->lastRow}")
                    ->getBorders()
                    ->getAllBorders()
                    ->setBorderStyle(Border::BORDER_HAIR);

                if ($this->detailMontoRange) {
                    $sheet->getStyle($this->detailMontoRange)
                        ->getNumberFormat()
                        ->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);

                    $sheet->getStyle($this->detailMontoRange)
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                    $sheet->getStyle($this->detailMontoRange)->applyFromArray([
                        'font' => [
                            'name' => 'Dubai Medium',
                        ],
                    ]);
                }

                $sheet->getStyle("A5:{$lastColLetter}{$this->lastRow}")
                    ->getAlignment()
                    ->setVertical(Alignment::VERTICAL_CENTER);
            },
        ];
    }
}
