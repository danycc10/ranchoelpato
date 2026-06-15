<div class="max-w-7xl mx-auto p-4 space-y-6">

    {{-- HEADER --}}
    <div class="-mx-4 px-4 py-3 bg-white border-b">
        <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-3">

            {{-- TITULO --}}
            <div class="min-w-0">
                <h1 class="text-2xl font-black">Pagos por cliente</h1>
                <p class="text-gray-600">Historial + resumen de contratos</p>

                {{-- LOADER GLOBAL --}}
                <div wire:loading class="mt-2 text-xs text-gray-500 flex items-center gap-2">
                    <span class="inline-block h-2 w-2 rounded-full bg-gray-400 animate-pulse"></span>
                    Cargando…
                </div>
            </div>

            {{-- ACCIONES + FILTROS --}}
            <div class="flex flex-col md:flex-row gap-3 md:items-end w-full md:w-auto">

                {{-- Exportar + Limpiar --}}
                <div class="flex gap-2 w-full md:w-auto">
                    <button
                        wire:click="exportExcel"
                        wire:loading.attr="disabled"
                        class="w-full md:w-auto rounded-xl bg-black text-white px-4 py-2 hover:opacity-90 disabled:opacity-60 disabled:cursor-not-allowed">
                        <span wire:loading.remove>Exportar Excel</span>
                        <span wire:loading>Exportando…</span>
                    </button>

                    <button
                        type="button"
                        wire:click="clearFilters"
                        wire:loading.attr="disabled"
                        class="w-full md:w-auto rounded-xl border bg-white px-4 py-2 hover:bg-gray-50 disabled:opacity-60 disabled:cursor-not-allowed">
                        Limpiar
                    </button>
                </div>

                {{-- BUSCADOR CLIENTE --}}
                <div x-data="{ open: false }" class="relative w-full md:w-96">
                    <label class="text-sm text-gray-600">Cliente</label>

                    <div class="flex gap-2">
                        <div class="relative flex-1">
                            <input
                                type="text"
                                wire:model.live.debounce.300ms="searchCliente"
                                @focus="open = true"
                                @input="open = true"
                                @keydown.escape="open = false"
                                @click.away="open = false"
                                placeholder="Buscar cliente..."
                                class="w-full rounded-xl border bg-white px-3 py-2 pr-10 focus:outline-none focus:ring-2 focus:ring-black/10">

                            <div class="absolute inset-y-0 right-3 flex items-center text-gray-400 pointer-events-none">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M12.9 14.32a8 8 0 111.414-1.414l3.387 3.386a1 1 0 01-1.414 1.415l-3.387-3.387zM14 8a6 6 0 11-12 0 6 6 0 0112 0z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </div>

                        @if(!empty($clienteId ?? null))
                        <button
                            type="button"
                            wire:click="clearCliente"
                            wire:loading.attr="disabled"
                            class="rounded-xl border bg-white px-3 py-2 hover:bg-gray-50 disabled:opacity-60">
                            Cambiar
                        </button>
                        @endif
                    </div>

                    {{-- Dropdown --}}
                    <div x-show="open" x-transition
                        class="absolute z-50 mt-1 w-full bg-white border rounded-xl shadow max-h-72 overflow-auto">

                        @if(strlen($searchCliente ?? '') < 3)
                            <div class="px-3 py-2 text-sm text-gray-500">
                            Escribe al menos 3 caracteres…
                    </div>
                    @else
                    @forelse($clientesFiltrados as $c)
                    <button type="button"
                        wire:click="selectCliente({{ $c->id }})"
                        @click="open = false"
                        class="block w-full text-left px-3 py-2 hover:bg-gray-100">
                        <div class="font-semibold">
                            {{ $c->nombres }} {{ $c->apellidos }}
                        </div>
                        @if(!empty($c->telefono ?? null))
                        <div class="text-xs text-gray-500">{{ $c->telefono }}</div>
                        @endif
                    </button>
                    @empty
                    <div class="px-3 py-2 text-sm text-gray-500">Sin resultados</div>
                    @endforelse
                    @endif
                </div>

                @if(!empty($clienteSeleccionadoNombre ?? null))
                <div class="mt-2">
                    <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full border bg-gray-50 text-sm">
                        <span class="font-semibold">Seleccionado:</span>
                        <span class="text-gray-700">{{ $clienteSeleccionadoNombre }}</span>
                    </span>
                </div>
                @endif
            </div>

            {{-- FILTRO LOTE --}}
            <div class="w-full md:w-[28rem]">
                <label class="text-sm text-gray-600">Finca / Lote</label>
                <select wire:model.live="contratoId"
                    class="w-full rounded-xl border bg-white px-3 py-2 focus:outline-none focus:ring-2 focus:ring-black/10">
                    <option value="">Todos</option>
                    @foreach($contratosCliente as $ct)
                    <option value="{{ $ct->id }}">
                        {{ $ct->finca }} — Lote {{ $ct->lote }} — {{ strtoupper($ct->estatus) }}
                    </option>
                    @endforeach
                </select>
            </div>

        </div>
    </div>
