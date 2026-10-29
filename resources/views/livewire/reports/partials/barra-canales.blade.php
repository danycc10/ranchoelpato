{{-- Una barra apilada: oficina abajo, WhatsApp arriba. --}}
@php
    use App\Livewire\Reports\WorkloadReport;
    use App\Services\Reportes\CanalRecibo;

    $oficina = $barra[CanalRecibo::OFICINA];
    $whatsapp = $barra[CanalRecibo::WHATSAPP];
    $total = $oficina + $whatsapp;
@endphp
<div class="flex-1 h-full flex flex-col justify-end items-center"
     title="{{ $titulo }} — {{ number_format($total) }} recibos{{ $total ? ': '.number_format($oficina).' en oficina, '.number_format($whatsapp).' por WhatsApp' : '' }}">
    @if ($etiqueta && $total > 0)
        <span class="text-xs text-gray-700 mb-1">{{ $total }}</span>
    @endif
    @if ($total > 0)
        <div class="w-full {{ $ancho }} flex flex-col gap-[2px]" style="height: {{ $total / $maximo * $escala }}%;">
            @if ($whatsapp > 0)
                <div class="rounded-t" style="flex: {{ $whatsapp }} 1 0px; background: {{ WorkloadReport::COLORES[CanalRecibo::WHATSAPP] }};"></div>
            @endif
            @if ($oficina > 0)
                <div class="{{ $whatsapp > 0 ? '' : 'rounded-t' }}" style="flex: {{ $oficina }} 1 0px; background: {{ WorkloadReport::COLORES[CanalRecibo::OFICINA] }};"></div>
            @endif
        </div>
    @endif
</div>
