{{--
    Cuenta a la que entró el dinero: primero las del propietario contable del
    terreno, con la opción de ver las de todos si el cliente depositó a otra.
--}}
@php
    $opciones = $this->cuentasParaElegir($cuentas, $propietarioId, $seleccionada);
    $nombrePropietario = $propietarioId ? ($propietarios[$propietarioId] ?? null) : null;
    $tieneCuentas = $propietarioId && $cuentas->contains(fn ($c) => (int) $c->propietario_id === $propietarioId);
    $elegida = $seleccionada ? $cuentas->firstWhere('id', (int) $seleccionada) : null;
    $deOtro = $elegida && $propietarioId && (int) $elegida->propietario_id !== $propietarioId;

    // Con varias dueñas, agrupadas y la del terreno primero.
    $grupos = $opciones
        ->groupBy(fn ($c) => (int) $c->propietario_id)
        ->sortBy(fn ($grupo, $id) => $id === $propietarioId ? '' : ($propietarios[$id] ?? 'zz'));
@endphp

<select class="{{ $clase }}" wire:model.live="{{ $modelo }}">
    <option value="">Selecciona…</option>
    @if ($grupos->count() > 1)
        @foreach ($grupos as $id => $grupo)
            <optgroup label="{{ $propietarios[$id] ?? 'Sin propietario' }}{{ $id === $propietarioId ? ' (de este terreno)' : '' }}">
                @foreach ($grupo as $c)
                    <option value="{{ $c->id }}">{{ $c->alias }}{{ $c->banco ? ' - '.$c->banco : '' }}</option>
                @endforeach
            </optgroup>
        @endforeach
    @else
        @foreach ($opciones as $c)
            <option value="{{ $c->id }}">{{ $c->alias }}{{ $c->banco ? ' - '.$c->banco : '' }}</option>
        @endforeach
    @endif
</select>

@if ($nombrePropietario)
    <div class="mt-1 text-xs text-gray-500">
        @if (! $tieneCuentas)
            {{ $nombrePropietario }} no tiene cuentas activas; se muestran las de todos.
        @elseif ($this->verTodasLasCuentas)
            Todas las cuentas activas.
            <button type="button" wire:click="$set('verTodasLasCuentas', false)" class="underline hover:text-gray-800">
                Solo las de {{ $nombrePropietario }}
            </button>
        @else
            Cuentas de {{ $nombrePropietario }}.
            <button type="button" wire:click="$set('verTodasLasCuentas', true)" class="underline hover:text-gray-800">
                Ver todas las activas
            </button>
        @endif
    </div>
@endif

@if ($deOtro)
    <div class="mt-1 text-xs font-semibold text-amber-700">
        ⚠ Esta cuenta es de {{ $propietarios[(int) $elegida->propietario_id] ?? 'otro propietario' }}, no de {{ $nombrePropietario }}. Confirma que el cliente depositó ahí.
    </div>
@endif
