<div class="max-w-7xl mx-auto p-4 space-y-6">

    {{-- Header --}}
    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-3">
        <div>
            <h1 class="text-2xl font-black">Movimientos de cuentas bancarias</h1>
            <p class="text-gray-600">
                Filtra por propietario, una o varias cuentas y rango de fechas para ver los pagos capturados.
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('reportes.index') }}"
                class="rounded-xl border px-4 py-2 hover:bg-gray-50">
                ← Volver
            </a>

            <button wire:click="clearFilters"
                type="button"
                class="rounded-xl border px-4 py-2 hover:bg-gray-50">
                Limpiar
            </button>

            <button wire:click="exportExcel"
                type="button"
                class="rounded-xl bg-black text-white px-4 py-2 font-semibold hover:opacity-90">
                Exportar Excel
            </button>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="bg-white rounded-2xl border p-4">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">

            {{-- Propietario --}}
            <div>
                <label class="text-sm text-gray-600">Propietario</label>
                <select wire:model.live="propietarioId"
                    class="block w-full rounded-xl border px-3 py-2">
                    <option value="">Todos</option>
                    @foreach($this->propietarios as $p)
                        <option value="{{ $p->id }}">
                            {{ $p->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Cuentas bancarias con checkbox --}}
            <div
                class="md:col-span-1"
                x-data="{ open: false }"
                @click.away="open = false">
                <label class="text-sm text-gray-600">Cuentas bancarias</label>

                <button
                    type="button"
                    @click="open = !open"
                    class="mt-1 w-full h-[42px] rounded-xl border border-gray-300 focus:border-black focus:ring-black px-3 text-left bg-white flex items-center justify-between shadow-sm">
                    <span class="truncate">
                        @if(count($cuentaBancariaIds) === 0)
                            Seleccionar cuentas
                        @elseif(count($cuentaBancariaIds) === 1)
                            1 cuenta seleccionada
                        @else
                            {{ count($cuentaBancariaIds) }} cuentas seleccionadas
                        @endif
                    </span>

                    <svg class="w-4 h-4 text-gray-500 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </button>

                <div
                    x-show="open"
                    x-transition
                    class="relative z-20"
                    style="display: none;">
                    <div class="absolute mt-2 w-full rounded-2xl border bg-white shadow-xl overflow-hidden">
                        <div class="px-3 py-2 border-b bg-gray-50 flex items-center justify-between">
                            <div class="text-sm font-semibold text-gray-700">
                                Selecciona una o varias cuentas
                            </div>

                            @if(count($cuentaBancariaIds))
                                <button
                                    type="button"
                                    wire:click="clearCuentas"
                                    class="text-xs font-semibold text-red-600 hover:text-red-700">
                                    Limpiar
                                </button>
                            @endif
                        </div>

                        <div class="max-h-72 overflow-y-auto p-2 space-y-1">
                            @forelse($this->cuentas as $c)
                                <label class="flex items-start gap-3 rounded-xl px-3 py-2 hover:bg-gray-50 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        wire:model.live="cuentaBancariaIds"
                                        value="{{ $c->id }}"
                                        class="mt-1 rounded border-gray-300 text-black focus:ring-black">

                                    <div class="min-w-0">
                                        <div class="font-semibold text-sm text-gray-900 truncate">
                                            {{ $c->alias }}
                                            @unless($c->activa)
                                                <span class="text-red-600 font-bold">[INACTIVA]</span>
                                            @endunless
                                        </div>


                                    </div>
                                </label>
                            @empty
                                <div class="px-3 py-6 text-sm text-gray-500 text-center">
                                    No hay cuentas disponibles con ese propietario.
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            {{-- Desde --}}
            <div>
                <label class="text-sm text-gray-600">Desde</label>
                <input type="date"
                    wire:model.live="desde"
                    class="block w-full rounded-xl border px-3 py-2">
            </div>

            {{-- Hasta --}}
            <div>
                <label class="text-sm text-gray-600">Hasta</label>
                <input type="date"
                    wire:model.live="hasta"
                    class="block w-full rounded-xl border px-3 py-2">
            </div>

        </div>

        {{-- Chips de cuentas seleccionadas --}}
        @if($this->cuentasSeleccionadas->count())
            <div class="mt-4">
                <div class="text-xs uppercase tracking-wide text-gray-500 mb-2">
                    Cuentas seleccionadas
                </div>

                <div class="flex flex-wrap gap-2">
                    @foreach($this->cuentasSeleccionadas as $cuenta)
                        <div class="inline-flex items-center gap-2 rounded-full bg-gray-100 border px-3 py-1.5 text-sm">
                            <span class="font-semibold">{{ $cuenta->alias }}</span>

                            @if($cuenta->banco)
                                <span class="text-gray-500">· {{ $cuenta->banco }}</span>
                            @endif

                            @if(!$cuenta->activa)
                                <span class="text-red-600 font-semibold">Inactiva</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    {{-- Resumen --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

        <div class="bg-white rounded-2xl border p-4">
            <div class="text-xs uppercase tracking-wide text-gray-500">
                Total del rango
            </div>
            <div class="text-2xl font-black mt-1">
                ${{ number_format($this->total, 2) }}
            </div>
            <div class="text-sm text-gray-600 mt-1">
                {{ \Carbon\Carbon::parse($desde)->format('d/m/Y') }}
                —
                {{ \Carbon\Carbon::parse($hasta)->format('d/m/Y') }}
            </div>
        </div>

        <div class="bg-white rounded-2xl border p-4">
            <div class="text-xs uppercase tracking-wide text-gray-500">
                Movimientos
            </div>
            <div class="text-2xl font-black mt-1">
                {{ $this->movimientos->total() }}
            </div>
            <div class="text-sm text-gray-600 mt-1">
                Pagos encontrados
            </div>
        </div>

        <div class="bg-white rounded-2xl border p-4">
            <div class="text-xs uppercase tracking-wide text-gray-500">
                Promedio
            </div>
            <div class="text-2xl font-black mt-1">
                ${{ number_format(
                    $this->movimientos->total()
                        ? ($this->total / $this->movimientos->total())
                        : 0,
                    2
                ) }}
            </div>
            <div class="text-sm text-gray-600 mt-1">
                Por movimiento
            </div>
        </div>

    </div>

    {{-- Tabla --}}
    <div class="bg-white rounded-2xl border overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50">
                    <tr class="text-left">
                        <th class="px-4 py-3">Fecha</th>
                        <th class="px-4 py-3">Folio</th>
                        <th class="px-4 py-3">Cliente</th>
                        <th class="px-4 py-3">Contrato / Lote</th>
                        <th class="px-4 py-3">Concepto</th>
                        <th class="px-4 py-3">Forma</th>
                        <th class="px-4 py-3">Cuenta</th>
                        <th class="px-4 py-3 text-right">Monto</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($this->movimientos as $r)

                        @php
                            $recibo = $r->recibo;

                            $cliente = trim(($recibo?->cliente?->nombres ?? '') . ' ' . ($recibo?->cliente?->apellidos ?? ''));
                            $finca = $recibo?->contrato?->lote?->fraccionamiento?->nombre ?? '—';
                            $manzana = $recibo?->contrato?->lote?->manzana ?? '—';
                            $lote = $recibo?->contrato?->lote?->lote ?? '—';

                            $cuenta = trim(
                                ($r->cuentaBancaria?->alias ?? '')
                                . (($r->cuentaBancaria?->banco ?? '') ? ' · ' . $r->cuentaBancaria->banco : '')
                                . (($r->cuentaBancaria?->numero ?? '') ? ' · ' . $r->cuentaBancaria->numero : '')
                            );

                            if ($cuenta === '') {
                                $cuenta = '—';
                            }
                        @endphp

                        <tr class="border-t hover:bg-gray-50">
                            <td class="px-4 py-3 whitespace-nowrap">
                                {{ \Carbon\Carbon::parse($r->created_at)->format('d/m/Y H:i') }}
                            </td>

                            <td class="px-4 py-3 font-semibold whitespace-nowrap">
                                {{ $recibo?->folio ?? '—' }}
                            </td>

                            <td class="px-4 py-3">
                                {{ $cliente ?: '—' }}
                            </td>

                            <td class="px-4 py-3">
                                <div class="font-semibold">
                                    {{ $recibo?->contrato?->folio_contrato ?? '—' }}
                                </div>
                                <div class="text-xs text-gray-500">
                                    {{ $finca }} · MZ {{ $manzana }} · LT {{ $lote }}
                                </div>
                            </td>

                            <td class="px-4 py-3">
                                {{ $recibo?->tipoCobro?->nombre ?? '—' }}
                            </td>

                            <td class="px-4 py-3">
                                {{ $r->formaPago?->nombre ?? '—' }}
                            </td>

                            <td class="px-4 py-3">
                                <div class="font-semibold">
                                    {{ $r->cuentaBancaria?->alias ?? '—' }}
                                </div>
       
                            </td>

                            <td class="px-4 py-3 text-right font-black">
                                ${{ number_format((float) $r->monto, 2) }}
                            </td>
                        </tr>

                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-10 text-center text-gray-500">
                                No hay movimientos con esos filtros.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4 border-t">
            {{ $this->movimientos->links() }}
        </div>
    </div>

</div>