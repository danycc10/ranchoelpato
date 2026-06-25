<?php

namespace App\Exports\Sheets;

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

class MonthlyIncomeExpectedDetailSheet implements FromCollection, WithColumnFormatting, WithEvents, WithHeadings, WithTitle
{
    public function __construct(
        public int $anio,
        public int $mes,
        public ?int $propietarioId,
    ) {}

    public function title(): string
    {
        return 'Detalle esperado';
    }

    protected function monthRange(): array
    {
        $start = now()->setDate($this->anio, $this->mes, 1)->startOfDay();
        $end = (clone $start)->endOfMonth()->endOfDay();

        return [$start->toDateString(), $end->toDateString()];
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
            'MONTO_ESPERADO',
            'PAGADO',
            'PENDIENTE',
            'ESTATUS',
        ];
    }

    public function collection()
    {
        [$start, $end] = $this->monthRange();

        // Misma lógica que la columna "Esperado" del reporte (getFilasProperty):
        // mensualidades de contratos tipo 'terreno' que vencen en el mes, en los
        // estatus contables, excluyendo cuotas importadas. El total de la columna
        // MONTO_ESPERADO cuadra exactamente con el "Esperado" del resumen.
        $rows = DB::table('cuotas')
            ->leftJoin('contratos', 'contratos.id', '=', 'cuotas.contrato_id')
            ->leftJoin('clientes', 'clientes.id', '=', 'contratos.cliente_id')
            ->leftJoin('lotes', 'lotes.id', '=', 'contratos.lote_id')
            ->leftJoin('fraccionamientos', 'fraccionamientos.id', '=', 'lotes.fraccionamiento_id')
            ->when(
                $this->propietarioId,
                fn ($q) => $q->where('fraccionamientos.propietario_id', $this->propietarioId)
            )
            ->whereBetween('cuotas.fecha_vencimiento', [$start, $end])
            ->where('contratos.tipo', 'terreno')
            ->whereIn('cuotas.estatus', ['pendiente', 'parcial', 'pagada', 'vencida'])
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('pagos')
                    ->whereColumn('pagos.cuota_id', 'cuotas.id')
                    ->whereNull('pagos.anulado_at')
                    ->whereRaw('UPPER(pagos.referencia) LIKE ?', ['%IMPORT%']);
            })
            ->orderBy('fraccionamientos.nombre')
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
                DB::raw('COALESCE(cuotas.monto, 0) as monto_esperado'),
                DB::raw('COALESCE(cuotas.pagado_total, 0) as pagado'),
                DB::raw('GREATEST(COALESCE(cuotas.monto, 0) - COALESCE(cuotas.pagado_total, 0), 0) as pendiente'),
                DB::raw('COALESCE(cuotas.estatus, "pendiente") as estatus'),
            ])
            ->get();

        $data = $rows->map(fn ($r) => [
            (string) ($r->numero ?? ''),
            (string) ($r->fecha_vencimiento ?? ''),
            (string) ($r->anio ?? ''),
            (string) ($r->mes ?? ''),
            (string) ($r->fraccionamiento ?? ''),
            (string) ($r->manzana ?? ''),
            (string) ($r->lote ?? ''),
            (string) ($r->cliente ?? ''),
            (string) ($r->contrato ?? ''),
            (float) ($r->monto_esperado ?? 0),
            (float) ($r->pagado ?? 0),
            (float) ($r->pendiente ?? 0),
            (string) ($r->estatus ?? ''),
        ]);

        // Fila de TOTAL para cuadrar contra el "Esperado" del resumen.
        $data->push([
            '', '', '', '', '', '', '', '', 'TOTAL',
            (float) $rows->sum('monto_esperado'),
            (float) $rows->sum('pagado'),
            (float) $rows->sum('pendiente'),
            '',
        ]);

        return $data;
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

                // Zebra para filas de datos, dejando fuera la fila TOTAL.
                $lastDataRow = $highestRow - 1;

                for ($r = 2; $r <= $lastDataRow; $r++) {
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

                // Fila TOTAL resaltada.
                $sheet->getStyle("A{$highestRow}:{$highestCol}{$highestRow}")->applyFromArray([
                    'font' => [
                        'name' => 'Dubai Medium',
                        'bold' => true,
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'E5E7EB'],
                    ],
                    'borders' => [
                        'top' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => '111827'],
                        ],
                    ],
                ]);

                $sheet->getStyle("J2:L{$highestRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                $sheet->getColumnDimension('A')->setWidth(10);
                $sheet->getColumnDimension('B')->setWidth(12);
                $sheet->getColumnDimension('C')->setWidth(8);
                $sheet->getColumnDimension('D')->setWidth(8);
                $sheet->getColumnDimension('E')->setWidth(26);
                $sheet->getColumnDimension('F')->setWidth(12);
                $sheet->getColumnDimension('G')->setWidth(12);
                $sheet->getColumnDimension('H')->setWidth(26);
                $sheet->getColumnDimension('I')->setWidth(16);
                $sheet->getColumnDimension('J')->setWidth(16);
                $sheet->getColumnDimension('K')->setWidth(14);
                $sheet->getColumnDimension('L')->setWidth(14);
                $sheet->getColumnDimension('M')->setWidth(14);

                $sheet->setShowGridlines(false);
            },
        ];
    }
}
