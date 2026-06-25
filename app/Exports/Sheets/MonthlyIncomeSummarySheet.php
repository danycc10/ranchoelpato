<?php

namespace App\Exports\Sheets;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

class MonthlyIncomeSummarySheet implements FromCollection, WithColumnFormatting, WithEvents, WithHeadings, WithTitle
{
    public function __construct(
        public int $anio,
        public int $mes,
        public string $modoVista,
        public Collection $metodosPago,
        public Collection $filas,
        public object $totales,
        public ?object $headerConceptos = null,
        public ?object $conceptosPorFinca = null,
        public ?string $mesNombre = null
    ) {}

    public function title(): string
    {
        return 'Resumen';
    }

    protected function modoVistaLabel(): string
    {
        return $this->modoVista === 'flujo_real'
            ? 'FLUJO REAL MENSUAL'
            : 'CÓMO VA EL MES';
    }

    public function headings(): array
    {
        $colEsperado = $this->modoVista === 'flujo_real'
            ? 'ESPERADO MES'
            : 'FLUJO MENSUAL ESPERADO';

        $colRecibido = $this->modoVista === 'flujo_real'
            ? 'FLUJO REAL'
            : 'FLUJO MENSUAL RECIBIDO';

        $colDiferencia = $this->modoVista === 'flujo_real'
            ? 'FLUJO - ESPERADO'
            : 'DIFERENCIA';

        $h = [
            'FRACCIONAMIENTO',
            $colEsperado,
            $colRecibido,
            $colDiferencia,
            'ADELANTADO',
            'DESFASADO',
        ];

        foreach ($this->metodosPago as $m) {
            $h[] = mb_strtoupper($m);
        }

        return $h;
    }

    public function collection()
    {
        $rows = $this->filas->map(function ($f) {
            $r = [
                (string) $f->finca,
                (float) $f->esperado,
                (float) $f->recibido,
                (float) $f->diferencia,
                (float) ($f->adelantado ?? 0),
                (float) ($f->atrasado ?? 0),
            ];

            foreach ($this->metodosPago as $m) {
                $r[] = (float) ($f->metodos[$m] ?? 0);
            }

            return $r;
        });

        $totalRow = [
            'TOTAL DE INGRESOS',
            (float) $this->totales->esperado,
            (float) $this->totales->recibido,
            (float) $this->totales->diferencia,
            (float) ($this->totales->adelantado ?? 0),
            (float) ($this->totales->atrasado ?? 0),
        ];

        foreach ($this->metodosPago as $m) {
            $totalRow[] = (float) ($this->totales->metodos[$m] ?? 0);
        }

        return $rows->push($totalRow);
    }

