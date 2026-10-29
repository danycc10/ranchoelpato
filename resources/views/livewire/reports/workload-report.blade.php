@php
    use App\Livewire\Reports\WorkloadReport;

    $e = $this->estadisticas;
    $cambio = $e['anteriores'] > 0 ? ($e['total'] - $e['anteriores']) / $e['anteriores'] * 100 : null;
    $maxHora = max(1, ...array_map(fn ($h) => $e['por_hora'][$h], $e['horas']));
    $maxDia = max(1, ...array_column($e['por_dia'], 'total'));
    $maxSemana = max(1, ...array_column($e['por_semana'], 'total'));
    $cadaCuantos = max(1, (int) ceil(count($e['por_dia']) / 16));
    $hora = fn (?int $h) => $h === null ? '—' : sprintf('%d:00', $h);
    $azul = '#2a78d6';
@endphp

<div class="max-w-7xl mx-auto p-4 space-y-6">

    {{-- Encabezado --}}
    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-3">
        <div>
            <h1 class="text-2xl font-black">Carga de trabajo</h1>
            <p class="text-gray-600">
                Cuántos recibos se generan por hora, día y semana, y quién los hace. Cuenta la hora en que se capturó cada recibo.
            </p>
        </div>

        <a href="{{ route('reportes.index') }}" class="rounded-xl border px-4 py-2 hover:bg-gray-50 self-start md:self-auto">
            ← Volver
        </a>
    </div>

    {{-- Filtros --}}
    <div class="bg-white rounded-2xl border p-4 flex flex-col lg:flex-row lg:items-end gap-4">
        <div class="flex flex-wrap gap-2">
            @foreach (['hoy' => 'Hoy', 'semana' => 'Esta semana', 'mes' => 'Este mes', '30' => 'Últimos 30 días'] as $clave => $texto)
                <button type="button" wire:click="rango('{{ $clave }}')" class="rounded-xl border px-3 py-2 text-sm hover:bg-gray-50">
                    {{ $texto }}
                </button>
            @endforeach
        </div>

        <div class="grid grid-cols-2 md:grid-cols-3 gap-3 flex-1">
            <div>
                <label class="text-sm text-gray-600">Desde</label>
                <input type="date" wire:model.live="desde" class="block w-full rounded-xl border px-3 py-2">
            </div>
            <div>
                <label class="text-sm text-gray-600">Hasta</label>
                <input type="date" wire:model.live="hasta" class="block w-full rounded-xl border px-3 py-2">
            </div>
            <div class="col-span-2 md:col-span-1">
                <label class="text-sm text-gray-600">Usuario</label>
                <select wire:model.live="usuarioId" class="block w-full rounded-xl border px-3 py-2">
                    <option value="">Todos</option>
                    @foreach ($this->usuarios as $u)
                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <div wire:loading.class="opacity-50" class="space-y-6 transition-opacity">

        {{-- Indicadores --}}
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
            <div class="bg-white rounded-2xl border p-4">
                <div class="text-sm text-gray-600">Recibos generados</div>
                <div class="text-3xl font-black mt-1">{{ number_format($e['total']) }}</div>
                <div class="text-xs text-gray-500 mt-1">
                    @if ($cambio === null)
                        Sin recibos del {{ $e['anterior_rango'] }} para comparar
                    @else
                        {{ $cambio >= 0 ? '▲' : '▼' }} {{ number_format(abs($cambio), 0) }}% contra el {{ $e['anterior_rango'] }} ({{ number_format($e['anteriores']) }})
                    @endif
                </div>
            </div>

            <div class="bg-white rounded-2xl border p-4">
                <div class="text-sm text-gray-600">Promedio por día trabajado</div>
                <div class="text-3xl font-black mt-1">{{ number_format($e['promedio_dia'], 1) }}</div>
                <div class="text-xs text-gray-500 mt-1">{{ $e['dias_trabajados'] }} {{ $e['dias_trabajados'] === 1 ? 'día' : 'días' }} con recibos</div>
            </div>

            <div class="bg-white rounded-2xl border p-4">
                <div class="text-sm text-gray-600">Hora más cargada</div>
                <div class="text-3xl font-black mt-1">{{ $hora($e['hora_pico']) }}</div>
                <div class="text-xs text-gray-500 mt-1">
                    @if ($e['hora_pico'] !== null)
                        {{ number_format($e['hora_pico_total']) }} recibos entre {{ $e['hora_pico'] }}:00 y {{ $e['hora_pico'] + 1 }}:00
                    @else
                        Sin recibos
                    @endif
                </div>
            </div>

            <div class="bg-white rounded-2xl border p-4">
                <div class="text-sm text-gray-600">Anulados</div>
                <div class="text-3xl font-black mt-1">{{ number_format($e['anulados']) }}</div>
                <div class="text-xs text-gray-500 mt-1">
                    {{ $e['total'] ? number_format($e['anulados'] / $e['total'] * 100, 1) : 0 }}% de los generados
                </div>
            </div>

            <div class="bg-white rounded-2xl border p-4 col-span-2 lg:col-span-1">
                <div class="text-sm text-gray-600">Monto cobrado</div>
                <div class="text-3xl font-black mt-1">${{ number_format($e['monto'], 0) }}</div>
                <div class="text-xs text-gray-500 mt-1">Sin contar anulados</div>
            </div>
        </div>

        <div class="grid lg:grid-cols-2 gap-6">

            {{-- Por hora --}}
            <div class="bg-white rounded-2xl border p-4">
                <div class="font-bold">Recibos por hora del día</div>
                <div class="text-sm text-gray-500">Total del periodo en cada hora.</div>

                <div class="mt-4 flex items-end gap-1 h-48 border-b border-gray-200">
                    @foreach ($e['horas'] as $h)
                        @php $n = $e['por_hora'][$h]; @endphp
                        <div class="flex-1 h-full flex flex-col justify-end items-center" title="{{ $h }}:00 a {{ $h + 1 }}:00 — {{ number_format($n) }} recibos">
                            @if ($n > 0)
                                <span class="text-xs text-gray-700 mb-1">{{ $n }}</span>
                            @endif
                            <div class="w-full max-w-[28px] rounded-t" style="height: {{ $n / $maxHora * 85 }}%; background: {{ $azul }};"></div>
                        </div>
                    @endforeach
                </div>
                <div class="flex gap-1 mt-1">
                    @foreach ($e['horas'] as $h)
                        <div class="flex-1 text-center text-xs text-gray-500">{{ $h }}</div>
                    @endforeach
                </div>
            </div>

            {{-- Por semana --}}
            <div class="bg-white rounded-2xl border p-4">
                <div class="font-bold">Recibos por semana</div>
                <div class="text-sm text-gray-500">Las 12 semanas que terminan en la del día «Hasta». Cada barra empieza en lunes.</div>

                <div class="mt-4 flex items-end gap-1 h-48 border-b border-gray-200">
                    @foreach ($e['por_semana'] as $s)
                        <div class="flex-1 h-full flex flex-col justify-end items-center" title="Semana del {{ $s['rango'] }} — {{ number_format($s['total']) }} recibos">
                            @if ($s['total'] > 0)
                                <span class="text-xs text-gray-700 mb-1">{{ $s['total'] }}</span>
                            @endif
                            <div class="w-full max-w-[36px] rounded-t" style="height: {{ $s['total'] / $maxSemana * 85 }}%; background: {{ $azul }};"></div>
                        </div>
                    @endforeach
                </div>
                <div class="flex gap-1 mt-1">
                    @foreach ($e['por_semana'] as $s)
                        <div class="flex-1 text-center text-xs text-gray-500">{{ $s['etiqueta'] }}</div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Por día --}}
        <div class="bg-white rounded-2xl border p-4">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <div>
                    <div class="font-bold">Recibos por día</div>
                    <div class="text-sm text-gray-500">Pasa el mouse sobre una barra para ver el día.</div>
                </div>
                <div class="text-sm text-gray-600">Día con más: <span class="font-semibold">{{ number_format(max(array_column($e['por_dia'], 'total'))) }}</span></div>
            </div>

            <div class="mt-4 flex items-end gap-px h-40 border-b border-gray-200">
                @foreach ($e['por_dia'] as $d)
                    <div class="flex-1 h-full flex flex-col justify-end items-center" title="{{ $d['dia_semana'] }} {{ $d['etiqueta'] }} — {{ number_format($d['total']) }} recibos">
                        <div class="w-full max-w-[36px] rounded-t" style="height: {{ $d['total'] / $maxDia * 100 }}%; background: {{ $azul }};"></div>
                    </div>
                @endforeach
            </div>
            <div class="flex gap-px mt-1">
                @foreach ($e['por_dia'] as $i => $d)
                    <div class="flex-1 text-center text-xs text-gray-500 whitespace-nowrap overflow-visible">
                        {{ $i % $cadaCuantos === 0 ? $d['etiqueta'] : '' }}
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Mapa de calor --}}
        <div class="bg-white rounded-2xl border p-4">
            <div class="font-bold">¿Cuándo se carga el trabajo?</div>
            <div class="text-sm text-gray-500">Recibos por día de la semana y hora, sumando todo el periodo. Más oscuro, más recibos.</div>

            <div class="mt-4 overflow-x-auto">
                <table class="border-separate" style="border-spacing: 2px;">
                    <thead>
                        <tr>
                            <th></th>
                            @foreach ($e['horas'] as $h)
                                <th class="text-xs font-normal text-gray-500 w-10">{{ $h }}</th>
                            @endforeach
                            <th class="text-xs font-semibold text-gray-600 pl-2 text-left">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (WorkloadReport::DIAS as $num => $nombre)
                            <tr>
                                <th class="text-xs font-semibold text-gray-600 pr-2 text-left">{{ $nombre }}</th>
                                @foreach ($e['horas'] as $h)
                                    @php
                                        $n = $e['calor'][$num][$h] ?? 0;
                                        $color = WorkloadReport::colorCalor($n, $e['calor_max']);
                                        $oscuro = $color && array_search($color, WorkloadReport::RAMPA, true) >= 3;
                                    @endphp
                                    <td class="h-9 w-10 text-center text-xs rounded {{ $color ? '' : 'bg-gray-50' }}"
                                        style="{{ $color ? "background: {$color};" : '' }} color: {{ $oscuro ? '#ffffff' : '#1f2937' }};"
                                        title="{{ $nombre }} {{ $h }}:00 a {{ $h + 1 }}:00 — {{ $n }} recibos">
                                        {{ $n ?: '' }}
                                    </td>
                                @endforeach
                                <td class="text-xs font-semibold text-gray-700 pl-2">{{ array_sum($e['calor'][$num] ?? []) ?: '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Por usuario --}}
        <div class="bg-white rounded-2xl border p-4">
            <div class="font-bold">Desempeño por usuario</div>
            <div class="text-sm text-gray-500">«Por hora trabajada» divide sus recibos entre las horas en que hizo al menos uno: su ritmo cuando está cobrando.</div>

            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-600 border-b">
                            <th class="py-2 pr-4">Usuario</th>
                            <th class="py-2 pr-4 text-right">Recibos</th>
                            <th class="py-2 pr-4">Parte del total</th>
                            <th class="py-2 pr-4 text-right">Días</th>
                            <th class="py-2 pr-4 text-right">Por día</th>
                            <th class="py-2 pr-4 text-right">Por hora trabajada</th>
                            <th class="py-2 pr-4 text-right">Hora más cargada</th>
                            <th class="py-2 pr-4 text-right">Anulados</th>
                            <th class="py-2 text-right">Monto</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($e['por_usuario'] as $u)
                            <tr class="border-b last:border-0">
                                <td class="py-2 pr-4 font-semibold">{{ $u['nombre'] }}</td>
                                <td class="py-2 pr-4 text-right">{{ number_format($u['total']) }}</td>
                                <td class="py-2 pr-4">
                                    <div class="flex items-center gap-2">
                                        <div class="h-2 w-24 bg-gray-100 rounded">
                                            <div class="h-2 rounded" style="width: {{ $u['porcentaje'] }}%; background: {{ $azul }};"></div>
                                        </div>
                                        <span class="text-xs text-gray-600">{{ number_format($u['porcentaje'], 0) }}%</span>
                                    </div>
                                </td>
                                <td class="py-2 pr-4 text-right">{{ $u['dias'] }}</td>
                                <td class="py-2 pr-4 text-right">{{ number_format($u['promedio_dia'], 1) }}</td>
                                <td class="py-2 pr-4 text-right">{{ number_format($u['por_hora_activa'], 1) }}</td>
                                <td class="py-2 pr-4 text-right">{{ $hora($u['hora_pico']) }}</td>
                                <td class="py-2 pr-4 text-right">{{ $u['anulados'] }}</td>
                                <td class="py-2 text-right">${{ number_format($u['monto'], 0) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="py-6 text-center text-gray-500">No se generaron recibos en este periodo.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
