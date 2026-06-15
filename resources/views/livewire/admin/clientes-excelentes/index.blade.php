<div class="max-w-6xl mx-auto p-4 sm:p-6 space-y-4 sm:space-y-6">
    <div class="flex items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black">Clientes excelentes</h1>
            <p class="text-gray-500">Detecta clientes que adelantan pagos y califica elegibilidad para condonación.</p>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="p-4 rounded-2xl border bg-white grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <div>
            <label class="text-xs text-gray-500">Meses a evaluar</label>
            <input type="number" min="1" class="w-full rounded-xl border p-2" wire:model.live="meses">
        </div>

        <div>
            <label class="text-xs text-gray-500">Score mínimo</label>
            <input type="number" class="w-full rounded-xl border p-2" wire:model.live="minScore">
        </div>

        <div class="sm:col-span-2 flex items-end">
            <label class="inline-flex items-center gap-2 text-sm font-semibold">
                <input type="checkbox" class="rounded" wire:model.live="soloElegibles">
                Solo elegibles (sin recargos y sin pagos tarde)
            </label>
        </div>
    </div>

    {{-- =========================
        TABLA (md+)
    ========================== --}}
    <div class="hidden md:block overflow-x-auto rounded-2xl border bg-white">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr class="text-left">
                    <th class="p-3">Cliente</th>
                    <th class="p-3">Contrato</th>
                    <th class="p-3">Score</th>
                    <th class="p-3">Adelantadas</th>
                    <th class="p-3">Prom. adelanto</th>
                    <th class="p-3">En gracia</th>
                    <th class="p-3">Tarde</th>
                    <th class="p-3">Recargos</th>
                    <th class="p-3 text-right">Acción</th>
                </tr>
            </thead>

            <tbody>
                @forelse($rows as $r)
                    @php
                        $score = (int)($r->score ?? 0);
                        $badge =
                            $score >= 30 ? 'bg-green-50 border-green-200 text-green-700' :
                            ($score >= 20 ? 'bg-amber-50 border-amber-200 text-amber-700' :
                            'bg-gray-50 border-gray-200 text-gray-700');
                    @endphp

                    <tr class="border-t" wire:key="cx-row-{{ $r->contrato_uuid }}">
                        <td class="p-3 font-semibold">{{ $r->cliente_nombre }}</td>
                        <td class="p-3">{{ $r->folio_contrato }}</td>
                        <td class="p-3">
                            <span class="px-2 py-1 rounded-lg border text-xs font-black {{ $badge }}">
                                {{ $score }}
                            </span>
                        </td>
                        <td class="p-3">{{ (int)($r->pagadas_adelantadas ?? 0) }}</td>
                        <td class="p-3">{{ number_format((float)($r->adelanto_promedio_dias ?? 0), 1) }} días</td>
                        <td class="p-3">{{ (int)($r->pagadas_en_gracia ?? 0) }}</td>
                        <td class="p-3">{{ (int)($r->pagadas_tarde ?? 0) }}</td>
                        <td class="p-3">{{ (int)($r->recargos_pagados ?? 0) }}</td>
                        <td class="p-3 text-right">
                            <a class="px-3 py-1.5 rounded-xl bg-black text-white font-semibold"
                               href="{{ route('admin.clientes-excelentes.contrato', ['contrato' => $r->contrato_uuid]) }}">
                                Ver / condonar
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td class="p-6 text-gray-500" colspan="9">Sin resultados con esos filtros.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- =========================
        CARDS (mobile)
    ========================== --}}
    <div class="md:hidden space-y-3">
        @forelse($rows as $r)
            @php
                $score = (int)($r->score ?? 0);
                $badge =
                    $score >= 30 ? 'bg-green-50 border-green-200 text-green-700' :
                    ($score >= 20 ? 'bg-amber-50 border-amber-200 text-amber-700' :
                    'bg-gray-50 border-gray-200 text-gray-700');
            @endphp

            <div class="rounded-2xl border bg-white p-4" wire:key="cx-card-{{ $r->contrato_uuid }}">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="font-black leading-tight break-words">
                            {{ $r->cliente_nombre }}
                        </div>
                        <div class="mt-1 text-xs text-gray-500">
                            Contrato: <span class="font-semibold text-gray-800">{{ $r->folio_contrato }}</span>
                        </div>
                    </div>

                    <div class="shrink-0 text-right">
                        <div class="text-xs text-gray-500">Score</div>
                        <span class="inline-flex px-2 py-1 rounded-lg border text-xs font-black {{ $badge }}">
                            {{ $score }}
                        </span>
                    </div>
                </div>

                <div class="mt-3 grid grid-cols-2 gap-2 text-sm">
                    <div>
                        <div class="text-xs text-gray-500">Adelantadas</div>
                        <div class="font-black">{{ (int)($r->pagadas_adelantadas ?? 0) }}</div>
                    </div>

                    <div class="text-right">
                        <div class="text-xs text-gray-500">Prom. adelanto</div>
                        <div class="font-black">{{ number_format((float)($r->adelanto_promedio_dias ?? 0), 1) }} días</div>
                    </div>

                    <div>
                        <div class="text-xs text-gray-500">En gracia</div>
                        <div class="font-semibold">{{ (int)($r->pagadas_en_gracia ?? 0) }}</div>
                    </div>

                    <div class="text-right">
                        <div class="text-xs text-gray-500">Tarde</div>
                        <div class="font-semibold">{{ (int)($r->pagadas_tarde ?? 0) }}</div>
                    </div>

                    <div class="col-span-2">
                        <div class="text-xs text-gray-500">Recargos pagados</div>
                        <div class="font-semibold">{{ (int)($r->recargos_pagados ?? 0) }}</div>
                    </div>
                </div>

                <div class="mt-4">
                    <a class="block w-full text-center px-4 py-2 rounded-xl bg-black text-white font-semibold"
                       href="{{ route('admin.clientes-excelentes.contrato', ['contrato' => $r->contrato_uuid]) }}">
                        Ver / condonar
                    </a>
                </div>
            </div>
        @empty
            <div class="rounded-2xl border bg-white p-6 text-gray-500 text-center">
                Sin resultados con esos filtros.
            </div>
        @endforelse
    </div>

    {{-- ✅ Paginación: Desktop links / Mobile botones --}}
    <div class="mt-2">
        {{-- Desktop --}}
        <div class="hidden md:block overflow-x-auto">
            <div class="min-w-max">
                {{ $rows->onEachSide(1)->links() }}
            </div>
        </div>

        {{-- Mobile --}}
        <div class="md:hidden flex items-center justify-between gap-2">
            <button
                type="button"
                wire:click="previousPage"
                @disabled($rows->onFirstPage())
                class="px-3 py-2 rounded-xl border font-semibold disabled:opacity-40 disabled:cursor-not-allowed"
            >
                ← Anterior
            </button>

            <div class="text-xs text-gray-600 font-semibold text-center">
                Página {{ $rows->currentPage() }} / {{ $rows->lastPage() }}
            </div>

            <button
                type="button"
                wire:click="nextPage"
                @disabled(! $rows->hasMorePages())
                class="px-3 py-2 rounded-xl border font-semibold disabled:opacity-40 disabled:cursor-not-allowed"
            >
                Siguiente →
            </button>
        </div>
    </div>

</div>
