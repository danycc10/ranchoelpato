<div class="p-4 sm:p-6 space-y-6">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Permisos</h1>
            <p class="text-sm text-gray-500">Administra los permisos del sistema.</p>
        </div>

        <button
            type="button"
            wire:click="abrirModalCrear"
            class="inline-flex items-center justify-center rounded-2xl bg-black px-5 py-3 text-sm font-semibold text-white shadow hover:bg-gray-800 transition"
        >
            + Crear permiso
        </button>
    </div>

    @if (session()->has('success'))
        <div class="rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
            {{ session('success') }}
        </div>
    @endif

    @if (session()->has('error'))
        <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            {{ session('error') }}
        </div>
    @endif

    <div class="rounded-3xl border border-gray-200 bg-white p-4 shadow-sm">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div class="md:col-span-2">
                <label class="block text-xs font-bold uppercase tracking-wide text-gray-500">
                    Buscar
                </label>
                <input
                    type="text"
                    wire:model.live.debounce.300ms="busqueda"
                    placeholder="Buscar por nombre o guard..."
                    class="mt-1.5 w-full rounded-2xl border-gray-300 focus:border-black focus:ring-black"
                >
            </div>

            <div class="flex items-end">
                <button
                    type="button"
                    wire:click="abrirModalCrear"
                    class="w-full rounded-2xl border border-black px-4 py-3 text-sm font-semibold text-black hover:bg-black hover:text-white transition"
                >
                    Nuevo permiso
                </button>
            </div>
        </div>
    </div>

    <div class="overflow-hidden rounded-3xl border border-gray-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-gray-600">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">ID</th>
                        <th class="px-4 py-3 text-left font-semibold">Nombre</th>
                        <th class="px-4 py-3 text-left font-semibold">Guard</th>
                        <th class="px-4 py-3 text-left font-semibold">Creado</th>
                        <th class="px-4 py-3 text-right font-semibold">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($permisos as $permiso)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-gray-700">{{ $permiso->id }}</td>
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $permiso->name }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $permiso->guard_name }}</td>
                            <td class="px-4 py-3 text-gray-500">
                                {{ optional($permiso->created_at)->format('d/m/Y H:i') }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end">
                                    <button
                                        type="button"
                                        wire:click="eliminar({{ $permiso->id }})"
                                        wire:confirm="¿Seguro que deseas eliminar este permiso?"
                                        class="rounded-xl border border-red-200 px-3 py-2 text-xs font-semibold text-red-600 hover:bg-red-50"
                                    >
                                        Eliminar
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-10 text-center text-sm text-gray-500">
                                No hay permisos registrados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-gray-100 px-4 py-3">
            {{ $permisos->links() }}
        </div>
    </div>

    {{-- Modal crear --}}
    @if($mostrarModalCrear)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-xl rounded-3xl bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                    <div>
                        <h2 class="text-lg font-bold text-gray-900">Crear permiso</h2>
                        <p class="text-sm text-gray-500">Agrega un nuevo permiso al sistema.</p>
                    </div>

                    <button
                        type="button"
                        wire:click="cerrarModalCrear"
                        class="rounded-xl p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-700"
                    >
                        ✕
                    </button>
                </div>

                <div class="space-y-5 px-6 py-5">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wide text-gray-500">
                            Nombre del permiso
                        </label>
                        <input
                            type="text"
                            wire:model.defer="name"
                            placeholder="Ejemplo: recibos.crear"
                            class="mt-1.5 w-full rounded-2xl border-gray-300 focus:border-black focus:ring-black"
                        >
                        @error('name')
                            <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wide text-gray-500">
                            Guard name
                        </label>
                        <select
                            wire:model.defer="guard_name"
                            class="mt-1.5 w-full rounded-2xl border-gray-300 focus:border-black focus:ring-black"
                        >
                            <option value="web">web</option>
                            <option value="api">api</option>
                        </select>
                        @error('guard_name')
                            <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="flex flex-col-reverse gap-3 border-t border-gray-100 px-6 py-4 sm:flex-row sm:justify-end">
                    <button
                        type="button"
                        wire:click="cerrarModalCrear"
                        class="rounded-2xl border border-gray-300 px-5 py-3 text-sm font-semibold text-gray-700 hover:bg-gray-50"
                    >
                        Cancelar
                    </button>

                    <button
                        type="button"
                        wire:click="guardar"
                        class="rounded-2xl bg-black px-5 py-3 text-sm font-semibold text-white hover:bg-gray-800"
                    >
                        Guardar permiso
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>