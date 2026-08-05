<?php

namespace App\Livewire\Admin\OpenAiUsos;

use App\Models\OpenAiUso;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use AuthorizesRequests, WithPagination;

    protected string $paginationTheme = 'tailwind';

    public string $desde = '';

    public string $hasta = '';

    public string $modelo = '';

    protected $queryString = [
        'desde' => ['except' => ''],
        'hasta' => ['except' => ''],
        'modelo' => ['except' => ''],
    ];

    public function mount(): void
    {
        $this->authorize('sistema.ver');
        $this->authorize('logs.ver');
        $this->desde = $this->desde ?: now()->startOfMonth()->toDateString();
        $this->hasta = $this->hasta ?: now()->toDateString();
    }

    public function updated(string $propiedad): void
    {
        if (in_array($propiedad, ['desde', 'hasta', 'modelo'], true)) {
            $this->resetPage();
        }
    }

    public function mesActual(): void
    {
        $this->desde = now()->startOfMonth()->toDateString();
        $this->hasta = now()->toDateString();
        $this->modelo = '';
        $this->resetPage();
    }

    public function render()
    {
        $consulta = OpenAiUso::query()
            ->when($this->desde, fn ($q) => $q->whereDate('consumido_en', '>=', $this->desde))
            ->when($this->hasta, fn ($q) => $q->whereDate('consumido_en', '<=', $this->hasta))
            ->when($this->modelo, fn ($q) => $q->where('modelo', $this->modelo));

        $resumen = (clone $consulta)
            ->selectRaw('COUNT(*) as solicitudes')
            ->selectRaw('COALESCE(SUM(tokens_entrada), 0) as tokens_entrada')
            ->selectRaw('COALESCE(SUM(tokens_entrada_cache), 0) as tokens_cache')
            ->selectRaw('COALESCE(SUM(tokens_salida), 0) as tokens_salida')
            ->selectRaw('COALESCE(SUM(tokens_razonamiento), 0) as tokens_razonamiento')
            ->selectRaw('COALESCE(SUM(tokens_total), 0) as tokens_total')
            ->selectRaw('SUM(costo_estimado_usd) as costo_estimado_usd')
            ->first();

        $porModelo = (clone $consulta)
            ->select('modelo')
            ->selectRaw('COUNT(*) as solicitudes')
            ->selectRaw('COALESCE(SUM(tokens_entrada), 0) as tokens_entrada')
            ->selectRaw('COALESCE(SUM(tokens_entrada_cache), 0) as tokens_cache')
            ->selectRaw('COALESCE(SUM(tokens_salida), 0) as tokens_salida')
            ->selectRaw('COALESCE(SUM(tokens_total), 0) as tokens_total')
            ->selectRaw('SUM(costo_estimado_usd) as costo_estimado_usd')
            ->groupBy('modelo')
            ->orderByDesc('tokens_total')
            ->get();

        $porOperacion = (clone $consulta)
            ->select('operacion')
            ->selectRaw('COUNT(*) as solicitudes')
            ->selectRaw('COALESCE(SUM(tokens_total), 0) as tokens_total')
            ->selectRaw('SUM(costo_estimado_usd) as costo_estimado_usd')
            ->groupBy('operacion')
            ->orderByDesc('tokens_total')
            ->get();

        $recientes = (clone $consulta)
            ->latest('consumido_en')
            ->paginate(15);

        $modelos = OpenAiUso::query()
            ->distinct()
            ->orderBy('modelo')
            ->pluck('modelo');

        return view('livewire.admin.open-ai-usos.index', compact(
            'resumen',
            'porModelo',
            'porOperacion',
            'recientes',
            'modelos',
        ))->layout('layouts.app');
    }
}
