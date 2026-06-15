<?php

namespace App\Livewire\Admin\Logs;

use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Spatie\Activitylog\Models\Activity;

class Index extends Component
{
    use WithPagination, AuthorizesRequests;

    protected string $paginationTheme = 'tailwind';

    public string $q = '';
    public ?string $logName = null;      // activity_log.log_name
    public ?string $event = null;        // created|updated|deleted|...
    public ?string $from = null;         // YYYY-MM-DD
    public ?string $to = null;           // YYYY-MM-DD
    public ?int $causerId = null;

    public bool $modalDetalle = false;
    public ?int $activityId = null;

    protected $queryString = [
        'q' => ['except' => ''],
        'logName' => ['except' => null],
        'event' => ['except' => null],
        'from' => ['except' => null],
        'to' => ['except' => null],
        'causerId' => ['except' => null],
    ];

    public function mount(): void
    {
        $this->authorize('logs.ver');
    }

    public function updating($name, $value): void
    {
        if (in_array($name, ['q','logName','event','from','to','causerId'], true)) {
            $this->resetPage();
        }
    }

    public function verDetalle(int $id): void
    {
        $this->authorize('logs.detalle');

        $this->activityId = $id;
        $this->modalDetalle = true;
    }

    public function cerrarDetalle(): void
    {
        $this->modalDetalle = false;
        $this->activityId = null;
    }

    public function eliminar(int $id): void
    {
        $this->authorize('logs.eliminar');

        Activity::query()->whereKey($id)->delete();
        $this->dispatch('toast', type: 'success', message: 'Log eliminado.');
        $this->cerrarDetalle();
    }

    public function limpiarFiltros(): void
    {
        $this->reset(['q','logName','event','from','to','causerId']);
        $this->resetPage();
    }

    public function getActivityProperty(): ?Activity
    {
        if (!$this->activityId) return null;

        return Activity::query()
            ->with(['causer'])
            ->find($this->activityId);
    }

    public function render()
    {
        $term = trim($this->q);

        $logs = Activity::query()
            ->with(['causer'])
            ->when($this->logName, fn($qq) => $qq->where('log_name', $this->logName))
            ->when($this->event, fn($qq) => $qq->where('event', $this->event))
            ->when($this->causerId, fn($qq) => $qq->where('causer_id', $this->causerId))
            ->when($this->from, fn($qq) => $qq->whereDate('created_at', '>=', $this->from))
            ->when($this->to, fn($qq) => $qq->whereDate('created_at', '<=', $this->to))
            ->when($term !== '', function ($qq) use ($term) {
                $qq->where(function ($q) use ($term) {
                    $q->where('description', 'like', "%{$term}%")
                      ->orWhere('event', 'like', "%{$term}%")
                      ->orWhere('log_name', 'like', "%{$term}%")
                      ->orWhere('subject_type', 'like', "%{$term}%")
                      ->orWhereHas('causer', function ($u) use ($term) {
                          $u->where('name', 'like', "%{$term}%")
                            ->orWhere('email', 'like', "%{$term}%");
                      });
                });
            })
            ->latest('id')
            ->paginate(20);

        $logNames = Activity::query()
            ->select('log_name')
            ->whereNotNull('log_name')
            ->groupBy('log_name')
            ->orderBy('log_name')
            ->pluck('log_name');

        $events = Activity::query()
            ->select('event')
            ->whereNotNull('event')
            ->groupBy('event')
            ->orderBy('event')
            ->pluck('event');

        return view('livewire.admin.logs.index', compact('logs', 'logNames', 'events'))
            ->layout('layouts.app');
    }
}