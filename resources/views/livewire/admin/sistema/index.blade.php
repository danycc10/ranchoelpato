<div class="max-w-6xl mx-auto p-4 sm:p-6 space-y-6">

    <div class="flex items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black">Sistema</h1>
            <p class="text-gray-500">Administración del sistema: accesos, auditoría y consumo de servicios.</p>
        </div>
    </div>

    <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm" aria-labelledby="whatsapp-integracion">
        <div class="border-b border-gray-200 bg-gray-950 px-5 py-4 text-white sm:px-6">
            <div class="flex flex-col justify-between gap-2 sm:flex-row sm:items-center">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-emerald-300">Canal de cobranza</p>
                    <h2 id="whatsapp-integracion" class="mt-1 text-xl font-black">Integración de WhatsApp</h2>
                </div>
                <span class="w-fit rounded-full bg-white/10 px-3 py-1 text-xs font-bold ring-1 ring-white/20">
                    Activa: {{ $whatsappProveedor === 'meta' ? 'Meta oficial' : 'OpenWA' }}
                </span>
            </div>
        </div>

        <div class="p-5 sm:p-6">
            <p class="max-w-3xl text-sm text-gray-600">
                El proveedor elegido recibe mensajes, responde el bot y entrega los recibos. Los pagos y comprobantes usan el mismo flujo sin importar la integración.
            </p>

            <div class="mt-4 grid gap-3 md:grid-cols-2">
                <label class="relative rounded-2xl border-2 p-4 transition
                    {{ $cambioWhatsappHabilitado ? 'cursor-pointer focus-within:ring-2 focus-within:ring-black focus-within:ring-offset-2 '.($whatsappProveedor === 'openwa' ? 'border-gray-950 bg-gray-50' : 'border-gray-200 hover:border-gray-400') : 'cursor-not-allowed border-gray-300 bg-gray-100 opacity-70' }}">
                    <input type="radio" wire:model.live="whatsappProveedor" value="openwa" class="sr-only" @disabled(!$cambioWhatsappHabilitado)>
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="font-black text-gray-950">OpenWA</p>
                            <p class="mt-1 text-sm text-gray-600">Servidor propio con sesión enlazada por código QR.</p>
                        </div>
                        <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $openWaConfigurado ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-900' }}">
                            {{ $openWaConfigurado ? 'Configurado' : 'Faltan datos' }}
                        </span>
                    </div>
                    <div class="mt-4 rounded-xl border bg-white px-3 py-2">
                        <p class="text-[10px] font-bold uppercase tracking-wide text-gray-500">Webhook</p>
                        <p class="mt-1 break-all font-mono text-xs text-gray-800">{{ $openWaWebhookUrl }}</p>
                    </div>
                </label>

                <label class="relative rounded-2xl border-2 p-4 transition
                    {{ $metaHabilitado && $cambioWhatsappHabilitado ? 'cursor-pointer focus-within:ring-2 focus-within:ring-blue-600 focus-within:ring-offset-2 '.($whatsappProveedor === 'meta' ? 'border-blue-600 bg-blue-50' : 'border-gray-200 hover:border-blue-300') : 'cursor-not-allowed border-gray-200 bg-gray-100 opacity-70' }}">
                    <input type="radio" wire:model.live="whatsappProveedor" value="meta" class="sr-only" @disabled(!$metaHabilitado || !$cambioWhatsappHabilitado)>
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="font-black text-gray-950">WhatsApp oficial de Meta</p>
                            <p class="mt-1 text-sm text-gray-600">Cloud API oficial, sin sesión QR ni servidor OpenWA.</p>
                        </div>
                        <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ !$metaHabilitado ? 'bg-gray-200 text-gray-700' : ($metaConfigurado ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-900') }}">
                            {{ !$metaHabilitado ? 'Desactivada' : ($metaConfigurado ? 'Configurado' : 'Faltan datos') }}
                        </span>
                    </div>
                    <div class="mt-4 rounded-xl border border-blue-100 bg-white px-3 py-2">
                        <p class="text-[10px] font-bold uppercase tracking-wide text-gray-500">URL de devolución para Meta</p>
                        <p class="mt-1 break-all font-mono text-xs text-gray-800">{{ $metaWebhookUrl }}</p>
                    </div>
                </label>
            </div>

            @error('whatsappProveedor')
                <p class="mt-3 text-sm font-bold text-red-600">{{ $message }}</p>
            @enderror

            <div class="mt-4 flex flex-col gap-3 border-t pt-4 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs text-gray-500">
                    @if($cambioWhatsappHabilitado)
                        Las llaves y secretos se administran únicamente en <code>.env</code>.
                    @else
                        Selector desactivado. OpenWA continúa funcionando con la configuración actual.
                    @endif
                </p>
                <button type="button" wire:click="guardarWhatsappProveedor" wire:loading.attr="disabled"
                    @disabled(!$cambioWhatsappHabilitado)
                    class="rounded-xl bg-black px-4 py-2.5 text-sm font-black text-white transition hover:bg-gray-800 disabled:cursor-not-allowed disabled:opacity-40">
                    <span wire:loading.remove wire:target="guardarWhatsappProveedor">Guardar integración</span>
                    <span wire:loading wire:target="guardarWhatsappProveedor">Guardando…</span>
                </button>
            </div>
        </div>
    </section>

    {{-- Condiciones con las que nacen los contratos de cesión de derechos --}}
    <section class="mb-6">
        <div class="rounded-2xl border bg-white p-5 shadow-sm">
            <h2 class="text-lg font-black">Contrato de cesión de derechos</h2>
            <p class="mt-1 text-sm text-gray-600">
                Valores con los que se propone cada contrato nuevo. Se pueden cambiar contrato por contrato.
            </p>

            <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label class="block text-sm font-semibold mb-1">Entrega de la posesión</label>
                    <select wire:model.live="cesion.posesion_entrega" class="w-full rounded-xl border-gray-300">
                        @foreach(\App\Services\Contratos\CondicionesCesionService::POSESION_OPCIONES as $opcion)
                        <option value="{{ $opcion }}">{{ $opcion }}</option>
                        @endforeach
                    </select>
                    @error('cesion.posesion_entrega') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold mb-1">Servicios e infraestructura</label>
                    <select wire:model.live="cesion.servicios_estado" class="w-full rounded-xl border-gray-300">
                        @foreach(\App\Services\Contratos\CondicionesCesionService::SERVICIOS_OPCIONES as $opcion)
                        <option value="{{ $opcion }}">{{ $opcion }}</option>
                        @endforeach
                    </select>
                    @error('cesion.servicios_estado') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold mb-1">Medidores y consumos a cargo de</label>
                    <select wire:model.live="cesion.servicios_a_cargo" class="w-full rounded-xl border-gray-300">
                        @foreach(\App\Services\Contratos\CondicionesCesionService::A_CARGO_OPCIONES as $opcion)
                        <option value="{{ $opcion }}">{{ $opcion }}</option>
                        @endforeach
                    </select>
                    @error('cesion.servicios_a_cargo') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold mb-1">Parcialidades para rescindir</label>
                        <input type="number" min="1" max="99" wire:model.live="cesion.parcialidades_rescision"
                            class="w-full rounded-xl border-gray-300">
                        @error('cesion.parcialidades_rescision') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-1">Tolerancia de medidas (%)</label>
                        <input type="number" step="0.01" min="0" max="100" wire:model.live="cesion.tolerancia_medidas"
                            class="w-full rounded-xl border-gray-300">
                        @error('cesion.tolerancia_medidas') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <div class="mt-4 flex justify-end border-t pt-4">
                <button type="button" wire:click="guardarCondicionesCesion" wire:loading.attr="disabled"
                    class="rounded-xl bg-black px-4 py-2.5 text-sm font-black text-white transition hover:bg-gray-800 disabled:opacity-40">
                    <span wire:loading.remove wire:target="guardarCondicionesCesion">Guardar condiciones</span>
                    <span wire:loading wire:target="guardarCondicionesCesion">Guardando…</span>
                </button>
            </div>
        </div>
    </section>

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

        {{-- Consumo OpenAI --}}
        @can('logs.ver')
        <button
            type="button"
            wire:click="goOpenAiUsos"
            class="text-left rounded-2xl border bg-white p-5 hover:bg-gray-50 transition shadow-sm"
        >
            <div class="flex items-start justify-between gap-3">
                <div>
                    <div class="text-sm text-gray-500">Monitoreo</div>
                    <div class="text-xl font-black mt-1">Consumo OpenAI</div>
                    <p class="text-sm text-gray-600 mt-2">
                        Tokens y costo estimado por modelo y periodo.
                    </p>
                </div>
                <div class="shrink-0 rounded-xl bg-emerald-50 p-2 text-emerald-700" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-6 w-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.5h4.5V21H3v-7.5Zm6.75-6h4.5V21h-4.5V7.5Zm6.75-4.5H21v18h-4.5V3Z" />
                    </svg>
                </div>
            </div>

            <div class="mt-4 flex items-center gap-2 text-sm font-semibold">
                <span class="px-3 py-1 rounded-xl border">Ver monitor</span>
                <span class="px-3 py-1 rounded-xl border">Por modelo</span>
            </div>
        </button>
        @endcan

        {{-- Impresion directa --}}
        <button
            type="button"
            wire:click="goImpresion"
            class="text-left rounded-2xl border bg-white p-5 hover:bg-gray-50 transition shadow-sm"
        >
            <div class="flex items-start justify-between gap-3">
                <div>
                    <div class="text-sm text-gray-500">Tickets</div>
                    <div class="text-xl font-black mt-1">Equipos con impresión directa</div>
                    <p class="text-sm text-gray-600 mt-2">
                        Modo de impresión y claves de los agentes instalados en cada computadora.
                    </p>
                </div>
                <div class="shrink-0 rounded-xl bg-indigo-50 p-2 text-indigo-700" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-6 w-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Z" />
                    </svg>
                </div>
            </div>

            <div class="mt-4 flex items-center gap-2 text-sm font-semibold">
                <span class="px-3 py-1 rounded-xl border">Modo</span>
                <span class="px-3 py-1 rounded-xl border">Emparejar</span>
            </div>
        </button>

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
