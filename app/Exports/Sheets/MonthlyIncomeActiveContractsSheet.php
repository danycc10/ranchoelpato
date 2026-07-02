<?php

namespace App\Exports\Sheets;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class MonthlyIncomeActiveContractsSheet implements FromCollection, WithColumnFormatting, WithEvents, WithHeadings, WithTitle
{
    public function __construct(
        public int $anio,
        public int $mes,
        public ?int $propietarioId,
    ) {}

    public function title(): string
    {
        return 'Contratos vigentes';
    }

    protected function monthRange(): array
    {
        $start = now()->setDate($this->anio, $this->mes, 1)->startOfDay();
        $end = (clone $start)->endOfMonth()->endOfDay();

        return [$start, $end];
    }

    public function headings(): array
    {
        return [
            'NOMBRE',
            'LOTE',
            'FRACCIONAMIENTO',
            'CANTIDAD QUE PAGA',
        ];
    }

    public function collection(): Collection
    {
        [$start, $end] = $this->monthRange();

        return DB::table('cuotas')
            ->leftJoin('contratos', 'contratos.id', '=', 'cuotas.contrato_id')
            ->leftJoin('clientes', 'clientes.id', '=', 'contratos.cliente_id')
            ->leftJoin('lotes', 'lotes.id', '=', 'contratos.lote_id')
            ->leftJoin('fraccionamientos', 'fraccionamientos.id', '=', 'lotes.fraccionamiento_id')

            ->when(
                $this->propietarioId,
                fn ($q) => $q->where('fraccionamientos.propietario_id', $this->propietarioId)
            )

            ->whereNotNull('cuotas.contrato_id')
            ->whereIn('contratos.estatus', ['activo', 'liquidado'])
            ->where('contratos.tipo', 'terreno')
            ->whereBetween('cuotas.fecha_vencimiento', [$start->toDateString(), $end->toDateString()])

            // ignorar cuotas historicas

            // una sola fila por contrato
            ->groupBy(
                'contratos.id',
                'clientes.nombres',
                'clientes.apellidos',
                'lotes.lote',
                'fraccionamientos.nombre',
                'contratos.monto_pago'
            )

            ->orderBy('fraccionamientos.nombre')
            ->orderBy('lotes.lote')
            ->orderBy('clientes.nombres')
            ->orderBy('clientes.apellidos')

            ->select([
                'contratos.id as contrato_id',
                DB::raw("COALESCE(NULLIF(TRIM(CONCAT_WS(' ', clientes.nombres, clientes.apellidos)), ''), 'SIN CLIENTE') as nombre"),
                DB::raw("COALESCE(lotes.lote, 'SIN LOTE') as lote"),
                DB::raw("COALESCE(fraccionamientos.nombre, 'Sin finca') as fraccionamiento"),
                DB::raw('COALESCE(contratos.monto_pago, 0) as cantidad_paga'),
            ])
            ->get()
            ->map(function ($r) {
                return [
                    (string) ($r->nombre ?? ''),
                    (string) ($r->lote ?? ''),
                    (string) ($r->fraccionamiento ?? ''),
                    (float) ($r->cantidad_paga ?? 0),
                ];
            });
    }

    public function columnFormats(): array
    {
        return [
            'D' => '"$"#,##0.00',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                $highestRow = $sheet->getHighestRow();
                $highestCol = $sheet->getHighestColumn();

                // Forzar fuente en toda la hoja usada
                $sheet->getStyle("A1:{$highestCol}{$highestRow}")->applyFromArray([
                    'font' => [
                        'name' => 'Dubai Medium',
                    ],
                ]);

                $sheet->freezePane('A2');
                $sheet->setAutoFilter("A1:{$highestCol}1");

                $sheet->getStyle("A1:{$highestCol}1")->applyFromArray([
                    'font' => [
                        'name' => 'Dubai Medium',
                        'bold' => true,
                        'color' => ['rgb' => 'FFFFFF'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '111827'],
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => 'E5E7EB'],
                        ],
                    ],
                ]);

                $sheet->getRowDimension(1)->setRowHeight(22);

                $sheet->getStyle("A1:{$highestCol}{$highestRow}")->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => 'E5E7EB'],
                        ],
                    ],
                ]);

                for ($r = 2; $r <= $highestRow; $r++) {
                    if (($r % 2) === 0) {
                        $sheet->getStyle("A{$r}:{$highestCol}{$r}")->applyFromArray([
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['rgb' => 'F9FAFB'],
                            ],
                            'font' => [
                                'name' => 'Dubai Medium',
                            ],
                        ]);
                    }
                }

                $sheet->getStyle("B2:B{$highestRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->getStyle("D2:D{$highestRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                $sheet->getStyle("D2:D{$highestRow}")->applyFromArray([
                    'font' => [
                        'name' => 'Dubai Medium',
                    ],
                ]);

                $sheet->getColumnDimension('A')->setWidth(30);
                $sheet->getColumnDimension('B')->setWidth(10);
                $sheet->getColumnDimension('C')->setWidth(26);
                $sheet->getColumnDimension('D')->setWidth(16);

                $sheet->setShowGridlines(false);
            },
        ];
    }
}
