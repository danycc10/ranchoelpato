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

class MonthlyIncomeLiquidatedContractsSheet implements FromCollection, WithColumnFormatting, WithEvents, WithHeadings, WithTitle
{
    public function __construct(
        public int $anio,
        public int $mes,
        public ?int $propietarioId,
    ) {}

    public function title(): string
    {
        return 'Liquidados';
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
            'FECHA_LIQUIDADO',
            'ESTATUS',
            'CONTRATO',
            'CLIENTE',
            'TELEFONO',
            'FRACCIONAMIENTO',
            'MANZANA',
            'LOTE',
            'FECHA_INICIO',
            'PRECIO_TOTAL',
            'ENGANCHE',
            'SALDO_INICIAL',
            'MONTO_PAGO',
            'TOTAL_PAGADO',
            'CUOTAS_TOTAL',
            'CUOTAS_PAGADAS',
        ];
    }

    public function collection(): Collection
    {
        [$start, $end] = $this->monthRange();

        $pagosRecibos = DB::table('recibos')
            ->join('recibos_pagos', 'recibos_pagos.recibo_id', '=', 'recibos.id')
            ->whereNull('recibos.deleted_at')
            ->whereNull('recibos.anulado_at')
            ->whereNull('recibos_pagos.deleted_at')
            ->whereNotNull('recibos.contrato_id')
            ->groupBy('recibos.contrato_id')
            ->selectRaw('recibos.contrato_id, MAX(DATE(recibos_pagos.fecha_efectiva)) as fecha_liquidado');

        $recibosHistoricos = DB::table('recibos')
            ->whereNull('recibos.deleted_at')
            ->whereNull('recibos.anulado_at')
            ->where('recibos.es_historico', true)
            ->whereNotNull('recibos.contrato_id')
            ->groupBy('recibos.contrato_id')
            ->selectRaw('recibos.contrato_id, MAX(DATE(recibos.fecha)) as fecha_liquidado');

        $pagos = DB::table('pagos')
            ->where('pagos.estatus', 'confirmado')
            ->whereNull('pagos.anulado_at')
            ->whereNotNull('pagos.contrato_id')
            ->groupBy('pagos.contrato_id')
            ->selectRaw('pagos.contrato_id, MAX(DATE(pagos.fecha_pago)) as fecha_liquidado');

        $eventos = $pagosRecibos
            ->unionAll($recibosHistoricos)
            ->unionAll($pagos);

        $ultimasFechas = DB::query()
            ->fromSub($eventos, 'eventos_liquidacion')
            ->groupBy('contrato_id')
            ->selectRaw('contrato_id, MAX(fecha_liquidado) as fecha_liquidado');

        $cuotasResumen = DB::table('cuotas')
            ->groupBy('contrato_id')
            ->selectRaw('
                contrato_id,
                COUNT(*) as cuotas_total,
                SUM(CASE WHEN estatus = "pagada" THEN 1 ELSE 0 END) as cuotas_pagadas,
                COALESCE(SUM(pagado_total), 0) as total_pagado
            ');

        $fechaLiquidado = 'COALESCE(
            DATE(contratos.liquidado_at),
            ultimas_fechas.fecha_liquidado,
            CASE WHEN contratos.estatus = "donacion" THEN DATE(contratos.created_at) END
        )';

        return DB::table('contratos')
            ->leftJoinSub($ultimasFechas, 'ultimas_fechas', function ($join) {
                $join->on('ultimas_fechas.contrato_id', '=', 'contratos.id');
            })
            ->leftJoinSub($cuotasResumen, 'cuotas_resumen', function ($join) {
                $join->on('cuotas_resumen.contrato_id', '=', 'contratos.id');
            })
            ->leftJoin('clientes', 'clientes.id', '=', 'contratos.cliente_id')
            ->leftJoin('lotes', 'lotes.id', '=', 'contratos.lote_id')
            ->leftJoin('fraccionamientos', 'fraccionamientos.id', '=', 'lotes.fraccionamiento_id')
            ->when(
                $this->propietarioId,
                fn ($q) => $q->where('fraccionamientos.propietario_id', $this->propietarioId)
            )
            ->where(function ($q) {
                $q->where(function ($sub) {
                    $sub->where('contratos.estatus', 'liquidado')
                        ->where('contratos.tipo', 'terreno');
                })->orWhere('contratos.estatus', 'donacion');
            })
            ->whereBetween(DB::raw($fechaLiquidado), [$start->toDateString(), $end->toDateString()])
            ->orderBy(DB::raw($fechaLiquidado))
            ->orderBy('fraccionamientos.nombre')
            ->orderBy('lotes.lote')
            ->selectRaw("
                {$fechaLiquidado} as fecha_liquidado,
                COALESCE(contratos.estatus, '') as estatus,
                COALESCE(contratos.folio_contrato, '') as contrato,
                COALESCE(NULLIF(TRIM(CONCAT_WS(' ', clientes.nombres, clientes.apellidos)), ''), 'SIN CLIENTE') as cliente,
                COALESCE(clientes.telefono, '') as telefono,
                COALESCE(fraccionamientos.nombre, 'Sin finca') as fraccionamiento,
                COALESCE(lotes.manzana, '') as manzana,
                COALESCE(lotes.lote, '') as lote,
                contratos.fecha_inicio,
                COALESCE(contratos.precio_total, 0) as precio_total,
                COALESCE(contratos.enganche, 0) as enganche,
                COALESCE(contratos.saldo_inicial, 0) as saldo_inicial,
                COALESCE(contratos.monto_pago, 0) as monto_pago,
                COALESCE(cuotas_resumen.total_pagado, 0) as total_pagado,
                COALESCE(cuotas_resumen.cuotas_total, 0) as cuotas_total,
                COALESCE(cuotas_resumen.cuotas_pagadas, 0) as cuotas_pagadas
            ")
            ->get()
            ->map(fn ($r) => [
                (string) ($r->fecha_liquidado ?? ''),
                (string) ($r->estatus ?? ''),
                (string) ($r->contrato ?? ''),
                (string) ($r->cliente ?? ''),
                (string) ($r->telefono ?? ''),
                (string) ($r->fraccionamiento ?? ''),
                (string) ($r->manzana ?? ''),
                (string) ($r->lote ?? ''),
                (string) ($r->fecha_inicio ?? ''),
                (float) ($r->precio_total ?? 0),
                (float) ($r->enganche ?? 0),
                (float) ($r->saldo_inicial ?? 0),
                (float) ($r->monto_pago ?? 0),
                (float) ($r->total_pagado ?? 0),
                (int) ($r->cuotas_total ?? 0),
                (int) ($r->cuotas_pagadas ?? 0),
            ]);
    }

    public function columnFormats(): array
    {
        return [
            'J' => '"$"#,##0.00',
            'K' => '"$"#,##0.00',
            'L' => '"$"#,##0.00',
            'M' => '"$"#,##0.00',
            'N' => '"$"#,##0.00',
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

                $sheet->getRowDimension(1)->setRowHeight(24);

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

                $sheet->getStyle("A2:A{$highestRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->getStyle("J2:N{$highestRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                $sheet->getStyle("O2:P{$highestRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                foreach ([
                    'A' => 16,
                    'B' => 14,
                    'C' => 18,
                    'D' => 30,
                    'E' => 16,
                    'F' => 26,
                    'G' => 12,
                    'H' => 12,
                    'I' => 14,
                    'J' => 14,
                    'K' => 14,
                    'L' => 14,
                    'M' => 14,
                    'N' => 14,
                    'O' => 14,
                    'P' => 15,
                ] as $col => $width) {
                    $sheet->getColumnDimension($col)->setWidth($width);
                }

                $sheet->setShowGridlines(false);
            },
        ];
    }
}
