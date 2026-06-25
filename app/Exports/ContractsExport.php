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

class ContractsExport implements FromArray, ShouldAutoSize, WithEvents, WithTitle
{
    public function __construct(
        public Collection $rows
    ) {}

    public function title(): string
    {
        return 'Contratos';
    }

    public function array(): array
    {
        $data = [];

        $data[] = ['REPORTE DE CONTRATOS'];
        $data[] = [''];
        $data[] = [
            'Cliente',
            'Folio',
            'Lote',
            'Fraccionamiento',
            'Fecha inicio',
            'Frecuencia',
            'Estatus',
            'Saldo actual',
            'Pago por periodo',
            'Pago mensual',
            'Día de pago',
            'Precio total',
            'Enganche',
            'Saldo inicial',
            'Tipo recargo',
            'Valor recargo',
            'Días gracia',
            'Tiene anualidad',
            'Fecha anualidad',
            'Monto anualidad',
            'Fecha cancelación',
        ];

        foreach ($this->rows as $row) {
            $pago = (float) ($row->monto_pago ?? 0);

            $pagoMensual = match ($row->frecuencia) {
                'mensual' => $pago,
                'semanal' => round($pago * 4, 2),
                default => 0,
            };

            $diaPago = match ($row->frecuencia) {
                'mensual' => ! empty($row->dia_mes) ? 'Día '.$row->dia_mes : '',
                'semanal' => $this->nombreDiaSemana($row->dia_semana),
                default => '',
            };

            $data[] = [
                $row->cliente?->nombre_completo ?? '',
                $row->folio_contrato ?? '',
                $row->lote?->lote ?? '',
                $row->lote?->fraccionamiento?->nombre ?? '',
                optional($row->fecha_inicio)->format('d/m/Y'),
                ucfirst((string) $row->frecuencia),
                ucfirst((string) $row->estatus),
                (float) ($row->saldo_actual ?? 0),
                $pago,
                $pagoMensual,
                $diaPago,
                (float) ($row->precio_total ?? 0),
                (float) ($row->enganche ?? 0),
                (float) ($row->saldo_inicial ?? 0),
                ucfirst((string) ($row->tipo_recargo ?? '')),
                (float) ($row->valor_recargo ?? 0),
                (int) ($row->dias_gracia ?? 0),
                ((int) ($row->tiene_anualidad ?? 0)) === 1 ? 'Sí' : 'No',
                ! empty($row->anualidad_fecha) ? optional($row->anualidad_fecha)->format('d/m/Y') : '',
                (float) ($row->anualidad_monto ?? 0),
                $row->deleted_at
        ? optional($row->deleted_at)->format('d/m/Y H:i')
        : '',
            ];
        }

        return $data;
    }

    protected function nombreDiaSemana($dia): string
    {
        return match ((int) $dia) {
            1 => 'Lunes',
            2 => 'Martes',
            3 => 'Miércoles',
            4 => 'Jueves',
            5 => 'Viernes',
            6 => 'Sábado',
            7 => 'Domingo',
            default => '',
        };
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet;
                $lastRow = 3 + $this->rows->count();

                $sheet->mergeCells('A1:U1');

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

                $sheet->getStyle('A3:U3')->applyFromArray([
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
                    $sheet->getStyle("A4:U{$lastRow}")->applyFromArray([
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

                    $sheet->getStyle("U4:U{$lastRow}")
                        ->getNumberFormat()
                        ->setFormatCode('dd/mm/yyyy hh:mm');

                    // Formato moneda
                    $sheet->getStyle("H4:J{$lastRow}")
                        ->getNumberFormat()
                        ->setFormatCode('$#,##0.00');

                    $sheet->getStyle("L4:P{$lastRow}")
                        ->getNumberFormat()
                        ->setFormatCode('$#,##0.00');

                    $sheet->getStyle("T4:T{$lastRow}")
                        ->getNumberFormat()
                        ->setFormatCode('$#,##0.00');
                }

                $sheet->freezePane('A4');

                // Altura header
                $sheet->getRowDimension(3)->setRowHeight(28);

                // Centrar ciertas columnas
                $sheet->getStyle("E4:G{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("K4:R{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Ancho manual opcional para mejorar lectura
                $sheet->getColumnDimension('A')->setWidth(30); // Cliente
                $sheet->getColumnDimension('B')->setWidth(18); // Folio
                $sheet->getColumnDimension('C')->setWidth(16); // Lote
                $sheet->getColumnDimension('D')->setWidth(24); // Fracc
                $sheet->getColumnDimension('E')->setWidth(14); // Fecha inicio
                $sheet->getColumnDimension('F')->setWidth(14); // Frecuencia
                $sheet->getColumnDimension('G')->setWidth(14); // Estatus
                $sheet->getColumnDimension('H')->setWidth(14); // Saldo actual
                $sheet->getColumnDimension('I')->setWidth(16); // Pago por periodo
                $sheet->getColumnDimension('J')->setWidth(16); // Pago mensual
                $sheet->getColumnDimension('K')->setWidth(16); // Día de pago
                $sheet->getColumnDimension('L')->setWidth(14); // Precio total
                $sheet->getColumnDimension('M')->setWidth(14); // Enganche
                $sheet->getColumnDimension('N')->setWidth(14); // Saldo inicial
                $sheet->getColumnDimension('O')->setWidth(14); // Tipo recargo
                $sheet->getColumnDimension('P')->setWidth(14); // Valor recargo
                $sheet->getColumnDimension('Q')->setWidth(12); // Días gracia
                $sheet->getColumnDimension('R')->setWidth(14); // Tiene anualidad
                $sheet->getColumnDimension('S')->setWidth(16); // Fecha anualidad
                $sheet->getColumnDimension('T')->setWidth(16); // Monto anualidad
            },
        ];
    }
}
