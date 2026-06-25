<?php

namespace App\Livewire\Reports;

use App\Exports\MonthlyIncomeExport;
use App\Models\Propietario;
use App\Services\Reports\MonthlyIncomeReportData;
use Illuminate\Support\Str;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;

class MonthlyIncomeReport extends Component
{
    public int $anio;

    public int $mes;

    public ?int $propietarioId = null;

    public string $modoVista = 'flujo_real';

    protected ?MonthlyIncomeReportData $reportData = null;

    public function mount(): void
    {
        $this->anio = (int) now()->year;
        $this->mes = (int) now()->month;
        $this->modoVista = 'flujo_real';

        $user = auth()->user();

        if ($user->propietario_id) {
            $this->propietarioId = $user->propietario_id;
        }
    }

    public function getPropietariosProperty()
    {
        return Propietario::query()
            ->orderBy('nombre')
            ->get(['id', 'nombre']);
    }

    public function getModoVistaLabelProperty(): string
    {
        return $this->reportData()->modoVistaLabel();
    }

    public function getMetodosPagoProperty()
    {
        return $this->reportData()->metodosPago();
    }

    public function getFilasProperty()
    {
        return $this->reportData()->filas();
    }

    public function getTotalesConceptosHeaderProperty()
    {
        return $this->reportData()->totalesConceptosHeader();
    }

    public function getConceptosPorFincaProperty()
    {
        return $this->reportData()->conceptosPorFinca();
    }

    public function getTotalesProperty()
    {
        return $this->reportData()->totales();
    }

    public function exportExcel()
    {
        $reportData = $this->reportData();
        $propNombre = null;

        if ($this->propietarioId) {
            $propNombre = Propietario::query()
                ->whereKey($this->propietarioId)
                ->value('nombre');
        }

        $timestamp = now()->format('Y-m-d_H-i-s');

        $file = 'ingresos_mensuales_'.$this->anio.'_'.str_pad((string) $this->mes, 2, '0', STR_PAD_LEFT);

        if ($propNombre) {
            $file .= '_'.Str::slug($propNombre, '_');
        }

        $file .= '_'.$this->modoVista.'_'.$timestamp.'.xlsx';

        return Excel::download(
            new MonthlyIncomeExport(
                anio: $this->anio,
                mes: $this->mes,
                propietarioId: $this->propietarioId,
                modoVista: $this->modoVista,
                metodosPago: $reportData->metodosPago(),
                filas: $reportData->filas(),
                totales: $reportData->totales(),
                headerConceptos: $reportData->totalesConceptosHeader(),
                conceptosPorFinca: $reportData->conceptosPorFinca(),
            ),
            $file
        );
    }

    public function render()
    {
        $reportData = $this->reportData();
        $mesNombre = now()->setMonth($this->mes)->translatedFormat('F');

        return view('livewire.reports.monthly-income-report', [
            'propietarios' => $this->propietarios,
            'metodosPago' => $reportData->metodosPago(),
            'filas' => $reportData->filas(),
            'totales' => $reportData->totales(),
            'headerConceptos' => $reportData->totalesConceptosHeader(),
            'conceptosPorFinca' => $reportData->conceptosPorFinca(),
            'mesNombre' => mb_strtoupper($mesNombre),
            'anio' => $this->anio,
            'modoVistaLabel' => $reportData->modoVistaLabel(),
        ])->layout('layouts.app');
    }

    protected function reportData(): MonthlyIncomeReportData
    {
        return $this->reportData ??= new MonthlyIncomeReportData(
            anio: $this->anio,
            mes: $this->mes,
            propietarioId: $this->propietarioId,
            modoVista: $this->modoVista,
        );
    }
}
