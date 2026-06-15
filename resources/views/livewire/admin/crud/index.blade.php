<div class="max-w-6xl mx-auto p-4">
    <div class="flex items-center justify-between gap-3 mb-4">
        <div>
            <h1 class="text-2xl font-black">{{ $title }}</h1>
            <p class="text-gray-500 text-sm">Mantenimiento del catálogo.</p>
        </div>

        <button wire:click="crear"
                type="button"
                class="px-4 py-2 rounded-xl bg-black text-white font-bold">
            + Nuevo
        </button>
    </div>

    {{-- Buscar: debounce para no refrescar cada tecla --}}
    <div class="mb-3">
        <input type="text"
               wire:model.debounce.350ms="q"
               class="w-full rounded-xl border-gray-300"
               placeholder="Buscar...">
    </div>

    <div class="overflow-x-auto bg-white rounded-2xl border">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50">
            <tr>
                @foreach($columns as $col)
                    <th class="text-left p-3">
                        @if(($col['sortable'] ?? false) === true)
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
                        @else
                            <span class="font-bold">{{ $col['label'] }}</span>
                        @endif
                    </th>
                @endforeach
                <th class="text-right p-3 font-bold">Acciones</th>
            </tr>
            </thead>

            <tbody>
            @forelse($items as $it)
                <tr class="border-t">
                    @foreach($columns as $col)
                        <td class="p-3">
                            @php $val = data_get($it, $col['key']); @endphp

                            @if(($col['type'] ?? null) === 'bool')
                                {{ $val ? 'Sí' : 'No' }}
                            @else
                                {{ $val ?? '—' }}
                            @endif
                        </td>
                    @endforeach

                    <td class="p-3 text-right whitespace-nowrap">
                        <button wire:click="editar({{ $it->id }})"
                                type="button"
                                class="px-3 py-1 rounded-lg border">
                            Editar
                        </button>

                        <button
                            type="button"
                            class="px-3 py-1 rounded-lg border text-red-600"
                            x-data
                            x-on:click="if(confirm('¿Eliminar este registro?')) { $wire.eliminar({{ $it->id }}) }"
                        >
                            Eliminar
                        </button>
                    </td>
                </tr>
            @empty
                <tr>
                    <td class="p-4 text-gray-500" colspan="{{ count($columns) + 1 }}">
                        Sin registros.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">
        {{ $items->links() }}
    </div>

    <x-dialog-modal wire:model="modal">
        <x-slot name="title">
            {{ $editId ? 'Editar' : 'Nuevo' }}
        </x-slot>

        <x-slot name="content">
            <div class="grid md:grid-cols-2 gap-4">
                @foreach($fields as $key => $cfg)
                    <div class="{{ ($cfg['span'] ?? 1) === 2 ? 'md:col-span-2' : '' }}">
                        <x-label :value="$cfg['label'] ?? $key" />

                        @php $type = $cfg['type'] ?? 'text'; @endphp

                        {{-- ✅ SELECT FIX DEFINITIVO: wire:ignore + manda (key,value) --}}
                        @if($type === 'select')
                            <div
                                wire:ignore
                                x-data="{
                                    key: @js($key),
                                    init() {
                                        const select = this.$refs.sel;

                                        // Valor inicial desde Livewire
                                        select.value = @this.get('form.' + this.key) ?? '';

                                        // Al cambiar, mandamos key + value al componente
                                        select.addEventListener('change', () => {
                                            @this.call('onSelectChanged', this.key, select.value);
                                        });

                                        // Cuando se abre editar/crear, volvemos a aplicar valores
                                        Livewire.on('sync-selects', () => {
                                            select.value = @this.get('form.' + this.key) ?? '';
                                        });
                                    }
                                }"
                                x-init="init()"
                            >
                                <select x-ref="sel" class="w-full rounded-xl border-gray-300">
                                    <option value="">-- Seleccionar --</option>
                                    @foreach($this->getOptions($key) as $optVal => $optLabel)
                                        <option value="{{ $optVal }}">{{ $optLabel }}</option>
                                    @endforeach
                                </select>
                            </div>

                        {{-- TEXTAREA: defer --}}
                        @elseif($type === 'textarea')
                            <textarea
                                class="w-full rounded-xl border-gray-300"
                                rows="3"
                                wire:model.defer="form.{{ $key }}"
                            ></textarea>

                        {{-- CHECKBOX: defer --}}
                        @elseif($type === 'checkbox')
                            <div class="flex items-center gap-2 mt-2">
                                <input
                                    type="checkbox"
                                    wire:model.defer="form.{{ $key }}"
                                    wire:change="onToggleChanged('{{ $key }}')"
                                >
                                <span class="text-sm text-gray-700">{{ $cfg['help'] ?? ' ' }}</span>
                            </div>

                        {{-- NUMBER: defer + blur --}}
                        @elseif($type === 'number')
                            <x-input
                                type="number"
                                step="{{ $cfg['step'] ?? '1' }}"
                                class="w-full"
                                wire:model.defer="form.{{ $key }}"
                                wire:blur="onNumberBlur('{{ $key }}')"
                            />

                        {{-- DATE: defer + change --}}
                        @elseif($type === 'date')
                            <x-input
                                type="date"
                                class="w-full"
                                wire:model.defer="form.{{ $key }}"
                                wire:change="onDateChanged('{{ $key }}')"
                            />

                        {{-- TEXT: defer --}}
                        @else
                            <x-input type="text" class="w-full" wire:model.defer="form.{{ $key }}" />
                        @endif

                        @error("form.$key")
                            <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                        @enderror

                        @if(!empty($cfg['hint']))
                            <p class="text-xs text-gray-500 mt-1">{{ $cfg['hint'] }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </x-slot>

        <x-slot name="footer">
            <button type="button"
                    wire:click="$set('modal', false)"
                    class="px-4 py-2 rounded-xl border">
                Cancelar
            </button>

            <button type="button"
                    wire:click="guardar"
                    class="px-4 py-2 rounded-xl bg-black text-white font-bold">
                Guardar
            </button>
        </x-slot>
    </x-dialog-modal>
</div>
