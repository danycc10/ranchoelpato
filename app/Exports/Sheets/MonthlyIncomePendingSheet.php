<?php

namespace App\Exports\Sheets;

use Carbon\Carbon;
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

class MonthlyIncomePendingSheet implements FromCollection, WithColumnFormatting, WithEvents, WithHeadings, WithTitle
{
    public function __construct(
        public int $anio,
        public int $mes,
        public ?int $propietarioId,
    ) {}

    public function title(): string
    {
        return 'Pendientes';
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
            'CUOTA_NUM',
            'VENCE',
            'AÑO',
            'MES',
            'FRACCIONAMIENTO',
            'MANZANA',
            'LOTE',
            'CLIENTE',
            'CONTRATO',
            'MONTO_CUOTA',
            'PAGADO',
            'PENDIENTE',
            'ESTATUS',
            'DÍAS_ATRASO (AL FIN DE MES)',
        ];
    }

    public function collection()
    {
        [$start, $end] = $this->monthRange();

        $q = DB::table('cuotas')
            ->leftJoin('contratos', 'contratos.id', '=', 'cuotas.contrato_id')
            ->leftJoin('clientes', 'clientes.id', '=', 'contratos.cliente_id')
            ->leftJoin('lotes', 'lotes.id', '=', 'contratos.lote_id')
            ->leftJoin('fraccionamientos', 'fraccionamientos.id', '=', 'lotes.fraccionamiento_id')
            ->when($this->propietarioId, fn ($qq) => $qq->where('fraccionamientos.propietario_id', $this->propietarioId))
            ->whereBetween('cuotas.fecha_vencimiento', [$start->toDateString(), $end->toDateString()])
            ->where('cuotas.estatus', '!=', 'pagada')
            ->orderBy('cuotas.fecha_vencimiento')
            ->orderBy('cuotas.id')
            ->select([
                'cuotas.numero',
                'cuotas.fecha_vencimiento',
                DB::raw('YEAR(cuotas.fecha_vencimiento) as anio'),
                DB::raw('MONTH(cuotas.fecha_vencimiento) as mes'),
                DB::raw('COALESCE(fraccionamientos.nombre, "Sin finca") as fraccionamiento'),
                'lotes.manzana',
                'lotes.lote',
                DB::raw("COALESCE(NULLIF(TRIM(CONCAT_WS(' ', clientes.nombres, clientes.apellidos)), ''), 'SIN CLIENTE') as cliente"),
                DB::raw("COALESCE(contratos.folio_contrato, '') as contrato"),
                DB::raw('COALESCE(cuotas.monto,0) as monto_cuota'),
                DB::raw('COALESCE(cuotas.pagado_total,0) as pagado_total'),
                DB::raw('GREATEST(COALESCE(cuotas.monto,0) - COALESCE(cuotas.pagado_total,0), 0) as pendiente'),
                DB::raw('COALESCE(cuotas.estatus, "pendiente") as estatus'),
            ]);

        $endDate = Carbon::parse($end->toDateString())->startOfDay();

        return $q->get()->map(function ($r) use ($endDate) {
            $vence = $r->fecha_vencimiento ? Carbon::parse($r->fecha_vencimiento)->startOfDay() : null;

            $diasAtraso = 0;
            if ($vence && $endDate->greaterThan($vence)) {
                $diasAtraso = $vence->diffInDays($endDate);
            }

            return [
                (string) ($r->numero ?? ''),
                (string) ($r->fecha_vencimiento ?? ''),
                (string) ($r->anio ?? ''),
                (string) ($r->mes ?? ''),
                (string) ($r->fraccionamiento ?? ''),
                (string) ($r->manzana ?? ''),
                (string) ($r->lote ?? ''),
                (string) ($r->cliente ?? ''),
                (string) ($r->contrato ?? ''),
                (float) ($r->monto_cuota ?? 0),
                (float) ($r->pagado_total ?? 0),
                (float) ($r->pendiente ?? 0),
                (string) ($r->estatus ?? ''),
                (int) $diasAtraso,
            ];
        });
    }

    public function columnFormats(): array
    {
        return [
            'J' => '"$"#,##0.00',
            'K' => '"$"#,##0.00',
            'L' => '"$"#,##0.00',
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

                $sheet->getStyle("J2:L{$highestRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                $sheet->getStyle("J2:L{$highestRow}")->applyFromArray([
                    'font' => [
                        'name' => 'Dubai Medium',
                    ],
                ]);

                $sheet->getColumnDimension('A')->setWidth(10);
                $sheet->getColumnDimension('B')->setWidth(12);
                $sheet->getColumnDimension('E')->setWidth(26);
                $sheet->getColumnDimension('H')->setWidth(26);
                $sheet->getColumnDimension('I')->setWidth(16);
                $sheet->getColumnDimension('J')->setWidth(14);
                $sheet->getColumnDimension('K')->setWidth(14);
                $sheet->getColumnDimension('L')->setWidth(14);
                $sheet->getColumnDimension('M')->setWidth(14);
                $sheet->getColumnDimension('N')->setWidth(18);

                $sheet->setShowGridlines(false);
            },
        ];
    }
}
