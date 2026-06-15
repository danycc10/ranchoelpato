<div class="max-w-6xl mx-auto p-4 sm:p-6 space-y-4">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-2xl font-black">Fraccionamientos</h1>
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
            placeholder="Buscar... (propietario, nombre, ubicación)">
    </div>

    {{-- =========================
        TABLA (md+)
    ========================== --}}
    <div class="hidden md:block overflow-x-auto bg-white rounded-2xl border">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    @php
                    $cols = [
                    ['key'=>'propietario','label'=>'Propietario','sortable'=>true],
                    ['key'=>'nombre','label'=>'Nombre','sortable'=>true],
                    ['key'=>'ubicacion','label'=>'Ubicación','sortable'=>true],
                    ];
                    @endphp

                    <th class="text-left p-3 font-bold">Logo</th>

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

                    <th class="text-left p-3 font-bold">Contrato base</th>
                    <th class="text-right p-3 font-bold">Acciones</th>
                </tr>
            </thead>

            <tbody>
                @forelse($items as $it)
                <tr class="border-t" wire:key="fracc-row-{{ $it->id }}">
                    <td class="p-3">
                        @if($it->logo_url)
                        <img src="{{ $it->logo_url }}"
                            alt="Logo {{ $it->nombre }}"
                            class="w-12 h-12 rounded-lg border object-contain bg-white">
                        @else
                        <span class="text-gray-400 text-xs">—</span>
                        @endif
                    </td>

                    <td class="p-3">{{ $it->propietario?->nombre ?? '—' }}</td>
                    <td class="p-3 font-semibold">{{ $it->nombre }}</td>
                    <td class="p-3">{{ $it->ubicacion ?? '—' }}</td>

                    <td class="p-3">
                        @if($it->contrato_base_path)
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-50 text-emerald-700 text-xs font-semibold border border-emerald-200">
                            DOCX cargado
                        </div>
                        <div class="text-xs text-gray-500 mt-1">
                            {{ $it->contrato_base_nombre }}
                        </div>
                        @else
                        <span class="text-gray-400 text-xs">Sin contrato base</span>
                        @endif
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
                    <td class="p-4 text-gray-500" colspan="6">Sin registros.</td>
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
        <div class="rounded-2xl border bg-white p-4 overflow-hidden" wire:key="fracc-card-{{ $it->id }}">

            <div class="flex items-start gap-3">
                {{-- Logo --}}
                <div class="shrink-0">
                    <div class="w-14 h-14 rounded-xl border bg-white flex items-center justify-center overflow-hidden">
                        @if($it->logo_url)
                        <img src="{{ $it->logo_url }}" alt="Logo {{ $it->nombre }}" class="w-full h-full object-contain">
                        @else
                        <span class="text-xs text-gray-400">Sin logo</span>
                        @endif
                    </div>
                </div>

                {{-- Info --}}
                <div class="min-w-0 flex-1">
                    <div class="font-black leading-tight break-words">
                        {{ $it->nombre }}
                    </div>

                    <div class="mt-1 text-sm text-gray-700 break-words">
                        <span class="text-gray-500">Propietario:</span>
                        <span class="font-semibold">{{ $it->propietario?->nombre ?? '—' }}</span>
                    </div>

                    <div class="mt-1 text-sm text-gray-700 break-words">
                        <span class="text-gray-500">Ubicación:</span>
                        <span class="font-semibold">{{ $it->ubicacion ?? '—' }}</span>
                    </div>

                    <div class="mt-2 text-sm text-gray-700 break-words">
                        <span class="text-gray-500">Contrato base:</span>
                        @if($it->contrato_base_path)
                        <span class="font-semibold text-emerald-700">Cargado</span>
                        <div class="text-xs text-gray-500 mt-1">{{ $it->contrato_base_nombre }}</div>
                        @else
                        <span class="font-semibold text-gray-400">Sin archivo</span>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Acciones --}}
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
                    <x-label value="Propietario" />
                    <select class="w-full rounded-xl border-gray-300"
                        wire:model.defer="propietario_id">
                        <option value="">— Selecciona —</option>
                        @foreach($propietarios as $p)
                        <option value="{{ $p->id }}">{{ $p->nombre }}</option>
                        @endforeach
                    </select>
                    @error('propietario_id') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2">
                    <x-label value="Nombre" />
                    <x-input type="text" class="w-full" wire:model.defer="nombre" />
                    @error('nombre') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2">
                    <x-label value="Ubicación" />
                    <x-input type="text" class="w-full" wire:model.defer="ubicacion" />
                    @error('ubicacion') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Logo --}}
                <div class="md:col-span-2">
                    <x-label value="Logo (opcional)" />

                    <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                        <div class="w-20 h-20 rounded-xl border bg-white flex items-center justify-center overflow-hidden">
                            @if($logo)
                            <img src="{{ $logo->temporaryUrl() }}" class="w-full h-full object-contain" alt="Preview logo">
                            @else
                            @php
                            $current = null;
                            if($editId){
                            $current = \App\Models\Fraccionamiento::find($editId)?->logo_url;
                            }
                            @endphp

                            @if($current)
                            <img src="{{ $current }}" class="w-full h-full object-contain" alt="Logo actual">
                            @else
                            <span class="text-xs text-gray-400">Sin logo</span>
                            @endif
                            @endif
                        </div>

                        <div class="flex-1">
                            <input type="file"
                                class="block w-full text-sm"
                                wire:model="logo"
                                accept="image/png,image/jpeg,image/webp">

                            @error('logo') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror

                            <p class="text-xs text-gray-500 mt-1">
                                JPG/PNG/WEBP · Máx 2MB · Se guardará como WEBP.
                            </p>

                            @if($editId)
                            <label class="inline-flex items-center gap-2 mt-2 text-sm">
                                <input type="checkbox" wire:model.defer="removeLogo" class="rounded">
                                Quitar logo actual
                            </label>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- ✅ Contrato base --}}
                <div class="md:col-span-2 border-t pt-4">
                    <x-label value="Contrato base DOCX (opcional)" />

                    <div class="space-y-3">
                        <input type="file"
                            class="block w-full text-sm"
                            wire:model="contrato_base"
                            accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document">

                        @error('contrato_base')
                        <p class="text-red-600 text-xs">{{ $message }}</p>
                        @enderror

                        <p class="text-xs text-gray-500">
                            Solo archivos DOCX · Máx 10MB · Se guardará en almacenamiento privado.
                        </p>

                        @if($contrato_base)
                        <div class="rounded-xl border bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
                            Archivo seleccionado: <span class="font-semibold">{{ $contrato_base->getClientOriginalName() }}</span>
                        </div>
                        @elseif($editId)
                        @php
                        $fraccActual = \App\Models\Fraccionamiento::find($editId);
                        @endphp

                        @if($fraccActual?->contrato_base_path)
                        <div class="rounded-xl border bg-gray-50 px-3 py-2 text-sm text-gray-700">
                            Archivo actual:
                            <span class="font-semibold">{{ $fraccActual->contrato_base_nombre }}</span>
                        </div>

                        <label class="inline-flex items-center gap-2 text-sm">
                            <input type="checkbox" wire:model.defer="removeContratoBase" class="rounded">
                            Quitar contrato base actual
                        </label>
                        @else
                        <div class="text-sm text-gray-400">
                            Este fraccionamiento aún no tiene contrato base.
                        </div>
                        @endif
                        @endif
                    </div>
                </div>

            </div>
        </x-slot>

        <x-slot name="footer">
            <div class="grid grid-cols-1 sm:flex sm:justify-end gap-2 w-full">
                <button
                    type="button"
                    wire:click="$set('modal', false)"
                    wire:loading.attr="disabled"
                    wire:target="guardar,logo,contrato_base"
                    class="w-full sm:w-auto px-4 py-2 rounded-xl border disabled:opacity-50 disabled:cursor-not-allowed">
                    Cancelar
                </button>

                <button
                    type="button"
                    wire:click="guardar"
                    wire:loading.attr="disabled"
                    wire:target="guardar,logo,contrato_base"
                    class="w-full sm:w-auto px-4 py-2 rounded-xl bg-black text-white font-bold disabled:opacity-50 disabled:cursor-not-allowed">
                    <span wire:loading.remove wire:target="guardar">Guardar</span>
                    <span wire:loading wire:target="guardar">Guardando...</span>
                </button>
            </div>
        </x-slot>
    </x-dialog-modal>

</div>