<div class="max-w-6xl mx-auto p-4 sm:p-6 space-y-4">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-2xl font-black">Formas de pago</h1>
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
               placeholder="Buscar...">
    </div>

    @php
        $cols = [
            ['key'=>'nombre','label'=>'Nombre','sortable'=>true],
            ['key'=>'requiere_cuenta','label'=>'Requiere cuenta','sortable'=>true,'type'=>'bool'],
            ['key'=>'activa','label'=>'Activa','sortable'=>true,'type'=>'bool'],
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
                        <button
                            type="button"
                            wire:click="sort('{{ $col['key'] }}')"
                            class="inline-flex items-center gap-2 font-bold hover:underline"
                        >
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
                <tr class="border-t" wire:key="fp-row-{{ $it->id }}">
                    @foreach($cols as $col)
                        @php $val = data_get($it, $col['key']); @endphp
                        <td class="p-3">
                            @if(($col['type'] ?? null) === 'bool')
                                <span class="px-2 py-1 rounded-full text-xs font-bold {{ $val ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                                    {{ $val ? 'Sí' : 'No' }}
                                </span>
                            @else
                                <span class="font-semibold text-gray-900">{{ $val ?? '—' }}</span>
                            @endif
                        </td>
                    @endforeach

                    <td class="p-3 text-right whitespace-nowrap">
                        <button wire:click="editar({{ $it->id }})"
                                type="button"
                                class="px-3 py-1 rounded-lg border hover:bg-gray-50">
                            Editar
                        </button>

                        <button
                            type="button"
                            class="px-3 py-1 rounded-lg border text-red-600 hover:bg-red-50"
                            x-data
                            x-on:click="if(confirm('¿Eliminar este registro?')) { $wire.eliminar({{ $it->id }}) }"
                        >
                            Eliminar
                        </button>
                    </td>
                </tr>
            @empty
                <tr>
                    <td class="p-4 text-gray-500" colspan="{{ count($cols) + 1 }}">
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
            <div class="rounded-2xl border bg-white p-4" wire:key="fp-card-{{ $it->id }}">

                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="font-black leading-tight break-words">
                            {{ $it->nombre ?? '—' }}
                        </div>

                        <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                            <span class="text-gray-500">Requiere cuenta:</span>
                            <span class="px-2 py-1 rounded-full text-xs font-bold {{ $it->requiere_cuenta ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                                {{ $it->requiere_cuenta ? 'Sí' : 'No' }}
                            </span>
                        </div>
                    </div>

                    <div class="shrink-0 text-right">
                        <div class="text-xs text-gray-500">Activa</div>
                        <span class="inline-flex px-2 py-1 rounded-full text-xs font-bold {{ $it->activa ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                            {{ $it->activa ? 'Sí' : 'No' }}
                        </span>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-2">
                    <button wire:click="editar({{ $it->id }})"
                            type="button"
                            class="w-full px-4 py-2 rounded-xl border font-semibold hover:bg-gray-50">
                        Editar
                    </button>

                    <button
                        type="button"
                        class="w-full px-4 py-2 rounded-xl border text-red-600 font-semibold hover:bg-red-50"
                        x-data
                        x-on:click="if(confirm('¿Eliminar este registro?')) { $wire.eliminar({{ $it->id }}) }"
                    >
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
                <div class="md:col-span-2">
                    <x-label value="Nombre" />
                    <x-input type="text" class="w-full" wire:model.defer="nombre" />
                    @error('nombre') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" wire:model.defer="requiere_cuenta" class="rounded">
                        <span class="text-sm text-gray-700 font-semibold">Requiere cuenta</span>
                    </label>
                    @error('requiere_cuenta') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" wire:model.defer="activa" class="rounded">
                        <span class="text-sm text-gray-700 font-semibold">Activa</span>
                    </label>
                    @error('activa') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
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