</div>

{{-- INFO ARRIBA --}}
@if($loteInfo)
<div class="rounded-2xl border p-4 bg-white shadow-sm">
    <div class="font-black">Información del contrato seleccionado</div>
    <div class="text-sm text-gray-700 mt-1 flex flex-wrap gap-x-3 gap-y-1">
        <span><span class="font-semibold">Finca:</span> {{ $loteInfo->finca }}</span>
        <span class="text-gray-400">•</span>
        <span><span class="font-semibold">Lote:</span> {{ $loteInfo->lote }}</span>
    </div>
</div>
@endif

{{-- RESUMEN --}}
<div class="rounded-2xl border p-4 space-y-4 bg-white shadow-sm">
    <div class="flex items-center justify-between gap-3">
        <div class="font-bold">Resumen de contratos</div>
    </div>

    <div class="overflow-auto max-h-[420px] rounded-xl border bg-white">
        <table class="min-w-full text-sm bg-white">
            <thead class="sticky top-0 bg-white text-left text-gray-600 border-b">
                <tr>
                    <th class="px-3 py-2">Finca</th>
                    <th class="px-3 py-2">Lote</th>
                    <th class="px-3 py-2">Estatus</th>
                    <th class="px-3 py-2 text-right">Precio</th>
                    <th class="px-3 py-2 text-right">Enganche</th>
                    <th class="px-3 py-2 text-right">Abonado</th>
                    <th class="px-3 py-2 text-right">Restante</th>
                    <th class="px-3 py-2 text-right">Saldo actual</th>
                    <th class="px-3 py-2">Último pago</th>
                </tr>
            </thead>

            <tbody class="bg-white [&>tr:nth-child(even)]:bg-gray-50 [&>tr:hover]:bg-gray-100">
                @forelse($contratosResumen as $c)
                @php
                $estatusLower = strtolower((string) $c->estatus);
                $badgeContrato = match(true) {
                str_contains($estatusLower, 'activo') => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                str_contains($estatusLower, 'cancel') => 'bg-rose-50 text-rose-700 border-rose-200',
                str_contains($estatusLower, 'mor') => 'bg-amber-50 text-amber-700 border-amber-200',
                str_contains($estatusLower, 'liquid') => 'bg-sky-50 text-sky-700 border-sky-200',
                default => 'bg-gray-50 text-gray-700 border-gray-200',
                };
                @endphp

                <tr class="border-b">
                    <td class="px-3 py-2 whitespace-nowrap font-medium">{{ $c->finca }}</td>
                    <td class="px-3 py-2 whitespace-nowrap">{{ $c->lote }}</td>
                    <td class="px-3 py-2">
                        <span class="inline-flex items-center px-2 py-1 rounded-full border text-xs font-semibold {{ $badgeContrato }}">
                            {{ strtoupper($c->estatus) }}
                        </span>
                    </td>

                    <td class="px-3 py-2 text-right tabular-nums font-bold">
                        ${{ number_format($c->precio_total, 2) }}
                    </td>
                    <td class="px-3 py-2 text-right tabular-nums">
                        ${{ number_format($c->enganche, 2) }}
                    </td>
                    <td class="px-3 py-2 text-right tabular-nums font-bold">
                        ${{ number_format($c->total_pagado, 2) }}
                    </td>
                    <td class="px-3 py-2 text-right tabular-nums font-bold">
                        ${{ number_format($c->saldo_restante_calc, 2) }}
                    </td>
                    <td class="px-3 py-2 text-right tabular-nums">
                        ${{ number_format($c->saldo_actual, 2) }}
                    </td>
                    <td class="px-3 py-2 whitespace-nowrap">
                        {{ $c->ultimo_pago ? \Carbon\Carbon::parse($c->ultimo_pago)->format('d/m/Y H:i') : '—' }}
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="10" class="px-3 py-6 text-gray-500 text-center">
                        Selecciona un cliente para ver el resumen
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- HISTORIAL --}}
<div class="rounded-2xl border p-4 space-y-4 bg-white shadow-sm">
    <div class="flex items-center justify-between gap-3">
        <div class="font-bold">Historial de pagos</div>
        <div class="text-xs text-gray-500">
            {{ ($pagos?->total() ?? 0) > 0 ? 'Da scroll dentro de la tabla para revisar más' : '' }}
        </div>
    </div>

    <div class="overflow-auto max-h-[560px] rounded-xl border bg-white">
        <table class="min-w-full text-sm bg-white">
            <thead class="sticky top-0 bg-white text-left text-gray-600 border-b">
                <tr>
                    <th class="px-3 py-2">Fecha</th>
                    <th class="px-3 py-2">Finca</th>
                    <th class="px-3 py-2">Lote</th>
                    <th class="px-3 py-2">Cuota</th>
                    <th class="px-3 py-2">Recibo</th>
                    <th class="px-3 py-2">Concepto</th>
                    <th class="px-3 py-2">Método</th>
                    <th class="px-3 py-2">Cuenta</th>
                    <th class="px-3 py-2">Referencia</th>
                    <th class="px-3 py-2 text-right">Monto</th>
                </tr>
            </thead>

            <tbody class="bg-white [&>tr:nth-child(even)]:bg-gray-50 [&>tr:hover]:bg-gray-100">
                @forelse($pagos as $p)
                @php
                $recibo = $p->recibo;

                $concepto = strtoupper(trim((string)($recibo?->tipoCobro?->nombre ?? '')));
                $metodoReal = strtoupper(trim((string)($p->formaPago?->nombre ?? '')));
                $metLower = strtolower($metodoReal);

                $cuenta = trim(
                ($p->cuentaBancaria?->alias ?? '')

                );

                if ($cuenta === '') {
                $cuenta = '—';
                }

                $esBanco = $cuenta !== '—';

                $cuotaNum = $recibo?->cuota?->numero
                ?? $recibo?->cuota?->no_cuota
                ?? $recibo?->cuota?->numero_cuota
                ?? $recibo?->cuota_id
                ?? '—';

                $finca = $recibo?->contrato?->lote?->fraccionamiento?->nombre ?? '—';

                $lote = $recibo?->contrato?->lote?->lote
                ?? $recibo?->contrato?->lote?->numero
                ?? $recibo?->contrato?->lote?->num_lote
                ?? '—';

                $badgeMetodo = match(true) {
                str_contains($metLower, 'efect') => 'bg-gray-50 text-gray-700 border-gray-200',
                str_contains($metLower, 'transfer') => 'bg-sky-50 text-sky-700 border-sky-200',
                str_contains($metLower, 'tarje') => 'bg-indigo-50 text-indigo-700 border-indigo-200',
                str_contains($metLower, 'oxxo') => 'bg-amber-50 text-amber-700 border-amber-200',
                default => 'bg-gray-50 text-gray-700 border-gray-200',
                };
                @endphp

                <tr class="border-b">
                    <td class="px-3 py-2 whitespace-nowrap">
                        {{ \Carbon\Carbon::parse($p->created_at)->format('d/m/Y H:i') }}
                    </td>
                    <td class="px-3 py-2 whitespace-nowrap">{{ $finca }}</td>
                    <td class="px-3 py-2 whitespace-nowrap">{{ $lote }}</td>
                    <td class="px-3 py-2 font-semibold whitespace-nowrap">
                        {{ $cuotaNum }}
                    </td>
                    <td class="px-3 py-2 whitespace-nowrap">
                        {{ $recibo?->folio ?? '—' }}
                    </td>
                    <td class="px-3 py-2 whitespace-nowrap">
                        {{ $concepto !== '' ? $concepto : '—' }}
                    </td>
                    <td class="px-3 py-2 whitespace-nowrap">
                        <span class="inline-flex items-center px-2 py-1 rounded-full border text-xs font-semibold {{ $badgeMetodo }}">
                            {{ $metodoReal !== '' ? $metodoReal : '—' }}
                        </span>
                    </td>
                    <td class="px-3 py-2 whitespace-nowrap">
                        {{ $esBanco ? $cuenta : '—' }}
                    </td>
                    <td class="px-3 py-2 whitespace-nowrap">
                        {{ $p->referencia ?: '—' }}
                    </td>
                    <td class="px-3 py-2 text-right tabular-nums font-black whitespace-nowrap">
                        ${{ number_format($p->monto, 2) }}
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="10" class="px-3 py-6 text-gray-500 text-center">
                        Sin pagos
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 pt-2">
        <div class="text-sm text-gray-600">
            Mostrando {{ $pagos->firstItem() ?? 0 }}–{{ $pagos->lastItem() ?? 0 }}
            de {{ $pagos->total() ?? 0 }}
        </div>

        <div class="overflow-auto">
            {{ $pagos->links() }}
        </div>
    </div>
</div>
</div>