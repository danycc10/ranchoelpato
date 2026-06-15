<div class="max-w-7xl mx-auto p-4 sm:p-6 space-y-6">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
        <div>
            <h1 class="text-2xl font-black">Logs</h1>
            <p class="text-gray-500">Historial de actividad (Spatie Activitylog).</p>
        </div>

        <button
            wire:click="limpiarFiltros"
            class="px-4 py-2 rounded-xl border hover:bg-gray-50 font-semibold text-sm w-full sm:w-auto">
            Limpiar filtros
        </button>
    </div>

    {{-- Filtros --}}
    <div class="p-4 rounded-2xl border bg-white grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
        <input
            class="w-full rounded-xl border p-2 sm:col-span-2 lg:col-span-2"
            placeholder="Buscar (usuario, descripción, modelo, evento)..."
            wire:model.live.debounce.300ms="q">

        <select class="w-full rounded-xl border p-2" wire:model.live="logName">
            <option value="">Todos los canales</option>
            @foreach($logNames as $ln)
                <option value="{{ $ln }}">{{ $ln }}</option>
            @endforeach
        </select>

        <select class="w-full rounded-xl border p-2" wire:model.live="event">
            <option value="">Todos los eventos</option>
            @foreach($events as $ev)
                <option value="{{ $ev }}">{{ $ev }}</option>
            @endforeach
        </select>

        <input type="date" class="w-full rounded-xl border p-2" wire:model.live="from">
        <input type="date" class="w-full rounded-xl border p-2" wire:model.live="to">
    </div>

    {{-- Tabla --}}
    <div class="overflow-x-auto rounded-2xl border bg-white">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr class="text-left">
                    <th class="p-3">Fecha</th>
                    <th class="p-3">Usuario</th>
                    <th class="p-3">Evento</th>
                    <th class="p-3">Canal</th>
                    <th class="p-3">Modelo</th>
                    <th class="p-3">Descripción</th>
                    <th class="p-3 text-right">Acciones</th>
                </tr>
            </thead>

            <tbody>
                @forelse($logs as $log)
                    @php
                        $user = $log->causer;
                        $subjectType = $log->subject_type ? class_basename($log->subject_type) : '—';
                    @endphp
                    <tr class="border-t hover:bg-gray-50">
                        <td class="p-3 whitespace-nowrap">
                            <div class="font-semibold">{{ $log->created_at->format('d/m/Y') }}</div>
                            <div class="text-xs text-gray-500">{{ $log->created_at->format('H:i:s') }}</div>
                        </td>

                        <td class="p-3">
                            <div class="font-semibold">{{ $user?->name ?? 'Sistema' }}</div>
                            <div class="text-xs text-gray-500">{{ $user?->email ?? '' }}</div>
                        </td>

                        <td class="p-3">
                            <span class="px-2 py-1 rounded-lg border text-xs font-bold">
                                {{ $log->event ?? '—' }}
                            </span>
                        </td>

                        <td class="p-3">
                            <span class="text-xs font-semibold">{{ $log->log_name ?? 'default' }}</span>
                        </td>

                        <td class="p-3">
                            <span class="text-xs font-semibold">{{ $subjectType }}</span>
                            @if($log->subject_id)
                                <span class="text-xs text-gray-500">#{{ $log->subject_id }}</span>
                            @endif
                        </td>

                        <td class="p-3">
                            <div class="font-semibold">{{ $log->description }}</div>
                        </td>

                        <td class="p-3 text-right">
                            <div class="flex justify-end gap-2 flex-wrap">
                                @can('logs.detalle')
                                    <button
                                        class="px-3 py-1.5 rounded-xl border hover:bg-gray-100 font-semibold"
                                        wire:click="verDetalle({{ $log->id }})">
                                        Ver
                                    </button>
                                @endcan

                                @can('logs.eliminar')
                                    <button
                                        class="px-3 py-1.5 rounded-xl border border-red-300 bg-red-50 hover:bg-red-100 font-semibold text-red-700"
                                        wire:click="eliminar({{ $log->id }})"
                                        onclick="return confirm('¿Eliminar este log?')">
                                        Eliminar
                                    </button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td class="p-6 text-gray-500 text-center" colspan="7">
                            No hay logs con esos filtros.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>
        {{ $logs->links() }}
    </div>

    {{-- Modal Detalle --}}
    @if($modalDetalle && $this->activity)
        @php
            $a = $this->activity;
            $props = (array) ($a->properties ?? []);
            $attrs = $props['attributes'] ?? null;
            $old   = $props['old'] ?? null;
        @endphp

        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/40" wire:click="cerrarDetalle"></div>

            <div class="relative w-full max-w-3xl bg-white rounded-2xl border shadow-xl p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-black">Detalle del Log</h3>
                        <p class="text-sm text-gray-500 mt-1">
                            {{ $a->created_at->format('d/m/Y H:i:s') }} ·
                            {{ $a->causer?->name ?? 'Sistema' }} ·
                            {{ $a->log_name ?? 'default' }}
                        </p>
                    </div>

                    <button class="px-2 py-1 rounded-lg border hover:bg-gray-50"
                        wire:click="cerrarDetalle">✕</button>
                </div>

                <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="rounded-xl border p-3">
                        <div class="text-xs text-gray-500">Evento</div>
                        <div class="font-bold">{{ $a->event ?? '—' }}</div>

                        <div class="text-xs text-gray-500 mt-3">Descripción</div>
                        <div class="font-semibold">{{ $a->description }}</div>

                        <div class="text-xs text-gray-500 mt-3">Subject</div>
                        <div class="font-semibold">
                            {{ $a->subject_type ? class_basename($a->subject_type) : '—' }}
                            @if($a->subject_id) #{{ $a->subject_id }} @endif
                        </div>
                    </div>

                    <div class="rounded-xl border p-3">
                        <div class="text-xs text-gray-500">Usuario</div>
                        <div class="font-bold">{{ $a->causer?->name ?? 'Sistema' }}</div>
                        <div class="text-sm text-gray-500">{{ $a->causer?->email ?? '' }}</div>

                        <div class="text-xs text-gray-500 mt-3">IP / User Agent</div>
                        <div class="text-sm font-semibold break-words">
                            {{ $a->properties['ip'] ?? '—' }}
                        </div>
                        <div class="text-xs text-gray-500 break-words mt-1">
                            {{ $a->properties['user_agent'] ?? '' }}
                        </div>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="rounded-xl border p-3">
                        <div class="text-sm font-black mb-2">Attributes (nuevo)</div>
                        <pre class="text-xs bg-gray-50 p-3 rounded-xl overflow-auto">{{ json_encode($attrs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                    </div>

                    <div class="rounded-xl border p-3">
                        <div class="text-sm font-black mb-2">Old (anterior)</div>
                        <pre class="text-xs bg-gray-50 p-3 rounded-xl overflow-auto">{{ json_encode($old, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                    </div>
                </div>

                <div class="mt-4 flex justify-end gap-2">
                    @can('logs.eliminar')
                        <button
                            class="px-4 py-2 rounded-xl border border-red-300 bg-red-50 hover:bg-red-100 font-semibold text-red-700"
                            wire:click="eliminar({{ $a->id }})"
                            onclick="return confirm('¿Eliminar este log?')">
                            Eliminar
                        </button>
                    @endcan

                    <button
                        class="px-4 py-2 rounded-xl bg-black text-white hover:bg-gray-800 font-semibold"
                        wire:click="cerrarDetalle">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>