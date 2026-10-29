@php
    use App\Livewire\Reports\WorkloadReport;
    use App\Services\Reportes\CanalRecibo;
@endphp
<div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-600">
    @foreach (CanalRecibo::NOMBRES as $clave => $nombre)
        <span class="inline-flex items-center gap-1.5">
            <span class="inline-block h-2.5 w-2.5 rounded-sm" style="background: {{ WorkloadReport::COLORES[$clave] }};"></span>
            {{ $nombre }}
        </span>
    @endforeach
</div>
