<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <div class="bg-white shadow-sm sm:rounded-2xl p-6">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">
                        Configuración de propietario contable
                    </h1>
                    <p class="text-sm text-gray-500 mt-1">
                        Selecciona un tipo de cobro y fraccionamiento. Después asigna propietario por forma de pago.
                    </p>
                </div>

                <button
                    wire:click="guardar"
                    class="px-5 py-2.5 rounded-xl bg-gray-900 text-white text-sm font-semibold hover:bg-gray-700"
                >
                    Guardar cambios
                </button>
            </div>

            @if (session('success'))
                <div class="mt-4 rounded-xl bg-green-50 border border-green-200 text-green-700 px-4 py-3 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-6">
                <div>
                    <label class="text-sm font-semibold text-gray-700">
                        Tipo de cobro
                    </label>
                    <select
                        wire:model.live="tipo_cobro_id"
                        class="mt-1 w-full rounded-xl border-gray-300 shadow-sm focus:border-gray-900 focus:ring-gray-900"
                    >
                        <option value="">Seleccionar tipo de cobro</option>
                        @foreach ($tiposCobro as $tipo)
                            <option value="{{ $tipo->id }}">
                                {{ $tipo->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="text-sm font-semibold text-gray-700">
                        Fraccionamiento
                    </label>
                    <select
                        wire:model.live="fraccionamiento_id"
                        class="mt-1 w-full rounded-xl border-gray-300 shadow-sm focus:border-gray-900 focus:ring-gray-900"
                    >
                        <option value="">Seleccionar fraccionamiento</option>
                        @foreach ($fraccionamientos as $fraccionamiento)
                            <option value="{{ $fraccionamiento->id }}">
                                {{ $fraccionamiento->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="bg-white shadow-sm sm:rounded-2xl overflow-hidden">
            <div class="px-6 py-4 border-b bg-gray-50 flex items-center justify-between">
                <div>
                    <h2 class="font-bold text-gray-800">
                        Matriz por forma de pago
                    </h2>
                    <p class="text-xs text-gray-500 mt-1">
                        Si dejas vacío el propietario, se usará la configuración por defecto.
                    </p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-100 text-gray-600 uppercase text-xs">
                        <tr>
                            <th class="px-4 py-3 text-left">Forma de pago</th>
                            <th class="px-4 py-3 text-left">Propietario contable</th>
                            <th class="px-4 py-3 text-center">Prioridad</th>
                            <th class="px-4 py-3 text-center">Activo</th>
                            <th class="px-4 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-100">
                        @forelse ($rows as $index => $row)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 font-semibold text-gray-800">
                                    {{ $row['forma_pago_nombre'] }}
                                </td>

                                <td class="px-4 py-3">
                                    <select
                                        wire:model="rows.{{ $index }}.propietario_id"
                                        class="w-full rounded-xl border-gray-300 shadow-sm focus:border-gray-900 focus:ring-gray-900"
                                    >
                                        <option value="">
                                            Usar propietario por defecto
                                        </option>

                                        @foreach ($propietarios as $propietario)
                                            <option value="{{ $propietario->id }}">
                                                {{ $propietario->nombre }}
                                            </option>
                                        @endforeach
                                    </select>

                                    @error("rows.$index.propietario_id")
                                        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                                    @enderror
                                </td>

                                <td class="px-4 py-3 text-center">
                                    <input
                                        type="number"
                                        min="0"
                                        wire:model="rows.{{ $index }}.prioridad"
                                        class="w-24 rounded-xl border-gray-300 text-center shadow-sm focus:border-gray-900 focus:ring-gray-900"
                                    >
                                </td>

                                <td class="px-4 py-3 text-center">
                                    <input
                                        type="checkbox"
                                        wire:model="rows.{{ $index }}.activo"
                                        class="rounded border-gray-300 text-gray-900 focus:ring-gray-900"
                                    >
                                </td>

                                <td class="px-4 py-3 text-right">
                                    <button
                                        wire:click="limpiarFila({{ $index }})"
                                        class="text-red-600 hover:underline text-sm"
                                    >
                                        Limpiar
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-10 text-center text-gray-500">
                                    Selecciona tipo de cobro y fraccionamiento para cargar la matriz.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if (count($rows))
                <div class="p-4 bg-gray-50 border-t flex justify-end">
                    <button
                        wire:click="guardar"
                        class="px-5 py-2.5 rounded-xl bg-gray-900 text-white text-sm font-semibold hover:bg-gray-700"
                    >
                        Guardar configuración
                    </button>
                </div>
            @endif
        </div>

    </div>
</div>