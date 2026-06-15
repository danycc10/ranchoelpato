<div class="max-w-6xl mx-auto p-4 sm:p-6 space-y-4 sm:space-y-6">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3 sm:gap-4">
        <div>
            <h1 class="text-2xl font-black">Cuotas</h1>
            <p class="text-gray-500">Busca una cuota y genera/imprime su recibo.</p>
        </div>

        <a href="{{ route('admin.recibos.crear') }}"
           class="w-full sm:w-auto text-center px-4 py-2 rounded-xl bg-black text-white text-sm font-semibold">
            Crear recibo
        </a>
    </div>

    {{-- Filtros --}}
    <div class="p-4 rounded-2xl border bg-white grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <input class="w-full rounded-xl border p-2 sm:col-span-2 lg:col-span-2"
               placeholder="Buscar por cliente, folio contrato, lote o número de cuota…"
               wire:model.live.debounce.300ms="q">

        <select class="w-full rounded-xl border p-2" wire:model.live="propietario_id">
            <option value="">Todos los propietarios</option>
            @foreach(($propietarios ?? []) as $p)
                <option value="{{ $p->id }}">{{ $p->nombre }}</option>
            @endforeach
        </select>

        <select class="w-full rounded-xl border p-2" wire:model.live="estatus">
            <option value="todas">Todas</option>
            <option value="pendientes">Pendientes</option>
            <option value="pagadas">Pagadas</option>
        </select>

        <select class="w-full rounded-xl border p-2" wire:model.live="tipo_contrato">
            <option value="todos">Todos los tipos</option>
            <option value="terreno">Contrato terreno</option>
            <option value="servicio">Contrato por servicio</option>
        </select>

        <input type="month" class="w-full rounded-xl border p-2" wire:model.change="mes">

        @if(($tipo_contrato ?? 'todos') === 'servicio')
            <select class="w-full rounded-xl border p-2 sm:col-span-2 lg:col-span-2"
                    wire:model.live="tipo_servicio_id">
                <option value="">Todos los servicios</option>
                @foreach(($tiposServicio ?? []) as $tc)
                    <option value="{{ $tc->id }}">{{ $tc->nombre }}</option>
                @endforeach
            </select>
        @endif

        <input type="date" class="w-full rounded-xl border p-2" wire:model.change="desde">
        <input type="date" class="w-full rounded-xl border p-2" wire:model.change="hasta">

        <button type="button"
                wire:click="limpiarFiltros"
                class="w-full sm:w-auto px-4 py-2 rounded-xl border hover:bg-gray-50 text-sm font-semibold sm:col-span-2 lg:col-span-4 justify-self-start">
            Limpiar filtros
        </button>
    </div>

    {{-- TABLA (md+) --}}
    <div class="hidden md:block overflow-x-auto rounded-2xl border bg-white">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr class="text-left">
                    <th class="p-3">Cliente</th>
                    <th class="p-3">Contrato</th>
                    <th class="p-3">Lote</th>
                    <th class="p-3">Cuota</th>
                    <th class="p-3">Vence</th>
                    <th class="p-3">Monto</th>
                    <th class="p-3">Estatus</th>
                    <th class="p-3 text-right">Acciones</th>
                </tr>
            </thead>

            <tbody>
                @forelse($cuotas as $cuota)
                    @php
                        $contrato = $cuota->contrato;
                        $cliente = $contrato?->cliente;
                        $lote = $contrato?->lote;
                        $fracc = $lote?->fraccionamiento;

                        $esPagada = ($cuota->estatus === 'pagada');
                        $tieneRecibo = (bool) (($cuota->tiene_pago_recibo ?? false) || ($cuota->tiene_recibo_directo ?? false));

                        $tipoContrato = $contrato?->tipo;
                        $esServicio = ($tipoContrato === 'servicio');

                        $esAnualidad = (bool)($cuota->es_anualidad ?? false) || (mb_strtoupper((string)($cuota->concepto ?? '')) === 'ANUALIDAD');

                        $fechaVencimiento = \Carbon\Carbon::parse($cuota->fecha_vencimiento);
                        $venceHoy = $fechaVencimiento->isToday();
                        $venceAtrasada = $fechaVencimiento->lt(today());

                        $esHistorico = (bool) (($cuota->tiene_recibo_directo ?? false) && !($cuota->tiene_pago_recibo ?? false));
                    @endphp

                    <tr wire:key="cuota-row-{{ $cuota->id }}"
                        class="border-t transition
                            {{ $esAnualidad ? 'bg-purple-50 hover:bg-purple-100' : ($esServicio ? 'bg-blue-50 hover:bg-blue-100' : 'bg-white hover:bg-gray-50') }}">

                        <td class="p-3">
                            <div class="font-semibold text-gray-900">{{ $cliente?->nombre_completo }}</div>
                        </td>

                        <td class="p-3">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-semibold">{{ $contrato?->folio_contrato }}</span>

                                @if($esServicio)
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-bold border border-blue-300 bg-blue-200 text-blue-900">
                                        SERVICIO
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-bold border border-gray-300 bg-gray-100 text-gray-700">
                                        TERRENO
                                    </span>
                                @endif

                                @if($esAnualidad)
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-black border border-purple-300 bg-purple-100 text-purple-900">
                                        ANUALIDAD
                                    </span>
                                @endif
                            </div>
                        </td>

                        <td class="p-3">
                            <div class="text-gray-900 font-medium">
                                {{ $fracc?->nombre ? $fracc->nombre.' | ' : '' }}{{ $lote?->clave }}
                            </div>
                        </td>

                        <td class="p-3">
                            <div class="flex items-center gap-2">
                                <span class="font-bold">#{{ $cuota->numero }}</span>
                                @if($esAnualidad)
                                    <span class="text-xs text-purple-800 font-semibold">
                                        (abono anual)
                                    </span>
                                @endif
                            </div>
                        </td>

                        <td class="p-3">
                            <span class="
                                @if(!$esPagada && $venceHoy)
                                    text-green-600 font-bold
                                @elseif(!$esPagada && $venceAtrasada)
                                    text-red-600 font-bold
                                @else
                                    text-gray-900
                                @endif
                            ">
                                {{ $fechaVencimiento->format('d/m/Y') }}
                            </span>
                        </td>

                        <td class="p-3"><span class="font-bold">${{ number_format((float)$cuota->monto, 2) }}</span></td>

                        <td class="p-3">
                            <div class="flex flex-col gap-1">
                                @if($esPagada)
                                    <span class="px-2 py-1 rounded-lg border border-green-300 bg-green-100 text-green-800 text-xs font-bold w-fit">
                                        PAGADA
                                    </span>
                                @else
                                    <span class="px-2 py-1 rounded-lg border border-amber-300 bg-amber-100 text-amber-800 text-xs font-bold w-fit">
                                        PENDIENTE
                                    </span>
                                @endif

                                @if($esHistorico)
                                    <span class="px-2 py-1 rounded-lg border border-yellow-300 bg-yellow-100 text-yellow-800 text-[11px] font-bold w-fit">
                                        HISTÓRICO
                                    </span>
                                @endif
                            </div>
                        </td>

                        <td class="p-3 text-right">
                            <div class="flex justify-end gap-2 flex-wrap">
                                @if(!$esPagada)
                                    @if($tipoContrato === 'terreno')
                                        <button class="px-3 py-1.5 rounded-xl border hover:bg-gray-100 font-semibold transition"
                                                wire:click="crearReciboDesdeCuota({{ $cuota->id }})">
                                            Crear recibo
                                        </button>
                                    @endif

                                    @if($tipoContrato === 'servicio')
                                        <button class="px-3 py-1.5 rounded-xl border border-blue-300 bg-blue-100 hover:bg-blue-200 font-semibold transition"
                                                wire:click="crearReciboServicioDesdeCuota({{ $cuota->id }})">
                                            Crear recibo
                                        </button>
                                    @endif
                                @endif

                                @if($esPagada && $tieneRecibo)
                                    <button class="px-3 py-1.5 rounded-xl bg-black text-white hover:bg-gray-800 font-semibold transition"
                                            wire:click="imprimirReciboDeCuota({{ $cuota->id }})">
                                        Imprimir recibo
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td class="p-6 text-gray-500 text-center" colspan="8">No hay cuotas.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- CARDS (mobile) --}}
    <div class="md:hidden space-y-3">
        @forelse($cuotas as $cuota)
            @php
                $contrato = $cuota->contrato;
                $cliente = $contrato?->cliente;
                $lote = $contrato?->lote;
                $fracc = $lote?->fraccionamiento;

                $esPagada = ($cuota->estatus === 'pagada');
                $tieneRecibo = (bool) (($cuota->tiene_pago_recibo ?? false) || ($cuota->tiene_recibo_directo ?? false));

                $tipoContrato = $contrato?->tipo;
                $esServicio = ($tipoContrato === 'servicio');

                $esAnualidad = (bool)($cuota->es_anualidad ?? false) || (mb_strtoupper((string)($cuota->concepto ?? '')) === 'ANUALIDAD');

                $fechaVencimiento = \Carbon\Carbon::parse($cuota->fecha_vencimiento);
                $venceHoy = $fechaVencimiento->isToday();
                $venceAtrasada = $fechaVencimiento->lt(today());

                $esHistorico = (bool) (($cuota->tiene_recibo_directo ?? false) && !($cuota->tiene_pago_recibo ?? false));
            @endphp

            <div wire:key="cuota-card-{{ $cuota->id }}"
                 class="rounded-2xl border bg-white p-4
                    {{ $esServicio ? 'border-blue-200' : '' }}
                    {{ $esAnualidad ? 'border-purple-200 bg-purple-50' : '' }}">

                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="font-black leading-tight truncate">{{ $cliente?->nombre_completo }}</div>

                        <div class="mt-1 flex flex-wrap items-center gap-2 text-xs">
                            <span class="text-gray-500">Contrato:</span>
                            <span class="font-semibold">{{ $contrato?->folio_contrato ?? '—' }}</span>

                            @if($esServicio)
                                <span class="px-2 py-0.5 rounded-full text-[11px] font-bold border border-blue-300 bg-blue-100 text-blue-900">SERVICIO</span>
                            @else
                                <span class="px-2 py-0.5 rounded-full text-[11px] font-bold border border-gray-300 bg-gray-100 text-gray-700">TERRENO</span>
                            @endif

                            @if($esAnualidad)
                                <span class="px-2 py-0.5 rounded-full text-[11px] font-black border border-purple-300 bg-purple-100 text-purple-900">
                                    ANUALIDAD
                                </span>
                            @endif
                        </div>

                        <div class="mt-1 text-sm text-gray-700 break-words">
                            <span class="text-gray-500">Lote:</span>
                            <span class="font-semibold">
                                {{ $fracc?->nombre ? $fracc->nombre.' | ' : '' }}{{ $lote?->clave ?? '—' }}
                            </span>
                        </div>
                    </div>

                    <div class="text-right shrink-0">
                        <div class="text-xs text-gray-500">Monto</div>
                        <div class="font-black">${{ number_format((float)$cuota->monto, 2) }}</div>
                    </div>
                </div>

                <div class="mt-3 grid grid-cols-2 gap-2 text-sm">
                    <div>
                        <div class="text-xs text-gray-500">Cuota</div>
                        <div class="font-black">
                            #{{ $cuota->numero }}
                            @if($esAnualidad)
                                <span class="text-xs text-purple-800 font-semibold">(abono anual)</span>
                            @endif
                        </div>
                    </div>

                    <div class="text-right">
                        <div class="text-xs text-gray-500">Vence</div>
                        <div class="
                            @if(!$esPagada && $venceHoy)
                                text-green-600 font-black
                            @elseif(!$esPagada && $venceAtrasada)
                                text-red-600 font-black
                            @else
                                font-semibold
                            @endif
                        ">
                            {{ $fechaVencimiento->format('d/m/Y') }}
                        </div>
                    </div>
                </div>

                <div class="mt-3 flex items-center justify-between gap-2">
                    <div class="flex flex-col gap-1">
                        @if($esPagada)
                            <span class="px-2 py-1 rounded-lg border border-green-300 bg-green-100 text-green-800 text-xs font-bold w-fit">
                                PAGADA
                            </span>
                        @else
                            <span class="px-2 py-1 rounded-lg border border-amber-300 bg-amber-100 text-amber-800 text-xs font-bold w-fit">
                                PENDIENTE
                            </span>
                        @endif

                        @if($esHistorico)
                            <span class="px-2 py-1 rounded-lg border border-yellow-300 bg-yellow-100 text-yellow-800 text-[11px] font-bold w-fit">
                                HISTÓRICO
                            </span>
                        @endif
                    </div>

                    <div class="flex gap-2">
                        @if(!$esPagada)
                            @if($tipoContrato === 'terreno')
                                <button class="px-3 py-2 rounded-xl border hover:bg-gray-50 font-semibold"
                                        wire:click="crearReciboDesdeCuota({{ $cuota->id }})">
                                    Crear recibo
                                </button>
                            @endif

                            @if($tipoContrato === 'servicio')
                                <button class="px-3 py-2 rounded-xl border border-blue-300 bg-blue-100 hover:bg-blue-200 font-semibold"
                                        wire:click="crearReciboServicioDesdeCuota({{ $cuota->id }})">
                                    Crear recibo
                                </button>
                            @endif
                        @endif

                        @if($esPagada && $tieneRecibo)
                            <button class="px-3 py-2 rounded-xl bg-black text-white hover:bg-gray-800 font-semibold"
                                    wire:click="imprimirReciboDeCuota({{ $cuota->id }})">
                                Imprimir
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="rounded-2xl border bg-white p-6 text-gray-500 text-center">
                No hay cuotas.
            </div>
        @endforelse
    </div>

    {{-- Paginación --}}
    <div class="mt-4">
        {{ $cuotas->links() }}
    </div>

    {{-- Modal imprimir --}}
    @if($modalImprimir ?? false)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/40" wire:click="cerrarModalImprimir"></div>

            <div class="relative w-full max-w-lg bg-white rounded-2xl border shadow-xl p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-black">Esta cuota tiene RECARGO</h3>
                        <p class="text-sm text-gray-500 mt-1">¿Qué recibo deseas imprimir?</p>
                    </div>

                    <button class="px-2 py-1 rounded-lg border hover:bg-gray-50"
                            wire:click="cerrarModalImprimir">✕</button>
                </div>

                <div class="mt-4 grid gap-2">
                    <button class="w-full px-4 py-2 rounded-xl border hover:bg-gray-50 font-semibold text-left"
                            wire:click="confirmarImpresion('principal')">
                        🧾 Imprimir SOLO recibo normal
                    </button>

                    <button class="w-full px-4 py-2 rounded-xl border hover:bg-gray-50 font-semibold text-left"
                            wire:click="confirmarImpresion('recargo')">
                        ⚠️ Imprimir SOLO recibo de recargo
                    </button>

                    <button class="w-full px-4 py-2 rounded-xl bg-black text-white font-semibold text-left"
                            wire:click="confirmarImpresion('ambos')">
                        ✅ Imprimir AMBOS
                    </button>
                </div>

                <div class="mt-4 text-xs text-gray-500">
                    Tip: Si el navegador bloquea popups, permite ventanas emergentes para que se abran ambos.
                </div>
            </div>
        </div>
    @endif

</div>

<script>
    document.addEventListener('livewire:init', () => {
        Livewire.on('open-print-tab', (payload) => {
            const url = payload?.url;
            if (!url) return;
            window.open(url, '_blank', 'noopener,noreferrer');
        });
    });
</script>