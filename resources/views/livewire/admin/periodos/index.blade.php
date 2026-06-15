<div class="max-w-6xl mx-auto p-4 sm:p-6 space-y-4">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-2xl font-black">Periodos</h1>
            <p class="text-gray-500 text-sm">Mantenimiento del catálogo.</p>
        </div>

        <button wire:click="crear"
                type="button"
                class="w-full sm:w-auto px-4 py-2 rounded-xl bg-black text-white font-bold">
            + Nuevo
        </button>
    </div>

    {{-- Buscar --}}
    <div>
        <input type="text"
               wire:model.debounce.350ms="q"
               class="w-full rounded-xl border-gray-300"
               placeholder="Buscar... (tipo, año, mes, nombre)">
    </div>

    @php
        $cols = [
            ['key'=>'tipo','label'=>'Tipo','sortable'=>true],
            ['key'=>'anio','label'=>'Año','sortable'=>true],
            ['key'=>'mes','label'=>'Mes','sortable'=>true],
            ['key'=>'nombre','label'=>'Nombre','sortable'=>true],
        ];
    @endphp

    {{-- =========================
        TABLA (md+)
    ========================== --}}
    <div class="hidden md:block overflow-x-auto bg-white rounded-2xl border">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50">
            <tr>
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
                <tr class="border-t" wire:key="periodo-row-{{ $it->id }}">
                    <td class="p-3">
                        {{ $it->tipo === 'mensual' ? 'Mensual' : 'Anual' }}
                    </td>
                    <td class="p-3">{{ $it->anio }}</td>
                    <td class="p-3">{{ $it->mes ?? '—' }}</td>
                    <td class="p-3 font-semibold text-gray-900">{{ $it->nombre }}</td>

                    <td class="p-3 text-right whitespace-nowrap">
                        <button wire:click="editar({{ $it->id }})"
                                type="button"
                                class="px-3 py-1 rounded-lg border hover:bg-gray-50">
                            Editar
                        </button>

                        <button type="button"
                                class="px-3 py-1 rounded-lg border text-red-600 hover:bg-red-50"
                                x-data
                                x-on:click="if(confirm('¿Eliminar este registro?')) { $wire.eliminar({{ $it->id }}) }">
                            Eliminar
                        </button>
                    </td>
                </tr>
            @empty
                <tr>
                    <td class="p-4 text-gray-500" colspan="5">
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
            <div class="rounded-2xl border bg-white p-4" wire:key="periodo-card-{{ $it->id }}">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="font-black leading-tight break-words">
                            {{ $it->nombre }}
                        </div>

                        <div class="mt-2 text-sm text-gray-700 space-y-1">
                            <div class="flex gap-2">
                                <span class="text-gray-500 shrink-0">Tipo:</span>
                                <span class="font-semibold">
                                    {{ $it->tipo === 'mensual' ? 'Mensual' : 'Anual' }}
                                </span>
                            </div>

                            <div class="flex gap-2">
                                <span class="text-gray-500 shrink-0">Año:</span>
                                <span class="font-semibold">{{ $it->anio }}</span>
                            </div>

                            <div class="flex gap-2">
                                <span class="text-gray-500 shrink-0">Mes:</span>
                                <span class="font-semibold">{{ $it->mes ?? '—' }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-2">
                    <button wire:click="editar({{ $it->id }})"
                            type="button"
                            class="w-full px-4 py-2 rounded-xl border font-semibold hover:bg-gray-50">
                        Editar
                    </button>

                    <button type="button"
                            class="w-full px-4 py-2 rounded-xl border text-red-600 font-semibold hover:bg-red-50"
                            x-data
                            x-on:click="if(confirm('¿Eliminar este registro?')) { $wire.eliminar({{ $it->id }}) }">
                        Eliminar
                    </button>
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
    {{-- =========================
        MODAL
    ========================== --}}
    <x-dialog-modal wire:model="modal">
        <x-slot name="title">
            {{ $editId ? 'Editar' : 'Nuevo' }}
        </x-slot>

        <x-slot name="content">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <x-label value="Tipo" />
                    <select class="w-full rounded-xl border-gray-300" wire:model="tipo">
                        <option value="mensual">Mensual</option>
                        <option value="anual">Anual</option>
                    </select>
                    @error('tipo') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <x-label value="Año" />
                    <x-input type="number" class="w-full" wire:model.defer="anio" />
                    @error('anio') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <x-label value="Mes (solo mensual)" />
                    <x-input type="number" class="w-full" wire:model.defer="mes" />
                    @error('mes') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2">
                    <x-label value="Nombre" />
                    <x-input type="text" class="w-full" wire:model.defer="nombre" />
                    @error('nombre') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2">
                    <p class="text-xs text-gray-500">
                        Nota: Si el tipo es <b>Anual</b>, el sistema guarda el mes como vacío.
                    </p>
                </div>
            </div>
        </x-slot>

        <x-slot name="footer">
            <div class="grid grid-cols-1 sm:flex sm:justify-end gap-2 w-full">
                <button type="button"
                        wire:click="$set('modal', false)"
                        class="w-full sm:w-auto px-4 py-2 rounded-xl border">
                    Cancelar
                </button>

                <button type="button"
                        wire:click="guardar"
                        class="w-full sm:w-auto px-4 py-2 rounded-xl bg-black text-white font-bold">
                    Guardar
                </button>
            </div>
        </x-slot>
    </x-dialog-modal>

</div>
