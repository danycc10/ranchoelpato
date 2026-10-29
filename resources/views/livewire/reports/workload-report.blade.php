@php
    use App\Livewire\Reports\WorkloadReport;
    use App\Services\Reportes\CanalRecibo;

    $e = $this->estadisticas;
    $cambio = $e['anteriores'] > 0 ? ($e['total'] - $e['anteriores']) / $e['anteriores'] * 100 : null;
    $maxHora = max(1, ...array_map(fn ($h) => $e['por_hora'][$h], $e['horas']));
    $maxDia = max(1, ...array_column($e['por_dia'], 'total'));
    $maxSemana = max(1, ...array_column($e['por_semana'], 'total'));
    $cadaCuantos = max(1, (int) ceil(count($e['por_dia']) / 16));
    $hora = fn (?int $h) => $h === null ? '—' : sprintf('%d:00', $h);

    // El mapa de calor en total o en promedio por cada lunes, martes... del periodo.
    $celda = fn (int $dia, int $h) => ($e['calor'][$dia][$h] ?? 0) / ($promedio ? max(1, $e['veces_dia_semana'][$dia] ?? 1) : 1);
    $maxCelda = 0;
    foreach (array_keys(WorkloadReport::DIAS) as $dia) {
        foreach ($e['horas'] as $h) {
            $maxCelda = max($maxCelda, $celda($dia, $h));
        }
    }
    $formato = fn (float $v) => $v <= 0 ? '' : ($promedio ? number_format($v, 1) : number_format($v));

    $p = $this->pronostico;

    $minutos = fn (?float $m) => $m === null ? '—' : '~'.number_format($m, $m < 10 ? 1 : 0).' min';
    $canales = $canal === '' ? array_keys(CanalRecibo::NOMBRES) : [$canal];
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

        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 flex-1">
            <div>
                <label class="text-sm text-gray-600">Desde</label>
                <input type="date" wire:model.live="desde" class="block w-full rounded-xl border px-3 py-2">
            </div>
            <div>
                <label class="text-sm text-gray-600">Hasta</label>
                <input type="date" wire:model.live="hasta" class="block w-full rounded-xl border px-3 py-2">
            </div>
            <div>
                <label class="text-sm text-gray-600">Canal</label>
                <select wire:model.live="canal" class="block w-full rounded-xl border px-3 py-2">
                    <option value="">Oficina y WhatsApp</option>
                    @foreach (CanalRecibo::NOMBRES as $clave => $nombre)
                        <option value="{{ $clave }}">{{ $nombre }} ({{ mb_strtolower(CanalRecibo::DESCRIPCIONES[$clave]) }})</option>
                    @endforeach
                </select>
            </div>
            <div>
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

        {{-- Por canal --}}
        <div class="grid {{ count($canales) > 1 ? 'md:grid-cols-2' : '' }} gap-4">
            @foreach ($canales as $clave)
                @php $c = $e['por_canal'][$clave]; @endphp
                <div class="bg-white rounded-2xl border p-4">
                    <div class="flex items-center gap-2">
                        <span class="inline-block h-3 w-3 rounded-sm" style="background: {{ WorkloadReport::COLORES[$clave] }};"></span>
                        <span class="font-bold">{{ CanalRecibo::NOMBRES[$clave] }}</span>
                        <span class="text-sm text-gray-500">· {{ CanalRecibo::DESCRIPCIONES[$clave] }}</span>
                    </div>

                    <div class="mt-3 grid grid-cols-2 sm:grid-cols-4 gap-4">
                        <div>
                            <div class="text-xs text-gray-600">Recibos</div>
                            <div class="text-2xl font-black">{{ number_format($c['recibos']) }}</div>
                            @if (count($canales) > 1)
                                <div class="text-xs text-gray-500">{{ number_format($c['porcentaje'], 0) }}% del total</div>
                            @endif
                        </div>
                        <div>
                            <div class="text-xs text-gray-600">Clientes atendidos</div>
                            <div class="text-2xl font-black">{{ number_format($c['clientes']) }}</div>
                            <div class="text-xs text-gray-500">{{ number_format($c['recibos_por_cliente'], 1) }} recibos por cliente</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-600">Un cliente cada</div>
                            <div class="text-2xl font-black">{{ $minutos($c['minutos_entre']) }}</div>
                            <div class="text-xs text-gray-500">cuando hay clientes seguidos</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-600">Hora más cargada</div>
                            <div class="text-2xl font-black">{{ $hora($c['hora_pico']) }}</div>
                            <div class="text-xs text-gray-500">${{ number_format($c['monto'], 0) }} cobrados</div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="-mt-3 text-xs text-gray-500">
            Varios recibos del mismo cliente con el mismo usuario en menos de 10 minutos cuentan como un solo cliente atendido (por ejemplo, cuota y recargo).
            «Un cliente cada» es el tiempo típico entre que se empieza con un cliente y se empieza con el siguiente, solo cuando venían uno tras otro (menos de 30 minutos entre ellos).
        </div>

        {{-- Pronóstico --}}
        <div class="bg-white rounded-2xl border p-4">
            <div class="font-bold">
                Pronóstico de los próximos 7 días{{ $canal !== '' ? ' · '.CanalRecibo::NOMBRES[$canal] : '' }}
            </div>
            <div class="text-sm text-gray-500">
                De todos los usuarios, sin importar las fechas ni el usuario elegidos; sí sigue al canal.
                Sale de las cuotas que vencen cada día y de cuándo suelen venir a pagar los clientes.
            </div>

            @if (! $p['disponible'])
                <div class="mt-4 rounded-xl bg-gray-50 p-4 text-sm text-gray-600">
                    Todavía no hay suficiente historia de cobros de cuotas para calcularlo. Hacen falta recibos de cuotas que hayan vencido hace más de un mes.
                </div>
            @else
                <div class="mt-4 grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-3">
                    @foreach ($p['dias'] as $d)
                        @php
                            $fecha = \Carbon\Carbon::parse($d['fecha']);
                            $esperados = (int) round($d['esperados']);
                            $diferencia = $d['esperados'] - $d['normal'];
                            $carga = match (true) {
                                $d['normal'] > 0 && $diferencia >= 3 && $d['esperados'] >= $d['normal'] * 1.25 => 'mas',
                                $d['normal'] > 0 && $diferencia <= -3 && $d['esperados'] <= $d['normal'] * 0.75 => 'menos',
                                default => 'normal',
                            };
                        @endphp
                        <div class="rounded-xl border p-3 {{ $carga === 'mas' ? 'border-amber-400 bg-amber-50' : '' }}">
                            <div class="text-sm font-semibold">
                                {{ $fecha->isToday() ? 'Hoy' : WorkloadReport::DIAS[$fecha->dayOfWeekIso] }}
                                <span class="font-normal text-gray-500">{{ $fecha->format('d/m') }}</span>
                            </div>
                            <div class="text-3xl font-black mt-1">~{{ $esperados }}</div>
                            <div class="text-xs text-gray-600">recibos esperados</div>
                            <div class="text-xs text-gray-500 mt-2">Un {{ mb_strtolower(WorkloadReport::DIAS[$fecha->dayOfWeekIso]) }} normal: {{ number_format($d['normal'], 0) }}</div>
                            <div class="text-xs text-gray-500">Vencen {{ number_format($d['vencen']) }} cuotas</div>
                            @if ($d['llevan'] !== null)
                                <div class="text-xs text-gray-700 mt-1">Llevan {{ $d['llevan'] }} hoy</div>
                            @endif
                            @if ($carga === 'mas')
                                <div class="text-xs font-semibold text-amber-800 mt-2">▲ Más carga de lo normal</div>
                            @elseif ($carga === 'menos')
                                <div class="text-xs font-semibold text-gray-600 mt-2">▼ Menos que lo normal</div>
                            @endif
                        </div>
                    @endforeach
                </div>

                <div class="mt-3 text-xs text-gray-500">
                    En las últimas 8 semanas este cálculo se desvió en promedio ±{{ number_format($p['error_promedio'], 1) }} recibos por día.
                    De los pagos de cuota que se capturan cerca del vencimiento, el {{ number_format($p['cobro_mismo_dia'], 0) }}% se hace el mismo día que vence.
                </div>
            @endif
        </div>

        <div class="grid lg:grid-cols-2 gap-6">

            {{-- Por hora --}}
            <div class="bg-white rounded-2xl border p-4">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <div class="font-bold">Recibos por hora del día</div>
                        <div class="text-sm text-gray-500">Total del periodo en cada hora.</div>
                    </div>
                    @include('livewire.reports.partials.leyenda-canales')
                </div>

                <div class="mt-4 flex items-end gap-1 h-48 border-b border-gray-200">
                    @foreach ($e['horas'] as $h)
                        @include('livewire.reports.partials.barra-canales', [
                            'barra' => array_map(fn ($porHora) => $porHora[$h], $e['por_hora_canal']),
                            'titulo' => $h.':00 a '.($h + 1).':00',
                            'maximo' => $maxHora,
                            'escala' => 85,
                            'ancho' => 'max-w-[28px]',
                            'etiqueta' => true,
                        ])
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
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <div class="font-bold">Recibos por semana</div>
                        <div class="text-sm text-gray-500">Las 12 semanas que terminan en la del día «Hasta». Cada barra empieza en lunes.</div>
                    </div>
                    @include('livewire.reports.partials.leyenda-canales')
                </div>

                <div class="mt-4 flex items-end gap-1 h-48 border-b border-gray-200">
                    @foreach ($e['por_semana'] as $s)
                        @include('livewire.reports.partials.barra-canales', [
                            'barra' => $s,
                            'titulo' => 'Semana del '.$s['rango'],
                            'maximo' => $maxSemana,
                            'escala' => 85,
                            'ancho' => 'max-w-[36px]',
                            'etiqueta' => true,
                        ])
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
                <div class="flex flex-col items-end gap-1">
                    @include('livewire.reports.partials.leyenda-canales')
                    <div class="text-sm text-gray-600">Día con más: <span class="font-semibold">{{ number_format(max(array_column($e['por_dia'], 'total'))) }}</span></div>
                </div>
            </div>

            <div class="mt-4 flex items-end gap-px h-40 border-b border-gray-200">
                @foreach ($e['por_dia'] as $d)
                    @include('livewire.reports.partials.barra-canales', [
                        'barra' => $d,
                        'titulo' => $d['dia_semana'].' '.$d['etiqueta'],
                        'maximo' => $maxDia,
                        'escala' => 100,
                        'ancho' => 'max-w-[36px]',
                        'etiqueta' => false,
                    ])
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
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div class="font-bold">¿Cuándo se carga el trabajo?</div>
                    <div class="text-sm text-gray-500">
                        @if ($promedio)
                            Recibos en promedio por cada día de la semana y hora del periodo: lo que se espera un día así. Más oscuro, más recibos.
                        @else
                            Recibos por día de la semana y hora, sumando todo el periodo. Más oscuro, más recibos.
                        @endif
                    </div>
                </div>
                <div class="inline-flex rounded-xl border overflow-hidden text-sm">
                    <button type="button" wire:click="$set('promedio', false)" class="px-3 py-1.5 {{ $promedio ? 'hover:bg-gray-50' : 'bg-black text-white' }}">Total</button>
                    <button type="button" wire:click="$set('promedio', true)" class="px-3 py-1.5 {{ $promedio ? 'bg-black text-white' : 'hover:bg-gray-50' }}">Promedio por día</button>
                </div>
            </div>

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
                                        $n = $celda($num, $h);
                                        $color = WorkloadReport::colorCalor($n, $maxCelda);
                                        $oscuro = $color && array_search($color, WorkloadReport::RAMPA, true) >= 3;
                                    @endphp
                                    <td class="h-9 w-10 text-center text-xs rounded {{ $color ? '' : 'bg-gray-50' }}"
                                        style="{{ $color ? "background: {$color};" : '' }} color: {{ $oscuro ? '#ffffff' : '#1f2937' }};"
                                        title="{{ $nombre }} {{ $h }}:00 a {{ $h + 1 }}:00 — {{ $formato($n) ?: 0 }} recibos{{ $promedio ? ' en promedio' : '' }}">
                                        {{ $formato($n) }}
                                    </td>
                                @endforeach
                                <td class="text-xs font-semibold text-gray-700 pl-2">{{ $formato(array_sum($e['calor'][$num] ?? []) / ($promedio ? max(1, $e['veces_dia_semana'][$num] ?? 1) : 1)) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Por usuario --}}
        <div class="bg-white rounded-2xl border p-4">
            <div class="font-bold">Desempeño por usuario</div>
            <div class="text-sm text-gray-500">
                «Por hora trabajada» divide sus recibos entre las horas en que hizo al menos uno: su ritmo cuando está cobrando.
                «Un cliente cada» es solo de la oficina, donde se forma la fila.
            </div>

            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-600 border-b">
                            <th class="py-2 pr-4">Usuario</th>
                            <th class="py-2 pr-4 text-right">Recibos</th>
                            <th class="py-2 pr-4">Parte del total</th>
                            <th class="py-2 pr-4 text-right">Oficina</th>
                            <th class="py-2 pr-4 text-right">WhatsApp</th>
                            <th class="py-2 pr-4 text-right">Clientes</th>
                            <th class="py-2 pr-4 text-right">Un cliente cada</th>
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
                                        <div class="h-2 w-24 bg-gray-100 rounded flex gap-[2px] overflow-hidden"
                                             title="{{ number_format($u[CanalRecibo::OFICINA]) }} en oficina, {{ number_format($u[CanalRecibo::WHATSAPP]) }} por WhatsApp">
                                            @foreach (array_keys(CanalRecibo::NOMBRES) as $clave)
                                                @if ($u[$clave] > 0)
                                                    <div class="h-2" style="width: {{ $u[$clave] / max(1, $e['total']) * 100 }}%; background: {{ WorkloadReport::COLORES[$clave] }};"></div>
                                                @endif
                                            @endforeach
                                        </div>
                                        <span class="text-xs text-gray-600">{{ number_format($u['porcentaje'], 0) }}%</span>
                                    </div>
                                </td>
                                <td class="py-2 pr-4 text-right">{{ number_format($u[CanalRecibo::OFICINA]) }}</td>
                                <td class="py-2 pr-4 text-right">{{ number_format($u[CanalRecibo::WHATSAPP]) }}</td>
                                <td class="py-2 pr-4 text-right">{{ number_format($u['clientes']) }}</td>
                                <td class="py-2 pr-4 text-right">{{ $minutos($u['minutos_entre']) }}</td>
                                <td class="py-2 pr-4 text-right">{{ $u['dias'] }}</td>
                                <td class="py-2 pr-4 text-right">{{ number_format($u['promedio_dia'], 1) }}</td>
                                <td class="py-2 pr-4 text-right">{{ number_format($u['por_hora_activa'], 1) }}</td>
                                <td class="py-2 pr-4 text-right">{{ $hora($u['hora_pico']) }}</td>
                                <td class="py-2 pr-4 text-right">{{ $u['anulados'] }}</td>
                                <td class="py-2 text-right">${{ number_format($u['monto'], 0) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="13" class="py-6 text-center text-gray-500">No se generaron recibos en este periodo.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
