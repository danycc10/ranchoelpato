<div class="max-w-6xl mx-auto p-6 space-y-4">

    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-3">
        <div>
            <h1 class="text-2xl font-black">Usuarios</h1>
            <p class="text-gray-600">Asigna roles y permisos directos.</p>
        </div>

        <div class="w-full md:w-80">
            <label class="text-xs text-gray-500">Buscar</label>
            <input class="w-full rounded-xl border p-2" placeholder="Nombre o correo..."
                   wire:model.live.debounce.300ms="q">
        </div>
    </div>

    <div class="overflow-x-auto rounded-2xl border bg-white">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr class="text-left">
                    <th class="p-3">Usuario</th>
                    <th class="p-3">Email</th>
                    <th class="p-3">Roles</th>
                    <th class="p-3">Permisos directos</th>
                    <th class="p-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @foreach($users as $u)
                    <tr class="border-t">
                        <td class="p-3 font-semibold">{{ $u->name }}</td>
                        <td class="p-3 text-gray-700">{{ $u->email }}</td>

                        <td class="p-3">
                            <div class="flex flex-wrap gap-1">
                                @forelse($u->roles as $r)
                                    <span class="px-2 py-1 text-xs rounded-lg border bg-gray-50">{{ $r->name }}</span>
                                @empty
                                    <span class="text-gray-400 text-xs">Sin rol</span>
                                @endforelse
                            </div>
                        </td>

                        <td class="p-3">
                            <div class="flex flex-wrap gap-1">
                                @forelse($u->permissions as $p)
                                    <span class="px-2 py-1 text-xs rounded-lg border bg-amber-50 border-amber-200 text-amber-700">
                                        {{ $p->name }}
                                    </span>
                                @empty
                                    <span class="text-gray-400 text-xs">Ninguno</span>
                                @endforelse
                            </div>
                        </td>

                        <td class="p-3 text-right">
                            @can('usuarios.editar')
                                <button class="px-3 py-1.5 rounded-xl bg-black text-white font-semibold"
                                        wire:click="abrir({{ $u->id }})">
                                    Editar
                                </button>
                            @endcan
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div>{{ $users->links() }}</div>

    @if($modal)
        <div class="fixed inset-0 z-50">
            <div class="absolute inset-0 bg-black/50" wire:click="cerrar"></div>

            <div class="relative h-full w-full flex items-center justify-center p-3 sm:p-6">
                <div class="w-full max-w-5xl bg-white rounded-2xl shadow-2xl border overflow-hidden max-h-[92vh] flex flex-col">

                    <div class="px-5 py-4 border-b bg-white sticky top-0 z-10">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h2 class="text-xl sm:text-2xl font-black leading-tight">
                                    Roles y permisos
                                </h2>
                                <p class="text-sm text-gray-600 mt-1">
                                    Usuario:
                                    <span class="font-semibold">{{ $userActual?->name }}</span>
                                    <span class="text-gray-400">({{ $userActual?->email }})</span>
                                </p>
                            </div>

                            <button class="px-3 py-1.5 rounded-xl border hover:bg-gray-50"
                                    wire:click="cerrar">
                                Cerrar
                            </button>
                        </div>
                    </div>

                    <div class="p-4 sm:p-5 overflow-auto flex-1">
                        <div class="grid lg:grid-cols-2 gap-4">
                            
                            <div class="mb-4 rounded-2xl border bg-white">
    <div class="px-4 py-3 border-b">
        <div class="font-black">Propietario asignado</div>
        <div class="text-xs text-gray-500">
            Relaciona este usuario con un propietario
        </div>
    </div>

    <div class="p-4">
        <select 
            wire:model.live="propietario_id"
            class="w-full rounded-xl border p-2"
        >
            <option value="">Sin propietario</option>

            @foreach($propietarios as $p)
                <option value="{{ $p->id }}">
                    {{ $p->nombre }}
                </option>
            @endforeach
        </select>
    </div>
