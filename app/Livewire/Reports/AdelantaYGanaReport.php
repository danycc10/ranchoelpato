<?php

namespace App\Livewire\Reports;

use App\Exports\AdelantaYGanaExport;
use App\Models\AbonoCapital;
use App\Models\Promocion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Cuántas veces se cobró «Adelanta y gana», cuánto efectivo entró por ella y
 * cuánto se regaló en bonificaciones.
 *
 * Cuenta por el día en que se capturó el abono. Los anulados no suman: su
 * recibo se canceló y el saldo del cliente se regresó como estaba. Se pueden
 * ver en la lista para saber qué se canceló.
 */
class AdelantaYGanaReport extends Component
{
    use WithPagination;

    #[Url(as: 'promocion')]
    public ?int $promocionId = null;

    public ?int $fraccionamientoId = null;

    public string $desde = '';

    public string $hasta = '';

    public bool $verAnulados = false;

    /** Lo de vigentes() para no consultarlo dos veces en el mismo render. */
    private ?Collection $vigentes = null;

    public function mount(): void
    {
        $this->promocionId ??= Promocion::query()
            ->where('tipo', Promocion::ADELANTA_Y_GANA)
            ->latest('fecha_inicio')
            ->latest('id')
            ->value('id');

        $this->rangoDeLaPromocion();
    }

    public function updatedPromocionId(): void
    {
        $this->fraccionamientoId = null;
        $this->rangoDeLaPromocion();
        $this->resetPage();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['fraccionamientoId', 'desde', 'hasta', 'verAnulados'], true)) {
            $this->resetPage();
        }
    }

    public function limpiar(): void
    {
        $this->fraccionamientoId = null;
        $this->verAnulados = false;
        $this->rangoDeLaPromocion();
        $this->resetPage();
    }

    /** Toda la vigencia; si no tiene fechas, el mes en curso. */
    private function rangoDeLaPromocion(): void
    {
        $promocion = $this->promocion;

        $this->desde = ($promocion?->fecha_inicio ?? now()->startOfMonth())->toDateString();
        $this->hasta = ($promocion?->fecha_fin ?? now())->toDateString();
    }

    public function getPromocionesProperty(): Collection
    {
        return Promocion::query()
            ->where('tipo', Promocion::ADELANTA_Y_GANA)
            ->orderByDesc('fecha_inicio')
            ->orderByDesc('id')
            ->get(['id', 'nombre', 'fecha_inicio', 'fecha_fin']);
    }

    public function getPromocionProperty(): ?Promocion
    {
        return $this->promocionId
            ? Promocion::query()->with('fraccionamientos:id,nombre')->find($this->promocionId)
            : null;
    }

    /** Los fraccionamientos donde se ofrece la promoción. */
    public function getFraccionamientosProperty(): Collection
    {
        return $this->promocion?->fraccionamientos->sortBy('nombre')->values() ?? collect();
    }

    /** Los abonos del periodo, sin filtrar por anulados. */
    private function base(): Builder
    {
        return AbonoCapital::query()
            ->where('promocion_id', $this->promocionId ?? 0)
            ->when($this->desde !== '', fn ($q) => $q->whereDate('created_at', '>=', $this->desde))
            ->when($this->hasta !== '', fn ($q) => $q->whereDate('created_at', '<=', $this->hasta))
            ->when($this->fraccionamientoId, fn ($q) => $q->whereHas(
                'contrato.lote',
                fn ($l) => $l->where('fraccionamiento_id', $this->fraccionamientoId)
            ));
    }

    private function lista(): Builder
    {
        return $this->base()
            ->when(! $this->verAnulados, fn ($q) => $q->whereNull('anulado_at'))
            ->with([
                // El recibo de un abono anulado está borrado, pero su folio sirve.
                'recibo' => fn ($q) => $q->withTrashed()->select('id', 'uuid', 'folio', 'fecha', 'deleted_at'),
                'contrato:id,uuid,folio_contrato,cliente_id,lote_id',
                'contrato.cliente:id,nombres,apellidos',
                'contrato.lote:id,fraccionamiento_id,manzana,lote',
                'contrato.lote.fraccionamiento:id,nombre',
                'capturadoPor:id,name',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    public function getAbonosProperty()
    {
        return $this->lista()->paginate(25);
    }

    /** Se cuentan personas, no contratos: quien tiene dos lotes es un cliente. */
    public function getResumenProperty(): array
    {
        $abonos = $this->vigentes();

        return [
            'usos' => $abonos->count(),
            'clientes' => $abonos->pluck('contrato.cliente_id')->unique()->count(),
            'abonado' => (float) $abonos->sum('monto_abono'),
            'bonificado' => (float) $abonos->sum('monto_bonificacion'),
            'anulados' => $this->base()->whereNotNull('anulado_at')->count(),
        ];
    }

    /** Usos, efectivo y bonificación de cada fraccionamiento, del que más abonó al que menos. */
    public function getPorFraccionamientoProperty(): Collection
    {
        return $this->vigentes()
            ->groupBy(fn (AbonoCapital $a) => $a->contrato?->lote?->fraccionamiento?->nombre ?? 'Sin fraccionamiento')
            ->map(fn (Collection $abonos, string $nombre) => [
                'nombre' => $nombre,
                'usos' => $abonos->count(),
                'clientes' => $abonos->pluck('contrato.cliente_id')->unique()->count(),
                'abonado' => (float) $abonos->sum('monto_abono'),
                'bonificado' => (float) $abonos->sum('monto_bonificacion'),
            ])
            ->sortByDesc('abonado')
            ->values();
    }

    /** Los que cuentan para los totales; una promoción de temporada son pocos cientos. */
    private function vigentes(): Collection
    {
        return $this->vigentes ??= $this->base()
            ->whereNull('anulado_at')
            ->with(['contrato:id,cliente_id,lote_id', 'contrato.lote:id,fraccionamiento_id', 'contrato.lote.fraccionamiento:id,nombre'])
            ->get(['id', 'contrato_id', 'monto_abono', 'monto_bonificacion']);
    }

    public function exportExcel()
    {
        $nombre = $this->promocion?->nombre ?? 'Adelanta y gana';

        return Excel::download(
            new AdelantaYGanaExport(
                abonos: $this->lista()->get(),
                titulo: mb_strtoupper($nombre).' · '.$this->desde.' a '.$this->hasta,
            ),
            'adelanta_y_gana_'.$this->desde.'_a_'.$this->hasta.'.xlsx'
        );
    }

    public function render()
    {
        return view('livewire.reports.adelanta-y-gana-report')
            ->layout('layouts.app');
    }
}
