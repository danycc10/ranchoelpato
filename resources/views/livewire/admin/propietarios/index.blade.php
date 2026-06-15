<div class="max-w-7xl mx-auto p-4 sm:p-6 space-y-4">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-2xl font-black">Propietarios</h1>
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
            placeholder="Buscar... (nombre, nombre legal, CURP, teléfono, correo)">
    </div>

    @php
    $cols = [
    ['key'=>'nombre','label'=>'Nombre','sortable'=>true],
    ['key'=>'nombre_legal','label'=>'Nombre legal','sortable'=>true],
    ['key'=>'curp','label'=>'CURP','sortable'=>true],
    ['key'=>'telefono','label'=>'Teléfono','sortable'=>true],
    ['key'=>'correo','label'=>'Correo','sortable'=>true],
    ];
    @endphp

    {{-- TABLA --}}
    <div class="hidden md:block overflow-x-auto bg-white rounded-2xl border">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    @foreach($cols as $col)
                    <th class="text-left p-3">
                        <button
                            type="button"
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

                    <th class="text-left p-3 font-bold">INE</th>
                    <th class="text-right p-3 font-bold">Acciones</th>
                </tr>
            </thead>

            <tbody>
                @forelse($items as $it)
                <tr class="border-t" wire:key="prop-row-{{ $it->id }}">
                    <td class="p-3 font-semibold text-gray-900">{{ $it->nombre }}</td>
                    <td class="p-3">{{ $it->nombre_legal ?? '—' }}</td>
                    <td class="p-3">{{ $it->curp ?? '—' }}</td>
                    <td class="p-3">{{ $it->telefono ?? '—' }}</td>
                    <td class="p-3">{{ $it->correo ?? '—' }}</td>

                    <td class="p-3">
                        <div class="flex flex-wrap gap-1">
                            <span class="px-2 py-1 rounded-lg text-xs font-semibold {{ $it->ine_frente ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                                Frente {{ $it->ine_frente ? '✓' : '—' }}
                            </span>
                            <span class="px-2 py-1 rounded-lg text-xs font-semibold {{ $it->ine_reverso ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                                Reverso {{ $it->ine_reverso ? '✓' : '—' }}
                            </span>
                        </div>
                    </td>

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
                            x-on:click="if(confirm('¿Eliminar este registro?')) { $wire.eliminar({{ $it->id }}) }">
                            Eliminar
                        </button>
                    </td>
                </tr>
                @empty
                <tr>
                    <td class="p-4 text-gray-500" colspan="7">
                        Sin registros.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- MOBILE --}}
    <div class="md:hidden space-y-3">
        @forelse($items as $it)
        <div class="rounded-2xl border bg-white p-4" wire:key="prop-card-{{ $it->id }}">
            <div class="min-w-0">
                <div class="font-black leading-tight break-words">
                    {{ $it->nombre }}
                </div>

                <div class="mt-1 text-sm text-gray-600 break-words">
                    {{ $it->nombre_legal ?: 'Sin nombre legal' }}
                </div>

                <div class="mt-3 text-sm text-gray-700 space-y-1">
                    <div class="flex gap-2">
                        <span class="text-gray-500 shrink-0">CURP:</span>
                        <span class="font-semibold break-words">{{ $it->curp ?? '—' }}</span>
                    </div>

                    <div class="flex gap-2">
                        <span class="text-gray-500 shrink-0">Tel:</span>
                        <span class="font-semibold break-words">{{ $it->telefono ?? '—' }}</span>
                    </div>

                    <div class="flex gap-2">
                        <span class="text-gray-500 shrink-0">Correo:</span>
                        <span class="font-semibold break-words">{{ $it->correo ?? '—' }}</span>
                    </div>

                    <div class="flex flex-wrap gap-1 pt-2">
                        <span class="px-2 py-1 rounded-lg text-xs font-semibold {{ $it->ine_frente ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                            INE frente {{ $it->ine_frente ? '✓' : '—' }}
                        </span>
                        <span class="px-2 py-1 rounded-lg text-xs font-semibold {{ $it->ine_reverso ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                            INE reverso {{ $it->ine_reverso ? '✓' : '—' }}
                        </span>
                    </div>
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
    <div class="mt-2">
        <div class="hidden md:block overflow-x-auto">
            <div class="min-w-max">
                {{ $items->onEachSide(1)->links() }}
            </div>
        </div>

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

    {{-- MODAL --}}
    <x-dialog-modal wire:model="modal">
        <x-slot name="title">
            {{ $editId ? 'Editar propietario' : 'Nuevo propietario' }}
        </x-slot>

        <x-slot name="content">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                <div>
                    <x-label value="Nombre" />
                    <x-input type="text" class="w-full" wire:model.defer="nombre" />
                    @error('nombre') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <x-label value="Nombre legal" />
                    <x-input type="text" class="w-full" wire:model.defer="nombre_legal" />
                    @error('nombre_legal') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <x-label value="CURP" />
                    <x-input type="text" class="w-full uppercase" wire:model.defer="curp" />
                    @error('curp') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <x-label value="Teléfono" />
                    <x-input type="text" class="w-full" wire:model.defer="telefono" />
                    @error('telefono') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2">
                    <x-label value="Correo" />
                    <x-input type="text" class="w-full" wire:model.defer="correo" />
                    @error('correo') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <x-label value="INE frente" />
                    <input type="file"
                        wire:model="ineFrenteFile"
                        class="block w-full text-sm border-gray-300 rounded-xl">

                    <p class="text-xs text-gray-500 mt-1">JPG, PNG, WEBP o PDF. Máximo 10 MB.</p>
                    @error('ineFrenteFile') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror

                    @if($this->ineFrentePreview)
                    <div class="mt-3 rounded-xl border bg-gray-50 p-3"
                        wire:key="preview-prop-ine-frente-{{ md5(($ine_frente ?? '') . ($this->ineFrentePreview ?? '')) }}">
                        @if($this->ineFrenteEsPdf)
                        <a href="{{ $this->ineFrentePreview }}"
                            target="_blank"
                            class="flex items-center justify-between gap-3 rounded-xl border bg-white px-3 py-3 hover:bg-gray-50">
                            <div>
                                <p class="text-sm font-semibold text-gray-800">Vista previa disponible</p>
                                <p class="text-xs text-gray-500">PDF cargado. Haz clic para abrirlo.</p>
                            </div>
                            <span class="px-2 py-1 rounded-lg bg-red-100 text-red-700 text-xs font-bold">PDF</span>
                        </a>
                        @else
                        <a href="{{ $this->ineFrentePreview }}" target="_blank" class="block">
                            <img src="{{ $this->ineFrentePreview }}"
                                alt="Vista previa INE frente"
                                class="w-full h-48 rounded-xl border object-contain bg-white">
                        </a>
                        @endif
                    </div>
                    @endif

                    @if($ineFrenteFile)
                    <div class="mt-2 text-xs text-green-700 font-semibold">
                        Nuevo archivo seleccionado: {{ $ineFrenteFile->getClientOriginalName() }}
                    </div>
                    @elseif($ine_frente)
                    <div class="mt-2 flex items-center justify-between gap-2 rounded-xl bg-gray-50 border px-3 py-2">
                        <span class="text-xs text-gray-700 break-all">Documento actual cargado</span>
                        <button type="button"
                            wire:click="eliminarDocumento('ine_frente')"
                            class="text-xs font-bold text-red-600">
                            Quitar
                        </button>
                    </div>
                    @endif
                </div>

                <div>
                    <x-label value="INE reverso" />
                    <input type="file"
                        wire:model="ineReversoFile"
                        class="block w-full text-sm border-gray-300 rounded-xl">

                    <p class="text-xs text-gray-500 mt-1">JPG, PNG, WEBP o PDF. Máximo 10 MB.</p>
                    @error('ineReversoFile') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror

                    @if($this->ineReversoPreview)
                    <div class="mt-3 rounded-xl border bg-gray-50 p-3"
                        wire:key="preview-prop-ine-reverso-{{ md5(($ine_reverso ?? '') . ($this->ineReversoPreview ?? '')) }}">
                        @if($this->ineReversoEsPdf)
                        <a href="{{ $this->ineReversoPreview }}"
                            target="_blank"
                            class="flex items-center justify-between gap-3 rounded-xl border bg-white px-3 py-3 hover:bg-gray-50">
                            <div>
                                <p class="text-sm font-semibold text-gray-800">Vista previa disponible</p>
                                <p class="text-xs text-gray-500">PDF cargado. Haz clic para abrirlo.</p>
                            </div>
                            <span class="px-2 py-1 rounded-lg bg-red-100 text-red-700 text-xs font-bold">PDF</span>
                        </a>
                        @else
                        <a href="{{ $this->ineReversoPreview }}" target="_blank" class="block">
                            <img src="{{ $this->ineReversoPreview }}"
                                alt="Vista previa INE reverso"
                                class="w-full h-48 rounded-xl border object-contain bg-white">
                        </a>
                        @endif
                    </div>
                    @endif

                    @if($ineReversoFile)
                    <div class="mt-2 text-xs text-green-700 font-semibold">
                        Nuevo archivo seleccionado: {{ $ineReversoFile->getClientOriginalName() }}
                    </div>
                    @elseif($ine_reverso)
                    <div class="mt-2 flex items-center justify-between gap-2 rounded-xl bg-gray-50 border px-3 py-2">
                        <span class="text-xs text-gray-700 break-all">Documento actual cargado</span>
                        <button type="button"
                            wire:click="eliminarDocumento('ine_reverso')"
                            class="text-xs font-bold text-red-600">
                            Quitar
                        </button>
                    </div>
                    @endif
                </div>

                <div class="md:col-span-2">
                    <x-label value="Notas" />
                    <textarea class="w-full rounded-xl border-gray-300"
                        rows="4"
                        wire:model.defer="notas"></textarea>
                    @error('notas') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
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
                    wire:loading.attr="disabled"
                    wire:target="guardar,ineFrenteFile,ineReversoFile"
                    class="w-full sm:w-auto px-4 py-2 rounded-xl bg-black text-white font-bold disabled:opacity-50 disabled:cursor-not-allowed">
                    <span wire:loading.remove wire:target="guardar,ineFrenteFile,ineReversoFile">
                        Guardar
                    </span>

                    <span wire:loading wire:target="guardar,ineFrenteFile,ineReversoFile">
                        Guardando...
                    </span>
                </button>
            </div>
        </x-slot>
    </x-dialog-modal>

</div>