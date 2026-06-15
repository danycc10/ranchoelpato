<?php

namespace App\Exports\Sheets;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use Maatwebsite\Excel\Events\AfterSheet;

class DailyReceiptsSummarySheet implements FromArray, WithTitle, WithEvents, ShouldAutoSize
{
    protected array $rowsOut = [];
    protected int $lastColIndex = 1;
    protected int $lastRow = 1;

    protected array $methodHeaderRows = [];
    protected array $methodTotalRows = [];
    protected array $summaryMoneyRanges = [];

    public function __construct(
        public string $fecha,
        public ?string $propietarioNombre,
        public Collection $rows,
    ) {}

    public function title(): string
    {
        return 'Resumen';
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
        $rows = $this->rows->map(function ($r) {
            return (object) [
                'metodo'   => $this->norm($r->metodo ?? 'Sin forma', true),
                'finca_id' => (int) ($r->finca_id ?? 0),
                'finca'    => $this->norm($r->finca ?? 'Sin finca', true),
                'concepto' => $this->norm($r->concepto ?? 'Sin concepto', true),
                'total'    => (float) ($r->total ?? 0),
            ];
        });

        $conceptos = $rows->pluck('concepto')->filter()->unique()->sort()->values();

        $metodos = $rows->pluck('metodo')
            ->filter()
            ->unique()
            ->sortBy(function ($metodo) {
                $m = mb_strtolower(trim((string) $metodo));

                return [
                    str_contains($m, 'efect') ? 0 : 1,
                    $m,
                ];
            })
            ->values();

        $pivot = [];
        $fincasPorMetodo = [];

        foreach ($rows as $r) {
            $m = $r->metodo;
            $f = $r->finca_id;
            $c = $r->concepto;

            if (!isset($pivot[$m])) $pivot[$m] = [];
            if (!isset($pivot[$m][$f])) $pivot[$m][$f] = [];
            if (!isset($pivot[$m][$f][$c])) $pivot[$m][$f][$c] = 0.0;

            $pivot[$m][$f][$c] += (float) $r->total;

            if (!isset($fincasPorMetodo[$m])) $fincasPorMetodo[$m] = [];
            $fincasPorMetodo[$m][$f] = $r->finca;
        }

        $heads = ['FINCA'];
        foreach ($conceptos as $c) {
            $heads[] = $c;
        }
        $heads[] = 'TOTAL';

        $this->lastColIndex = count($heads);

        $this->rowsOut = [];
        $this->methodHeaderRows = [];
        $this->methodTotalRows = [];
        $this->summaryMoneyRanges = [];

        $this->rowsOut[] = ['REPORTE DIARIO DE RECIBOS'];
        $this->rowsOut[] = ['Fecha: ' . $this->fecha . ' | Propietario: ' . ($this->propietarioNombre ?: 'Todos')];
        $this->rowsOut[] = [];

        foreach ($metodos as $metodo) {
            $this->rowsOut[] = [$metodo];
            $this->methodHeaderRows[] = count($this->rowsOut);

            $this->rowsOut[] = $heads;
            $headerRowNum = count($this->rowsOut);
            $dataStartRow = $headerRowNum + 1;

            $totalesMetodoPorConcepto = array_fill_keys($conceptos->toArray(), 0.0);
            $totalMetodo = 0.0;

            $fincas = $fincasPorMetodo[$metodo] ?? [];
            asort($fincas);

            foreach ($fincas as $fincaId => $fincaNombre) {
                $row = [];
                $row[] = $fincaNombre;

                $totalFila = 0.0;

                foreach ($conceptos as $c) {
                    $monto = (float) ($pivot[$metodo][$fincaId][$c] ?? 0.0);
                    $row[] = $monto;

                    $totalesMetodoPorConcepto[$c] += $monto;
                    $totalFila += $monto;
                }

                $row[] = $totalFila;
                $totalMetodo += $totalFila;

                $this->rowsOut[] = $row;
            }

            $totalRow = ['TOTAL ' . $metodo];
            foreach ($conceptos as $c) {
                $totalRow[] = (float) $totalesMetodoPorConcepto[$c];
            }
            $totalRow[] = (float) $totalMetodo;

            $this->rowsOut[] = $totalRow;
            $this->methodTotalRows[] = count($this->rowsOut);

            $dataEndRow = count($this->rowsOut);

            for ($col = 2; $col <= count($heads); $col++) {
                $letter = Coordinate::stringFromColumnIndex($col);
                $this->summaryMoneyRanges[] = "{$letter}{$dataStartRow}:{$letter}{$dataEndRow}";
            }

            $this->rowsOut[] = [];
        }

        $this->lastRow = count($this->rowsOut);

        return $this->rowsOut;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastColLetter = Coordinate::stringFromColumnIndex($this->lastColIndex);

                $sheet->setShowGridlines(false);

                // Fuerza Dubai Medium en toda la hoja usada
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

                $sheet->freezePane('A4');

                $sheet->getStyle("A1:{$lastColLetter}{$this->lastRow}")
                    ->getBorders()
                    ->getAllBorders()
                    ->setBorderStyle(Border::BORDER_HAIR);

                for ($r = 1; $r <= $this->lastRow; $r++) {
                    $a = (string) ($sheet->getCell("A{$r}")->getValue() ?? '');

                    if ($a === 'FINCA') {
                        $sheet->getStyle("A{$r}:{$lastColLetter}{$r}")->applyFromArray([
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
                    }
                }

                $palettes = [
                    ['head' => 'D1FAE5', 'total' => 'ECFDF5'],
                    ['head' => 'DBEAFE', 'total' => 'EFF6FF'],
                    ['head' => 'EDE9FE', 'total' => 'F5F3FF'],
                    ['head' => 'FEF3C7', 'total' => 'FFFBEB'],
                    ['head' => 'FFE4E6', 'total' => 'FFF1F2'],
                ];

                foreach ($this->methodHeaderRows as $i => $rowNum) {
                    $pal = $palettes[$i % count($palettes)];

                    $sheet->mergeCells("A{$rowNum}:{$lastColLetter}{$rowNum}");
                    $sheet->getStyle("A{$rowNum}:{$lastColLetter}{$rowNum}")->applyFromArray([
                        'font' => [
                            'name' => 'Dubai Medium',
                            'bold' => true,
                            'size' => 12,
                        ],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'color' => ['rgb' => $pal['head']],
                        ],
                    ]);

                    $totalRowNum = $this->methodTotalRows[$i] ?? null;
                    if ($totalRowNum) {
                        $sheet->getStyle("A{$totalRowNum}:{$lastColLetter}{$totalRowNum}")->applyFromArray([
                            'font' => [
                                'name' => 'Dubai Medium',
                                'bold' => true,
                            ],
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'color' => ['rgb' => $pal['total']],
                            ],
                        ]);
                    }
                }

                foreach ($this->summaryMoneyRanges as $range) {
                    $sheet->getStyle($range)
                        ->getNumberFormat()
                        ->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);

                    $sheet->getStyle($range)
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                    $sheet->getStyle($range)->applyFromArray([
                        'font' => [
                            'name' => 'Dubai Medium',
                        ],
                    ]);
                }

                $sheet->getStyle("A1:A{$this->lastRow}")->applyFromArray([
                    'font' => [
                        'name' => 'Dubai Medium',
                        'bold' => true,
                    ],
                ]);
            },
        ];
    }
}