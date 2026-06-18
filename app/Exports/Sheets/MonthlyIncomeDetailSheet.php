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
use PhpOffice\PhpSpreadsheet\Style\Fill;

class MonthlyIncomeDetailSheet implements FromCollection, WithColumnFormatting, WithEvents, WithHeadings, WithTitle
{
    public function __construct(
        public int $anio,
        public int $mes,
        public ?int $propietarioId,
        public string $modoVista,
    ) {}

    public function title(): string
    {
        return 'Detalle';
    }

    protected function monthRange(): array
    {
        $start = now()->setDate($this->anio, $this->mes, 1)->startOfDay();
        $end = (clone $start)->endOfMonth()->endOfDay();

        return [$start->toDateString(), $end->toDateString()];
    }

    protected function modoVistaLabel(): string
    {
        return $this->modoVista === 'flujo_real'
            ? 'FLUJO REAL MENSUAL'
            : 'CÓMO VA EL MES';
    }

    public function headings(): array
    {
        return [
            'FOLIO',
            'FECHA_PAGO',
            'FECHA_HORA_PAGO',
            'FECHA_RECIBO',
            'SEMANA_PAGO',
            'FRACCIONAMIENTO',
            'MANZANA',
            'LOTE',
            'CLIENTE',
            'CONCEPTO',
            'FORMA_PAGO',
            'CUENTA_BANCARIA',
            'PERIODO',
            'MONTO',
        ];
    }

