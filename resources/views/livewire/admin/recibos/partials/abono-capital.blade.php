{{-- Cómo queda el saldo con el abono y si le toca la bonificación de «Adelanta y gana». --}}
@php
    $bonificacion = (float) $abono['bonificacion'];
    $promocion = $abono['promocion'];
@endphp

<div class="mt-4 rounded-2xl border p-4 {{ $bonificacion > 0 ? 'border-emerald-200 bg-emerald-50' : 'border-gray-200 bg-gray-50' }}">
    @if($bonificacion > 0)
        <div class="flex items-start justify-between gap-3">
            <div>
                <div class="text-xs font-bold uppercase tracking-wide text-emerald-700">🎁 {{ $promocion->nombre }}</div>
                <div class="mt-1 text-sm text-emerald-900">
                    {{ $abono['bloques'] }} {{ $abono['bloques'] === 1 ? 'bloque' : 'bloques' }} de
                    ${{ number_format((float) $promocion->abono_por_bloque, 0) }} en efectivo
                </div>
            </div>
            <div class="text-right">
                <div class="text-xs text-emerald-700">Bonificación</div>
                <div class="text-2xl font-black text-emerald-800">${{ number_format($bonificacion, 2) }}</div>
            </div>
        </div>
    @elseif($promocion)
        <div class="text-xs font-bold uppercase tracking-wide text-gray-500">{{ $promocion->nombre }}</div>
    @endif

    @if($abono['aviso'])
        <div class="mt-2 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
            {{ $abono['aviso'] }}
        </div>
    @endif

    <div class="mt-3 grid grid-cols-1 sm:grid-cols-3 gap-2 text-sm">
        <div>
            <div class="text-xs text-gray-500">Saldo actual</div>
            <div class="font-bold">${{ number_format((float) $abono['saldo_actual'], 2) }}</div>
        </div>
        <div>
            <div class="text-xs text-gray-500">Baja el saldo</div>
            <div class="font-bold">${{ number_format($monto + $bonificacion, 2) }}</div>
        </div>
        <div>
            <div class="text-xs text-gray-500">Saldo nuevo</div>
            <div class="font-black">${{ number_format((float) $abono['saldo_nuevo'], 2) }}</div>
        </div>
    </div>

    <p class="mt-2 text-xs text-gray-500">
        Se quitan pagos del final del calendario: termina antes y sigue pagando lo mismo.
        Se puede abonar hasta ${{ number_format((float) $abono['maximo'], 2) }}.
    </p>
</div>
