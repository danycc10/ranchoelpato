{{-- La tabla de la circular: un botón por cantidad; la que no esté se escribe abajo. --}}
<div class="mb-4">
    <div class="flex flex-wrap items-baseline justify-between gap-2">
        <div class="text-sm font-black">¿Cuánto va a abonar?</div>
        <div class="text-xs text-gray-500">
            Por cada ${{ number_format((float) $promocion->abono_por_bloque, 0) }}
            se bonifican ${{ number_format((float) $promocion->bonificacion_por_bloque, 0) }}
        </div>
    </div>

    <div class="mt-2 grid grid-cols-2 sm:grid-cols-4 gap-2">
        @foreach($opciones as $opcion)
            @php $elegida = abs($monto - $opcion['abono']) < 0.005; @endphp

            <button
                type="button"
                wire:key="opcion-abono-{{ (int) $opcion['abono'] }}"
                wire:click="elegirAbono({{ $opcion['abono'] }})"
                @disabled(! $opcion['disponible'])
                class="rounded-xl border p-3 text-left transition
                    {{ $elegida ? 'border-emerald-600 bg-emerald-600 text-white shadow' : 'bg-white hover:border-emerald-400 hover:bg-emerald-50' }}
                    disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-white disabled:hover:border-gray-200"
            >
                <div class="text-lg font-black leading-tight">${{ number_format($opcion['abono'], 0) }}</div>
                <div class="mt-1 text-xs {{ $elegida ? 'text-emerald-50' : 'text-emerald-700' }} font-semibold">
                    + ${{ number_format($opcion['bonificacion'], 0) }} de bonificación
                </div>
                <div class="text-[11px] {{ $elegida ? 'text-emerald-100' : 'text-gray-500' }}">
                    Baja ${{ number_format($opcion['reduccion'], 0) }}
                </div>
            </button>
        @endforeach
    </div>

    @if(collect($opciones)->contains(fn ($o) => ! $o['disponible']))
        <p class="mt-2 text-xs text-gray-500">Las cantidades en gris pasan de lo que le queda por pagar.</p>
    @endif
</div>
