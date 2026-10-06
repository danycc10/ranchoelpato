<div class="max-w-7xl mx-auto p-6 space-y-5">

    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-black">Validación de evidencias</h1>
            <p class="text-gray-500 text-sm">
                @if($this->pendientes > 0)
                    Faltan por revisar <span class="font-bold text-gray-700">{{ $this->pendientes }}</span>
                    {{ $this->pendientes === 1 ? 'comprobante' : 'comprobantes' }}.
                @else
                    No hay comprobantes esperando revisión.
                @endif
            </p>
        </div>

        <a href="{{ route('admin.recibos.index') }}"
            class="px-4 py-2 border rounded-xl font-bold text-sm hover:bg-gray-50">
            Ir a recibos
        </a>
    </div>

    {{-- FILTROS --}}
    <div class="bg-white border rounded-2xl p-5 shadow-sm">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4">

            <div class="lg:col-span-2">
                <label class="text-xs text-gray-500 font-semibold">Buscar</label>
                <input
                    type="text"
                    wire:model.live.debounce.400ms="q"
                    placeholder="Cliente, folio o referencia"
                    class="mt-1 w-full border rounded-xl px-4 py-3 text-sm">
            </div>

            <div>
                <label class="text-xs text-gray-500 font-semibold">Estado</label>
                <select wire:model.live="estado" class="mt-1 w-full border rounded-xl px-3 py-3 text-sm">
                    <option value="sin_validar">Por validar</option>
                    <option value="validados">Ya validados</option>
                    <option value="todos">Todos</option>
                </select>
            </div>

            <div>
                <label class="text-xs text-gray-500 font-semibold">Forma de pago</label>
                <select wire:model.live="forma_pago_id" class="mt-1 w-full border rounded-xl px-3 py-3 text-sm">
                    <option value="">Todas</option>
                    @foreach($this->formasPago as $forma)
                    <option value="{{ $forma->id }}">{{ $forma->nombre }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Mismo selector de cuentas que en Movimientos bancarios. --}}
            <div x-data="{ abierto: false }" @click.away="abierto = false">
                <label class="text-xs text-gray-500 font-semibold">Cuenta / tarjeta</label>

                <button type="button" @click="abierto = !abierto"
                    class="mt-1 w-full border rounded-xl px-3 py-3 text-sm text-left bg-white flex items-center justify-between gap-2">
                    <span class="truncate">
                        @if(count($cuentas_ids) === 0)
                            Todas
                        @elseif(count($cuentas_ids) === 1)
                            1 cuenta
                        @else
                            {{ count($cuentas_ids) }} cuentas
                        @endif
                    </span>

                    <svg class="w-4 h-4 text-gray-500 shrink-0 transition-transform" :class="{ 'rotate-180': abierto }"
                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <div x-show="abierto" x-transition class="relative z-30" style="display: none;">
                    <div class="absolute mt-2 w-full min-w-[16rem] rounded-2xl border bg-white shadow-xl overflow-hidden">
                        <div class="px-3 py-2 border-b bg-gray-50 flex items-center justify-between gap-2">
                            <div class="text-sm font-semibold text-gray-700">Una o varias</div>

                            @if(count($cuentas_ids))
                            <button type="button" wire:click="limpiarCuentas"
                                class="text-xs font-semibold text-red-600 hover:text-red-700">
                                Limpiar
                            </button>
                            @endif
                        </div>

                        <div class="max-h-72 overflow-y-auto p-2 space-y-1">
                            @forelse($this->cuentas as $cuenta)
                            <label wire:key="cuenta-{{ $cuenta->id }}"
                                class="flex items-start gap-3 rounded-xl px-3 py-2 hover:bg-gray-50 cursor-pointer">
                                <input type="checkbox"
                                    wire:model.live="cuentas_ids"
                                    value="{{ $cuenta->id }}"
                                    class="mt-1 rounded border-gray-300 text-black focus:ring-black">

                                <div class="min-w-0">
                                    <div class="font-semibold text-sm text-gray-900 truncate">
                                        {{ $cuenta->alias ?: trim(($cuenta->banco ?? '').' '.($cuenta->numero ?? '')) }}
                                    </div>
                                </div>
                            </label>
                            @empty
                            <div class="px-3 py-6 text-sm text-gray-500 text-center">
                                Ninguna cuenta tiene comprobantes por aquí.
                            </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="text-xs text-gray-500 font-semibold">Desde</label>
                    <input type="date" wire:model.live="desde"
                        class="mt-1 w-full border rounded-xl px-2 py-3 text-sm">
                </div>
                <div>
                    <label class="text-xs text-gray-500 font-semibold">Hasta</label>
                    <input type="date" wire:model.live="hasta"
                        class="mt-1 w-full border rounded-xl px-2 py-3 text-sm">
                </div>
            </div>
        </div>

        <div class="mt-4 flex justify-end">
            <button type="button" wire:click="limpiarFiltros"
                class="text-sm text-gray-500 hover:text-black font-semibold">
                Limpiar filtros
            </button>
        </div>
    </div>

    {{-- BARRA DE ACCIONES: aparece solo cuando hay algo palomeado --}}
    @if(count($seleccion) > 0)
    @php
    // Cada boton sale solo si hay algo a lo que aplicarle: ofrecer "validar"
    // sobre pagos ya validados es prometer algo que no va a pasar.
    $porValidar = $this->seleccionPorValidar;
    $yaValidados = $this->seleccionValidados;
    @endphp

    <div class="sticky top-4 z-20 bg-black text-white rounded-2xl px-5 py-4 shadow-lg flex flex-wrap items-center justify-between gap-3">
        <div class="text-sm">
            <span class="font-bold">
                {{ count($seleccion) }} {{ count($seleccion) === 1 ? 'pago seleccionado' : 'pagos seleccionados' }}
            </span>

            @if($porValidar > 0 && $yaValidados > 0)
            <span class="text-white/60">
                · {{ $porValidar }} por validar, {{ $yaValidados }} ya {{ $yaValidados === 1 ? 'validado' : 'validados' }}
            </span>
            @endif
        </div>

        <div class="flex flex-wrap gap-2">
            @if($porValidar > 0)
            <button type="button" wire:click="validarSeleccionados"
                wire:loading.attr="disabled" wire:target="validarSeleccionados"
                class="px-4 py-2 bg-green-500 hover:bg-green-600 rounded-xl font-bold text-sm disabled:opacity-50">
                <span wire:loading.remove wire:target="validarSeleccionados">
                    Marcar {{ $porValidar }} como {{ $porValidar === 1 ? 'validado' : 'validados' }}
                </span>
                <span wire:loading wire:target="validarSeleccionados">Validando…</span>
            </button>
            @endif

            @if($yaValidados > 0)
            <button type="button" wire:click="quitarValidacionSeleccionados"
                wire:loading.attr="disabled" wire:target="quitarValidacionSeleccionados"
                class="px-4 py-2 border border-white/40 hover:bg-white/10 rounded-xl font-bold text-sm disabled:opacity-50">
                <span wire:loading.remove wire:target="quitarValidacionSeleccionados">
                    Quitar validación a {{ $yaValidados }}
                </span>
                <span wire:loading wire:target="quitarValidacionSeleccionados">Quitando…</span>
            </button>
            @endif

            <button type="button" wire:click="$set('seleccion', [])"
                class="px-4 py-2 text-white/70 hover:text-white font-semibold text-sm">
                Cancelar
            </button>
        </div>
    </div>
    @endif

    {{-- TABLA --}}
    <div class="bg-white border rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
                    <tr>
                        <th class="px-4 py-3 w-10">
                            {{-- La wire:key lleva el estado a propósito: sin ella Livewire
                                 reusa el mismo <input> y la palomita se queda prendida en el
                                 DOM aunque el atributo `checked` ya no venga. Al cambiar la
                                 key, el elemento se recrea con el estado correcto. --}}
                            <input type="checkbox"
                                wire:key="check-pagina-{{ $pagos->currentPage() }}-{{ $this->paginaCompleta() ? 'on' : 'off' }}"
                                wire:click="alternarPagina"
                                @checked($this->paginaCompleta())
                                title="Seleccionar todo lo de esta página"
                                class="rounded border-gray-300">
                        </th>
                        <th class="px-4 py-3 text-left">Evidencia</th>
                        <th class="px-4 py-3 text-left">Fecha</th>
                        <th class="px-4 py-3 text-left">Folio</th>
                        <th class="px-4 py-3 text-left">Cliente</th>
                        <th class="px-4 py-3 text-left">Forma / Cuenta</th>
                        <th class="px-4 py-3 text-left">Referencia</th>
                        <th class="px-4 py-3 text-right">Monto</th>
                        <th class="px-4 py-3 text-left">Estado</th>
                    </tr>
                </thead>

                <tbody class="divide-y">
                    @forelse($pagos as $pago)
                    @php
                    $url = route('admin.recibo-pagos.evidencia.show', $pago->id).'?v='.urlencode((string) $pago->evidencia_path);
                    $esPdf = $pago->evidencia_mime === 'application/pdf';
                    @endphp

                    <tr wire:key="pago-{{ $pago->id }}"
                        class="hover:bg-gray-50 {{ in_array($pago->id, $seleccion) ? 'bg-gray-50' : '' }}">

                        <td class="px-4 py-3 align-top">
                            <input type="checkbox"
                                value="{{ $pago->id }}"
                                wire:model.live="seleccion"
                                class="rounded border-gray-300">
                        </td>

                        <td class="px-4 py-3">
                            <button type="button" wire:click="verEvidencia({{ $pago->id }})"
                                class="block rounded-lg overflow-hidden border hover:ring-2 hover:ring-black transition"
                                title="Ver comprobante en grande">
                                @if($esPdf)
                                <div class="w-16 h-16 flex flex-col items-center justify-center bg-gray-100 text-gray-500">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5A3.375 3.375 0 0 0 10.125 2.25H8.25A3.375 3.375 0 0 0 4.875 5.625v12.75A3.375 3.375 0 0 0 8.25 21.75h7.5A3.375 3.375 0 0 0 19.125 18.375Z" />
                                    </svg>
                                    <span class="text-[10px] font-bold mt-1">PDF</span>
                                </div>
                                @else
                                <img src="{{ $url }}" alt="Comprobante de {{ $pago->recibo?->folio }}"
                                    class="w-16 h-16 object-cover bg-gray-100">
                                @endif
                            </button>
                        </td>

                        <td class="px-4 py-3 whitespace-nowrap">
                            {{ optional($pago->fecha_efectiva)->format('d/m/Y') ?? '—' }}
                        </td>

                        <td class="px-4 py-3 font-mono text-xs">
                            {{ $pago->recibo?->folio ?? '—' }}
                        </td>

                        <td class="px-4 py-3">
                            {{ trim(($pago->recibo?->cliente?->nombres ?? '').' '.($pago->recibo?->cliente?->apellidos ?? '')) ?: '—' }}
                        </td>

                        <td class="px-4 py-3">
                            <div class="font-semibold">{{ $pago->formaPago?->nombre ?? '—' }}</div>
                            @if($pago->cuentaBancaria)
                            <div class="text-xs text-gray-500">{{ $pago->cuentaBancaria->alias }}</div>
                            @endif
                        </td>

                        <td class="px-4 py-3 text-xs text-gray-600">
                            {{ $pago->referencia ?: '—' }}
                        </td>

                        <td class="px-4 py-3 text-right font-bold whitespace-nowrap">
                            ${{ number_format((float) $pago->monto, 2) }}
                        </td>

                        <td class="px-4 py-3">
                            @if($pago->validado_at)
                            <span class="inline-flex px-2 py-1 rounded-full text-xs font-bold bg-green-100 text-green-800">
                                Validado
                            </span>
                            <div class="text-[11px] text-gray-500 mt-1">
                                {{ $pago->validado_at->format('d/m/Y H:i') }}
                                @if($pago->validadoPor)
                                · {{ $pago->validadoPor->name }}
                                @endif
                            </div>
                            @else
                            <span class="inline-flex px-2 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800">
                                Por validar
                            </span>
                            @endif
                        </td>
                    </tr>

                    @empty
                    <tr>
                        <td colspan="9" class="px-4 py-12 text-center text-gray-400">
                            @if($estado === 'sin_validar')
                            Todo al corriente: no hay comprobantes esperando revisión.
                            @else
                            No hay pagos con evidencia que cumplan el filtro.
                            @endif
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4 border-t">
            {{ $pagos->links() }}
        </div>
    </div>

    {{-- MODAL DEL COMPROBANTE --}}
    @if($modalEvidenciaOpen && $this->evidencia)
    @php
    $ev = $this->evidencia;
    $evUrl = route('admin.recibo-pagos.evidencia.show', $ev->id).'?v='.urlencode((string) $ev->evidencia_path);
    @endphp

    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
        wire:click.self="cerrarEvidencia">
        <div class="bg-white rounded-2xl max-w-3xl w-full max-h-[90vh] overflow-auto">

            <div class="flex items-start justify-between p-5 border-b">
                <div>
                    <div class="font-black">{{ $ev->recibo?->folio ?? 'Comprobante' }}</div>
                    <div class="text-sm text-gray-500">
                        {{ $ev->formaPago?->nombre }} ·
                        ${{ number_format((float) $ev->monto, 2) }} ·
                        {{ optional($ev->fecha_efectiva)->format('d/m/Y') }}
                    </div>
                </div>

                <button type="button" wire:click="cerrarEvidencia"
                    class="text-gray-400 hover:text-black font-bold text-xl leading-none">&times;</button>
            </div>

            <div class="p-5">
                @if($ev->evidencia_mime === 'application/pdf')
                <a href="{{ $evUrl }}" target="_blank" rel="noopener"
                    class="block text-center px-4 py-10 border-2 border-dashed rounded-xl text-gray-500 hover:bg-gray-50">
                    El comprobante es un PDF. Ábrelo en otra pestaña.
                </a>
                @else
                <a href="{{ $evUrl }}" target="_blank" rel="noopener" title="Abrir en tamaño completo">
                    <img src="{{ $evUrl }}" alt="Comprobante" class="w-full rounded-xl border">
                </a>
                @endif
            </div>
        </div>
    </div>
    @endif
</div>
