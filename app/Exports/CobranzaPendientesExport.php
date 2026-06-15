<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\{
    WithMultipleSheets,
    FromCollection,
    FromArray,
    WithHeadings,
    WithMapping,
    ShouldAutoSize,
    WithTitle,
    WithStyles,
    WithColumnFormatting,
    WithEvents
};
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class CobranzaPendientesExport implements WithMultipleSheets
{
    public function __construct(
        protected Collection $cuotasHoy,
        protected Collection $cuotasAtrasadas,
        protected string $fecha
    ) {}

    public function sheets(): array
    {
        return [
            new CobranzaPendientesDiaSheet($this->cuotasHoy, $this->fecha),
            new CobranzaAtrasadasSheet($this->cuotasAtrasadas, $this->fecha),
            new CobranzaResumenSheet($this->cuotasHoy, $this->cuotasAtrasadas, $this->fecha),
        ];
    }
}

class CobranzaPendientesDiaSheet implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithTitle, WithStyles, WithColumnFormatting, WithEvents
{
    public function __construct(
        protected Collection $cuotas,
        protected string $fecha
    ) {}

    public function collection()
    {
        return $this->cuotas;
    }

    public function title(): string
    {
        return 'Pendientes del día';
    }

    public function headings(): array
    {
        return [
            'Cliente',
            'Teléfono',
            'Correo',
            'Contrato',
            'Fraccionamiento',
            'Lote',
            'Fecha vencimiento',
            'Monto',
            'Estatus',
        ];
    }

    public function map($cuota): array
    {
        $cliente = $cuota->contrato?->cliente;
        $lote = $cuota->contrato?->lote;
        $fracc = $lote?->fraccionamiento?->nombre ?? '—';

        return [
            trim(($cliente?->nombres ?? '') . ' ' . ($cliente?->apellidos ?? '')) ?: '—',
            $cliente?->telefono ?: '—',
            $cliente?->correo ?: '—',
            $cuota->contrato?->folio_contrato ?: '—',
            $fracc,
            $lote?->lote ?? '—',
            Carbon::parse($cuota->fecha_vencimiento)->format('d/m/Y'),
            (float) $cuota->monto,
            ucfirst($cuota->estatus ?? '—'),
        ];
    }

    public function columnFormats(): array
    {
        return [
            'H' => '$#,##0.00',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '111827'],
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = max($this->cuotas->count() + 1, 2);

                $sheet->freezePane('A2');
                $sheet->setAutoFilter("A1:I{$lastRow}");

                $sheet->getStyle("A1:I{$lastRow}")
                    ->getBorders()
                    ->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);

                $sheet->getStyle("A1:I{$lastRow}")
                    ->getAlignment()
                    ->setVertical(Alignment::VERTICAL_CENTER);

                $sheet->getStyle("H2:H{$lastRow}")
                    ->getNumberFormat()
                    ->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
            },
        ];
    }
}

class CobranzaAtrasadasSheet implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithTitle, WithStyles, WithColumnFormatting, WithEvents
{
    public function __construct(
        protected Collection $cuotas,
        protected string $fecha
    ) {}

    public function collection()
    {
        return $this->cuotas;
    }

    public function title(): string
    {
        return 'Atrasadas';
    }

    public function headings(): array
    {
        return [
            'Cliente',
            'Teléfono',
            'Correo',
            'Contrato',
            'Fraccionamiento',
            'Lote',
            'Fecha vencimiento',
            'Días atraso',
            'Monto',
            'Recargo',
            'Total',
            'Estatus',
        ];
    }

    protected function calcularRecargo($cuota): float
    {
        $contrato = $cuota->contrato;

        $recargoAplicado = (float) ($cuota->recargo_aplicado ?? 0);

        if ($recargoAplicado > 0) {
            return round($recargoAplicado, 2);
        }

        $tipo = $contrato?->tipo_recargo ?? null;
        $valor = (float) ($contrato?->valor_recargo ?? 0);

        if ($valor <= 0) {
            return 0.0;
        }

        if ($tipo === 'porcentaje') {
            return round(((float) $cuota->monto) * ($valor / 100), 2);
        }

        return round($valor, 2);
    }

