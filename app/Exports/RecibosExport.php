<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class RecibosExport implements FromCollection, WithColumnFormatting, WithEvents, WithHeadings
{
    public function __construct(
        public ?int $propietarioId,
        public ?string $desde,
        public ?string $hasta,
        public ?int $tipoCobroid,
        public ?int $formaPagoId,
        public ?int $cuentaId,
        public string $validacion = '',   // '' | 'validado' | 'sin_validar'
        public string $verEliminados = 'activos',
    ) {}

    public function headings(): array
    {
        return [
            'FOLIO',
            'FECHA_RECIBO',
            'FECHA_EFECTIVA',
            'CLIENTE',
            'LOTE',
            'FRACCIONAMIENTO',
            'TIPO_COBRO',
            'MONTO_PAGO',
            'FORMA_PAGO',
            'CUENTA_BANCARIA',
            'REFERENCIA',
            'TIENE_EVIDENCIA',
            'VALIDADO',
            'VALIDADO_EN',
            'VALIDADO_POR',
        ];
    }

    public function collection()
    {
        $q = DB::table('recibos_pagos')
            ->join('recibos', 'recibos.id', '=', 'recibos_pagos.recibo_id')
            ->leftJoin('clientes', 'clientes.id', '=', 'recibos.cliente_id')
            ->leftJoin('contratos', 'contratos.id', '=', 'recibos.contrato_id')
            ->leftJoin('lotes', 'lotes.id', '=', 'recibos.lote_id')
            ->leftJoin('fraccionamientos', 'fraccionamientos.id', '=', 'lotes.fraccionamiento_id')
            ->leftJoin('tipos_cobro', 'tipos_cobro.id', '=', 'recibos.tipos_cobro_id')
            ->leftJoin('formas_pago', 'formas_pago.id', '=', 'recibos_pagos.forma_pago_id')
            ->leftJoin('cuentas_bancarias', 'cuentas_bancarias.id', '=', 'recibos_pagos.cuenta_bancaria_id')
            ->leftJoin('users as validador', 'validador.id', '=', 'recibos_pagos.validado_por_user_id')
            ->whereNull('recibos_pagos.deleted_at')
            ->where(function ($q) {
                $q->whereNull('recibos.es_historico')
                    ->orWhere('recibos.es_historico', false);
            })
            ->where(function ($q) {
                $q->whereNull('recibos.folio')
                    ->orWhere('recibos.folio', 'not like', 'REC%');
            });

        if ($this->verEliminados === 'eliminados') {
            $q->whereNotNull('recibos.deleted_at');
        } elseif ($this->verEliminados === 'activos') {
            $q->whereNull('recibos.deleted_at');
        }

        $q->when($this->propietarioId, fn ($q) => $q->where('fraccionamientos.propietario_id', $this->propietarioId))
            ->when($this->tipoCobroid, fn ($q) => $q->where('recibos.tipos_cobro_id', $this->tipoCobroid))
            ->when($this->formaPagoId, fn ($q) => $q->where('recibos_pagos.forma_pago_id', $this->formaPagoId))
            ->when($this->cuentaId, fn ($q) => $q->where('recibos_pagos.cuenta_bancaria_id', $this->cuentaId))
            ->when($this->desde, fn ($q) => $q->whereDate('recibos.fecha', '>=', $this->desde))
            ->when($this->hasta, fn ($q) => $q->whereDate('recibos.fecha', '<=', $this->hasta));

        if ($this->validacion === 'validado') {
            $q->whereNotNull('recibos_pagos.validado_at');
        } elseif ($this->validacion === 'sin_validar') {
            $q->whereNotNull('recibos_pagos.evidencia_path')
                ->whereNull('recibos_pagos.validado_at');
        }

        $q->orderBy('recibos.fecha')
            ->orderBy('recibos.id')
            ->orderBy('recibos_pagos.orden')
            ->select([
                'recibos.folio',
                'recibos.fecha as fecha_recibo',
                'recibos_pagos.fecha_efectiva',
                DB::raw("COALESCE(NULLIF(TRIM(CONCAT_WS(' ', clientes.nombres, clientes.apellidos)), ''), 'SIN CLIENTE') as cliente"),
                DB::raw("COALESCE(lotes.lote, '') as lote"),
                DB::raw("COALESCE(fraccionamientos.nombre, '') as fraccionamiento"),
                DB::raw("COALESCE(tipos_cobro.nombre, '') as tipo_cobro"),
                'recibos_pagos.monto',
                DB::raw("COALESCE(formas_pago.nombre, '') as forma_pago"),
                DB::raw("COALESCE(NULLIF(cuentas_bancarias.alias,''), cuentas_bancarias.banco, '') as cuenta_bancaria"),
                DB::raw("COALESCE(recibos_pagos.referencia, '') as referencia"),
                DB::raw("IF(recibos_pagos.evidencia_path IS NOT NULL, 'Sí', 'No') as tiene_evidencia"),
                DB::raw("IF(recibos_pagos.validado_at IS NOT NULL, 'Sí', 'No') as validado"),
                'recibos_pagos.validado_at',
                DB::raw("COALESCE(validador.name, '') as validado_por"),
            ]);

        return $q->get()->map(fn ($r) => [
            (string) ($r->folio ?? ''),
            (string) ($r->fecha_recibo ?? ''),
            (string) ($r->fecha_efectiva ?? ''),
            (string) ($r->cliente ?? ''),
            (string) ($r->lote ?? ''),
            (string) ($r->fraccionamiento ?? ''),
            (string) ($r->tipo_cobro ?? ''),
            (float) ($r->monto ?? 0),
            (string) ($r->forma_pago ?? ''),
            (string) ($r->cuenta_bancaria ?? ''),
            (string) ($r->referencia ?? ''),
            (string) ($r->tiene_evidencia ?? ''),
            (string) ($r->validado ?? ''),
            $r->validado_at ? \Carbon\Carbon::parse($r->validado_at)->format('d/m/Y H:i') : '',
            (string) ($r->validado_por ?? ''),
        ]);
    }

    public function columnFormats(): array
    {
        return [
            'H' => '"$"#,##0.00',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();
                $highestCol = $sheet->getHighestColumn();

                $sheet->insertNewRowBefore(1, 1);
                $sheet->mergeCells("A1:{$highestCol}1");
                $sheet->setCellValue('A1', 'RECIBOS');

                $sheet->getStyle("A1:{$highestCol}1")->applyFromArray([
                    'font' => ['name' => 'Dubai Medium', 'bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '111827']],
                ]);

                $sheet->freezePane('A3');
                $sheet->setAutoFilter("A2:{$highestCol}2");

                $sheet->getStyle("A2:{$highestCol}2")->applyFromArray([
                    'font' => ['name' => 'Dubai Medium', 'bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '111827']],
                ]);

                $highestRow = $sheet->getHighestRow();

                for ($r = 3; $r <= $highestRow; $r++) {
                    if ($r % 2 === 0) {
                        $sheet->getStyle("A{$r}:{$highestCol}{$r}")->applyFromArray([
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F9FAFB']],
                        ]);
                    }
                }

                $sheet->getStyle("H3:H{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                $sheet->getColumnDimension('A')->setWidth(18);
                $sheet->getColumnDimension('B')->setWidth(14);
                $sheet->getColumnDimension('C')->setWidth(14);
                $sheet->getColumnDimension('D')->setWidth(28);
                $sheet->getColumnDimension('E')->setWidth(10);
                $sheet->getColumnDimension('F')->setWidth(26);
                $sheet->getColumnDimension('G')->setWidth(18);
                $sheet->getColumnDimension('H')->setWidth(14);
                $sheet->getColumnDimension('I')->setWidth(16);
                $sheet->getColumnDimension('J')->setWidth(22);
                $sheet->getColumnDimension('K')->setWidth(20);
                $sheet->getColumnDimension('L')->setWidth(14);
                $sheet->getColumnDimension('M')->setWidth(12);
                $sheet->getColumnDimension('N')->setWidth(16);
                $sheet->getColumnDimension('O')->setWidth(20);

                $sheet->setShowGridlines(false);
            },
        ];
    }
}
