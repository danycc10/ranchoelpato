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

class MonthlyIncomeHistoricDetailSheet implements FromCollection, WithColumnFormatting, WithEvents, WithHeadings, WithTitle
{
    public function __construct(
        public int $anio,
        public int $mes,
        public ?int $propietarioId,
    ) {}

    public function title(): string
    {
        return 'Historico';
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
            'FECHA_RECIBO',
            'FECHA_VENCIMIENTO',
            'FRACCIONAMIENTO',
            'MANZANA',
            'LOTE',
            'CLIENTE',
            'CONTRATO',
            'TIPO_COBRO',
            'FORMA_PAGO',
            'CUENTA_BANCARIA',
            'PERIODO',
            'PROPIETARIO_CONTABLE',
            'MONTO_HISTORICO',
        ];
    }

    public function collection()
    {
        [$start, $end] = $this->monthRange();

        $q = DB::table('recibos')
            ->leftJoin('tipos_cobro', 'tipos_cobro.id', '=', 'recibos.tipos_cobro_id')
            ->leftJoin('formas_pago', 'formas_pago.id', '=', 'recibos.forma_pago_id')
            ->leftJoin('cuentas_bancarias', 'cuentas_bancarias.id', '=', 'recibos.cuentas_bancarias_id')
            ->leftJoin('periodos', 'periodos.id', '=', 'recibos.periodo_id')
            ->leftJoin('propietarios', 'propietarios.id', '=', 'recibos.propietario_contable_id')
            ->leftJoin('contratos', 'contratos.id', '=', 'recibos.contrato_id')
            ->leftJoin('clientes as recibo_clientes', 'recibo_clientes.id', '=', 'recibos.cliente_id')
            ->leftJoin('clientes as contrato_clientes', 'contrato_clientes.id', '=', 'contratos.cliente_id')
            ->leftJoin('cuotas', 'cuotas.id', '=', 'recibos.cuota_id')
            ->leftJoin('lotes', 'lotes.id', '=', 'contratos.lote_id')
            ->leftJoin('fraccionamientos', 'fraccionamientos.id', '=', 'lotes.fraccionamiento_id')
            ->whereNull('recibos.deleted_at')
            ->whereNull('recibos.anulado_at')
            ->whereIn('contratos.estatus', ['activo', 'liquidado'])
            ->where('recibos.es_historico', true)
            ->where('tipos_cobro.nombre', 'MENSUALIDAD')
            ->where('contratos.tipo', 'terreno')
            ->whereBetween('cuotas.fecha_vencimiento', [$start, $end])
            ->when(
                $this->propietarioId,
                fn ($qq) => $qq->where('recibos.propietario_contable_id', $this->propietarioId)
            )
            ->orderBy('cuotas.fecha_vencimiento')
            ->orderBy('fraccionamientos.nombre')
            ->orderBy('recibos.fecha')
            ->orderBy('recibos.folio')
            ->select([
                'recibos.folio',
                'recibos.fecha',
                'cuotas.fecha_vencimiento',
                DB::raw('COALESCE(fraccionamientos.nombre, "Sin finca") as fraccionamiento'),
                'lotes.manzana',
                'lotes.lote',
                DB::raw("
                    COALESCE(
                        NULLIF(TRIM(CONCAT_WS(' ', recibo_clientes.nombres, recibo_clientes.apellidos)), ''),
                        NULLIF(TRIM(CONCAT_WS(' ', contrato_clientes.nombres, contrato_clientes.apellidos)), ''),
                        'SIN CLIENTE'
                    ) as cliente
                "),
                DB::raw('COALESCE(contratos.folio_contrato, "") as contrato'),
                DB::raw('COALESCE(tipos_cobro.nombre, "SIN CONCEPTO") as tipo_cobro'),
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
                DB::raw('COALESCE(propietarios.nombre, "SIN PROPIETARIO") as propietario_contable'),
                'recibos.monto',
            ]);

        return $q->get()->map(function ($r) {
            return [
                (string) ($r->folio ?? ''),
                (string) ($r->fecha ?? ''),
                (string) ($r->fecha_vencimiento ?? ''),
                (string) ($r->fraccionamiento ?? ''),
                (string) ($r->manzana ?? ''),
                (string) ($r->lote ?? ''),
                (string) ($r->cliente ?? ''),
                (string) ($r->contrato ?? ''),
                (string) ($r->tipo_cobro ?? ''),
                (string) ($r->forma_pago ?? ''),
                (string) ($r->cuenta_bancaria ?? ''),
                (string) ($r->periodo ?? ''),
                (string) ($r->propietario_contable ?? ''),
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

                $sheet->getStyle("N2:N{$highestRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                $sheet->getColumnDimension('A')->setWidth(16);
                $sheet->getColumnDimension('B')->setWidth(12);
                $sheet->getColumnDimension('C')->setWidth(16);
                $sheet->getColumnDimension('D')->setWidth(26);
                $sheet->getColumnDimension('E')->setWidth(12);
                $sheet->getColumnDimension('F')->setWidth(12);
                $sheet->getColumnDimension('G')->setWidth(28);
                $sheet->getColumnDimension('H')->setWidth(18);
                $sheet->getColumnDimension('I')->setWidth(18);
                $sheet->getColumnDimension('J')->setWidth(16);
                $sheet->getColumnDimension('K')->setWidth(24);
                $sheet->getColumnDimension('L')->setWidth(18);
                $sheet->getColumnDimension('M')->setWidth(24);
                $sheet->getColumnDimension('N')->setWidth(16);

                $sheet->setShowGridlines(false);
            },
        ];
    }
}