    public function collection()
    {
        [$start, $end] = $this->monthRange();

        $q = DB::table('recibos_pagos')
            ->join('recibos', 'recibos.id', '=', 'recibos_pagos.recibo_id')
            ->leftJoin('contratos', 'contratos.id', '=', 'recibos.contrato_id')
            ->leftJoin('clientes', 'clientes.id', '=', 'recibos.cliente_id')
            ->leftJoin('lotes', 'lotes.id', '=', 'contratos.lote_id')
            ->leftJoin('fraccionamientos', 'fraccionamientos.id', '=', 'lotes.fraccionamiento_id')
            ->leftJoin('tipos_cobro', 'tipos_cobro.id', '=', 'recibos.tipos_cobro_id')
            ->leftJoin('formas_pago', 'formas_pago.id', '=', 'recibos_pagos.forma_pago_id')
            ->leftJoin('cuentas_bancarias', 'cuentas_bancarias.id', '=', 'recibos_pagos.cuenta_bancaria_id')
            ->leftJoin('periodos', 'periodos.id', '=', 'recibos.periodo_id')
            ->leftJoin('cuotas', 'cuotas.id', '=', 'recibos.cuota_id')

            ->whereNull('recibos_pagos.deleted_at')
            ->whereNull('recibos.deleted_at')
            ->whereNull('recibos.anulado_at')
            ->where(function ($qq) {
                $qq->whereNull('recibos.es_historico')
                    ->orWhere('recibos.es_historico', false);
            })
            ->where('recibos.afecta_reportes', true)
            ->where('recibos.folio', 'not like', 'REC%')
            ->where('contratos.estatus', 'activo')

            ->when(
                $this->propietarioId,
                fn ($qq) => $qq->where('recibos.propietario_contable_id', $this->propietarioId)
            );

        // ===== CAMBIO CLAVE: RESPETAR SWITCH =====
        if ($this->modoVista === 'flujo_real') {

            $q->where(function ($main) use ($start, $end) {
                $main->where(function ($sub) use ($start, $end) {
                    $sub->where('tipos_cobro.nombre', 'MENSUALIDAD')
                        ->whereBetween('cuotas.fecha_vencimiento', [$start, $end])
                        ->whereBetween(DB::raw('DATE(recibos_pagos.fecha_efectiva)'), [$start, $end]);
                })->orWhere(function ($sub) use ($start, $end) {
                    $sub->where(function ($x) {
                        $x->whereNull('tipos_cobro.nombre')
                            ->orWhere('tipos_cobro.nombre', '!=', 'MENSUALIDAD');
                    })
                        ->whereBetween(DB::raw('DATE(recibos_pagos.fecha_efectiva)'), [$start, $end]);
                });
            });

        } else {

            $q->where(function ($main) use ($start, $end) {
                $main->where(function ($sub) use ($start, $end) {
                    $sub->where('tipos_cobro.nombre', 'MENSUALIDAD')
                        ->whereBetween('cuotas.fecha_vencimiento', [$start, $end]);
                })->orWhere(function ($sub) use ($start, $end) {
                    $sub->where(function ($x) {
                        $x->whereNull('tipos_cobro.nombre')
                            ->orWhere('tipos_cobro.nombre', '!=', 'MENSUALIDAD');
                    })
                        ->whereBetween(DB::raw('DATE(recibos_pagos.fecha_efectiva)'), [$start, $end]);
                });
            });

        }

        $q->orderBy('recibos_pagos.fecha_efectiva')
            ->orderBy('recibos.id')
            ->orderBy('recibos_pagos.id')
            ->select([
                'recibos.folio',
                DB::raw('DATE(recibos_pagos.fecha_efectiva) as fecha_pago'),
                'recibos_pagos.fecha_efectiva as fecha_hora_pago',
                'recibos.fecha as fecha_recibo',
                'recibos.semana_pago',

                DB::raw('COALESCE(fraccionamientos.nombre, "Sin finca") as fraccionamiento'),

                'lotes.manzana',
                'lotes.lote',

                DB::raw("
                    COALESCE(
                        NULLIF(TRIM(CONCAT_WS(' ', clientes.nombres, clientes.apellidos)), ''),
                        'SIN CLIENTE'
                    ) as cliente
                "),

                DB::raw('COALESCE(tipos_cobro.nombre, "SIN CONCEPTO") as concepto'),
                DB::raw('COALESCE(formas_pago.nombre, "SIN FORMA") as forma_pago'),

                DB::raw("
                    COALESCE(
                        NULLIF(cuentas_bancarias.alias, ''),
                        NULLIF(TRIM(CONCAT_WS(' - ', cuentas_bancarias.banco, cuentas_bancarias.numero)), ''),
                        NULLIF(cuentas_bancarias.banco, ''),
                        'SIN CUENTA'
                    ) as cuenta_bancaria
                "),

                DB::raw('COALESCE(periodos.nombre, "SIN PERIODO") as periodo'),

                'recibos_pagos.monto',
            ]);

        return $q->get()->map(function ($r) {
            return [
                (string) ($r->folio ?? ''),
                (string) ($r->fecha_pago ?? ''),
                (string) ($r->fecha_hora_pago ?? ''),
                (string) ($r->fecha_recibo ?? ''),
                (string) ($r->semana_pago ?? ''),
                (string) ($r->fraccionamiento ?? ''),
                (string) ($r->manzana ?? ''),
                (string) ($r->lote ?? ''),
                (string) ($r->cliente ?? ''),
                (string) ($r->concepto ?? ''),
                (string) ($r->forma_pago ?? ''),
                (string) ($r->cuenta_bancaria ?? ''),
                (string) ($r->periodo ?? ''),
                (float) ($r->monto ?? 0),
            ];
        });
    }

    public function columnFormats(): array
    {
        return [
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

                // ===== TITULO =====
                $sheet->insertNewRowBefore(1, 2);

                $sheet->mergeCells("A1:{$highestCol}1");
                $sheet->mergeCells("A2:{$highestCol}2");

                $sheet->setCellValue('A1', 'DETALLE DE INGRESOS');
                $sheet->setCellValue('A2', 'MODO: '.$this->modoVistaLabel());

                $sheet->getStyle("A1:{$highestCol}1")->applyFromArray([
                    'font' => [
                        'name' => 'Dubai Medium',
                        'bold' => true,
                        'size' => 16,
                        'color' => ['rgb' => 'FFFFFF'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '111827'],
                    ],
                ]);

                $sheet->getStyle("A2:{$highestCol}2")->applyFromArray([
                    'font' => [
                        'name' => 'Dubai Medium',
                        'bold' => true,
                        'size' => 11,
                        'color' => ['rgb' => '111827'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'E5E7EB'],
                    ],
                ]);

                $sheet->freezePane('A4');
                $sheet->setAutoFilter("A3:{$highestCol}3");

                $sheet->getStyle("A3:{$highestCol}3")->applyFromArray([
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
                        'startColor' => ['rgb' => '111827'],
                    ],
                ]);

                $highestRow = $sheet->getHighestRow();

                for ($r = 4; $r <= $highestRow; $r++) {
                    if (($r % 2) === 0) {
                        $sheet->getStyle("A{$r}:{$highestCol}{$r}")
                            ->applyFromArray([
                                'fill' => [
                                    'fillType' => Fill::FILL_SOLID,
                                    'startColor' => ['rgb' => 'F9FAFB'],
                                ],
                            ]);
                    }
                }

                $sheet->getStyle("N4:N{$highestRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                // ===== ANCHOS =====
                $sheet->getColumnDimension('A')->setWidth(14);
                $sheet->getColumnDimension('B')->setWidth(12);
                $sheet->getColumnDimension('C')->setWidth(20);
                $sheet->getColumnDimension('D')->setWidth(12);
                $sheet->getColumnDimension('E')->setWidth(14);
                $sheet->getColumnDimension('F')->setWidth(28);
                $sheet->getColumnDimension('G')->setWidth(12);
                $sheet->getColumnDimension('H')->setWidth(12);
                $sheet->getColumnDimension('I')->setWidth(28);
                $sheet->getColumnDimension('J')->setWidth(18);
                $sheet->getColumnDimension('K')->setWidth(16);
                $sheet->getColumnDimension('L')->setWidth(24);
                $sheet->getColumnDimension('M')->setWidth(18);
                $sheet->getColumnDimension('N')->setWidth(14);

                $sheet->setShowGridlines(false);
            },
        ];
    }
}
