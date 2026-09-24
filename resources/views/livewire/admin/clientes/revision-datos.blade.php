<div class="mx-auto w-full max-w-5xl space-y-6 p-4 sm:p-6">

    <div class="flex items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black">Datos por revisar</h1>
            <p class="text-gray-500">
                Lo que los clientes capturaron con su liga. Nada de esto se ha aplicado todavía.
            </p>
        </div>

        <a href="{{ route('admin.clientes') }}" class="rounded-xl border px-4 py-2 text-sm font-bold hover:bg-gray-50">
            Ir a clientes
        </a>
    </div>

    @forelse($solicitudes as $solicitud)
        @php
            $filas = $this->comparacion($solicitud);
        @endphp

        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm"
                 wire:key="solicitud-{{ $solicitud->id }}">

            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-4">
                <div>
                    <h2 class="text-lg font-black">{{ $solicitud->cliente?->nombre_completo ?? 'Cliente eliminado' }}</h2>
                    <p class="text-xs text-gray-500">
                        Capturado {{ $solicitud->capturada_en?->diffForHumans() }}
                    </p>
                </div>

                <div class="flex gap-2">
                    <button wire:click="descartar({{ $solicitud->id }})"
                            wire:confirm="¿Descartar lo que capturó el cliente? La liga deja de servir."
                            class="rounded-xl border px-4 py-2 text-sm font-bold text-red-600 hover:bg-red-50">
                        Descartar
                    </button>

                    <button wire:click="aplicar({{ $solicitud->id }})"
                            wire:loading.attr="disabled"
                            wire:target="aplicar({{ $solicitud->id }})"
                            class="rounded-xl bg-black px-4 py-2 text-sm font-bold text-white disabled:opacity-50">
                        Aplicar lo seleccionado
                    </button>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="p-3 w-10"></th>
                            <th class="p-3">Dato</th>
                            <th class="p-3">Lo que había</th>
                            <th class="p-3">Lo que capturó</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($filas as $fila)
                            <tr class="border-t {{ $fila['cambia'] ? '' : 'text-gray-400' }}">
                                <td class="p-3">
                                    <input type="checkbox"
                                           class="rounded"
                                           value="{{ $fila['campo'] }}"
                                           wire:model.live="seleccion.{{ $solicitud->id }}">
                                </td>
                                <td class="p-3 font-semibold">{{ $fila['etiqueta'] }}</td>
                                <td class="p-3">{{ $fila['actual'] ?: '—' }}</td>
                                <td class="p-3 {{ $fila['cambia'] ? 'font-bold text-gray-900' : '' }}">
                                    {{ $fila['nuevo'] }}
                                    @unless($fila['cambia'])
                                        <span class="ml-1 text-xs">(sin cambio)</span>
                                    @endunless
                                </td>
                            </tr>
                        @endforeach

                        @foreach([
                            ['campo' => 'ine_frente', 'titulo' => 'Identificación (frente)', 'lado' => 'frente', 'archivo' => $solicitud->ine_frente, 'actual' => $solicitud->cliente?->ine_frente],
                            ['campo' => 'ine_reverso', 'titulo' => 'Identificación (reverso)', 'lado' => 'reverso', 'archivo' => $solicitud->ine_reverso, 'actual' => $solicitud->cliente?->ine_reverso],
                        ] as $credencial)
                            @continue(! $credencial['archivo'])

                            <tr class="border-t" wire:key="ine-{{ $solicitud->id }}-{{ $credencial['campo'] }}">
                                <td class="p-3 align-top">
                                    <input type="checkbox" class="rounded"
                                           value="{{ $credencial['campo'] }}"
                                           wire:model.live="seleccion.{{ $solicitud->id }}">
                                </td>
                                <td class="p-3 align-top font-semibold">{{ $credencial['titulo'] }}</td>
                                <td class="p-3 align-top">{{ $credencial['actual'] ? 'Ya tenía una' : '—' }}</td>
                                <td class="p-3">
                                    {{-- Hay que verla para poder validarla. --}}
                                    <a href="{{ route('admin.clientes.solicitud-ine', ['solicitud' => $solicitud->id, 'lado' => $credencial['lado']]) }}"
                                       target="_blank" rel="noopener"
                                       title="Abrir la imagen en grande">
                                        <img src="{{ route('admin.clientes.solicitud-ine', ['solicitud' => $solicitud->id, 'lado' => $credencial['lado']]) }}"
                                             alt="{{ $credencial['titulo'] }} que subió el cliente"
                                             loading="lazy"
                                             class="h-32 w-auto max-w-[240px] rounded-xl border object-contain hover:opacity-90">
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @empty
        <div class="rounded-2xl border border-gray-200 bg-white p-10 text-center text-gray-500">
            No hay datos por revisar.
        </div>
    @endforelse

    <div>{{ $solicitudes->links() }}</div>
</div>