    public function columnFormats(): array
    {
        $lastColIndex = 6 + count($this->metodosPago);
        $formats = [];

        for ($i = 2; $i <= $lastColIndex; $i++) {
            $formats[$this->colLetter($i)] = '"$"#,##0.00';
        }

        return $formats;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                $sheet->insertNewRowBefore(1, 8);

                $lastCol = $this->colLetter(6 + count($this->metodosPago));
                $tableHeaderRow = 9;
                $firstDataRow = 10;

                $bgMain = '0F766E';
                $bgSoft = 'CCFBF1';
                $bgHead = '99F6E4';
                $bgZebra = 'F8FAFC';
                $borderC = '0F766E';

                $highestRow = $sheet->getHighestRow();

                $sheet->getStyle("A1:{$lastCol}{$highestRow}")->applyFromArray([
                    'font' => [
                        'name' => 'Dubai Medium',
                    ],
                ]);

                // ===== TÍTULO =====
                $sheet->mergeCells("A1:{$lastCol}1");
                $sheet->setCellValue('A1', 'RESUMEN DE INGRESOS MENSUALES');

                $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
                    'font' => [
                        'name' => 'Dubai Medium',
                        'bold' => true,
                        'size' => 18,
                        'color' => ['rgb' => 'FFFFFF'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => $bgMain],
                    ],
                ]);
                $sheet->getRowDimension(1)->setRowHeight(30);

                // ===== KPIs =====
                $sheet->setCellValue('A2', 'RECARGO');
                $sheet->setCellValue('B2', (float) ($this->headerConceptos->{'Recargo'} ?? 0));

                $sheet->setCellValue('C2', 'CAMBIO DE CONTRATO');
                $sheet->setCellValue('D2', (float) ($this->headerConceptos->{'Cambio de contrato'} ?? 0));

                $sheet->setCellValue('A3', 'PAGO ELECTRICIDAD');
                $sheet->setCellValue('B3', (float) ($this->headerConceptos->{'Pago electricidad'} ?? 0));

                $sheet->setCellValue('C3', 'ENGANCHE');
                $sheet->setCellValue('D3', (float) ($this->headerConceptos->{'Enganche'} ?? 0));

                $sheet->getStyle('A2:D3')->applyFromArray([
                    'font' => [
                        'name' => 'Dubai Medium',
                        'bold' => true,
                        'color' => ['rgb' => '111827'],
                    ],
                    'alignment' => [
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => $bgSoft],
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => $borderC],
                        ],
                    ],
                ]);

                $sheet->getStyle('B2:B3')->getNumberFormat()->setFormatCode('"$"#,##0.00');
                $sheet->getStyle('D2:D3')->getNumberFormat()->setFormatCode('"$"#,##0.00');

                // ===== AÑO / MES / MODO =====
                $sheet->setCellValue('F2', 'AÑO');
                $sheet->setCellValue('G2', $this->anio);
                $sheet->setCellValue('F3', 'MES');
                $sheet->setCellValue('G3', $this->mesNombre ?: $this->mes);
                $sheet->setCellValue('F4', 'MODO');
                $sheet->setCellValue('G4', $this->modoVistaLabel());

                $sheet->getStyle('F2:G4')->applyFromArray([
                    'font' => [
                        'name' => 'Dubai Medium',
                        'bold' => true,
                        'color' => ['rgb' => '111827'],
                    ],
                    'alignment' => [
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'wrapText' => true,
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => $bgSoft],
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => $borderC],
                        ],
                    ],
                ]);

                // ===== LOGO =====
                $logoPath = public_path('storage/images/logo-espinoza.jpeg');
                if (file_exists($logoPath)) {
                    $drawing = new Drawing;
                    $drawing->setName('Logo');
                    $drawing->setDescription('Logo reporte');
                    $drawing->setPath($logoPath);
                    $drawing->setCoordinates('I2');
                    $drawing->setHeight(55);
                    $drawing->setResizeProportional(true);
                    $drawing->setWorksheet($sheet);
                }

                // ===== MATRIZ CONCEPTOS POR FINCA =====
                if ($this->conceptosPorFinca && ! empty($this->conceptosPorFinca->fincas)) {
                    $fincas = collect($this->conceptosPorFinca->fincas);
                    $filasConceptos = collect($this->conceptosPorFinca->filas);

                    $fincasFiltradas = $fincas->filter(function ($finca) use ($filasConceptos) {
                        $nombre = $finca['nombre'];

                        foreach ($filasConceptos as $fila) {
                            if ((float) ($fila['fincas'][$nombre] ?? 0) > 0) {
                                return true;
                            }
                        }

                        return false;
                    })->values();

                    $filasFiltradas = $filasConceptos->map(function ($fila) use ($fincasFiltradas) {
                        $nuevaFila = $fila;
                        $nuevaFila['fincas'] = collect($fila['fincas'])
                            ->only($fincasFiltradas->pluck('nombre')->toArray())
                            ->toArray();

                        return $nuevaFila;
                    })->filter(function ($fila) {
                        return collect($fila['fincas'])->sum() > 0;
                    })->values();

                    if ($fincasFiltradas->isNotEmpty() && $filasFiltradas->isNotEmpty()) {
                        $matrixTitleRow = 5;
                        $matrixHeaderRow = 6;
                        $matrixDataStart = 7;

                        $sheet->mergeCells("A{$matrixTitleRow}:".$this->colLetter(count($fincasFiltradas) + 1)."{$matrixTitleRow}");
                        $sheet->setCellValue("A{$matrixTitleRow}", 'CONCEPTOS AGRUPADOS POR FINCA');

                        $sheet->getStyle("A{$matrixTitleRow}:".$this->colLetter(count($fincasFiltradas) + 1)."{$matrixTitleRow}")
                            ->applyFromArray([
                                'font' => [
                                    'name' => 'Dubai Medium',
                                    'bold' => true,
                                    'color' => ['rgb' => 'FFFFFF'],
                                ],
                                'alignment' => [
                                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                                    'vertical' => Alignment::VERTICAL_CENTER,
                                ],
                                'fill' => [
                                    'fillType' => Fill::FILL_SOLID,
                                    'startColor' => ['rgb' => $bgMain],
                                ],
                            ]);

                        $sheet->setCellValue("A{$matrixHeaderRow}", 'CONCEPTO');

                        $colIndex = 2;
                        foreach ($fincasFiltradas as $finca) {
                            $sheet->setCellValue($this->colLetter($colIndex).$matrixHeaderRow, mb_strtoupper($finca['nombre']));
                            $colIndex++;
                        }

                        $matrixLastCol = $this->colLetter($colIndex - 1);

                        $sheet->getStyle("A{$matrixHeaderRow}:{$matrixLastCol}{$matrixHeaderRow}")->applyFromArray([
                            'font' => [
                                'name' => 'Dubai Medium',
                                'bold' => true,
                                'color' => ['rgb' => '111827'],
                            ],
                            'alignment' => [
                                'horizontal' => Alignment::HORIZONTAL_CENTER,
                                'vertical' => Alignment::VERTICAL_CENTER,
                            ],
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['rgb' => $bgHead],
                            ],
                            'borders' => [
                                'allBorders' => [
                                    'borderStyle' => Border::BORDER_THIN,
                                    'color' => ['rgb' => $borderC],
                                ],
                            ],
                        ]);

                        $row = $matrixDataStart;
                        foreach ($filasFiltradas as $fila) {
                            $sheet->setCellValue("A{$row}", $fila['concepto']);

                            $colIndex = 2;
                            foreach ($fincasFiltradas as $finca) {
                                $sheet->setCellValue(
                                    $this->colLetter($colIndex).$row,
                                    (float) ($fila['fincas'][$finca['nombre']] ?? 0)
                                );
                                $colIndex++;
                            }

                            $sheet->getStyle("A{$row}:{$matrixLastCol}{$row}")->applyFromArray([
                                'font' => [
                                    'name' => 'Dubai Medium',
                                    'color' => ['rgb' => '111827'],
                                ],
                                'alignment' => [
                                    'vertical' => Alignment::VERTICAL_CENTER,
                                ],
                                'fill' => [
                                    'fillType' => Fill::FILL_SOLID,
                                    'startColor' => ['rgb' => (($row % 2) === 0 ? 'FFFFFF' : $bgZebra)],
                                ],
                                'borders' => [
                                    'allBorders' => [
                                        'borderStyle' => Border::BORDER_THIN,
                                        'color' => ['rgb' => 'D1D5DB'],
                                    ],
                                ],
                            ]);

                            $sheet->getStyle("B{$row}:{$matrixLastCol}{$row}")
                                ->getAlignment()
                                ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                            $sheet->getStyle("B{$row}:{$matrixLastCol}{$row}")
                                ->getNumberFormat()
                                ->setFormatCode('"$"#,##0.00');

                            $row++;
                        }

                        $sheet->getColumnDimension('A')->setWidth(24);
                        for ($i = 2; $i < $colIndex; $i++) {
                            $sheet->getColumnDimension($this->colLetter($i))->setWidth(16);
                        }
                    }
                }

                // ===== TABLA PRINCIPAL =====
                $highestRow = $sheet->getHighestRow();

                $sheet->getStyle("A{$tableHeaderRow}:{$lastCol}{$tableHeaderRow}")->applyFromArray([
                    'font' => [
                        'name' => 'Dubai Medium',
                        'bold' => true,
                        'color' => ['rgb' => '111827'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => $bgHead],
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => $borderC],
                        ],
                    ],
                ]);

                $sheet->getStyle("A{$tableHeaderRow}:{$lastCol}{$highestRow}")->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => 'D1D5DB'],
                        ],
                    ],
                ]);

                for ($r = $firstDataRow; $r <= $highestRow; $r++) {
                    if (($r % 2) === 0) {
                        $sheet->getStyle("A{$r}:{$lastCol}{$r}")->applyFromArray([
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['rgb' => $bgZebra],
                            ],
                        ]);
                    }
                }

                $sheet->getStyle("A{$highestRow}:{$lastCol}{$highestRow}")->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => $bgMain],
                    ],
                    'font' => [
                        'name' => 'Dubai Medium',
                        'bold' => true,
                        'color' => ['rgb' => 'FFFFFF'],
                    ],
                ]);

                $sheet->getStyle("B{$firstDataRow}:{$lastCol}{$highestRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                $sheet->getStyle("B{$firstDataRow}:{$lastCol}{$highestRow}")
                    ->getNumberFormat()
                    ->setFormatCode('"$"#,##0.00');

                // DIFERENCIA ahora está en D
                $sheet->getStyle("D{$firstDataRow}:D{$highestRow}")
                    ->getNumberFormat()
                    ->setFormatCode('"$"#,##0.00;[Red]-"$"#,##0.00');

                $sheet->freezePane("A{$firstDataRow}");
                $sheet->setAutoFilter("A{$tableHeaderRow}:{$lastCol}{$tableHeaderRow}");

                $sheet->getColumnDimension('A')->setWidth(30);
                $sheet->getColumnDimension('B')->setWidth(22);
                $sheet->getColumnDimension('C')->setWidth(22);
                $sheet->getColumnDimension('D')->setWidth(18); // diferencia
                $sheet->getColumnDimension('E')->setWidth(18); // adelantado
                $sheet->getColumnDimension('F')->setWidth(18); // desfasado

                $startMethodCol = 7;
                for ($i = 0; $i < count($this->metodosPago); $i++) {
                    $sheet->getColumnDimension($this->colLetter($startMethodCol + $i))->setWidth(18);
                }

                $sheet->setShowGridlines(false);
            },
        ];
    }

    private function colLetter(int $index): string
    {
        $letter = '';

        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $letter = chr(65 + $mod).$letter;
            $index = intdiv($index - 1, 26);
        }

        return $letter;
    }
}
