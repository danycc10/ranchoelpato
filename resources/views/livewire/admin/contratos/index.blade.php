<div class="max-w-6xl mx-auto p-4 sm:p-6 space-y-4">

{{-- Header --}}
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
    <div>
        <h1 class="text-2xl font-black">Contratos</h1>
        <p class="text-gray-500 text-sm">Lista de contratos.</p>
    </div>

    <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
        <button
            type="button"
            wire:click="exportarExcel"
            class="rounded-xl bg-black text-white px-4 py-2 hover:opacity-90">
            Exportar Excel
        </button>

        <a href="{{ route('admin.contratos.create') }}"
            class="w-full sm:w-auto text-center px-4 py-2 rounded-xl bg-black text-white font-bold">
            + Nuevo
        </a>
    </div>
</div>

    {{-- ✅ FILTROS (responsive) --}}
    <div class="bg-white rounded-2xl border p-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">

            <div class="sm:col-span-2 lg:col-span-2">
                <label class="text-xs text-gray-500 font-semibold">Búsqueda libre</label>
                <input type="text"
                    wire:model.live="q"
                    class="w-full rounded-xl border-gray-300"
                    placeholder="Buscar... (folio, cliente, lote, fracc, estatus)">
            </div>

            <div>
                <label class="text-xs text-gray-500 font-semibold">Estatus</label>
                <select class="w-full rounded-xl border-gray-300" wire:model.live="estatusFilter">
                    <option value="">Todos</option>
                    <option value="activo">Activo</option>
                    <option value="cancelado">Cancelado</option>
                    <option value="liquidado">Liquidado</option>
                    <option value="moroso">Moroso</option>
                        <option value="donacion">Donacion</option>
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
                <input type="date"
                    wire:model.live="inicioDesde"
                    class="w-full rounded-xl border-gray-300">
            </div>

            <div>
                <label class="text-xs text-gray-500 font-semibold">Inicio hasta</label>
                <input type="date"
                    wire:model.live="inicioHasta"
                    class="w-full rounded-xl border-gray-300">
            </div>

            <div>
                <label class="text-xs text-gray-500 font-semibold">Saldo mín</label>
                <input type="number"
                    step="0.01"
                    wire:model.debounce.350ms="saldoMin"
                    class="w-full rounded-xl border-gray-300"
                    placeholder="0">
            </div>

            <div>
                <label class="text-xs text-gray-500 font-semibold">Saldo máx</label>
                <input type="number"
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

            <button type="button"
                wire:click="limpiarFiltros"
                class="w-full sm:w-auto px-3 py-2 rounded-xl border font-semibold hover:bg-gray-50">
                Limpiar filtros
            </button>
        </div>
    </div>

    {{-- =========================
TABLA (md+)
========================== --}}
    <div class="hidden md:block overflow-x-auto bg-white rounded-2xl border">
        <table class="min-w-full text-sm">

            <thead class="bg-gray-50">

                @php
                $cols = [
                ['key'=>'fecha_inicio','label'=>'Inicio'],
                ['key'=>'frecuencia','label'=>'Frecuencia'],
                ['key'=>'estatus','label'=>'Estatus'],
                ['key'=>'saldo_actual','label'=>'Saldo'],
                ];
                @endphp

                <tr>

                    <th class="text-left p-3 font-bold">Nombre</th>
                    <th class="text-left p-3 font-bold">Lote</th>
                    <th class="text-left p-3 font-bold">Fraccionamiento</th>

                    @foreach($cols as $col)

                    <th class="text-left p-3">

                        <button type="button"
                            wire:click="sort('{{ $col['key'] }}')"
                            class="inline-flex items-center gap-2 font-bold hover:underline">

                            {{ $col['label'] }}

                            @if(($sortBy ?? '') === $col['key'])
                            <span class="text-xs text-gray-500">
                                {{ ($sortDir ?? 'asc') === 'asc' ? '▲' : '▼' }}
                            </span>
                            @else
                            <span class="text-xs text-gray-300">↕</span>
                            @endif

                        </button>

                    </th>

                    @endforeach

                    <th class="text-right p-3 font-bold">Acciones</th>

                </tr>
            </thead>


            <tbody>

                @forelse($items as $it)

                <tr class="border-t hover:bg-gray-50" wire:key="contrato-row-{{ $it->id }}">

                    <td class="p-3 font-semibold">
                        {{ $it->cliente?->nombre_completo ?? '—' }}
                    </td>

                    <td class="p-3">
                        {{ $it->lote?->lote ?? '—' }}
                    </td>

                    <td class="p-3">
                        {{ $it->lote?->fraccionamiento?->nombre ?? '—' }}
                    </td>

                    <td class="p-3">
                        {{ optional($it->fecha_inicio)->format('d/m/Y') }}
                    </td>

                    <td class="p-3">
                        {{ $it->frecuencia === 'semanal' ? 'Semanal' : 'Mensual' }}
                    </td>

                    <td class="p-3">

                        <span class="px-2 py-1 rounded-full text-xs font-bold
            {{ $it->estatus === 'activo'
            ? 'bg-green-100 text-green-800'
            : 'bg-gray-100 text-gray-700' }}">

                            {{ ucfirst($it->estatus) }}

                        </span>

                    </td>

                    <td class="p-3 font-bold">
                        ${{ number_format((float)$it->saldo_actual,2) }}
                    </td>

                    <td class="p-3 text-right whitespace-nowrap">

                        <a class="px-3 py-1 rounded-lg border hover:bg-gray-50"
                            href="{{ route('admin.contratos.show',$it->uuid) }}">
                            Ver
                        </a>
    @can('contratos.editar')
                        <a class="px-3 py-1 rounded-lg border hover:bg-gray-50"
   href="{{ route('admin.contratos.edit', $it->uuid) }}">
  Editar
