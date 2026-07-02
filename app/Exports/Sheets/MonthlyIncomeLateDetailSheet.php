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

class MonthlyIncomeLateDetailSheet implements FromCollection, WithColumnFormatting, WithEvents, WithHeadings, WithTitle
{
    public function __construct(
        public int $anio,
        public int $mes,
        public ?int $propietarioId,
    ) {}

    public function title(): string
    {
        return 'Desfasados';
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
            'FOLIO',
            'FECHA_PAGO',
            'FECHA_RECIBO',
            'FECHA_VENCIMIENTO',
            'FRACCIONAMIENTO',
            'LOTE',
            'CONTRATO',
            'FORMA_PAGO',
            'MONTO_ATRASADO',
        ];
    }

    public function collection()
    {
        [$start, $end] = $this->monthRange();

        $q = DB::table('recibos_pagos')
            ->join('recibos', 'recibos.id', '=', 'recibos_pagos.recibo_id')
            ->leftJoin('formas_pago', 'formas_pago.id', '=', 'recibos_pagos.forma_pago_id')
            ->leftJoin('tipos_cobro', 'tipos_cobro.id', '=', 'recibos.tipos_cobro_id')
            ->leftJoin('contratos', 'contratos.id', '=', 'recibos.contrato_id')
            ->leftJoin('cuotas', 'cuotas.id', '=', 'recibos.cuota_id')
            ->leftJoin('lotes', 'lotes.id', '=', 'contratos.lote_id')
            ->leftJoin('fraccionamientos', 'fraccionamientos.id', '=', 'lotes.fraccionamiento_id')

            ->whereNull('recibos_pagos.deleted_at')
            ->whereNull('recibos.deleted_at')
            ->whereNull('recibos.anulado_at')
            ->where(function ($q) {
                $q->whereNull('recibos.es_historico')
                    ->orWhere('recibos.es_historico', false);
            })
            ->where('recibos.afecta_reportes', true)
            ->where('recibos.folio', 'not like', 'REC%')
            ->whereIn('contratos.estatus', ['activo', 'liquidado'])
            ->where('contratos.tipo', 'terreno')
            ->where('tipos_cobro.nombre', 'MENSUALIDAD')

            // Pagado en el mes seleccionado
            ->whereBetween('recibos_pagos.fecha_efectiva', [$start, $end])

            // Atrasado / desfasado: cuota vencía antes del inicio del mes
            ->where('cuotas.fecha_vencimiento', '<', $start)

            ->when(
                $this->propietarioId,
                fn ($qq) => $qq->where('fraccionamientos.propietario_id', $this->propietarioId)
            )

            ->orderBy('cuotas.fecha_vencimiento')
            ->orderBy('fraccionamientos.nombre')
            ->orderBy('recibos_pagos.fecha_efectiva')
            ->orderBy('recibos.folio')

            ->select([
                'recibos.folio',
                'recibos_pagos.fecha_efectiva as fecha_pago',
                'recibos.fecha as fecha_recibo',
                'cuotas.fecha_vencimiento',
                DB::raw('COALESCE(fraccionamientos.nombre, "Sin finca") as fraccionamiento'),
                'lotes.lote',
                'contratos.folio_contrato',
                DB::raw('COALESCE(formas_pago.nombre, "SIN FORMA") as forma_pago'),
                'recibos_pagos.monto as monto_atrasado',
            ]);

        return $q->get()->map(function ($r) {
            return [
                (string) ($r->folio ?? ''),
                (string) ($r->fecha_pago ?? ''),
                (string) ($r->fecha_recibo ?? ''),
                (string) ($r->fecha_vencimiento ?? ''),
                (string) ($r->fraccionamiento ?? ''),
                (string) ($r->lote ?? ''),
                (string) ($r->folio_contrato ?? ''),
                (string) ($r->forma_pago ?? ''),
                (float) ($r->monto_atrasado ?? 0),
            ];
        });
    }

    public function columnFormats(): array
    {
        return [
            'I' => '"$"#,##0.00',
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

                for ($r = 2; $r <= $highestRow; $r++) {
                    if (($r % 2) === 0) {
                        $sheet->getStyle("A{$r}:{$highestCol}{$r}")->applyFromArray([
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['rgb' => 'F9FAFB'],
                            ],
                        ]);
                    }
                }

                $sheet->getStyle("I2:I{$highestRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                $sheet->getColumnDimension('A')->setWidth(16);
                $sheet->getColumnDimension('B')->setWidth(12);
                $sheet->getColumnDimension('C')->setWidth(12);
                $sheet->getColumnDimension('D')->setWidth(16);
                $sheet->getColumnDimension('E')->setWidth(26);
                $sheet->getColumnDimension('F')->setWidth(12);
                $sheet->getColumnDimension('G')->setWidth(18);
                $sheet->getColumnDimension('H')->setWidth(16);
                $sheet->getColumnDimension('I')->setWidth(16);

                $sheet->setShowGridlines(false);
            },
        ];
    }
}
