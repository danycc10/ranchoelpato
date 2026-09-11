<div class="mx-auto w-full max-w-5xl space-y-6 p-4 sm:p-6">

    <div class="flex items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black">Equipos con impresión directa</h1>
            <p class="text-gray-500">
                Con el agente local el ticket sale solo en la impresora de quien lo captura,
                sin abrir el diálogo del navegador.
            </p>
        </div>

        <a href="{{ route('admin.sistema.index') }}" class="rounded-xl border px-4 py-2 text-sm font-bold hover:bg-gray-50">
            Volver a Sistema
        </a>
    </div>

    {{-- Modo de impresión --}}
    <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-200 bg-gray-950 px-5 py-4 text-white sm:px-6">
            <p class="text-xs font-bold uppercase tracking-[0.18em] text-emerald-300">Impresión de tickets</p>
            <h2 class="mt-1 text-xl font-black">Modo de impresión</h2>
        </div>

        <div class="p-5 sm:p-6">
            <div class="grid gap-3 md:grid-cols-2">
                @foreach($modos as $modo)
                    <label class="cursor-pointer rounded-2xl border-2 p-4 transition
                        {{ $modoImpresion === $modo->value ? 'border-gray-950 bg-gray-50' : 'border-gray-200 hover:border-gray-400' }}">
                        <input type="radio" wire:model.live="modoImpresion" value="{{ $modo->value }}" class="sr-only">
                        <p class="font-black text-gray-950">{{ $modo->label() }}</p>
                        <p class="mt-1 text-sm text-gray-600">
                            {{ $modo->value === 'browser'
                                ? 'Se abre el ticket en una pestaña y tú mandas a imprimir.'
                                : 'El agente instalado en la computadora jala el ticket y lo imprime solo.' }}
                        </p>
                    </label>
                @endforeach
            </div>

            <div class="mt-4">
                <button wire:click="guardarModo"
                    wire:loading.attr="disabled"
                    wire:target="guardarModo"
                    class="rounded-xl bg-black px-5 py-2.5 font-bold text-white disabled:opacity-50">
                    <span wire:loading.remove wire:target="guardarModo">Guardar modo</span>
                    <span wire:loading wire:target="guardarModo">Guardando...</span>
                </button>
            </div>

            @if($modoImpresion === 'local_agent')
                <p class="mt-4 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900 ring-1 ring-amber-200">
                    Si el agente está apagado o el usuario no tiene equipo emparejado, el ticket se
                    abre en el navegador para que nadie se quede sin imprimir.
                </p>
            @endif
        </div>
    </section>

    {{-- Generar clave --}}
    <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-200 px-5 py-4 sm:px-6">
            <h2 class="text-lg font-black">Emparejar una computadora</h2>
            <p class="mt-1 text-sm text-gray-600">
                Genera la clave, ejecuta el agente con <span class="font-mono">--configure</span> en esa
                computadora, pega la dirección de este sistema y la clave, y elige la impresora térmica.
            </p>
        </div>

        <div class="p-5 sm:p-6">
            @if($claveGenerada)
                <div class="mb-5 rounded-2xl border-2 border-emerald-300 bg-emerald-50 p-4">
                    <p class="text-sm font-bold text-emerald-900">
                        Clave para {{ $claveDeUsuario }} — cópiala ahora, no se vuelve a mostrar.
                    </p>
                    <p class="mt-2 break-all rounded-xl bg-white px-3 py-2 font-mono text-sm">{{ $claveGenerada }}</p>
                    <button wire:click="olvidarClave" class="mt-3 text-xs font-bold text-emerald-900 underline">
                        Ya la copié, ocultar
                    </button>
                </div>
            @endif

            <div class="grid gap-4 md:grid-cols-[1fr_1fr_auto] md:items-end">
                <div>
                    <label class="text-xs font-semibold text-gray-500">Usuario</label>
                    <select wire:model.live="usuarioId" class="mt-1 w-full rounded-xl border px-3 py-3">
                        <option value="">Selecciona...</option>
                        @foreach($usuarios as $usuario)
                            <option value="{{ $usuario->id }}">{{ $usuario->name }}</option>
                        @endforeach
                    </select>
                    @error('usuarioId')<div class="mt-1 text-xs text-red-600">{{ $message }}</div>@enderror
                </div>

                <div>
                    <label class="text-xs font-semibold text-gray-500">Nombre del equipo (opcional)</label>
                    <input type="text" wire:model.live="nombreEquipo"
                        placeholder="Caja principal"
                        class="mt-1 w-full rounded-xl border px-3 py-3">
                    @error('nombreEquipo')<div class="mt-1 text-xs text-red-600">{{ $message }}</div>@enderror
                </div>

                <button wire:click="generarClave"
                    wire:loading.attr="disabled"
                    wire:target="generarClave"
                    class="rounded-xl bg-black px-5 py-3 font-bold text-white disabled:opacity-50">
                    <span wire:loading.remove wire:target="generarClave">Generar clave</span>
                    <span wire:loading wire:target="generarClave">Generando...</span>
                </button>
            </div>

            <p class="mt-3 text-xs text-gray-500">
                Generar una clave nueva para el mismo usuario reemplaza la anterior.
            </p>
        </div>
    </section>

    {{-- Equipos emparejados --}}
    <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-200 px-5 py-4 sm:px-6">
            <h2 class="text-lg font-black">Equipos emparejados</h2>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="p-3">Equipo</th>
                        <th class="p-3">Usuario</th>
                        <th class="p-3">Impresora de tickets</th>
                        <th class="p-3">Estado</th>
                        <th class="p-3">Último latido</th>
                        <th class="p-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($agentes as $agente)
                        <tr class="border-t" wire:key="agente-{{ $agente->id }}">
                            <td class="p-3 font-semibold">
                                {{ $agente->name }}
                                <div class="text-xs text-gray-400">
                                    clave ····{{ $agente->token_last_four }}
                                    @if($agente->version) · v{{ $agente->version }} @endif
                                </div>
                            </td>
                            <td class="p-3">{{ $agente->user?->name ?? '—' }}</td>
                            <td class="p-3">{{ $agente->ticket_printer ?? '—' }}</td>
                            <td class="p-3">
                                @if(! $agente->is_active)
                                    <span class="rounded-full bg-gray-200 px-2 py-1 text-xs font-bold text-gray-700">Desactivado</span>
                                @elseif($agente->isOnline())
                                    <span class="rounded-full bg-emerald-100 px-2 py-1 text-xs font-bold text-emerald-800">En línea</span>
                                @else
                                    <span class="rounded-full bg-amber-100 px-2 py-1 text-xs font-bold text-amber-900">Desconectado</span>
                                @endif
                            </td>
                            <td class="p-3 text-gray-600">
                                {{ $agente->last_seen_at?->diffForHumans() ?? 'Nunca' }}
                            </td>
                            <td class="p-3 text-right">
                                @if($agente->is_active)
                                    <button wire:click="revocar({{ $agente->id }})"
                                        wire:confirm="¿Desactivar este equipo? Dejará de imprimir hasta que se genere otra clave."
                                        class="text-xs font-bold text-red-600 underline">
                                        Desactivar
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-6 text-center text-gray-500">
                                Todavía no hay computadoras emparejadas.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