</a>
     @endcan
                    </td>

                </tr>

                @empty

                <tr>
                    <td class="p-4 text-gray-500" colspan="8">
                        Sin registros.
                    </td>
                </tr>

                @endforelse

            </tbody>

        </table>
    </div>

    {{-- =========================
        CARDS (mobile)
    ========================== --}}
    <div class="md:hidden space-y-3">
        @forelse($items as $it)
        @php
        $cliente = $it->cliente?->nombre_completo ?? '—';
        $loteClave = $it->lote?->clave ?? '—';
        $fracc = $it->lote?->fraccionamiento?->nombre ?? '—';

        $frecuencia = $it->frecuencia === 'semanal' ? 'Semanal' : 'Mensual';
        $inicio = optional($it->fecha_inicio)->format('d/m/Y') ?? '—';
        $saldo = '$'.number_format((float)$it->saldo_actual, 2);
        $estatus = ucfirst($it->estatus);
        @endphp

        <div class="rounded-2xl border bg-white p-4 overflow-hidden" wire:key="contrato-card-{{ $it->id }}">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
 

                    <div class="mt-2 text-sm text-gray-700">
                        <span class="text-gray-500">Cliente:</span>
                        <span class="font-semibold break-words">{{ $cliente }}</span>
                    </div>
                </div>
                

                <div class="shrink-0 text-right">
                    <div class="text-xs text-gray-500">Saldo</div>
                    <div class="font-black">{{ $saldo }}</div>

                    <div class="mt-2 text-xs text-gray-500">Estatus</div>
                    <span class="inline-flex px-2 py-1 rounded-full text-xs font-bold
                            {{ $it->estatus === 'activo' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700' }}">
                        {{ $estatus }}
                    </span>
                </div>
            </div>

            <div class="mt-3 grid grid-cols-1 gap-2 text-sm">
                
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
                    <div class="font-semibold text-right min-w-0 break-words">{{ $loteClave }}</div>
                </div>

                <div class="flex items-start justify-between gap-3">
                    <div class="text-gray-500 shrink-0">Fracc</div>
                    <div class="font-semibold text-right min-w-0 break-words">{{ $fracc }}</div>
                </div>
            </div>

            <div class="mt-4">
                <a href="{{ route('admin.contratos.show', $it->uuid) }}"
                    class="block w-full text-center px-4 py-2 rounded-xl border font-semibold hover:bg-gray-50">
                    Ver
                </a>
            </div>
        </div>

        @empty
        <div class="rounded-2xl border bg-white p-6 text-gray-500 text-center">
            Sin registros.
        </div>
        @endforelse
    </div>

    {{-- Paginación --}}
    <div class="mt-4">
        {{ $items->links() }}
    </div>

</div>