</div>

                            <div class="rounded-2xl border bg-white">
                                <div class="px-4 py-3 border-b flex items-center justify-between">
                                    <div>
                                        <div class="font-black">Roles</div>
                                        <div class="text-xs text-gray-500">Selecciona uno</div>
                                    </div>
                                    <span class="text-xs text-gray-400">
                                        {{ $roleSeleccionado ? '1 seleccionado' : '0 seleccionados' }}
                                    </span>
                                </div>

                                <div class="p-4">
                                    <div class="space-y-3">
                                        @foreach($roles as $r)
                                            <label class="flex items-center gap-3 rounded-xl border p-3 hover:bg-gray-50 cursor-pointer">
                                                <input type="radio"
                                                       name="role_unico"
                                                       class="rounded"
                                                       wire:model.live="roleSeleccionado"
                                                       value="{{ $r->name }}">
                                                <div class="flex-1">
                                                    <div class="font-semibold">{{ $r->name }}</div>
                                                    <div class="text-xs text-gray-500">{{ $r->permissions()->count() }} permisos</div>
                                                </div>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            </div>

                            <div class="rounded-2xl border bg-white">
                                <div class="px-4 py-3 border-b flex items-center justify-between gap-3">
                                    <div>
                                        <div class="font-black">Permisos directos</div>
                                        <div class="text-xs text-gray-500">Overrides por usuario</div>
                                    </div>

                                    <label class="text-xs text-gray-700 inline-flex items-center gap-2 select-none">
                                        <input type="checkbox" class="rounded" wire:model.live="mostrarPermisosRol">
                                        Mostrar permisos efectivos
                                    </label>
                                </div>

                                <div class="p-4">
                                    <div class="mb-3">
                                        <input
                                            type="text"
                                            placeholder="Filtrar permisos…"
                                            class="w-full rounded-xl border p-2 text-sm"
                                            oninput="
                                                const q=this.value.toLowerCase();
                                                document.querySelectorAll('[data-perm-row]').forEach(el=>{
                                                    el.style.display = el.dataset.permRow.includes(q) ? '' : 'none';
                                                });
                                            ">
                                    </div>

                                    <div class="max-h-[360px] overflow-auto pr-1 space-y-2">
                                        @foreach($permisos as $p)
                                            <label
                                                class="flex items-center gap-3 rounded-xl border p-2 hover:bg-gray-50 cursor-pointer"
                                                data-perm-row="{{ strtolower($p->name) }}">
                                                <input type="checkbox"
                                                       class="rounded"
                                                       wire:model.live="permisosSeleccionados"
                                                       value="{{ $p->name }}">
                                                <span class="text-sm break-all">{{ $p->name }}</span>
                                            </label>
                                        @endforeach
                                    </div>

                                    <div class="mt-3 text-xs text-gray-500">
                                        Directos: <span class="font-semibold">{{ count($permisosSeleccionados) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        @if($mostrarPermisosRol)
                            <div class="mt-4 rounded-2xl border bg-gray-50">
                                <div class="px-4 py-3 border-b flex items-center justify-between">
                                    <div class="font-black">Permisos efectivos (rol + directos)</div>
                                    <span class="text-xs text-gray-500">
                                        {{ count($permisosEfectivos ?? []) }} total
                                    </span>
                                </div>

                                <div class="p-4">
                                    <div class="max-h-[220px] overflow-auto">
                                        <div class="flex flex-wrap gap-2">
                                            @forelse($permisosEfectivos as $pp)
                                                <span class="px-2.5 py-1 rounded-xl text-xs border bg-white break-all">
                                                    {{ $pp }}
                                                </span>
                                            @empty
                                                <span class="text-gray-500 text-sm">Sin permisos.</span>
                                            @endforelse
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="px-5 py-4 border-t bg-white sticky bottom-0 z-10">
                        <div class="flex items-center justify-end gap-2">
                            <button class="px-4 py-2 rounded-xl border hover:bg-gray-50"
                                    wire:click="cerrar">
                                Cancelar
                            </button>

                            <button class="px-4 py-2 rounded-xl bg-black text-white font-semibold"
                                    wire:click="guardar">
                                Guardar cambios
                            </button>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    @endif

</div>