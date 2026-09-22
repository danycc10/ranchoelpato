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
            $porDefecto = $this->camposPorDefecto($solicitud);
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
                                           wire:model="seleccion.{{ $solicitud->id }}"
                                           @checked(in_array($fila['campo'], $porDefecto, true))>
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

                        @if($solicitud->ine_frente || $solicitud->ine_reverso)
                            <tr class="border-t">
                                <td class="p-3">
                                    <input type="checkbox" class="rounded" value="ine_frente"
                                           wire:model="seleccion.{{ $solicitud->id }}"
                                           @checked(in_array('ine_frente', $porDefecto, true))
                                           @disabled(! $solicitud->ine_frente)>
                                </td>
                                <td class="p-3 font-semibold">Identificación</td>
                                <td class="p-3">—</td>
                                <td class="p-3 font-bold text-gray-900">
                                    {{ $solicitud->ine_frente ? 'Frente' : '' }}
                                    {{ $solicitud->ine_reverso ? ' y reverso' : '' }}
                                    subidos por el cliente
                                </td>
                            </tr>
                        @endif
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
