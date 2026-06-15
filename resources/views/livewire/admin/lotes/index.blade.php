<div class="max-w-6xl mx-auto p-4 sm:p-6 space-y-4">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-2xl font-black">Lotes</h1>
            <p class="text-gray-500 text-sm">Mantenimiento del catálogo.</p>
        </div>

        <div class="flex flex-col sm:flex-row gap-2">
            <button wire:click="exportarExcel"
                    type="button"
                    class="w-full sm:w-auto px-4 py-2 rounded-xl border font-bold hover:bg-gray-50">
                Exportar Excel
            </button>

            <button wire:click="crear"
                    type="button"
                    class="w-full sm:w-auto px-4 py-2 rounded-xl bg-black text-white font-bold">
                + Nuevo
            </button>
        </div>
    </div>

    {{-- FILTROS --}}
    <div class="bg-white rounded-2xl border p-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">

            <div class="sm:col-span-2">
                <label class="text-xs text-gray-500 font-semibold">Búsqueda libre</label>
                <input type="text"
                       wire:model.live="q"
                       class="w-full rounded-xl border-gray-300"
                       placeholder="Buscar... (fracc, propietario, manzana, lote, clave, estatus)">
            </div>

            <div>
                <label class="text-xs text-gray-500 font-semibold">Fraccionamiento</label>
                <select class="w-full rounded-xl border-gray-300" wire:model.live="fraccionamientoFilter">
                    <option value="">Todos</option>
                    @foreach($fraccionamientos as $f)
                        <option value="{{ $f->id }}">{{ $f->nombre }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="text-xs text-gray-500 font-semibold">Propietario</label>
                <select class="w-full rounded-xl border-gray-300" wire:model.live="propietarioFilter">
                    <option value="">Todos</option>
                    @foreach($propietarios as $p)
                        <option value="{{ $p->id }}">{{ $p->nombre }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="text-xs text-gray-500 font-semibold">Estatus</label>
                <select class="w-full rounded-xl border-gray-300" wire:model.live="estatusFilter">
                    <option value="">Todos</option>
                    <option value="disponible">Disponible</option>
                    <option value="apartado">Apartado</option>
                    <option value="vendido">Vendido</option>
                    <option value="donacion">Donación</option>
                    <option value="cancelado">Cancelado</option>
                </select>
            </div>

            <div>
                <label class="text-xs text-gray-500 font-semibold">Manzana</label>
                <input type="text"
                       wire:model.debounce.350ms="manzanaFilter"
                       class="w-full rounded-xl border-gray-300">
            </div>

            <div>
                <label class="text-xs text-gray-500 font-semibold">Lote</label>
                <input type="text"
                       wire:model.debounce.350ms="loteFilter"
                       class="w-full rounded-xl border-gray-300">
            </div>

            <div>
                <label class="text-xs text-gray-500 font-semibold">Área m² (mín)</label>
                <input type="number"
                       step="0.01"
                       wire:model.debounce.350ms="areaMin"
                       class="w-full rounded-xl border-gray-300">
            </div>

            <div>
                <label class="text-xs text-gray-500 font-semibold">Área m² (máx)</label>
                <input type="number"
                       step="0.01"
                       wire:model.debounce.350ms="areaMax"
                       class="w-full rounded-xl border-gray-300">
            </div>

            <div>
                <label class="text-xs text-gray-500 font-semibold">Precio (mín)</label>
                <input type="number"
                       step="0.01"
                       wire:model.debounce.350ms="precioMin"
                       class="w-full rounded-xl border-gray-300">
            </div>

            <div>
                <label class="text-xs text-gray-500 font-semibold">Precio (máx)</label>
                <input type="number"
                       step="0.01"
                       wire:model.debounce.350ms="precioMax"
                       class="w-full rounded-xl border-gray-300">
            </div>

        </div>

        <div class="mt-4 flex justify-between">
            <div class="text-xs text-gray-500">
                Tip: puedes combinar búsqueda libre + filtros.
            </div>

            <button type="button"
                    wire:click="limpiarFiltros"
                    class="px-3 py-2 rounded-xl border font-semibold hover:bg-gray-50">
                Limpiar filtros
            </button>
        </div>
    </div>

    {{-- TABLA DESKTOP --}}
    <div class="hidden md:block overflow-x-auto bg-white rounded-2xl border">
        <table class="min-w-full text-sm">

            <thead class="bg-gray-50">
                <tr>
                    <th class="p-3 font-bold text-left">Fraccionamiento</th>
                    <th class="p-3 font-bold text-left">Propietario</th>
                    <th class="p-3 font-bold text-left">Manzana</th>
                    <th class="p-3 font-bold text-left">Lote</th>
                    <th class="p-3 font-bold text-left">Clave</th>
                    <th class="p-3 font-bold text-left">Área</th>
                    <th class="p-3 font-bold text-left">Estatus</th>
                    <th class="p-3 font-bold text-right">Acciones</th>
                </tr>
            </thead>

            <tbody>
                @forelse($items as $it)
                    <tr class="border-t">
                        <td class="p-3">{{ $it->fraccionamiento?->nombre }}</td>
                        <td class="p-3">{{ $it->fraccionamiento?->propietario?->nombre }}</td>
                        <td class="p-3">{{ $it->manzana ?: '—' }}</td>
                        <td class="p-3">{{ $it->lote }}</td>
                        <td class="p-3">{{ $it->clave }}</td>
                        <td class="p-3">{{ $it->area_m2 !== null ? number_format((float)$it->area_m2, 2) . ' m²' : '—' }}</td>

                        <td class="p-3">
                            <span class="inline-flex px-2 py-1 rounded-full text-xs font-bold
                                @if($it->estatus === 'disponible') bg-green-100 text-green-800
                                @elseif($it->estatus === 'apartado') bg-amber-100 text-amber-800
                                @elseif($it->estatus === 'vendido') bg-blue-100 text-blue-800
                                @elseif($it->estatus === 'donacion') bg-purple-100 text-purple-800
                                @elseif($it->estatus === 'cancelado') bg-red-100 text-red-800
                                @endif
                            ">
                                {{ $it->estatus === 'donacion' ? 'Donación' : ucfirst($it->estatus) }}
                            </span>
                        </td>

                        <td class="p-3 text-right">
                            <button wire:click="editar({{ $it->id }})"
                                    class="px-3 py-1 border rounded-lg hover:bg-gray-50">
                                Editar
                            </button>

                            <button type="button"
                                    class="px-3 py-1 border rounded-lg text-red-600 hover:bg-red-50"
                                    x-data
                                    x-on:click="if(confirm('¿Eliminar este lote?')) { $wire.eliminar({{ $it->id }}) }">
                                Eliminar
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="p-4 text-gray-500 text-center">
                            Sin registros
                        </td>
                    </tr>
                @endforelse
            </tbody>

        </table>
    </div>

    {{-- MOBILE CARDS --}}
    <div class="md:hidden space-y-3">
        @forelse($items as $it)
            <div class="bg-white border rounded-2xl p-4">
                <div class="flex justify-between gap-3">
                    <div class="min-w-0">
                        <div class="font-bold break-words">{{ $it->clave }}</div>
                        <div class="text-sm text-gray-500 break-words">{{ $it->fraccionamiento?->nombre }}</div>
                        <div class="text-sm text-gray-500 break-words">{{ $it->fraccionamiento?->propietario?->nombre }}</div>
                    </div>

                    <span class="inline-flex h-fit px-2 py-1 rounded-full text-xs font-bold
                        @if($it->estatus === 'disponible') bg-green-100 text-green-800
                        @elseif($it->estatus === 'apartado') bg-amber-100 text-amber-800
                        @elseif($it->estatus === 'vendido') bg-blue-100 text-blue-800
                        @elseif($it->estatus === 'donacion') bg-purple-100 text-purple-800
                        @elseif($it->estatus === 'cancelado') bg-red-100 text-red-800
                        @endif
                    ">
                        {{ $it->estatus === 'donacion' ? 'Donación' : ucfirst($it->estatus) }}
                    </span>
                </div>

                <div class="mt-3 text-sm text-gray-700 space-y-1">
                    <div><span class="text-gray-500">Manzana:</span> <span class="font-semibold">{{ $it->manzana ?: '—' }}</span></div>
                    <div><span class="text-gray-500">Lote:</span> <span class="font-semibold">{{ $it->lote }}</span></div>
                    <div><span class="text-gray-500">Área:</span> <span class="font-semibold">{{ $it->area_m2 !== null ? number_format((float)$it->area_m2, 2) . ' m²' : '—' }}</span></div>
                </div>

                <div class="mt-3 flex gap-2">
                    <button wire:click="editar({{ $it->id }})" class="px-3 py-2 border rounded-xl w-full">
                        Editar
                    </button>
                    <button type="button"
                            class="px-3 py-2 border rounded-xl w-full text-red-600"
                            x-data
                            x-on:click="if(confirm('¿Eliminar este lote?')) { $wire.eliminar({{ $it->id }}) }">
                        Eliminar
                    </button>
                </div>
            </div>
        @empty
            <div class="bg-white border rounded-2xl p-6 text-center text-gray-500">
                Sin registros
            </div>
        @endforelse
    </div>

    {{-- PAGINACION --}}
    <div>
        {{ $items->links() }}
    </div>

    {{-- MODAL --}}
    <x-dialog-modal wire:model="modal">

        <x-slot name="title">
            {{ $editId ? 'Editar lote' : 'Nuevo lote' }}
        </x-slot>

        <x-slot name="content">
            <div class="max-h-[70vh] overflow-y-auto pr-1">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                    {{-- Fraccionamiento --}}
                    <div class="md:col-span-2">
                        <x-label value="Fraccionamiento" />
                        <select wire:model="fraccionamiento_id" class="w-full rounded-xl border">
                            <option value="">Selecciona...</option>
                            @foreach($fraccionamientos as $f)
                                <option value="{{ $f->id }}">{{ $f->nombre }}</option>
                            @endforeach
                        </select>
                        @error('fraccionamiento_id') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    {{-- Manzana --}}
                    <div>
                        <x-label value="Manzana" />
                        <input type="text" wire:model.lazy="manzana" class="w-full rounded-xl border" placeholder="Ej: 2, 2A" />
                        @error('manzana') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    {{-- Lote --}}
                    <div>
                        <x-label value="Lote" />
                        <input type="text" wire:model.lazy="lote" class="w-full rounded-xl border" placeholder="Ej: 3, 3B" />
                        @error('lote') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    {{-- Clave --}}
                    <div class="md:col-span-2">
                        <div class="flex items-center justify-between">
                            <x-label value="Clave" />
                            <label class="text-xs text-gray-500 flex items-center gap-2 select-none">
                                <input type="checkbox" wire:model.lazy="claveManual" class="rounded">
                                Escribir clave manual
                            </label>
                        </div>

                        <input type="text" wire:model.lazy="clave" class="w-full rounded-xl border" placeholder="Ej: M2 - L3B" />
                        <div class="text-xs text-gray-500 mt-1">
                            Si no está en manual, se autogenera con Manzana + Lote.
                        </div>
                        @error('clave') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    {{-- Área --}}
                    <div>
                        <x-label value="Área (m²)" />
                        <input type="number" step="0.01" wire:model.lazy="area_m2" class="w-full rounded-xl border" placeholder="Ej: 160.50" />
                        @error('area_m2') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    {{-- Precio lista --}}
                    <div>
                        <x-label value="Precio lista" />
                        <input type="number" step="0.01" wire:model.lazy="precio_lista" class="w-full rounded-xl border" placeholder="Ej: 250000" />
                        @error('precio_lista') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    {{-- Estatus --}}
                    <div class="md:col-span-2">
                        <x-label value="Estatus" />
                        <select wire:model="estatus" class="w-full rounded-xl border">
                            <option value="disponible">Disponible</option>
                            <option value="apartado">Apartado</option>
                            <option value="vendido">Vendido</option>
                            <option value="donacion">Donación</option>
                            <option value="cancelado">Cancelado</option>
                        </select>
                        @error('estatus') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    {{-- MEDIDAS --}}
                    <div class="md:col-span-2 pt-2">
                        <div class="text-sm font-black text-gray-900">Medidas</div>
                        <div class="text-xs text-gray-500">Captura las medidas de cada lado del lote.</div>
                    </div>

                    <div>
                        <x-label value="Medida norte" />
                        <input type="text" wire:model.lazy="medida_norte" class="w-full rounded-xl border" placeholder="Ej: 10.50 m" />
                        @error('medida_norte') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div>
                        <x-label value="Medida sur" />
                        <input type="text" wire:model.lazy="medida_sur" class="w-full rounded-xl border" placeholder="Ej: 10.50 m" />
                        @error('medida_sur') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div>
                        <x-label value="Medida este" />
                        <input type="text" wire:model.lazy="medida_este" class="w-full rounded-xl border" placeholder="Ej: 16.00 m" />
                        @error('medida_este') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div>
                        <x-label value="Medida oeste" />
                        <input type="text" wire:model.lazy="medida_oeste" class="w-full rounded-xl border" placeholder="Ej: 16.00 m" />
                        @error('medida_oeste') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    {{-- COLINDANCIAS --}}
                    <div class="md:col-span-2 pt-2">
                        <div class="text-sm font-black text-gray-900">Colindancias</div>
                        <div class="text-xs text-gray-500">Captura con qué colinda cada lado.</div>
                    </div>

                    <div>
                        <x-label value="Colindancia norte" />
                        <input type="text" wire:model.lazy="colindancia_norte" class="w-full rounded-xl border" placeholder="Ej: Calle principal" />
                        @error('colindancia_norte') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div>
                        <x-label value="Colindancia sur" />
                        <input type="text" wire:model.lazy="colindancia_sur" class="w-full rounded-xl border" placeholder="Ej: Lote 12" />
                        @error('colindancia_sur') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div>
                        <x-label value="Colindancia este" />
                        <input type="text" wire:model.lazy="colindancia_este" class="w-full rounded-xl border" placeholder="Ej: Área verde" />
                        @error('colindancia_este') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div>
                        <x-label value="Colindancia oeste" />
                        <input type="text" wire:model.lazy="colindancia_oeste" class="w-full rounded-xl border" placeholder="Ej: Lote 14" />
                        @error('colindancia_oeste') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    {{-- Notas --}}
                    <div class="md:col-span-2">
                        <x-label value="Notas" />
                        <textarea wire:model.lazy="notas" rows="3" class="w-full rounded-xl border" placeholder="Opcional..."></textarea>
                        @error('notas') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                </div>
            </div>
        </x-slot>

        <x-slot name="footer">
            <button wire:click="$set('modal', false)"
                    class="px-4 py-2 border rounded-xl">
                Cancelar
            </button>

            <button wire:click="guardar"
                    class="px-4 py-2 bg-black text-white rounded-xl">
                Guardar
            </button>
        </x-slot>

    </x-dialog-modal>

</div>