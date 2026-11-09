{{-- Cuántas veces se cobró una promoción de cobranza; las de contrato no llevan. --}}
@if($r->tipo === \App\Models\Promocion::ADELANTA_Y_GANA)
    <div class="font-semibold">{{ number_format($r->usos) }} {{ $r->usos === 1 ? 'uso' : 'usos' }}</div>
    @if($r->usos > 0)
        <div class="text-xs text-gray-600 whitespace-nowrap">
            ${{ number_format((float) $r->abonado, 2) }} abonado ·
            <span class="text-emerald-700">${{ number_format((float) $r->bonificado, 2) }} bonificado</span>
        </div>
    @endif
    @can('reportes.ver')
        <a href="{{ route('reportes.adelanta.y.gana', ['promocion' => $r->id]) }}"
           class="text-xs font-semibold text-blue-700 hover:underline">Ver reporte →</a>
    @endcan
@else
    <span class="text-gray-400">—</span>
@endif