    public function map($cuota): array
    {
        $cliente = $cuota->contrato?->cliente;
        $lote = $cuota->contrato?->lote;
        $fracc = $lote?->fraccionamiento?->nombre ?? '—';

        $vencimiento = Carbon::parse($cuota->fecha_vencimiento)->startOfDay();
        $fechaCorte = Carbon::parse($this->fecha)->startOfDay();

        $dias = $fechaCorte->greaterThan($vencimiento)
            ? $vencimiento->diffInDays($fechaCorte)
            : 0;

        $monto = (float) $cuota->monto;
        $recargo = $this->calcularRecargo($cuota);
        $total = $monto + $recargo;

        return [
            trim(($cliente?->nombres ?? '') . ' ' . ($cliente?->apellidos ?? '')) ?: '—',
            $cliente?->telefono ?: '—',
            $cliente?->correo ?: '—',
            $cuota->contrato?->folio_contrato ?: '—',
            $fracc,
            $lote?->lote ?? '—',
            $vencimiento->format('d/m/Y'),
            $dias,
            $monto,
            $recargo,
            $total,
            ucfirst($cuota->estatus ?? '—'),
        ];
    }

    public function columnFormats(): array
    {
        return [
            'I' => '$#,##0.00',
            'J' => '$#,##0.00',
            'K' => '$#,##0.00',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '111827'],
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = max($this->cuotas->count() + 1, 2);

                $sheet->freezePane('A2');
                $sheet->setAutoFilter("A1:L{$lastRow}");

                $sheet->getStyle("A1:L{$lastRow}")
                    ->getBorders()
                    ->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);

                $sheet->getStyle("A1:L{$lastRow}")
                    ->getAlignment()
                    ->setVertical(Alignment::VERTICAL_CENTER);

                $sheet->getStyle("H2:H{$lastRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->getStyle("I2:K{$lastRow}")
                    ->getNumberFormat()
                    ->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
            },
        ];
    }
}

class CobranzaResumenSheet implements FromArray, WithTitle, ShouldAutoSize, WithStyles, WithColumnFormatting, WithEvents
{
    public function __construct(
        protected Collection $cuotasHoy,
        protected Collection $cuotasAtrasadas,
        protected string $fecha
    ) {}

    public function title(): string
    {
        return 'Resumen';
    }

    protected function calcularRecargo($cuota): float
    {
        $contrato = $cuota->contrato;

        $recargoAplicado = (float) ($cuota->recargo_aplicado ?? 0);

        if ($recargoAplicado > 0) {
            return round($recargoAplicado, 2);
        }

        $tipo = $contrato?->tipo_recargo ?? null;
        $valor = (float) ($contrato?->valor_recargo ?? 0);

        if ($valor <= 0) {
            return 0.0;
        }

        if ($tipo === 'porcentaje') {
            return round(((float) $cuota->monto) * ($valor / 100), 2);
        }

        return round($valor, 2);
    }

    protected function totalRecargos(Collection $cuotas): float
    {
        return (float) $cuotas->sum(fn ($cuota) => $this->calcularRecargo($cuota));
    }

    protected function totalConRecargos(Collection $cuotas): float
    {
        return (float) $cuotas->sum(function ($cuota) {
            return (float) $cuota->monto + $this->calcularRecargo($cuota);
        });
    }

    public function array(): array
    {
        $montoHoy = (float) $this->cuotasHoy->sum('monto');
        $montoAtrasadas = (float) $this->cuotasAtrasadas->sum('monto');

        $recargosAtrasadas = $this->totalRecargos($this->cuotasAtrasadas);
        $totalAtrasadas = $this->totalConRecargos($this->cuotasAtrasadas);

        return [
            ['Resumen de cobranza'],
            ['Fecha', Carbon::parse($this->fecha)->format('d/m/Y')],
            [],
            ['Concepto', 'Cantidad', 'Monto', 'Recargos', 'Total'],
            ['Pendientes del día', $this->cuotasHoy->count(), $montoHoy, 0, $montoHoy],
            ['Atrasadas', $this->cuotasAtrasadas->count(), $montoAtrasadas, $recargosAtrasadas, $totalAtrasadas],
            [
                'Total',
                $this->cuotasHoy->count() + $this->cuotasAtrasadas->count(),
                $montoHoy + $montoAtrasadas,
                $recargosAtrasadas,
                $montoHoy + $totalAtrasadas,
            ],
        ];
    }

    public function columnFormats(): array
    {
        return [
            'C' => '$#,##0.00',
            'D' => '$#,##0.00',
            'E' => '$#,##0.00',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'size' => 16,
                ],
            ],
            4 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '111827'],
                ],
            ],
            7 => [
                'font' => [
                    'bold' => true,
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                $sheet->mergeCells('A1:E1');

                $sheet->getStyle('A4:E7')
                    ->getBorders()
                    ->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);

                $sheet->getStyle('C5:E7')
                    ->getNumberFormat()
                    ->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
            },
        ];
    }
}