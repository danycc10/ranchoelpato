<div class="max-w-7xl mx-auto p-3 sm:p-4 space-y-4 sm:space-y-6">

    {{-- HEADER / FILTROS --}}
    <div class="bg-white border rounded-2xl p-3 sm:p-4 shadow-sm">
        <div class="flex flex-col xl:flex-row xl:items-end xl:justify-between gap-3">
            <div class="min-w-0">
                <h1 class="text-xl sm:text-2xl font-black">Reporte diario</h1>
                <p class="text-sm sm:text-base text-gray-600">
                    Desglosado por método de pago, por finca y concepto.
                </p>

            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 xl:flex gap-3 xl:items-end w-full xl:w-auto">
                <div class="w-full xl:w-auto">
                    <label class="text-sm text-gray-600">Fecha</label>
                    <input
                        type="date"
                        wire:model.live="fecha"
                        wire:loading.attr="disabled"
                        class="block w-full rounded-xl border px-3 py-2.5 text-sm sm:text-base focus:outline-none focus:ring-2 focus:ring-black/10 disabled:opacity-60">
                </div>

                <div
                    x-data="{ open: false }"
                    class="w-full xl:w-[320px] relative">
                    <label class="text-sm text-gray-600">Propietarios</label>

                    {{-- BOTÓN SELECT --}}
                    <button
                        type="button"
                        @click="open = !open"
                        class="mt-1 w-full rounded-xl border bg-white px-4 py-3 text-left text-sm flex justify-between items-center shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-black/10">
                        <span class="truncate">
                            @if(empty($propietarioIds))
                            Todos
                            @else
                            @php
                            $names = ($propietarios ?? collect())
                            ->whereIn('id', collect($propietarioIds)->map(fn($id) => (int)$id)->all())
                            ->pluck('nombre')
                            ->values();
                            @endphp
                            {{ $names->take(2)->join(', ') }}
                            @if($names->count() > 2)
                            +{{ $names->count() - 2 }}
                            @endif
                            @endif
                        </span>

                        <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    {{-- DROPDOWN --}}
                    <div
                        x-show="open"
                        @click.away="open = false"
                        x-transition
                        class="absolute right-0 z-50 mt-2 w-[320px] bg-white border rounded-2xl shadow-xl max-h-72 overflow-y-auto">

                        {{-- TODOS --}}
                        <label class="flex items-center gap-2 px-3 py-2 border-b text-sm font-semibold">
                            <input
                                type="checkbox"
                                wire:click="$set('propietarioIds', [])"
                                @checked(empty($propietarioIds))
                                class="rounded border-gray-300">
                            Todos
                        </label>

                        {{-- LISTA --}}
                        @foreach($propietarios as $p)
                        <label class="flex items-center gap-2 px-3 py-2 text-sm hover:bg-gray-50">
                            <input
                                type="checkbox"
                                wire:model.live="propietarioIds"
                                value="{{ $p->id }}"
                                class="rounded border-gray-300">
                            {{ $p->nombre }}
                        </label>
                        @endforeach
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 sm:gap-3 w-full xl:w-auto sm:col-span-2 xl:col-span-1">
                    <button
                        wire:click="exportExcel"
                        wire:loading.attr="disabled"
                        class="w-full rounded-xl bg-black text-white px-4 py-2.5 text-sm sm:text-base hover:opacity-90 disabled:opacity-60 disabled:cursor-not-allowed">
                        <span wire:loading.remove>Exportar Excel</span>
                        <span wire:loading>Exportando…</span>
                    </button>

                    <button
                        type="button"
                        wire:click="clearFilters"
                        wire:loading.attr="disabled"
                        class="w-full rounded-xl border px-4 py-2.5 text-sm sm:text-base hover:bg-gray-50 disabled:opacity-60 disabled:cursor-not-allowed">
                        Limpiar
                    </button>

                    <a
                        href="{{ route('reportes.index') }}"
                        class="w-full flex items-center justify-center rounded-xl border px-4 py-2.5 text-sm sm:text-base hover:bg-gray-50">
                        Volver
                    </a>
                </div>
            </div>
        </div>

        <div class="mt-3 flex flex-wrap gap-2">
            <span class="inline-flex items-center px-3 py-1 rounded-full border bg-gray-50 text-[11px] sm:text-xs font-semibold text-gray-700">
                Fecha: {{ \Carbon\Carbon::parse($fecha)->format('d/m/Y') }}
            </span>

            @if(!empty($propietarioIds))
            @php
            $propsSeleccionados = ($propietarios ?? collect())
            ->whereIn('id', collect($propietarioIds)->map(fn($id) => (int) $id)->all())
            ->pluck('nombre')
            ->values();
            @endphp

            <span class="inline-flex items-center px-3 py-1 rounded-full border bg-gray-50 text-[11px] sm:text-xs font-semibold text-gray-700">
                Propietarios: {{ $propsSeleccionados->join(', ') }}
            </span>
            @else
            <span class="inline-flex items-center px-3 py-1 rounded-full border bg-gray-50 text-[11px] sm:text-xs font-semibold text-gray-700">
                Propietarios: Todos
            </span>
            @endif
        </div>
    </div>

    {{-- SECCIONES POR METODO DE PAGO --}}
    @foreach($metodosPago as $i => $metodo)
    @php
    $data = $resumenPorMetodo[$metodo] ?? null;

    $themes = [
    ['border'=>'border-blue-400','accent'=>'border-l-blue-700','headBg'=>'bg-blue-200','headText'=>'text-blue-950','thBg'=>'bg-blue-100','totalBg'=>'bg-blue-200','ring'=>'ring-blue-100'],
    ['border'=>'border-emerald-400','accent'=>'border-l-emerald-700','headBg'=>'bg-emerald-200','headText'=>'text-emerald-950','thBg'=>'bg-emerald-100','totalBg'=>'bg-emerald-200','ring'=>'ring-emerald-100'],
    ['border'=>'border-violet-400','accent'=>'border-l-violet-700','headBg'=>'bg-violet-200','headText'=>'text-violet-950','thBg'=>'bg-violet-100','totalBg'=>'bg-violet-200','ring'=>'ring-violet-100'],
    ['border'=>'border-amber-400','accent'=>'border-l-amber-700','headBg'=>'bg-amber-200','headText'=>'text-amber-950','thBg'=>'bg-amber-100','totalBg'=>'bg-amber-200','ring'=>'ring-amber-100'],
    ['border'=>'border-rose-400','accent'=>'border-l-rose-700','headBg'=>'bg-rose-200','headText'=>'text-rose-950','thBg'=>'bg-rose-100','totalBg'=>'bg-rose-200','ring'=>'ring-rose-100'],
    ['border'=>'border-cyan-400','accent'=>'border-l-cyan-700','headBg'=>'bg-cyan-200','headText'=>'text-cyan-950','thBg'=>'bg-cyan-100','totalBg'=>'bg-cyan-200','ring'=>'ring-cyan-100'],
    ];

    $t = $themes[$i % count($themes)];
    @endphp

    <div class="rounded-2xl border border-l-8 {{ $t['border'] }} {{ $t['accent'] }} overflow-hidden shadow-sm ring-2 sm:ring-4 {{ $t['ring'] }}">
        <div class="p-3 sm:p-4 {{ $t['headBg'] }}">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2">
                <div class="font-black {{ $t['headText'] }} tracking-wide text-sm sm:text-base break-words">
                    {{ strtoupper($metodo) }} — {{ \Carbon\Carbon::parse($fecha)->format('d/m/Y') }}
                </div>

                @if($data && count($data->filas ?? []))
                <span class="inline-flex items-center self-start md:self-auto px-3 py-1 rounded-full border border-black/10 bg-white/70 text-[11px] sm:text-xs font-black {{ $t['headText'] }}">
                    Total: ${{ number_format((float)$data->totales->total_general, 2) }}
                </span>
                @endif
            </div>

            <div class="text-[11px] sm:text-xs {{ $t['headText'] }} opacity-80 mt-1">
                Desglose por finca y concepto
            </div>
        </div>

        <div class="p-3 sm:p-4 bg-white">
            <div class="overflow-x-auto overflow-y-auto max-h-[420px] rounded-xl border border-black/10">
                <table class="min-w-[760px] w-full text-xs sm:text-sm">
                    <thead class="sticky top-0 z-10 text-left {{ $t['thBg'] }} border-b border-black/10">
                        <tr>
                            <th class="py-2 px-2 font-black">NOMBRE</th>

                            @foreach($conceptos as $c)
                            <th class="py-2 px-2 whitespace-nowrap font-black">{{ strtoupper($c) }}</th>
                            @endforeach

                            <th class="py-2 px-2 font-black text-right">TOTAL</th>
                        </tr>
                    </thead>

                    <tbody class="[&>tr:nth-child(even)]:bg-gray-50/60 [&>tr:hover]:bg-gray-100/60">
                        @forelse(($data?->filas ?? []) as $fila)
                        <tr class="border-b border-black/5">
                            <td class="py-2 px-2 font-bold whitespace-nowrap text-gray-900">
                                {{ $fila->finca }}
                            </td>

                            @foreach($conceptos as $c)
                            <td class="py-2 px-2 tabular-nums text-gray-900 whitespace-nowrap">
                                ${{ number_format((float)($fila->totales[$c] ?? 0), 2) }}
                            </td>
                            @endforeach

                            <td class="py-2 px-2 font-black tabular-nums text-gray-950 text-right whitespace-nowrap">
                                ${{ number_format((float)$fila->total_general, 2) }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="{{ 2 + count($conceptos) }}" class="py-6 px-3 text-gray-500 text-center">
                                Sin datos para este método.
                            </td>
                        </tr>
                        @endforelse

                        @if($data && count($data->filas ?? []))
                        <tr class="border-t-2 border-black/15 {{ $t['totalBg'] }}">
                            <td class="py-2 px-2 font-black">TOTAL</td>

                            @foreach($conceptos as $c)
                            <td class="py-2 px-2 font-black tabular-nums whitespace-nowrap">
                                ${{ number_format((float)($data->totales->por_concepto[$c] ?? 0), 2) }}
                            </td>
                            @endforeach

                            <td class="py-2 px-2 font-black tabular-nums text-right whitespace-nowrap">
                                ${{ number_format((float)$data->totales->total_general, 2) }}
                            </td>
                        </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endforeach

    {{-- TOTAL GENERAL --}}
    <div class="rounded-2xl border p-3 sm:p-4 bg-white shadow-sm space-y-3">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2">
            <div class="font-black text-sm sm:text-base">
                TOTAL GENERAL — {{ \Carbon\Carbon::parse($fecha)->format('d/m/Y') }}
            </div>
            <span class="inline-flex items-center self-start md:self-auto px-3 py-1 rounded-full border bg-gray-50 text-[11px] sm:text-xs font-black text-gray-700">
                ${{ number_format((float)$totalGeneral->total_general, 2) }}
            </span>
        </div>

        <div class="overflow-x-auto rounded-xl border border-black/10">
            <table class="min-w-[760px] w-full text-xs sm:text-sm">
                <thead class="sticky top-0 bg-gray-100 text-left border-b">
                    <tr>
                        <th class="py-2 px-2 font-black">TOTAL</th>
                        @foreach($conceptos as $c)
                        <th class="py-2 px-2 whitespace-nowrap font-black">{{ strtoupper($c) }}</th>
                        @endforeach
                        <th class="py-2 px-2 font-black text-right">TOTAL</th>
                    </tr>
                </thead>

                <tbody class="[&>tr:hover]:bg-gray-100/60">
                    <tr class="bg-gray-50">
                        <td class="py-2 px-2 font-black">TOTAL</td>
                        @foreach($conceptos as $c)
                        <td class="py-2 px-2 font-black tabular-nums whitespace-nowrap">
                            ${{ number_format((float)($totalGeneral->por_concepto[$c] ?? 0), 2) }}
                        </td>
                        @endforeach
                        <td class="py-2 px-2 font-black tabular-nums text-right whitespace-nowrap">
                            ${{ number_format((float)$totalGeneral->total_general, 2) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- DETALLE DE PAGOS --}}
    <div class="rounded-2xl border p-3 sm:p-4 bg-white shadow-sm space-y-4">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2">
            <div class="font-bold text-sm sm:text-base">Detalle de pagos</div>

            <div class="text-xs sm:text-sm text-gray-600">
                Mostrando {{ $recibos->firstItem() ?? 0 }}–{{ $recibos->lastItem() ?? 0 }}
                de {{ $recibos->total() ?? 0 }}
            </div>
        </div>

        {{-- Tabla desktop/tablet --}}
        <div class="hidden md:block overflow-x-auto overflow-y-auto max-h-[560px] rounded-xl border border-black/10">
            <table class="min-w-[980px] w-full text-sm">
                <thead class="sticky top-0 bg-gray-100 text-left border-b z-10">
                    <tr>
                        <th class="py-2 px-3 font-black">Folio</th>
                        <th class="py-2 px-3 font-black">Persona</th>
                        <th class="py-2 px-3 font-black">Lote</th>
                        <th class="py-2 px-3 font-black">Finca</th>
                        <th class="py-2 px-3 font-black">Concepto</th>
                        <th class="py-2 px-3 font-black">Forma</th>
                        <th class="py-2 px-3 font-black">Cuenta bancaria</th>
                        <th class="py-2 px-3 font-black text-right">Monto</th>
                    </tr>
                </thead>

                <tbody class="[&>tr:nth-child(even)]:bg-gray-50/60 [&>tr:hover]:bg-gray-100/60">
                    @forelse($recibos as $r)
                    @php
                    $recibo = $r->recibo;
                    $concepto = strtoupper(trim((string)($recibo?->tipoCobro?->nombre ?? '')));
                    $forma = strtoupper(trim((string)($r->formaPago?->nombre ?? '')));
                    $formaLower = strtolower($forma);

                    $badgeForma = match(true) {
                    str_contains($formaLower, 'efect') => 'bg-gray-50 text-gray-700 border-gray-200',
                    str_contains($formaLower, 'transfer') => 'bg-sky-50 text-sky-700 border-sky-200',
                    str_contains($formaLower, 'tarje') => 'bg-indigo-50 text-indigo-700 border-indigo-200',
                    str_contains($formaLower, 'oxxo') => 'bg-amber-50 text-amber-800 border-amber-200',
                    default => 'bg-gray-50 text-gray-700 border-gray-200',
                    };

                    $persona = $recibo?->cliente?->nombre_completo
                    ?? $recibo?->cliente?->nombre
                    ?? '—';

                    $lote = $recibo?->contrato?->lote?->lote
                    ?? $recibo?->contrato?->lote?->clave
                    ?? $recibo?->contrato?->lote?->nombre
                    ?? '—';

                    $finca = $recibo?->contrato?->lote?->fraccionamiento?->nombre ?? '—';

                    $cuenta = $r->cuentaBancaria?->alias ?? '—';

                    if (blank($cuenta)) {
                    $cuenta = '—';
                    }
                    @endphp

                    <tr
                        class="border-b border-black/5 cursor-pointer hover:bg-gray-100/70"
                        wire:click="openReciboModal({{ $r->id }})">
                        <td class="py-2 px-3 font-medium whitespace-nowrap">{{ $recibo?->folio ?? '—' }}</td>
                        <td class="py-2 px-3 whitespace-nowrap">{{ $persona }}</td>
                        <td class="py-2 px-3 whitespace-nowrap">{{ $lote }}</td>
                        <td class="py-2 px-3 whitespace-nowrap">{{ $finca }}</td>
                        <td class="py-2 px-3 whitespace-nowrap">{{ $concepto !== '' ? $concepto : '—' }}</td>
                        <td class="py-2 px-3 whitespace-nowrap">
                            <span class="inline-flex items-center px-2 py-1 rounded-full border text-xs font-semibold {{ $badgeForma }}">
                                {{ $forma !== '' ? $forma : '—' }}
                            </span>
                        </td>
                        <td class="py-2 px-3 whitespace-nowrap text-gray-700">{{ $cuenta }}</td>
                        <td class="py-2 px-3 font-black tabular-nums text-right whitespace-nowrap">
                            ${{ number_format((float)$r->monto, 2) }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="py-6 text-gray-500 text-center">Sin pagos.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Cards móvil --}}
        <div class="md:hidden space-y-3">
            @forelse($recibos as $r)
            @php
            $recibo = $r->recibo;
            $concepto = strtoupper(trim((string)($recibo?->tipoCobro?->nombre ?? '')));
            $forma = strtoupper(trim((string)($r->formaPago?->nombre ?? '')));
            $formaLower = strtolower($forma);

            $badgeForma = match(true) {
            str_contains($formaLower, 'efect') => 'bg-gray-50 text-gray-700 border-gray-200',
            str_contains($formaLower, 'transfer') => 'bg-sky-50 text-sky-700 border-sky-200',
            str_contains($formaLower, 'tarje') => 'bg-indigo-50 text-indigo-700 border-indigo-200',
            str_contains($formaLower, 'oxxo') => 'bg-amber-50 text-amber-800 border-amber-200',
            default => 'bg-gray-50 text-gray-700 border-gray-200',
            };

            $persona = $recibo?->cliente?->nombre_completo
            ?? $recibo?->cliente?->nombre
            ?? '—';

            $lote = $recibo?->contrato?->lote?->lote
            ?? $recibo?->contrato?->lote?->clave
            ?? $recibo?->contrato?->lote?->nombre
            ?? '—';

            $finca = $recibo?->contrato?->lote?->fraccionamiento?->nombre ?? '—';

            $cuenta = $r->cuentaBancaria?->alias
            ?? '—';

            if (blank($cuenta)) {
            $cuenta = '—';
            }
            @endphp

            <button
                type="button"
                wire:click="openReciboModal({{ $r->id }})"
                class="w-full text-left rounded-2xl border bg-white p-4 shadow-sm active:scale-[0.99] transition">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="font-black text-sm truncate">{{ $recibo?->folio ?? '—' }}</div>
                        <div class="text-sm text-gray-700 truncate">{{ $persona }}</div>
                    </div>

                    <div class="text-right shrink-0">
                        <div class="font-black text-sm">
                            ${{ number_format((float)$r->monto, 2) }}
                        </div>
                    </div>
                </div>

                <div class="mt-3 grid grid-cols-1 gap-2 text-xs">
                    <div>
                        <span class="text-gray-500">Lote:</span>
                        <span class="font-semibold">{{ $lote }}</span>
                    </div>
                    <div>
                        <span class="text-gray-500">Finca:</span>
                        <span class="font-semibold">{{ $finca }}</span>
                    </div>
                    <div>
                        <span class="text-gray-500">Concepto:</span>
                        <span class="font-semibold">{{ $concepto !== '' ? $concepto : '—' }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <span class="inline-flex items-center px-2 py-1 rounded-full border text-[11px] font-semibold {{ $badgeForma }}">
                            {{ $forma !== '' ? $forma : '—' }}
                        </span>
                        <span class="text-gray-500 truncate">{{ $cuenta }}</span>
                    </div>
                </div>
            </button>
            @empty
            <div class="rounded-2xl border bg-white p-6 text-center text-gray-500">
                Sin pagos.
            </div>
            @endforelse
        </div>

        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
            <div class="text-xs text-gray-500">
                Tip: toca un pago para ver su detalle.
            </div>
            <div class="overflow-auto">
                {{ $recibos->links() }}
            </div>
        </div>
    </div>

    @if($showReciboModal && $selectedReciboData)
    <div class="fixed inset-0 z-50 bg-black/50 p-2 sm:p-4 overflow-y-auto">
        <div class="min-h-full flex items-center justify-center">
            <div class="bg-white w-full max-w-5xl rounded-2xl shadow-xl overflow-hidden">
                <div class="px-4 sm:px-5 py-4 border-b flex items-start sm:items-center justify-between gap-3">
                    <div class="min-w-0">
                        <h2 class="text-base sm:text-lg font-black">Detalle del pago</h2>
                        <p class="text-xs sm:text-sm text-gray-500 truncate">{{ $selectedReciboData['folio'] }}</p>
                    </div>

                    <button
                        type="button"
                        wire:click="closeReciboModal"
                        class="px-3 py-1.5 text-sm border rounded-lg hover:bg-gray-50 shrink-0">
                        Cerrar
                    </button>
                </div>

                <div class="p-4 sm:p-5 grid grid-cols-1 xl:grid-cols-2 gap-4 sm:gap-6">
                    <div class="space-y-4 order-2 xl:order-1">
                        <div class="rounded-2xl border p-4 bg-gray-50">
                            <h3 class="font-black mb-3 text-sm sm:text-base">Información</h3>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                                <div>
                                    <div class="text-gray-500 text-xs sm:text-sm">Folio</div>
                                    <div class="font-semibold break-words">{{ $selectedReciboData['folio'] }}</div>
                                </div>

                                <div>
                                    <div class="text-gray-500 text-xs sm:text-sm">Fecha</div>
                                    <div class="font-semibold">
                                        {{ \Carbon\Carbon::parse($selectedReciboData['fecha'])->format('d/m/Y H:i') }}
                                    </div>
                                </div>

                                <div>
                                    <div class="text-gray-500 text-xs sm:text-sm">Persona</div>
                                    <div class="font-semibold break-words">{{ $selectedReciboData['persona'] }}</div>
                                </div>

                                <div>
                                    <div class="text-gray-500 text-xs sm:text-sm">Lote</div>
                                    <div class="font-semibold break-words">{{ $selectedReciboData['lote'] }}</div>
                                </div>

                                <div>
                                    <div class="text-gray-500 text-xs sm:text-sm">Finca</div>
                                    <div class="font-semibold break-words">{{ $selectedReciboData['finca'] }}</div>
                                </div>

                                <div>
                                    <div class="text-gray-500 text-xs sm:text-sm">Concepto</div>
                                    <div class="font-semibold break-words">{{ $selectedReciboData['concepto'] }}</div>
                                </div>

                                <div>
                                    <div class="text-gray-500 text-xs sm:text-sm">Método de pago</div>
                                    <div class="font-semibold break-words">{{ $selectedReciboData['forma_pago'] }}</div>
                                </div>

                                <div>
                                    <div class="text-gray-500 text-xs sm:text-sm">Cuenta bancaria</div>
                                    <div class="font-semibold break-words">{{ $selectedReciboData['cuenta_bancaria'] }}</div>
                                </div>

                                <div>
                                    <div class="text-gray-500 text-xs sm:text-sm">Referencia</div>
                                    <div class="font-semibold break-words">{{ $selectedReciboData['referencia'] ?: '—' }}</div>
                                </div>

                                <div class="sm:col-span-2">
                                    <div class="text-gray-500 text-xs sm:text-sm">Monto</div>
                                    <div class="font-black text-lg sm:text-xl">
                                        ${{ number_format((float)$selectedReciboData['monto'], 2) }}
                                    </div>
                                </div>

                                <div class="sm:col-span-2">
                                    <div class="text-gray-500 text-xs sm:text-sm">Observaciones</div>
                                    <div class="font-semibold break-words">
                                        {{ $selectedReciboData['observaciones'] ?: '—' }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-2xl border p-4 bg-gray-50">
                        <h3 class="font-black mb-3 text-sm sm:text-base">Evidencia</h3>

                        @php
                        $evidenciaUrl = !empty($selectedReciboData['evidencia_url'])
                        ? $selectedReciboData['evidencia_url'] . '?v=' . urlencode($selectedReciboData['evidencia_path'] ?? '')
                        : null;

                        $esPdf = ($selectedReciboData['evidencia_mime'] ?? null) === 'application/pdf'
                        || strtolower(pathinfo((string)($selectedReciboData['evidencia_path'] ?? ''), PATHINFO_EXTENSION)) === 'pdf';
                        @endphp

                        <div class="rounded-2xl border bg-white min-h-[240px] sm:min-h-[380px] flex items-center justify-center overflow-hidden">
                            @if($evidenciaUrl)
                            @if($esPdf)
                            <div class="text-center px-4 py-8">
                                <div class="mx-auto h-16 w-16 rounded-2xl bg-red-50 border border-red-200 text-red-700 flex items-center justify-center font-black text-sm">
                                    PDF
                                </div>

                                <div class="mt-4 text-sm font-bold text-gray-800">
                                    Evidencia en PDF
                                </div>

                                <div class="mt-1 text-xs text-gray-500">
                                    Este comprobante está guardado como archivo PDF.
                                </div>

                                <a
                                    href="{{ $evidenciaUrl }}"
                                    target="_blank"
                                    class="inline-flex mt-4 px-4 py-2 rounded-xl border font-semibold hover:bg-gray-50">
                                    Ver PDF
                                </a>
                            </div>
                            @else
                            <a href="{{ $evidenciaUrl }}" target="_blank" class="block w-full">
                                <img
                                    wire:key="detalle-evidencia-{{ md5((string)($selectedReciboData['evidencia_path'] ?? 'sin-evidencia')) }}"
                                    src="{{ $evidenciaUrl }}"
                                    alt="Evidencia"
                                    class="max-h-[60vh] sm:max-h-[520px] w-full object-contain">
                            </a>
                            @endif
                            @else
                            <div class="text-sm text-gray-400 text-center px-4">
                                Este pago no tiene evidencia.
                            </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="px-4 sm:px-5 py-4 border-t flex justify-end">
                    <button
                        type="button"
                        wire:click="closeReciboModal"
                        class="w-full sm:w-auto px-4 py-2 rounded-xl border hover:bg-gray-50">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    <div
        wire:loading.flex
        wire:target="fecha, propietarioIds"
        class="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm items-center justify-center">

        <div class="bg-white rounded-2xl shadow-2xl px-6 py-5 flex flex-col items-center gap-3">

            {{-- Spinner --}}
            <svg class="animate-spin h-8 w-8 text-black" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-20" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-80" fill="currentColor"
                    d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z">
                </path>
            </svg>

            {{-- Texto --}}
            <div class="text-center">
                <p class="font-bold text-sm">Cargando reporte...</p>
                <p class="text-xs text-gray-500">
                    Procesando filtros y actualizando datos
                </p>
            </div>

        </div>
    </div>
</div>