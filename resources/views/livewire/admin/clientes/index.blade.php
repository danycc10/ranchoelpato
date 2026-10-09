<div class="max-w-6xl mx-auto p-4 sm:p-6 space-y-4 sm:space-y-6">

    {{-- Liga recien generada: se muestra una sola vez --}}
    @if($ligaDatos)
    <div class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/70 p-4">
        <div class="w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl">
            <div class="border-b px-5 py-4">
                <h2 class="text-lg font-black">Liga para {{ $ligaDatosCliente }}</h2>
                <p class="mt-1 text-sm text-gray-600">
                    Mándasela al cliente para que complete sus datos. Vence en una semana
                    y deja de servir en cuanto se revise lo que capture.
                </p>
            </div>

            <div class="space-y-3 p-5">
                <input type="text" readonly value="{{ $ligaDatos }}"
                    onclick="this.select()"
                    class="w-full rounded-xl border bg-gray-50 px-3 py-2.5 font-mono text-xs">

                <div class="flex justify-end gap-2">
                    <button type="button"
                        onclick="navigator.clipboard.writeText(@js($ligaDatos)); this.textContent='Copiada';"
                        class="rounded-xl bg-black px-4 py-2 text-sm font-bold text-white">
                        Copiar liga
                    </button>

                    <button wire:click="cerrarLigaDatos"
                        class="rounded-xl border px-4 py-2 text-sm font-bold hover:bg-gray-50">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-2xl font-black">Clientes</h1>
            <p class="text-gray-500 text-sm">Alta y administración de clientes.</p>
        </div>

        <a href="{{ route('admin.clientes.revision-datos') }}"
            class="w-full sm:w-auto px-4 py-2 rounded-xl border font-bold text-center hover:bg-gray-50">
            Datos por revisar
        </a>

        <button
            wire:click="crear"
            class="w-full sm:w-auto px-4 py-2 rounded-xl bg-black text-white font-bold">
            + Nuevo
        </button>
    </div>

    {{-- Buscar y filtrar --}}
    <div class="grid gap-3 sm:grid-cols-[1fr_auto]">
        <input
            type="text"
            wire:model.live.debounce.300ms="q"
            class="w-full rounded-xl border-gray-300"
            placeholder="Buscar por nombre, apellidos, nombre legal, teléfono, correo, CURP o RFC...">

        <select wire:model.live="ligaFilter"
                class="w-full rounded-xl border-gray-300 sm:w-64"
                aria-label="Filtrar por el estado de la liga">
            <option value="">Todas las ligas</option>
            <option value="contestada">Contestadas · por revisar</option>
            <option value="enviada">Enviadas · sin contestar</option>
            <option value="vencida">Vencidas</option>
            <option value="sin">Sin liga</option>
        </select>
    </div>

    @if($ligaFilter !== '')
        <div class="flex items-center gap-2 text-sm text-gray-600">
            <span>Mostrando solo: <span class="font-bold">{{ [
                'contestada' => 'contestadas, por revisar',
                'enviada' => 'enviadas sin contestar',
                'vencida' => 'ligas vencidas',
                'sin' => 'clientes sin liga',
            ][$ligaFilter] ?? '' }}</span></span>

            <button type="button" wire:click="$set('ligaFilter', '')"
                    class="font-semibold text-indigo-700 underline">
                Quitar filtro
            </button>
        </div>
    @endif

    {{-- TABLA --}}
    <div class="hidden md:block overflow-x-auto bg-white rounded-2xl border">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="text-left p-3">
                        <button type="button" wire:click="sort('id')" class="font-bold">#</button>
                    </th>
                    <th class="text-left p-3">
                        <button type="button" wire:click="sort('nombres')" class="font-bold">Cliente</button>
                    </th>
                    <th class="text-left p-3">Teléfono</th>
                    <th class="text-left p-3">Correo</th>
                    <th class="text-left p-3">CURP</th>
                    <th class="text-left p-3">INE</th>
                    <th class="text-left p-3">Expediente</th>
                    <th class="text-left p-3">Liga</th>
                    <th class="text-left p-3">
                        <button type="button" wire:click="sort('estatus')" class="font-bold">Estatus</button>
                    </th>
                    <th class="text-right p-3">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $it)
                <tr class="border-t" wire:key="cliente-row-{{ $it->id }}">
                    <td class="p-3">{{ $it->id }}</td>

                    <td class="p-3">
                        <div class="font-semibold">{{ $it->nombre_completo }}</div>
                        <div class="text-xs text-gray-500">{{ $it->direccion ?: '—' }}</div>
                    </td>

                    <td class="p-3">
                        {{ $it->telefono ?: '—' }}
                        @if($it->telefonos_adicionales_count > 0)
                        <span class="ml-1 text-xs text-gray-500"
                            title="Tiene {{ $it->telefonos_adicionales_count }} teléfono(s) más">
                            +{{ $it->telefonos_adicionales_count }}
                        </span>
                        @endif
                    </td>

                    <td class="p-3">
                        <div class="max-w-[240px] break-all">
                            {{ $it->correo ?: '—' }}
                        </div>
                    </td>

                    <td class="p-3">{{ $it->curp ?: '—' }}</td>

                    <td class="p-3">
                        <div class="flex flex-wrap gap-1">
                            <span class="px-2 py-1 rounded-lg text-xs font-semibold {{ $it->ine_frente ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                                Frente {{ $it->ine_frente ? '✓' : '—' }}
                            </span>
                            <span class="px-2 py-1 rounded-lg text-xs font-semibold {{ $it->ine_reverso ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                                Reverso {{ $it->ine_reverso ? '✓' : '—' }}
                            </span>
                        </div>
                    </td>

                    {{-- Qué tan completo está su expediente --}}
                    @php
                        $avance = $progresos[$it->id];
                        $barra = match (true) {
                            $avance['porcentaje'] >= 100 => 'bg-emerald-500',
                            $avance['porcentaje'] >= 60 => 'bg-amber-500',
                            default => 'bg-red-500',
                        };
                    @endphp

                    <td class="p-3">
                        <div class="w-32"
                             title="{{ $avance['faltan'] ? 'Faltan: '.implode(', ', $avance['faltan']) : 'Expediente completo' }}">
                            <div class="flex items-center justify-between text-xs font-semibold">
                                <span class="{{ $avance['porcentaje'] >= 100 ? 'text-emerald-700' : 'text-gray-700' }}">
                                    {{ $avance['llenos'] }}/{{ $avance['total'] }}
                                </span>
                                <span class="text-gray-500">{{ $avance['porcentaje'] }}%</span>
                            </div>

                            <div class="mt-1 h-1.5 w-full overflow-hidden rounded-full bg-gray-200">
                                <div class="h-full rounded-full {{ $barra }}" style="width: {{ $avance['porcentaje'] }}%"></div>
                            </div>
                        </div>
                    </td>

                    {{-- Estado de la liga que se le mandó --}}
                    @php $liga = $ligas[$it->id] ?? null; @endphp

                    <td class="p-3">
                        @if(! $liga)
                            <span class="text-xs text-gray-400">Sin liga</span>
                        @elseif($liga->esperaRevision())
                            <a href="{{ route('admin.clientes.revision-datos') }}"
                               class="inline-flex items-center gap-1 rounded-lg bg-indigo-100 px-2 py-1 text-xs font-bold text-indigo-800 hover:bg-indigo-200">
                                Contestada · revisar
                            </a>
                        @elseif($liga->vencida())
                            <span class="rounded-lg bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-600"
                                  title="Venció el {{ $liga->expira_en->format('d/m/Y') }}">
                                Liga vencida
                            </span>
                        @else
                            <span class="rounded-lg bg-amber-100 px-2 py-1 text-xs font-semibold text-amber-900"
                                  title="Vence el {{ $liga->expira_en->format('d/m/Y') }}">
                                Enviada · sin contestar
                            </span>
                        @endif
                    </td>

                    <td class="p-3">
                        <span class="px-2 py-1 rounded-full text-xs
                                {{ $it->estatus === 'activo' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700' }}">
                            {{ ucfirst($it->estatus) }}
                        </span>
                    </td>

                    <td class="p-3 whitespace-nowrap">
                        <div class="flex items-center justify-end gap-1">

                            <button wire:click="editar({{ $it->id }})"
                                title="Editar cliente" aria-label="Editar cliente"
                                class="rounded-xl border p-2 text-gray-700 transition hover:bg-gray-50 hover:text-black">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125" />
                                </svg>
                            </button>

                            <button wire:click="generarLigaDatos({{ $it->id }})"
                                title="Generar liga para que el cliente capture sus datos"
                                aria-label="Pedir datos al cliente"
                                class="rounded-xl border p-2 text-indigo-600 transition hover:bg-indigo-50">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
                                </svg>
                            </button>

                            @if($it->estatus === 'activo')
                                <button wire:click="toggleEstatus({{ $it->id }})"
                                    title="Desactivar cliente" aria-label="Desactivar cliente"
                                    class="rounded-xl border p-2 text-amber-600 transition hover:bg-amber-50">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 0 0 5.636 5.636m12.728 12.728A9 9 0 0 1 5.636 5.636m12.728 12.728L5.636 5.636" />
                                    </svg>
                                </button>
                            @else
                                <button wire:click="toggleEstatus({{ $it->id }})"
                                    title="Activar cliente" aria-label="Activar cliente"
                                    class="rounded-xl border p-2 text-emerald-600 transition hover:bg-emerald-50">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m9 12.75 2.25 2.25L15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                    </svg>
                                </button>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td class="p-4 text-gray-500" colspan="11">Sin clientes.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- MOBILE --}}
    <div class="md:hidden space-y-3">
        @forelse($items as $it)
        <div
            wire:key="cliente-card-{{ $it->id }}"
            class="rounded-2xl border bg-white p-4 overflow-hidden">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="font-black leading-tight break-words">
                        {{ $it->nombre_completo }}
                    </div>

                    <div class="mt-1 text-sm text-gray-600 break-words">
                        <span class="text-gray-500">Dirección:</span>
                        <span class="font-semibold">{{ $it->direccion ?: '—' }}</span>
                    </div>
                </div>

                <div class="shrink-0 text-right">
                    <div class="text-xs text-gray-500">Estatus</div>
                    <span class="inline-flex px-2 py-1 rounded-full text-xs font-bold
                            {{ $it->estatus === 'activo' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700' }}">
                        {{ ucfirst($it->estatus) }}
                    </span>
                </div>
            </div>

            <div class="mt-3 grid grid-cols-1 gap-2 text-sm">
                <div class="flex items-start justify-between gap-3">
                    <div class="text-gray-500 shrink-0">Teléfono</div>
                    <div class="font-semibold text-right min-w-0 break-words">
                        {{ $it->telefono ?: '—' }}
                    </div>
                </div>

                <div class="flex items-start justify-between gap-3">
                    <div class="text-gray-500 shrink-0">Correo</div>
                    <div class="font-semibold text-right min-w-0 break-all">
                        {{ $it->correo ?: '—' }}
                    </div>
                </div>

                <div class="flex items-start justify-between gap-3">
                    <div class="text-gray-500 shrink-0">CURP</div>
                    <div class="font-semibold text-right min-w-0 break-words">
                        {{ $it->curp ?: '—' }}
                    </div>
                </div>

                <div class="pt-1">
                    <div class="text-gray-500 text-xs mb-1">INE</div>
                    <div class="flex flex-wrap gap-1">
                        <span class="px-2 py-1 rounded-lg text-xs font-semibold {{ $it->ine_frente ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                            Frente {{ $it->ine_frente ? '✓' : '—' }}
                        </span>
                        <span class="px-2 py-1 rounded-lg text-xs font-semibold {{ $it->ine_reverso ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                            Reverso {{ $it->ine_reverso ? '✓' : '—' }}
                        </span>
                    </div>
                </div>

                {{-- Avance del expediente y estado de la liga --}}
                @php
                    $avance = $progresos[$it->id];
                    $liga = $ligas[$it->id] ?? null;
                    $barra = match (true) {
                        $avance['porcentaje'] >= 100 => 'bg-emerald-500',
                        $avance['porcentaje'] >= 60 => 'bg-amber-500',
                        default => 'bg-red-500',
                    };
                @endphp

                <div class="pt-1">
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-gray-500">Expediente</span>
                        <span class="font-bold {{ $avance['porcentaje'] >= 100 ? 'text-emerald-700' : 'text-gray-700' }}">
                            {{ $avance['llenos'] }}/{{ $avance['total'] }} · {{ $avance['porcentaje'] }}%
                        </span>
                    </div>

                    <div class="mt-1 h-1.5 w-full overflow-hidden rounded-full bg-gray-200">
                        <div class="h-full rounded-full {{ $barra }}" style="width: {{ $avance['porcentaje'] }}%"></div>
                    </div>

                    @if($avance['faltan'])
                        <p class="mt-1 text-[11px] text-gray-500">
                            Falta: {{ implode(', ', array_slice($avance['faltan'], 0, 3)) }}@if(count($avance['faltan']) > 3) y {{ count($avance['faltan']) - 3 }} más @endif
                        </p>
                    @endif

                    <div class="mt-2">
                        @if(! $liga)
                            <span class="text-xs text-gray-400">Sin liga generada</span>
                        @elseif($liga->esperaRevision())
                            <a href="{{ route('admin.clientes.revision-datos') }}"
                               class="inline-block rounded-lg bg-indigo-100 px-2 py-1 text-xs font-bold text-indigo-800">
                                Contestada · revisar
                            </a>
                        @elseif($liga->vencida())
                            <span class="rounded-lg bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-600">
                                Liga vencida
                            </span>
                        @else
                            <span class="rounded-lg bg-amber-100 px-2 py-1 text-xs font-semibold text-amber-900">
                                Enviada · vence {{ $liga->expira_en->format('d/m') }}
                            </span>
                        @endif
                    </div>
                </div>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-2">
                <button
                    wire:click="editar({{ $it->id }})"
                    class="flex w-full items-center justify-center gap-2 rounded-xl border px-4 py-2 font-semibold hover:bg-gray-50">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125" />
                    </svg>
                    Editar
                </button>

                <button
                    wire:click="generarLigaDatos({{ $it->id }})"
                    class="flex w-full items-center justify-center gap-2 rounded-xl border px-4 py-2 font-semibold text-indigo-700 hover:bg-indigo-50">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
                    </svg>
                    Pedir datos
                </button>

                <button
                    wire:click="toggleEstatus({{ $it->id }})"
                    class="flex w-full items-center justify-center gap-2 rounded-xl border px-4 py-2 font-semibold hover:bg-gray-50
                        {{ $it->estatus === 'activo' ? 'text-amber-700' : 'text-emerald-700' }}">
                    @if($it->estatus === 'activo')
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 0 0 5.636 5.636m12.728 12.728A9 9 0 0 1 5.636 5.636m12.728 12.728L5.636 5.636" />
                        </svg>
                        Desactivar
                    @else
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m9 12.75 2.25 2.25L15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        Activar
                    @endif
                </button>
            </div>
        </div>
        @empty
        <div class="rounded-2xl border bg-white p-6 text-gray-500 text-center">
            Sin clientes.
        </div>
        @endforelse
    </div>

    {{-- Paginación --}}
    <div class="mt-4">
        {{ $items->links() }}
    </div>

    {{-- Modal --}}
    @if($modal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-4xl bg-white rounded-2xl p-4 sm:p-5 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-3 gap-3">
                <div class="font-black text-lg">
                    {{ $editId ? 'Editar cliente' : 'Nuevo cliente' }}
                </div>
                <button wire:click="$set('modal', false)" class="px-3 py-1 rounded-xl border">X</button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="text-sm font-semibold">Nombres</label>
                    <input type="text" wire:model.live="form.nombres" class="w-full mt-1 rounded-xl border-gray-300">
                    @error('form.nombres') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="text-sm font-semibold">Apellidos</label>
                    <input type="text" wire:model.live="form.apellidos" class="w-full mt-1 rounded-xl border-gray-300">
                    @error('form.apellidos') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="text-sm font-semibold">Nombre legal</label>
                    <input type="text" wire:model.live="form.nombre_legal" class="w-full mt-1 rounded-xl border-gray-300">
                    @error('form.nombre_legal') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="text-sm font-semibold">Teléfonos</label>
                    <p class="text-xs text-gray-400 mt-0.5">
                        El principal es el que sale en contratos y reportes. Si solo tiene uno,
                        déjalo en «Llamadas y WhatsApp».
                    </p>

                    {{-- Encabezados: con tres campos por renglón, sin ellos no se
                         entiende cuál es cuál. --}}
                    <div class="hidden sm:grid grid-cols-[1fr_12rem_1fr_5.5rem] gap-2 mt-3 px-1 text-xs font-semibold text-gray-500">
                        <div>Número</div>
                        <div>¿Para qué sirve?</div>
                        <div>Nota (opcional)</div>
                        <div></div>
                    </div>

                    {{-- El principal --}}
                    <div class="grid sm:grid-cols-[1fr_12rem_1fr_5.5rem] gap-2 mt-1">
                        <div>
                            <input type="text" wire:model.live="form.telefono"
                                class="w-full rounded-xl border-gray-300" placeholder="8781234567">
                            @error('form.telefono') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        <select wire:model.live="form.telefono_uso"
                            class="w-full rounded-xl border-gray-300 text-sm">
                            @foreach(\App\Models\ClienteTelefono::USOS as $valor => $texto)
                            <option value="{{ $valor }}">{{ $texto }}</option>
                            @endforeach
                        </select>

                        <div class="flex items-center text-sm text-gray-500 px-1">Principal</div>

                        <div></div>
                    </div>

                    {{-- Los demás números del cliente --}}
                    @foreach($telefonosExtra as $i => $extra)
                    <div wire:key="tel-extra-{{ $i }}"
                        class="grid sm:grid-cols-[1fr_12rem_1fr_5.5rem] gap-2 mt-2">
                        <div>
                            <input type="text" wire:model.live="telefonosExtra.{{ $i }}.telefono"
                                class="w-full rounded-xl border-gray-300" placeholder="Otro teléfono">
                            @error('telefonosExtra.'.$i.'.telefono')
                            <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <select wire:model.live="telefonosExtra.{{ $i }}.uso"
                            class="w-full rounded-xl border-gray-300 text-sm">
                            @foreach(\App\Models\ClienteTelefono::USOS as $valor => $texto)
                            <option value="{{ $valor }}">{{ $texto }}</option>
                            @endforeach
                        </select>

                        <div>
                            <input type="text" wire:model.live="telefonosExtra.{{ $i }}.etiqueta"
                                class="w-full rounded-xl border-gray-300" placeholder="Casa, recados…">
                            @error('telefonosExtra.'.$i.'.etiqueta')
                            <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex gap-1">
                            <button type="button" wire:click="hacerPrincipal({{ $i }})"
                                title="Volverlo el principal"
                                class="h-10 w-10 rounded-xl border font-semibold hover:bg-gray-50">
                                &uarr;
                            </button>

                            <button type="button" wire:click="quitarTelefono({{ $i }})"
                                title="Quitar este teléfono"
                                class="h-10 w-10 rounded-xl border text-red-600 hover:bg-red-50">
                                &times;
                            </button>
                        </div>
                    </div>
                    @endforeach

                    <button type="button" wire:click="agregarTelefono"
                        class="mt-3 text-sm font-semibold text-gray-600 hover:text-black">
                        + Agregar otro teléfono
                    </button>
                </div>

                <div>
                    <label class="text-sm font-semibold">Correo</label>
                    <input type="email" wire:model.live="form.correo" class="w-full mt-1 rounded-xl border-gray-300" placeholder="cliente@email.com">
                    @error('form.correo') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="text-sm font-semibold">Dirección</label>
                    <input type="text" wire:model.live="form.direccion" class="w-full mt-1 rounded-xl border-gray-300">
                    @error('form.direccion') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="text-sm font-semibold">RFC</label>
                    <input type="text" wire:model.live="form.rfc" class="w-full mt-1 rounded-xl border-gray-300">
                    @error('form.rfc') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="text-sm font-semibold">CURP</label>
                    <input type="text" wire:model.live="form.curp" class="w-full mt-1 rounded-xl border-gray-300">
                    @error('form.curp') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Datos que declara el cliente en el contrato de cesión de derechos --}}
                <div class="md:col-span-2 pt-2 border-t">
                    <p class="text-sm font-bold text-gray-700">Datos para el contrato</p>
                    <p class="text-xs text-gray-500">Se imprimen en la declaración del contrato de cesión de derechos.</p>
                </div>

                <div>
                    <label class="text-sm font-semibold">Tipo de persona</label>
                    <select wire:model.live="form.persona_tipo" class="w-full mt-1 rounded-xl border-gray-300">
                        <option value="">—</option>
                        @foreach(\App\Services\Contratos\DatosIdentidadService::PERSONA_TIPOS as $valor => $etiqueta)
                        <option value="{{ $valor }}">{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                    @error('form.persona_tipo') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="text-sm font-semibold">Nacionalidad</label>
                    <input type="text" wire:model.live="form.nacionalidad" class="w-full mt-1 rounded-xl border-gray-300">
                    @error('form.nacionalidad') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="text-sm font-semibold">Estado civil</label>
                    <select wire:model.live="form.estado_civil" class="w-full mt-1 rounded-xl border-gray-300">
                        <option value="">—</option>
                        @foreach(\App\Services\Contratos\DatosIdentidadService::ESTADOS_CIVILES as $opcion)
                        <option value="{{ $opcion }}">{{ $opcion }}</option>
                        @endforeach
                    </select>
                    @error('form.estado_civil') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="text-sm font-semibold">Ocupación</label>
                    <input type="text" wire:model.live="form.ocupacion" class="w-full mt-1 rounded-xl border-gray-300">
                    @error('form.ocupacion') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="text-sm font-semibold">Identificación oficial</label>
                    <select wire:model.live="form.identificacion_tipo" class="w-full mt-1 rounded-xl border-gray-300">
                        <option value="">—</option>
                        @foreach(\App\Services\Contratos\DatosIdentidadService::IDENTIFICACIONES as $opcion)
                        <option value="{{ $opcion }}">{{ $opcion }}</option>
                        @endforeach
                    </select>
                    @error('form.identificacion_tipo') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="text-sm font-semibold">Número de identificación</label>
                    <input type="text" wire:model.live="form.identificacion_numero" class="w-full mt-1 rounded-xl border-gray-300">
                    @error('form.identificacion_numero') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="text-sm font-semibold">INE frente</label>
                    <input type="file"
                        wire:model="ineFrenteFile"
                        class="w-full mt-1 rounded-xl border-gray-300">

                    <p class="text-xs text-gray-500 mt-1">JPG, PNG, WEBP o PDF. Máx 10 MB.</p>
                    @error('ineFrenteFile') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror

                    @if($this->ineFrentePreview)
                    <div class="mt-3 rounded-xl border bg-gray-50 p-3"
                        wire:key="preview-ine-frente-{{ md5(($ine_frente ?? '') . ($this->ineFrentePreview ?? '')) }}">
                        @if($this->ineFrenteEsPdf)
                        <a href="{{ $this->ineFrentePreview }}"
                            target="_blank"
                            class="flex items-center justify-between gap-3 rounded-xl border bg-white px-3 py-3 hover:bg-gray-50">
                            <div>
                                <p class="text-sm font-semibold text-gray-800">Vista previa disponible</p>
                                <p class="text-xs text-gray-500">PDF cargado. Haz clic para abrirlo.</p>
                            </div>
                            <span class="px-2 py-1 rounded-lg bg-red-100 text-red-700 text-xs font-bold">PDF</span>
                        </a>
                        @else
                        <a href="{{ $this->ineFrentePreview }}" target="_blank" rel="noopener"
                            class="group block cursor-zoom-in rounded-xl focus:outline-none focus-visible:ring-2 focus-visible:ring-black focus-visible:ring-offset-2">
                            <img src="{{ $this->ineFrentePreview }}"
                                alt="Vista previa INE frente"
                                class="relative z-0 w-full h-48 rounded-xl border object-contain bg-white transform-gpu transition-transform duration-200 ease-out motion-reduce:transform-none motion-reduce:transition-none group-hover:z-10 group-hover:scale-[1.12] group-focus:scale-[1.12]">
                        </a>
                        @endif
                    </div>
                    @endif

                    @if($ineFrenteFile)
                    <p class="text-xs text-green-700 mt-2 font-semibold">
                        Nuevo archivo seleccionado: {{ $ineFrenteFile->getClientOriginalName() }}
                    </p>
                    @elseif($ine_frente)
                    <div class="mt-2 flex items-center justify-between gap-2 rounded-xl bg-gray-50 border px-3 py-2">
                        <span class="text-xs text-gray-700 break-all">Documento actual cargado</span>
                        <button type="button"
                            wire:click="eliminarDocumento('ine_frente')"
                            class="text-xs font-bold text-red-600">
                            Quitar
                        </button>
                    </div>
                    @endif
                </div>

                <div>
                    <label class="text-sm font-semibold">INE reverso</label>
                    <input type="file"
                        wire:model="ineReversoFile"
                        class="w-full mt-1 rounded-xl border-gray-300">

                    <p class="text-xs text-gray-500 mt-1">JPG, PNG, WEBP o PDF. Máx 10 MB.</p>
                    @error('ineReversoFile') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror

                    @if($this->ineReversoPreview)
                    <div class="mt-3 rounded-xl border bg-gray-50 p-3"
                        wire:key="preview-ine-reverso-{{ md5(($ine_reverso ?? '') . ($this->ineReversoPreview ?? '')) }}">
                        @if($this->ineReversoEsPdf)
                        <a href="{{ $this->ineReversoPreview }}"
                            target="_blank"
                            class="flex items-center justify-between gap-3 rounded-xl border bg-white px-3 py-3 hover:bg-gray-50">
                            <div>
                                <p class="text-sm font-semibold text-gray-800">Vista previa disponible</p>
                                <p class="text-xs text-gray-500">PDF cargado. Haz clic para abrirlo.</p>
                            </div>
                            <span class="px-2 py-1 rounded-lg bg-red-100 text-red-700 text-xs font-bold">PDF</span>
                        </a>
                        @else
                        <a href="{{ $this->ineReversoPreview }}" target="_blank" rel="noopener"
                            class="group block cursor-zoom-in rounded-xl focus:outline-none focus-visible:ring-2 focus-visible:ring-black focus-visible:ring-offset-2">
                            <img src="{{ $this->ineReversoPreview }}"
                                alt="Vista previa INE reverso"
                                class="relative z-0 w-full h-48 rounded-xl border object-contain bg-white transform-gpu transition-transform duration-200 ease-out motion-reduce:transform-none motion-reduce:transition-none group-hover:z-10 group-hover:scale-[1.12] group-focus:scale-[1.12]">
                        </a>
                        @endif
                    </div>
                    @endif

                    @if($ineReversoFile)
                    <p class="text-xs text-green-700 mt-2 font-semibold">
                        Nuevo archivo seleccionado: {{ $ineReversoFile->getClientOriginalName() }}
                    </p>
                    @elseif($ine_reverso)
                    <div class="mt-2 flex items-center justify-between gap-2 rounded-xl bg-gray-50 border px-3 py-2">
                        <span class="text-xs text-gray-700 break-all">Documento actual cargado</span>
                        <button type="button"
                            wire:click="eliminarDocumento('ine_reverso')"
                            class="text-xs font-bold text-red-600">
                            Quitar
                        </button>
                    </div>
                    @endif
                </div>

                <div class="md:col-span-2">
                    <label class="text-sm font-semibold">Notas</label>
                    <textarea wire:model.live="form.notas" rows="3" class="w-full mt-1 rounded-xl border-gray-300"></textarea>
                    @error('form.notas') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="mt-5 grid grid-cols-1 sm:flex sm:justify-end gap-2">
                <button wire:click="$set('modal', false)" class="w-full sm:w-auto px-4 py-2 rounded-xl border">
                    Cancelar
                </button>
                <button
                    wire:click="guardar"
                    wire:loading.attr="disabled"
                    wire:target="guardar,ineFrenteFile,ineReversoFile"
                    class="w-full sm:w-auto px-5 py-2 rounded-xl bg-black text-white font-bold disabled:opacity-50 disabled:cursor-not-allowed">
                    <span wire:loading.remove wire:target="guardar,ineFrenteFile,ineReversoFile">
                        Guardar
                    </span>

                    <span wire:loading wire:target="guardar,ineFrenteFile,ineReversoFile">
                        Guardando...
                    </span>
                </button>
            </div>
        </div>
    </div>
    @endif
</div>
