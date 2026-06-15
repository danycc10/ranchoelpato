<div class="max-w-6xl mx-auto p-4 sm:p-6 space-y-4 sm:space-y-6">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-2xl font-black">Clientes</h1>
            <p class="text-gray-500 text-sm">Alta y administración de clientes.</p>
        </div>

        <button
            wire:click="crear"
            class="w-full sm:w-auto px-4 py-2 rounded-xl bg-black text-white font-bold">
            + Nuevo
        </button>
    </div>

    {{-- Buscar --}}
    <div>
        <input
            type="text"
            wire:model.live.debounce.300ms="q"
            class="w-full rounded-xl border-gray-300"
            placeholder="Buscar por nombre, apellidos, nombre legal, teléfono, correo, CURP o RFC...">
    </div>

    {{-- TABLA --}}
    <div class="hidden md:block overflow-x-auto bg-white rounded-2xl border">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="text-left p-3">
                        <button type="button" wire:click="sort('id')" class="font-bold">#</button>
                    </th>
                    <th class="text-left p-3">
                        <button type="button" wire:click="sort('nombres')" class="font-bold">Cliente</button>
                    </th>
                    <th class="text-left p-3">
                        <button type="button" wire:click="sort('nombre_legal')" class="font-bold">Nombre legal</button>
                    </th>
                    <th class="text-left p-3">Teléfono</th>
                    <th class="text-left p-3">Correo</th>
                    <th class="text-left p-3">CURP</th>
                    <th class="text-left p-3">INE</th>
                    <th class="text-left p-3">
                        <button type="button" wire:click="sort('estatus')" class="font-bold">Estatus</button>
                    </th>
                    <th class="text-right p-3">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $it)
                <tr class="border-t" wire:key="cliente-row-{{ $it->id }}">
                    <td class="p-3">{{ $it->id }}</td>

                    <td class="p-3">
                        <div class="font-semibold">{{ $it->nombre_completo }}</div>
                        <div class="text-xs text-gray-500">{{ $it->direccion ?: '—' }}</div>
                    </td>

                    <td class="p-3">{{ $it->nombre_legal ?: '—' }}</td>
                    <td class="p-3">{{ $it->telefono ?: '—' }}</td>

                    <td class="p-3">
                        <div class="max-w-[240px] break-all">
                            {{ $it->correo ?: '—' }}
                        </div>
                    </td>

                    <td class="p-3">{{ $it->curp ?: '—' }}</td>

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

                    <td class="p-3">
                        <span class="px-2 py-1 rounded-full text-xs
                                {{ $it->estatus === 'activo' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700' }}">
                            {{ ucfirst($it->estatus) }}
                        </span>
                    </td>

                    <td class="p-3 text-right space-x-2 whitespace-nowrap">
                        <button wire:click="editar({{ $it->id }})"
                            class="px-3 py-1 rounded-xl border hover:bg-gray-50">
                            Editar
                        </button>

                        <button wire:click="toggleEstatus({{ $it->id }})"
                            class="px-3 py-1 rounded-xl border hover:bg-gray-50">
                            {{ $it->estatus === 'activo' ? 'Desactivar' : 'Activar' }}
                        </button>
                    </td>
                </tr>
                @empty
                <tr>
                    <td class="p-4 text-gray-500" colspan="9">Sin clientes.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- MOBILE --}}
    <div class="md:hidden space-y-3">
        @forelse($items as $it)
        <div
            wire:key="cliente-card-{{ $it->id }}"
            class="rounded-2xl border bg-white p-4 overflow-hidden">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="font-black leading-tight break-words">
                        {{ $it->nombre_completo }}
                    </div>

                    <div class="mt-1 text-sm text-gray-600 break-words">
                        <span class="text-gray-500">Nombre legal:</span>
                        <span class="font-semibold">{{ $it->nombre_legal ?: '—' }}</span>
                    </div>

                    <div class="mt-1 text-sm text-gray-600 break-words">
                        <span class="text-gray-500">Dirección:</span>
                        <span class="font-semibold">{{ $it->direccion ?: '—' }}</span>
                    </div>
                </div>

                <div class="shrink-0 text-right">
                    <div class="text-xs text-gray-500">Estatus</div>
                    <span class="inline-flex px-2 py-1 rounded-full text-xs font-bold
                            {{ $it->estatus === 'activo' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700' }}">
                        {{ ucfirst($it->estatus) }}
                    </span>
                </div>
            </div>

            <div class="mt-3 grid grid-cols-1 gap-2 text-sm">
                <div class="flex items-start justify-between gap-3">
                    <div class="text-gray-500 shrink-0">Teléfono</div>
                    <div class="font-semibold text-right min-w-0 break-words">
                        {{ $it->telefono ?: '—' }}
                    </div>
                </div>

                <div class="flex items-start justify-between gap-3">
                    <div class="text-gray-500 shrink-0">Correo</div>
                    <div class="font-semibold text-right min-w-0 break-all">
                        {{ $it->correo ?: '—' }}
                    </div>
                </div>

                <div class="flex items-start justify-between gap-3">
                    <div class="text-gray-500 shrink-0">CURP</div>
                    <div class="font-semibold text-right min-w-0 break-words">
                        {{ $it->curp ?: '—' }}
                    </div>
                </div>

                <div class="pt-1">
                    <div class="text-gray-500 text-xs mb-1">INE</div>
                    <div class="flex flex-wrap gap-1">
                        <span class="px-2 py-1 rounded-lg text-xs font-semibold {{ $it->ine_frente ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                            Frente {{ $it->ine_frente ? '✓' : '—' }}
                        </span>
                        <span class="px-2 py-1 rounded-lg text-xs font-semibold {{ $it->ine_reverso ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                            Reverso {{ $it->ine_reverso ? '✓' : '—' }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-2">
                <button
                    wire:click="editar({{ $it->id }})"
                    class="w-full px-4 py-2 rounded-xl border hover:bg-gray-50 font-semibold">
                    Editar
                </button>

                <button
                    wire:click="toggleEstatus({{ $it->id }})"
                    class="w-full px-4 py-2 rounded-xl border hover:bg-gray-50 font-semibold">
                    {{ $it->estatus === 'activo' ? 'Desactivar' : 'Activar' }}
                </button>
            </div>
        </div>
        @empty
        <div class="rounded-2xl border bg-white p-6 text-gray-500 text-center">
            Sin clientes.
        </div>
        @endforelse
    </div>

    {{-- Paginación --}}
    <div class="mt-4">
        {{ $items->links() }}
    </div>

    {{-- Modal --}}
    @if($modal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-4xl bg-white rounded-2xl p-4 sm:p-5 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-3 gap-3">
                <div class="font-black text-lg">
                    {{ $editId ? 'Editar cliente' : 'Nuevo cliente' }}
                </div>
                <button wire:click="$set('modal', false)" class="px-3 py-1 rounded-xl border">X</button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="text-sm font-semibold">Nombres</label>
                    <input type="text" wire:model.live="form.nombres" class="w-full mt-1 rounded-xl border-gray-300">
                    @error('form.nombres') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="text-sm font-semibold">Apellidos</label>
                    <input type="text" wire:model.live="form.apellidos" class="w-full mt-1 rounded-xl border-gray-300">
                    @error('form.apellidos') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="text-sm font-semibold">Nombre legal</label>
                    <input type="text" wire:model.live="form.nombre_legal" class="w-full mt-1 rounded-xl border-gray-300">
                    @error('form.nombre_legal') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="text-sm font-semibold">Teléfono (10 dígitos)</label>
                    <input type="text" wire:model.live="form.telefono" class="w-full mt-1 rounded-xl border-gray-300" placeholder="8781234567">
                    @error('form.telefono') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="text-sm font-semibold">Correo</label>
                    <input type="email" wire:model.live="form.correo" class="w-full mt-1 rounded-xl border-gray-300" placeholder="cliente@email.com">
                    @error('form.correo') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="text-sm font-semibold">Dirección</label>
                    <input type="text" wire:model.live="form.direccion" class="w-full mt-1 rounded-xl border-gray-300">
                    @error('form.direccion') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="text-sm font-semibold">RFC</label>
                    <input type="text" wire:model.live="form.rfc" class="w-full mt-1 rounded-xl border-gray-300">
                    @error('form.rfc') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="text-sm font-semibold">CURP</label>
                    <input type="text" wire:model.live="form.curp" class="w-full mt-1 rounded-xl border-gray-300">
                    @error('form.curp') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="text-sm font-semibold">INE frente</label>
                    <input type="file"
                        wire:model="ineFrenteFile"
                        class="w-full mt-1 rounded-xl border-gray-300">

                    <p class="text-xs text-gray-500 mt-1">JPG, PNG, WEBP o PDF. Máx 10 MB.</p>
                    @error('ineFrenteFile') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror

                    @if($this->ineFrentePreview)
                    <div class="mt-3 rounded-xl border bg-gray-50 p-3"
                        wire:key="preview-ine-frente-{{ md5(($ine_frente ?? '') . ($this->ineFrentePreview ?? '')) }}">
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
                    <p class="text-xs text-green-700 mt-2 font-semibold">
                        Nuevo archivo seleccionado: {{ $ineFrenteFile->getClientOriginalName() }}
                    </p>
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
                    <label class="text-sm font-semibold">INE reverso</label>
                    <input type="file"
                        wire:model="ineReversoFile"
                        class="w-full mt-1 rounded-xl border-gray-300">

                    <p class="text-xs text-gray-500 mt-1">JPG, PNG, WEBP o PDF. Máx 10 MB.</p>
                    @error('ineReversoFile') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror

                    @if($this->ineReversoPreview)
                    <div class="mt-3 rounded-xl border bg-gray-50 p-3"
                        wire:key="preview-ine-reverso-{{ md5(($ine_reverso ?? '') . ($this->ineReversoPreview ?? '')) }}">
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
                    <p class="text-xs text-green-700 mt-2 font-semibold">
                        Nuevo archivo seleccionado: {{ $ineReversoFile->getClientOriginalName() }}
                    </p>
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
                    <label class="text-sm font-semibold">Notas</label>
                    <textarea wire:model.live="form.notas" rows="3" class="w-full mt-1 rounded-xl border-gray-300"></textarea>
                    @error('form.notas') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="mt-5 grid grid-cols-1 sm:flex sm:justify-end gap-2">
                <button wire:click="$set('modal', false)" class="w-full sm:w-auto px-4 py-2 rounded-xl border">
                    Cancelar
                </button>
                <button
                    wire:click="guardar"
                    wire:loading.attr="disabled"
                    wire:target="guardar,ineFrenteFile,ineReversoFile"
                    class="w-full sm:w-auto px-5 py-2 rounded-xl bg-black text-white font-bold disabled:opacity-50 disabled:cursor-not-allowed">
                    <span wire:loading.remove wire:target="guardar,ineFrenteFile,ineReversoFile">
                        Guardar
                    </span>

                    <span wire:loading wire:target="guardar,ineFrenteFile,ineReversoFile">
                        Guardando...
                    </span>
                </button>
            </div>
        </div>
    </div>
    @endif
</div>