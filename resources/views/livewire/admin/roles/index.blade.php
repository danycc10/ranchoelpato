<div class="max-w-6xl mx-auto p-6 space-y-4">
    <div>
        <h1 class="text-2xl font-black">Roles</h1>
        <p class="text-gray-600">Define qué permisos tiene cada rol.</p>
    </div>

    <div class="grid md:grid-cols-3 gap-4">
        <div class="rounded-2xl border bg-white p-4">
            <div class="font-black mb-2">Lista de roles</div>
            <div class="space-y-2">
                @foreach($roles as $r)
                    <button class="w-full text-left px-3 py-2 rounded-xl border hover:bg-gray-50
                        {{ $roleActual?->id === $r->id ? 'bg-gray-50 border-black' : '' }}"
                        wire:click="seleccionarRol({{ $r->id }})">
                        <div class="font-semibold">{{ $r->name }}</div>
                        <div class="text-xs text-gray-500">
                            {{ $r->permissions()->count() }} permisos
                        </div>
                    </button>
                @endforeach
            </div>
        </div>

        <div class="md:col-span-2 rounded-2xl border bg-white p-4">
            @if(!$roleActual)
                <div class="text-gray-500">Selecciona un rol para editar sus permisos.</div>
            @else
                <div class="flex items-start justify-between gap-3 mb-3">
                    <div>
                        <div class="font-black text-lg">Rol: {{ $roleActual->name }}</div>
                        <div class="text-sm text-gray-600">Marca/desmarca permisos y guarda.</div>
                    </div>

                    @can('roles.editar')
                        <button class="px-4 py-2 rounded-xl bg-black text-white font-semibold"
                                wire:click="guardar">
                            Guardar
                        </button>
                    @endcan
                </div>

                <div class="max-h-[520px] overflow-auto pr-2 grid md:grid-cols-2 gap-2">
                    @foreach($permisos as $p)
                        <label class="flex items-center gap-2 rounded-xl border p-2">
                            <input type="checkbox" class="rounded"
                                   wire:model.live="permisosSeleccionados" value="{{ $p->name }}"
                                   @cannot('roles.editar') disabled @endcannot>
                            <span class="text-sm">{{ $p->name }}</span>
                        </label>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
