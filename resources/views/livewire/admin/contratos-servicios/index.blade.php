<div class="max-w-6xl mx-auto p-4 sm:p-6 space-y-4">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
        <div>
            <h1 class="text-2xl font-black">Contratos de servicio</h1>
            <p class="text-gray-500">Financiamiento de instalación (agua / electricidad).</p>
        </div>

        <a href="{{ route('admin.contratos-servicios.create') }}"
           class="w-full sm:w-auto text-center px-4 py-2 rounded-xl bg-black text-white font-semibold">
            Crear
        </a>
    </div>

    {{-- Filtros --}}
    <div class="bg-white rounded-2xl border p-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            <input
                wire:model.live.debounce.300ms="q"
                class="border rounded-xl px-3 py-2 w-full"
                placeholder="Buscar (folio, cliente, contrato base)..."
            />

            <select wire:model.live="servicio" class="border rounded-xl px-3 py-2 w-full">
                <option value="">Todos</option>
                <option value="agua">Agua</option>
                <option value="electricidad">Electricidad</option>
            </select>

            <div class="text-sm text-gray-500 flex items-center">
                Tip: puedes buscar por el folio del contrato base.
            </div>
        </div>
    </div>

    {{-- =========================
        TABLA (md+)
    ========================== --}}
    <div class="hidden md:block bg-white border rounded-2xl overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="p-3 text-left">Folio</th>
                    <th class="p-3 text-left">Servicio</th>
                    <th class="p-3 text-left">Cliente</th>
                    <th class="p-3 text-left">Lote</th>
                    <th class="p-3 text-left">Contrato base</th>
                    <th class="p-3 text-right">Saldo</th>
                    <th class="p-3 text-left">Estatus</th>
                    <th class="p-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $c)
                    <tr class="border-t" wire:key="cs-row-{{ $c->id }}">
                        <td class="p-3 font-semibold">{{ $c->folio_contrato }}</td>
                        <td class="p-3">
                            <span class="px-2 py-1 rounded-lg bg-gray-100">
                                {{ $c->servicio_tipo === 'agua' ? 'Agua' : 'Electricidad' }}
                            </span>
                        </td>
                        <td class="p-3">{{ $c->cliente->nombre_completo ?? '-' }}</td>
                        <td class="p-3">{{ $c->lote->clave ?? '-' }}</td>
                        <td class="p-3">{{ $c->contratoBase->folio_contrato ?? '-' }}</td>
                        <td class="p-3 text-right">${{ number_format((float)$c->saldo_actual, 2) }}</td>
                        <td class="p-3">{{ ucfirst($c->estatus) }}</td>
                    <td class="p-3 text-right">
    <div class="flex items-center justify-end gap-3">
        <a class="px-3 py-1 rounded-lg border hover:bg-gray-50" href="{{ route('admin.contratos-servicios.show', $c->uuid) }}">
            Ver
        </a>

        <a class="px-3 py-1 rounded-lg border hover:bg-gray-50"
           href="{{ route('admin.contratos-servicios.edit', $c->uuid) }}">
            Editar
        </a>
    </div>
</td>
                    </tr>
                @empty
                    <tr><td class="p-6 text-center text-gray-500" colspan="8">Sin contratos de servicio.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- =========================
        CARDS (mobile)
    ========================== --}}
    <div class="md:hidden space-y-3">
        @forelse($items as $c)
            @php
                $cliente = $c->cliente->nombre_completo ?? '-';
                $lote = $c->lote->clave ?? '-';
                $base = $c->contratoBase->folio_contrato ?? '-';
                $saldo = '$'.number_format((float)$c->saldo_actual, 2);
                $serv = $c->servicio_tipo === 'agua' ? 'Agua' : 'Electricidad';
            @endphp

            <div class="rounded-2xl border bg-white p-4 overflow-hidden" wire:key="cs-card-{{ $c->id }}">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="text-xs text-gray-500">Folio</div>
                        <div class="font-black truncate">{{ $c->folio_contrato }}</div>

                        <div class="mt-2 inline-flex px-2 py-1 rounded-full text-xs font-bold bg-gray-100 text-gray-700">
                            {{ $serv }}
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
                        <div class="text-gray-500 shrink-0">Lote</div>
                        <div class="font-semibold text-right min-w-0 break-words">{{ $lote }}</div>
                    </div>

                    <div class="flex items-start justify-between gap-3">
                        <div class="text-gray-500 shrink-0">Contrato base</div>
                        {{-- 🔥 anti overflow por folios largos --}}
                        <div class="font-semibold text-right min-w-0 break-all">{{ $base }}</div>
                    </div>

                </div>

            <div class="mt-4 grid grid-cols-2 gap-2">
    <a href="{{ route('admin.contratos-servicios.show', $c->uuid) }}"
       class="block w-full text-center px-4 py-2 rounded-xl border font-semibold hover:bg-gray-50">
        Ver
    </a>

    <a href="{{ route('admin.contratos-servicios.edit', $c->uuid) }}"
       class="block w-full text-center px-4 py-2 rounded-xl bg-black text-white font-semibold hover:opacity-90">
        Editar
    </a>
</div>
            </div>

        @empty
            <div class="rounded-2xl border bg-white p-6 text-gray-500 text-center">
                Sin contratos de servicio.
            </div>
        @endforelse
    </div>

    {{-- ✅ Paginación: Desktop links / Mobile botones --}}
    <div class="mt-2">
        {{-- Desktop --}}
        <div class="hidden md:block overflow-x-auto">
            <div class="min-w-max">
                {{ $items->onEachSide(1)->links() }}
            </div>
        </div>

        {{-- Mobile --}}
        <div class="md:hidden flex items-center justify-between gap-2">
            <button
                type="button"
                wire:click="previousPage"
                @disabled($items->onFirstPage())
                class="px-3 py-2 rounded-xl border font-semibold disabled:opacity-40 disabled:cursor-not-allowed"
            >
                ← Anterior
            </button>

            <div class="text-xs text-gray-600 font-semibold text-center">
                Página {{ $items->currentPage() }} / {{ $items->lastPage() }}
            </div>

            <button
                type="button"
                wire:click="nextPage"
                @disabled(! $items->hasMorePages())
                class="px-3 py-2 rounded-xl border font-semibold disabled:opacity-40 disabled:cursor-not-allowed"
            >
                Siguiente →
            </button>
        </div>
    </div>

</div>
