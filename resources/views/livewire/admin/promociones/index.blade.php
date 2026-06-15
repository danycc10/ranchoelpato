<div class="max-w-6xl mx-auto p-4 sm:p-6 space-y-4">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-2xl font-black">Promociones</h1>
            <p class="text-gray-500 text-sm">Crea y administra promociones.</p>
        </div>

        <button
            wire:click="crear"
            class="w-full sm:w-auto px-4 py-2 rounded-xl bg-black text-white font-bold"
        >
            + Nueva
        </button>
    </div>

    {{-- Buscar --}}
    <div>
        <input
            type="text"
            wire:model.live.debounce.300ms="q"
            class="w-full rounded-xl border-gray-300"
            placeholder="Buscar por nombre, código o tipo..."
        >
    </div>

    {{-- =========================
        TABLA (md+)
    ========================== --}}
    <div class="hidden md:block bg-white rounded-2xl border overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="p-3 text-left">
                            <button wire:click="sort('nombre')" class="font-bold">Nombre</button>
                        </th>
                        <th class="p-3 text-left">
                            <button wire:click="sort('codigo')" class="font-bold">Código</button>
                        </th>
                        <th class="p-3 text-left">Tipo</th>
                        <th class="p-3 text-left">Activa</th>
                        <th class="p-3 text-left">Vigencia</th>
                        <th class="p-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $r)
                        <tr class="border-t" wire:key="promo-row-{{ $r->id }}">
                            <td class="p-3 font-semibold">{{ $r->nombre }}</td>
                            <td class="p-3">
                                <div class="max-w-[260px] break-all">
                                    {{ $r->codigo }}
                                </div>
                            </td>
                            <td class="p-3">{{ $r->tipo }}</td>
                            <td class="p-3">
                                <span class="px-2 py-1 rounded-full text-xs
                                    {{ $r->activa ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                                    {{ $r->activa ? 'Sí' : 'No' }}
                                </span>
                            </td>
                            <td class="p-3 text-xs text-gray-600 whitespace-nowrap">
                                {{ $r->fecha_inicio?->format('d/m/Y') ?? '—' }}
                                —
                                {{ $r->fecha_fin?->format('d/m/Y') ?? '—' }}
                            </td>
                            <td class="p-3 text-right whitespace-nowrap">
                                <button wire:click="editar({{ $r->id }})"
                                        class="px-3 py-1 rounded-xl border hover:bg-gray-50">
                                    Editar
                                </button>
                                <button wire:click="eliminar({{ $r->id }})"
                                        class="px-3 py-1 rounded-xl border hover:bg-red-50 text-red-700">
                                    Eliminar
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="p-4 text-gray-500" colspan="6">Sin promociones.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-3">
            {{ $rows->onEachSide(1)->links() }}
        </div>
    </div>

    {{-- =========================
        CARDS (mobile)
    ========================== --}}
    <div class="md:hidden space-y-3">
        @forelse($rows as $r)
            @php
                $vig = ($r->fecha_inicio?->format('d/m/Y') ?? '—') . ' — ' . ($r->fecha_fin?->format('d/m/Y') ?? '—');
            @endphp

            <div class="rounded-2xl border bg-white p-4 overflow-hidden" wire:key="promo-card-{{ $r->id }}">

                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="font-black leading-tight break-words">
                            {{ $r->nombre }}
                        </div>

                        <div class="mt-1 text-sm text-gray-600">
                            <span class="text-gray-500">Tipo:</span>
                            <span class="font-semibold break-words">{{ $r->tipo }}</span>
                        </div>
                    </div>

                    <div class="shrink-0 text-right">
                        <div class="text-xs text-gray-500">Activa</div>
                        <span class="inline-flex px-2 py-1 rounded-full text-xs font-bold
                            {{ $r->activa ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                            {{ $r->activa ? 'Sí' : 'No' }}
                        </span>
                    </div>
                </div>

                <div class="mt-3 grid grid-cols-1 gap-2 text-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div class="text-gray-500 shrink-0">Código</div>
                        {{-- 🔥 anti overflow --}}
                        <div class="font-semibold text-right min-w-0 break-all">{{ $r->codigo }}</div>
                    </div>

                    <div class="flex items-start justify-between gap-3">
                        <div class="text-gray-500 shrink-0">Vigencia</div>
                        <div class="font-semibold text-right min-w-0 break-words">{{ $vig }}</div>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-2">
                    <button wire:click="editar({{ $r->id }})"
                            class="w-full px-4 py-2 rounded-xl border hover:bg-gray-50 font-semibold">
                        Editar
                    </button>

                    <button wire:click="eliminar({{ $r->id }})"
                            class="w-full px-4 py-2 rounded-xl border hover:bg-red-50 text-red-700 font-semibold">
                        Eliminar
                    </button>
                </div>
            </div>

        @empty
            <div class="rounded-2xl border bg-white p-6 text-gray-500 text-center">
                Sin promociones.
            </div>
        @endforelse
    </div>

    {{-- ✅ Paginación: Desktop links / Mobile botones --}}
    {{-- Paginación --}}
    <div class="mt-4">
        {{ $rows->links() }}
    </div>

    {{-- Modal --}}
    @if($modal)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-2xl bg-white rounded-2xl shadow border overflow-hidden">
                <div class="p-4 border-b flex items-center justify-between gap-3">
                    <div class="font-black">{{ $editId ? 'Editar promoción' : 'Nueva promoción' }}</div>
                    <button wire:click="$set('modal', false)" class="px-3 py-1 rounded-xl border">Cerrar</button>
                </div>

                <div class="p-4 sm:p-5 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label class="text-sm font-semibold">Nombre</label>
                        <input type="text" wire:model.live="form.nombre" class="w-full mt-1 rounded-xl border-gray-300">
                        @error('form.nombre') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="text-sm font-semibold">Código</label>
                        <input type="text" wire:model.live="form.codigo" class="w-full mt-1 rounded-xl border-gray-300"
                               placeholder="Si lo dejas vacío se genera solo">
                        @error('form.codigo') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="text-sm font-semibold">Tipo</label>
                        <select wire:model.live="form.tipo" class="w-full mt-1 rounded-xl border-gray-300">
                            <option value="diferir_primer_pago">Diferir primer pago</option>
                            <option value="cuotas_fijas">Cuotas fijas</option>
                            <option value="descuento_porcentaje">Descuento %</option>
                            <option value="descuento_fijo">Descuento fijo</option>
                        </select>
                        @error('form.tipo') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    @if(($form['tipo'] ?? '') === 'diferir_primer_pago')
                        <div class="md:col-span-2">
                            <label class="text-sm font-semibold">Días diferidos</label>
                            <input type="number" wire:model.live="form.dias_diferidos" class="w-full mt-1 rounded-xl border-gray-300">
                            @error('form.dias_diferidos') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    @endif

                    @if(($form['tipo'] ?? '') === 'cuotas_fijas')
                        <div class="md:col-span-2">
                            <label class="text-sm font-semibold">Número de cuotas</label>
                            <input type="number" wire:model.live="form.numero_cuotas" class="w-full mt-1 rounded-xl border-gray-300">
                            @error('form.numero_cuotas') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    @endif

                    @if(($form['tipo'] ?? '') === 'descuento_porcentaje')
                        <div class="md:col-span-2">
                            <label class="text-sm font-semibold">Porcentaje (0-100)</label>
                            <input type="number" step="0.01" wire:model.live="form.porcentaje" class="w-full mt-1 rounded-xl border-gray-300">
                            @error('form.porcentaje') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    @endif

                    @if(($form['tipo'] ?? '') === 'descuento_fijo')
                        <div class="md:col-span-2">
                            <label class="text-sm font-semibold">Monto fijo</label>
                            <input type="number" step="0.01" wire:model.live="form.monto_fijo" class="w-full mt-1 rounded-xl border-gray-300">
                            @error('form.monto_fijo') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    @endif

                    <div>
                        <label class="text-sm font-semibold">Activa</label>
                        <select wire:model.live="form.activa" class="w-full mt-1 rounded-xl border-gray-300">
                            <option value="1">Sí</option>
                            <option value="0">No</option>
                        </select>
                    </div>

                    <div>
                        <label class="text-sm font-semibold">Fecha inicio</label>
                        <input type="date" wire:model.live="form.fecha_inicio" class="w-full mt-1 rounded-xl border-gray-300">
                        @error('form.fecha_inicio') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="md:col-span-2">
                        <label class="text-sm font-semibold">Fecha fin</label>
                        <input type="date" wire:model.live="form.fecha_fin" class="w-full mt-1 rounded-xl border-gray-300">
                        @error('form.fecha_fin') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="p-4 border-t grid grid-cols-1 sm:flex sm:items-center sm:justify-end gap-2">
                    <button wire:click="$set('modal', false)" class="w-full sm:w-auto px-4 py-2 rounded-xl border">
                        Cancelar
                    </button>
                    <button wire:click="guardar" class="w-full sm:w-auto px-5 py-2 rounded-xl bg-black text-white font-bold">
                        Guardar
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
