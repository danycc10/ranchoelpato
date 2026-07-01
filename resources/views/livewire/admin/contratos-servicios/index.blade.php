<div class="max-w-6xl mx-auto p-4 sm:p-6 space-y-4">

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-2xl font-black">Contratos de servicio</h1>
            <p class="text-gray-500 text-sm">Financiamiento de instalacion de agua y electricidad.</p>
        </div>

        <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
            <button
                type="button"
                wire:click="exportarExcel"
                class="rounded-xl bg-black text-white px-4 py-2 font-semibold hover:opacity-90">
                Exportar Excel
            </button>

            @can('contratos_servicios.editar')
                <a href="{{ route('admin.contratos-servicios.create') }}"
                   class="w-full sm:w-auto text-center px-4 py-2 rounded-xl bg-black text-white font-bold">
                    + Nuevo
                </a>
            @endcan
        </div>
    </div>

    <div class="bg-white rounded-2xl border p-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="sm:col-span-2 lg:col-span-2">
                <label class="text-xs text-gray-500 font-semibold">Busqueda libre</label>
                <input
                    type="text"
                    wire:model.live.debounce.300ms="q"
                    class="w-full rounded-xl border-gray-300"
                    placeholder="Buscar por folio, cliente, lote, fracc, base o estatus">
            </div>

            <div>
                <label class="text-xs text-gray-500 font-semibold">Servicio</label>
                <select wire:model.live="servicio" class="w-full rounded-xl border-gray-300">
                    <option value="">Todos</option>
                    <option value="agua">Agua</option>
                    <option value="electricidad">Electricidad</option>
                </select>
            </div>

            <div>
                <label class="text-xs text-gray-500 font-semibold">Estatus</label>
                <select class="w-full rounded-xl border-gray-300" wire:model.live="estatusFilter">
                    <option value="">Todos</option>
                    <option value="activo">Activo</option>
                    <option value="cancelado">Cancelado</option>
                    <option value="liquidado">Liquidado</option>
                    <option value="moroso">Moroso</option>
                </select>
            </div>

            <div>
                <label class="text-xs text-gray-500 font-semibold">Frecuencia</label>
                <select class="w-full rounded-xl border-gray-300" wire:model.live="frecuenciaFilter">
                    <option value="">Todas</option>
                    <option value="semanal">Semanal</option>
                    <option value="mensual">Mensual</option>
                </select>
            </div>

            <div class="sm:col-span-2 lg:col-span-2">
                <label class="text-xs text-gray-500 font-semibold">Fraccionamiento</label>
                <select class="w-full rounded-xl border-gray-300" wire:model.live="fraccionamientoFilter">
                    <option value="">Todos</option>
                    @foreach($fraccionamientos as $f)
                        <option value="{{ $f->id }}">{{ $f->nombre }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="text-xs text-gray-500 font-semibold">Inicio desde</label>
                <input type="date" wire:model.live="inicioDesde" class="w-full rounded-xl border-gray-300">
            </div>

            <div>
                <label class="text-xs text-gray-500 font-semibold">Inicio hasta</label>
                <input type="date" wire:model.live="inicioHasta" class="w-full rounded-xl border-gray-300">
            </div>

            <div>
                <label class="text-xs text-gray-500 font-semibold">Saldo min</label>
                <input
                    type="number"
                    step="0.01"
                    wire:model.debounce.350ms="saldoMin"
                    class="w-full rounded-xl border-gray-300"
                    placeholder="0">
            </div>

            <div>
                <label class="text-xs text-gray-500 font-semibold">Saldo max</label>
                <input
                    type="number"
                    step="0.01"
                    wire:model.debounce.350ms="saldoMax"
                    class="w-full rounded-xl border-gray-300"
                    placeholder="9999999">
            </div>
        </div>

        <div class="mt-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <label class="inline-flex items-center gap-2 text-sm font-semibold">
                <input type="checkbox" class="rounded" wire:model.live="soloConSaldo">
                Solo con saldo pendiente
            </label>

            <button
                type="button"
                wire:click="limpiarFiltros"
                class="w-full sm:w-auto px-3 py-2 rounded-xl border font-semibold hover:bg-gray-50">
                Limpiar filtros
            </button>
        </div>
    </div>

    <div class="hidden md:block bg-white border rounded-2xl overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50">
                @php
                    $cols = [
                        ['key' => 'folio_contrato', 'label' => 'Folio'],
                        ['key' => 'servicio_tipo', 'label' => 'Servicio'],
                        ['key' => 'fecha_inicio', 'label' => 'Inicio'],
                        ['key' => 'frecuencia', 'label' => 'Frecuencia'],
                        ['key' => 'estatus', 'label' => 'Estatus'],
                        ['key' => 'saldo_actual', 'label' => 'Saldo'],
                    ];
                @endphp

                <tr>
                    @foreach($cols as $col)
                        <th class="p-3 text-left">
                            <button
                                type="button"
                                wire:click="sort('{{ $col['key'] }}')"
                                class="inline-flex items-center gap-2 font-bold hover:underline">
                                {{ $col['label'] }}

                                @if(($sortBy ?? '') === $col['key'])
                                    <span class="text-xs text-gray-500">
                                        {{ ($sortDir ?? 'asc') === 'asc' ? 'ASC' : 'DESC' }}
                                    </span>
                                @else
                                    <span class="text-xs text-gray-300">-</span>
                                @endif
                            </button>
                        </th>
                    @endforeach

                    <th class="p-3 text-left">Cliente</th>
                    <th class="p-3 text-left">Lote</th>
                    <th class="p-3 text-left">Contrato base</th>
                    <th class="p-3 text-right">Acciones</th>
                </tr>
            </thead>

            <tbody>
                @forelse($items as $c)
                    @php
                        $servicioLabel = ($c->servicio_tipo ?? '') === 'agua' ? 'Agua' : 'Electricidad';
                        $estatusClase = match($c->estatus) {
                            'activo' => 'bg-green-100 text-green-800',
                            'cancelado' => 'bg-red-100 text-red-800',
                            'liquidado' => 'bg-blue-100 text-blue-800',
                            'moroso' => 'bg-yellow-100 text-yellow-800',
                            default => 'bg-gray-100 text-gray-700',
                        };
                    @endphp

                    <tr class="border-t hover:bg-gray-50" wire:key="cs-row-{{ $c->id }}">
                        <td class="p-3 font-semibold">{{ $c->folio_contrato }}</td>
                        <td class="p-3">
                            <span class="px-2 py-1 rounded-lg bg-gray-100">{{ $servicioLabel }}</span>
                        </td>
                        <td class="p-3">{{ optional($c->fecha_inicio)->format('d/m/Y') }}</td>
                        <td class="p-3">{{ $c->frecuencia === 'semanal' ? 'Semanal' : 'Mensual' }}</td>
                        <td class="p-3">
                            <span class="px-2 py-1 rounded-full text-xs font-bold {{ $estatusClase }}">
                                {{ ucfirst($c->estatus) }}
                            </span>
                        </td>
                        <td class="p-3 font-bold">${{ number_format((float) $c->saldo_actual, 2) }}</td>
                        <td class="p-3">{{ $c->cliente?->nombre_completo ?? '-' }}</td>
                        <td class="p-3">{{ $c->lote?->clave ?? '-' }}</td>
                        <td class="p-3">{{ $c->contratoBase?->folio_contrato ?? '-' }}</td>
                        <td class="p-3 text-right whitespace-nowrap">
                            <a class="px-3 py-1 rounded-lg border hover:bg-gray-50"
                               href="{{ route('admin.contratos-servicios.show', $c->uuid) }}">
                                Ver
                            </a>

                            @can('contratos_servicios.editar')
                                <a class="px-3 py-1 rounded-lg border hover:bg-gray-50"
                                   href="{{ route('admin.contratos-servicios.edit', $c->uuid) }}">
                                    Editar
                                </a>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td class="p-4 text-gray-500" colspan="10">Sin contratos de servicio.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="md:hidden space-y-3">
        @forelse($items as $c)
            @php
                $cliente = $c->cliente?->nombre_completo ?? '-';
                $lote = $c->lote?->clave ?? '-';
                $base = $c->contratoBase?->folio_contrato ?? '-';
                $saldo = '$'.number_format((float) $c->saldo_actual, 2);
                $servicioLabel = ($c->servicio_tipo ?? '') === 'agua' ? 'Agua' : 'Electricidad';
                $inicio = optional($c->fecha_inicio)->format('d/m/Y') ?? '-';
                $frecuencia = $c->frecuencia === 'semanal' ? 'Semanal' : 'Mensual';
            @endphp

            <div class="rounded-2xl border bg-white p-4 overflow-hidden" wire:key="cs-card-{{ $c->id }}">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="text-xs text-gray-500">Folio</div>
                        <div class="font-black break-all">{{ $c->folio_contrato }}</div>

                        <div class="mt-2 inline-flex px-2 py-1 rounded-full text-xs font-bold bg-gray-100 text-gray-700">
                            {{ $servicioLabel }}
                        </div>
                    </div>

                    <div class="shrink-0 text-right">
                        <div class="text-xs text-gray-500">Saldo</div>
                        <div class="font-black">{{ $saldo }}</div>

                        <div class="mt-2 text-xs text-gray-500">Estatus</div>
                        <div class="font-semibold">{{ ucfirst($c->estatus) }}</div>
                    </div>
                </div>

                <div class="mt-3 grid grid-cols-1 gap-2 text-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div class="text-gray-500 shrink-0">Cliente</div>
                        <div class="font-semibold text-right min-w-0 break-words">{{ $cliente }}</div>
                    </div>

                    <div class="flex items-start justify-between gap-3">
                        <div class="text-gray-500 shrink-0">Inicio</div>
                        <div class="font-semibold text-right min-w-0 break-words">{{ $inicio }}</div>
                    </div>

                    <div class="flex items-start justify-between gap-3">
                        <div class="text-gray-500 shrink-0">Frecuencia</div>
                        <div class="font-semibold text-right min-w-0 break-words">{{ $frecuencia }}</div>
                    </div>

                    <div class="flex items-start justify-between gap-3">
                        <div class="text-gray-500 shrink-0">Lote</div>
                        <div class="font-semibold text-right min-w-0 break-words">{{ $lote }}</div>
                    </div>

                    <div class="flex items-start justify-between gap-3">
                        <div class="text-gray-500 shrink-0">Contrato base</div>
                        <div class="font-semibold text-right min-w-0 break-all">{{ $base }}</div>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-2 gap-2">
                    <a href="{{ route('admin.contratos-servicios.show', $c->uuid) }}"
                       class="block w-full text-center px-4 py-2 rounded-xl border font-semibold hover:bg-gray-50">
                        Ver
                    </a>

                    @can('contratos_servicios.editar')
                        <a href="{{ route('admin.contratos-servicios.edit', $c->uuid) }}"
                           class="block w-full text-center px-4 py-2 rounded-xl bg-black text-white font-semibold hover:opacity-90">
                            Editar
                        </a>
                    @endcan
                </div>
            </div>
        @empty
            <div class="rounded-2xl border bg-white p-6 text-gray-500 text-center">
                Sin contratos de servicio.
            </div>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $items->onEachSide(1)->links() }}
    </div>
</div>
