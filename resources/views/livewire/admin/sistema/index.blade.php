<div class="max-w-6xl mx-auto p-4 sm:p-6 space-y-6">

    <div class="flex items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black">Sistema</h1>
            <p class="text-gray-500">Administración del sistema: usuarios, roles y logs.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">

        {{-- Usuarios --}}
        @can('usuarios.ver')
        <button
            type="button"
            wire:click="goUsuarios"
            class="text-left rounded-2xl border bg-white p-5 hover:bg-gray-50 transition shadow-sm"
        >
            <div class="flex items-start justify-between gap-3">
                <div>
                    <div class="text-sm text-gray-500">Módulo</div>
                    <div class="text-xl font-black mt-1">Usuarios</div>
                    <p class="text-sm text-gray-600 mt-2">
                        Alta, edición y control de accesos.
                    </p>
                </div>
                <div class="shrink-0 text-2xl">👤</div>
            </div>

            <div class="mt-4 flex items-center gap-2 text-sm font-semibold">
                <span class="px-3 py-1 rounded-xl border">Ver</span>
                @can('usuarios.crear') <span class="px-3 py-1 rounded-xl border">Crear</span> @endcan
                @can('usuarios.editar') <span class="px-3 py-1 rounded-xl border">Editar</span> @endcan
                @can('usuarios.eliminar') <span class="px-3 py-1 rounded-xl border">Eliminar</span> @endcan
            </div>
        </button>
        @endcan

        {{-- Roles --}}
        @can('roles.ver')
        <button
            type="button"
            wire:click="goRoles"
            class="text-left rounded-2xl border bg-white p-5 hover:bg-gray-50 transition shadow-sm"
        >
            <div class="flex items-start justify-between gap-3">
                <div>
                    <div class="text-sm text-gray-500">Módulo</div>
                    <div class="text-xl font-black mt-1">Roles</div>
                    <p class="text-sm text-gray-600 mt-2">
                        Control de permisos por rol (dueño/admin/jefa/secretaria).
                    </p>
                </div>
                <div class="shrink-0 text-2xl">🛡️</div>
            </div>

            <div class="mt-4 flex items-center gap-2 text-sm font-semibold">
                <span class="px-3 py-1 rounded-xl border">Ver</span>
                @can('roles.crear') <span class="px-3 py-1 rounded-xl border">Crear</span> @endcan
                @can('roles.editar') <span class="px-3 py-1 rounded-xl border">Editar</span> @endcan
                @can('roles.eliminar') <span class="px-3 py-1 rounded-xl border">Eliminar</span> @endcan
            </div>
        </button>
        @endcan
        
                {{-- Permisos --}}
        @can('permisos.ver')
        <button
            type="button"
            wire:click="goPermisos"
            class="text-left rounded-2xl border bg-white p-5 hover:bg-gray-50 transition shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <div class="text-sm text-gray-500">Módulo</div>
                    <div class="text-xl font-black mt-1">Permisos</div>
                    <p class="text-sm text-gray-600 mt-2">
                        Gestión de permisos individuales del sistema.
                    </p>
                </div>
                <div class="shrink-0 text-2xl">🔑</div>
            </div>

            <div class="mt-4 flex items-center gap-2 text-sm font-semibold">
                <span class="px-3 py-1 rounded-xl border">Ver</span>
                @can('permisos.crear') <span class="px-3 py-1 rounded-xl border">Crear</span> @endcan
                @can('permisos.eliminar') <span class="px-3 py-1 rounded-xl border">Eliminar</span> @endcan
            </div>
        </button>
        @endcan

        {{-- Logs --}}
        @can('logs.ver')
        <button
            type="button"
            wire:click="goLogs"
            class="text-left rounded-2xl border bg-white p-5 hover:bg-gray-50 transition shadow-sm"
        >
            <div class="flex items-start justify-between gap-3">
                <div>
                    <div class="text-sm text-gray-500">Módulo</div>
                    <div class="text-xl font-black mt-1">Logs</div>
                    <p class="text-sm text-gray-600 mt-2">
                        Revisión de eventos, errores y acciones del sistema.
                    </p>
                </div>
                <div class="shrink-0 text-2xl">📜</div>
            </div>

            <div class="mt-4 flex items-center gap-2 text-sm font-semibold">
                <span class="px-3 py-1 rounded-xl border">Ver</span>
                @can('logs.detalle') <span class="px-3 py-1 rounded-xl border">Detalle</span> @endcan
                @can('logs.eliminar') <span class="px-3 py-1 rounded-xl border">Eliminar</span> @endcan
            </div>
        </button>
        @endcan

        {{-- Si no tiene permisos a nada --}}
        @cannot('usuarios.ver')
            @cannot('roles.ver')
                @cannot('logs.ver')
                    <div class="rounded-2xl border bg-white p-6 text-gray-600">
                        No tienes permisos para ver módulos del sistema.
                    </div>
                @endcannot
            @endcannot
        @endcannot

    </div>
</div>
