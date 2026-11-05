<?php

namespace App\Livewire\Dashboard;

use App\Models\FelicitacionCumpleanos;
use App\Services\Clientes\CumpleanosClientes;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * Los clientes que cumplen años hoy, para felicitarlos por WhatsApp, y los
 * de la semana que viene. El correo lo manda solo el comando de las 9:00.
 */
class Cumpleaneros extends Component
{
    public const HORA_CORREO = 9;

    private const DIAS_PROXIMOS = 7;

    public function getHoyProperty(): Collection
    {
        $hoy = now();
        $cumpleaneros = app(CumpleanosClientes::class)->cumplenEl($hoy);

        $felicitaciones = FelicitacionCumpleanos::query()
            ->with('usuario:id,name')
            ->where('anio', $hoy->year)
            ->whereIn('cliente_id', $cumpleaneros->pluck('cliente.id'))
            ->get()
            ->groupBy('cliente_id');

        return $cumpleaneros->map(function (array $c) use ($felicitaciones) {
            $suyas = $felicitaciones->get($c['cliente']->id, collect())->keyBy('canal');

            return $c + [
                'enlace' => CumpleanosClientes::enlaceWhatsapp($c['cliente']),
                'tiene_correo' => (bool) filter_var(trim((string) $c['cliente']->correo), FILTER_VALIDATE_EMAIL),
                'correo' => $suyas->get(FelicitacionCumpleanos::CORREO),
                'whatsapp' => $suyas->get(FelicitacionCumpleanos::WHATSAPP),
            ];
        });
    }

    public function getProximosProperty(): Collection
    {
        $cumplenAnios = app(CumpleanosClientes::class);

        return collect(range(1, self::DIAS_PROXIMOS))
            ->flatMap(function (int $dias) use ($cumplenAnios) {
                $dia = now()->addDays($dias);

                return $cumplenAnios->cumplenEl($dia)->map(fn (array $c) => $c + ['dia' => $dia]);
            });
    }

    /** Al abrir WhatsApp queda dicho quién lo felicitó, para que no le escriban dos. */
    public function marcarFelicitado(int $clienteId): void
    {
        if (! $this->hoy->contains(fn (array $c) => $c['cliente']->id === $clienteId)) {
            return;
        }

        FelicitacionCumpleanos::firstOrCreate(
            ['cliente_id' => $clienteId, 'anio' => now()->year, 'canal' => FelicitacionCumpleanos::WHATSAPP],
            ['user_id' => auth()->id()]
        );
    }

    public static function fechaCorta(Carbon $dia): string
    {
        return ['', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'][$dia->dayOfWeekIso].' '.$dia->format('d/m');
    }

    public function render()
    {
        return view('livewire.dashboard.cumpleaneros');
    }
}
