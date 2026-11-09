<div class="max-w-7xl mx-auto p-4 space-y-6">

    {{-- Header --}}
    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-3">
        <div>
            <h1 class="text-2xl font-black">Adelanta y gana</h1>
            <p class="text-gray-600">
                Cuántas veces se usó la promoción, el efectivo que entró por ella y lo que se bonificó a los saldos.
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('reportes.index') }}"
                class="rounded-xl border px-4 py-2 hover:bg-gray-50">
                ← Volver
            </a>

            <button wire:click="limpiar"
                type="button"
                class="rounded-xl border px-4 py-2 hover:bg-gray-50">
                Limpiar
            </button>

            <button wire:click="exportExcel"
                type="button"
                @disabled(! $promocionId)
                class="rounded-xl bg-black text-white px-4 py-2 font-semibold hover:opacity-90 disabled:opacity-40">
                Exportar Excel
            </button>
        </div>
    </div>

    @if($this->promociones->isEmpty())
        <div class="bg-white rounded-2xl border p-10 text-center text-gray-500">
            Todavía no hay ninguna promoción «Adelanta y gana». Se da de alta en Administración → Promociones.
        </div>
    @else
        @php($resumen = $this->resumen)

        {{-- Filtros --}}
        <div class="bg-white rounded-2xl border p-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="text-sm text-gray-600">Promoción</label>
                    <select wire:model.live="promocionId" class="block w-full rounded-xl border px-3 py-2">
                        @foreach($this->promociones as $p)
                            <option value="{{ $p->id }}">
                                {{ $p->nombre }}
                                @if($p->fecha_inicio) ({{ $p->fecha_inicio->format('d/m/Y') }}) @endif
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="text-sm text-gray-600">Fraccionamiento</label>
                    <select wire:model.live="fraccionamientoId" class="block w-full rounded-xl border px-3 py-2">
                        <option value="">Todos</option>
                        @foreach($this->fraccionamientos as $f)
                            <option value="{{ $f->id }}">{{ $f->nombre }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="text-sm text-gray-600">Desde</label>
                    <input type="date" wire:model.live="desde" class="block w-full rounded-xl border px-3 py-2">
                </div>

                <div>
                    <label class="text-sm text-gray-600">Hasta</label>
                    <input type="date" wire:model.live="hasta" class="block w-full rounded-xl border px-3 py-2">
                </div>
            </div>

            @if($this->promocion?->fecha_inicio || $this->promocion?->fecha_fin)
                <div class="mt-3 text-xs text-gray-500">
                    Vigencia de la promoción:
                    {{ $this->promocion->fecha_inicio?->format('d/m/Y') ?? '—' }}
                    —
                    {{ $this->promocion->fecha_fin?->format('d/m/Y') ?? '—' }}
                </div>
            @endif
        </div>

        {{-- Resumen --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="bg-white rounded-2xl border p-4">
                <div class="text-xs uppercase tracking-wide text-gray-500">Veces que se usó</div>
                <div class="text-2xl font-black mt-1">{{ number_format($resumen['usos']) }}</div>
                <div class="text-sm text-gray-600 mt-1">
                    {{ number_format($resumen['clientes']) }} {{ $resumen['clientes'] === 1 ? 'cliente' : 'clientes' }}
                </div>
            </div>

            <div class="bg-white rounded-2xl border p-4">
                <div class="text-xs uppercase tracking-wide text-gray-500">Efectivo recibido</div>
                <div class="text-2xl font-black mt-1">${{ number_format($resumen['abonado'], 2) }}</div>
                <div class="text-sm text-gray-600 mt-1">
                    Promedio ${{ number_format($resumen['usos'] ? $resumen['abonado'] / $resumen['usos'] : 0, 2) }} por abono
                </div>
            </div>

            <div class="bg-white rounded-2xl border p-4">
                <div class="text-xs uppercase tracking-wide text-gray-500">Bonificado</div>
                <div class="text-2xl font-black mt-1 text-emerald-700">${{ number_format($resumen['bonificado'], 2) }}</div>
                <div class="text-sm text-gray-600 mt-1">Lo que costó la promoción</div>
            </div>

            <div class="bg-white rounded-2xl border p-4">
                <div class="text-xs uppercase tracking-wide text-gray-500">Bajaron los saldos</div>
                <div class="text-2xl font-black mt-1">${{ number_format($resumen['abonado'] + $resumen['bonificado'], 2) }}</div>
                <div class="text-sm text-gray-600 mt-1">
                    @if($resumen['anulados'])
                        {{ $resumen['anulados'] }} {{ $resumen['anulados'] === 1 ? 'anulado' : 'anulados' }}, no cuentan
                    @else
                        Efectivo + bonificación
                    @endif
                </div>
            </div>
        </div>

        {{-- Por fraccionamiento --}}
        @if($this->porFraccionamiento->count() > 1)
            <div class="bg-white rounded-2xl border overflow-hidden">
                <div class="px-4 py-3 border-b font-bold">Por fraccionamiento</div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr class="text-left">
                                <th class="px-4 py-2">Fraccionamiento</th>
                                <th class="px-4 py-2 text-right">Usos</th>
                                <th class="px-4 py-2 text-right">Clientes</th>
                                <th class="px-4 py-2 text-right">Efectivo</th>
                                <th class="px-4 py-2 text-right">Bonificado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($this->porFraccionamiento as $f)
                                <tr class="border-t">
                                    <td class="px-4 py-2 font-semibold">{{ $f['nombre'] }}</td>
                                    <td class="px-4 py-2 text-right">{{ number_format($f['usos']) }}</td>
                                    <td class="px-4 py-2 text-right">{{ number_format($f['clientes']) }}</td>
                                    <td class="px-4 py-2 text-right">${{ number_format($f['abonado'], 2) }}</td>
                                    <td class="px-4 py-2 text-right text-emerald-700">${{ number_format($f['bonificado'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- Detalle --}}
        <div class="bg-white rounded-2xl border overflow-hidden">
            <div class="px-4 py-3 border-b flex items-center justify-between gap-3">
                <div class="font-bold">Abonos</div>
                <label class="inline-flex items-center gap-2 text-sm text-gray-600">
                    <input type="checkbox" wire:model.live="verAnulados" class="rounded border-gray-300 text-black focus:ring-black">
                    Mostrar anulados
                </label>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr class="text-left">
                            <th class="px-4 py-3">Fecha</th>
                            <th class="px-4 py-3">Recibo</th>
                            <th class="px-4 py-3">Cliente</th>
                            <th class="px-4 py-3">Contrato / Lote</th>
                            <th class="px-4 py-3 text-right">Abonó</th>
                            <th class="px-4 py-3 text-right">Bonificación</th>
                            <th class="px-4 py-3 text-right">Saldo</th>
                            <th class="px-4 py-3">Capturó</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($this->abonos as $a)
                            @php($lote = $a->contrato?->lote)
                            <tr class="border-t hover:bg-gray-50 {{ $a->anulado_at ? 'opacity-60' : '' }}" wire:key="abono-{{ $a->id }}">
                                <td class="px-4 py-3 whitespace-nowrap">
                                    {{ $a->created_at->format('d/m/Y H:i') }}
                                    @if($a->anulado_at)
                                        <div class="text-xs font-bold text-red-700">ANULADO {{ $a->anulado_at->format('d/m/Y') }}</div>
                                    @endif
                                </td>

                                <td class="px-4 py-3 font-semibold whitespace-nowrap">
                                    @if($a->recibo?->uuid && ! $a->recibo->trashed())
                                        <a href="{{ route('admin.recibos.edit', $a->recibo->uuid) }}" class="text-blue-700 hover:underline">
                                            {{ $a->recibo->folio }}
                                        </a>
                                    @else
                                        {{ $a->recibo?->folio ?? '—' }}
                                    @endif
                                </td>

                                <td class="px-4 py-3">{{ $a->contrato?->cliente?->nombre_completo ?: '—' }}</td>

                                <td class="px-4 py-3">
                                    <div class="font-semibold">
                                        @if($a->contrato?->uuid)
                                            <a href="{{ route('admin.contratos.show', $a->contrato->uuid) }}" class="hover:underline">
                                                {{ $a->contrato->folio_contrato }}
                                            </a>
                                        @else
                                            {{ $a->contrato?->folio_contrato ?? '—' }}
                                        @endif
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        {{ $lote?->fraccionamiento?->nombre ?? '—' }} · MZ {{ $lote?->manzana ?? '—' }} · LT {{ $lote?->lote ?? '—' }}
                                    </div>
                                </td>

                                <td class="px-4 py-3 text-right font-black whitespace-nowrap">
                                    ${{ number_format((float) $a->monto_abono, 2) }}
                                </td>

                                <td class="px-4 py-3 text-right font-semibold text-emerald-700 whitespace-nowrap">
                                    + ${{ number_format((float) $a->monto_bonificacion, 2) }}
                                </td>

                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <div class="text-xs text-gray-500 line-through">${{ number_format((float) $a->saldo_anterior, 2) }}</div>
                                    <div class="font-semibold">${{ number_format((float) $a->saldo_nuevo, 2) }}</div>
                                </td>

                                <td class="px-4 py-3">{{ $a->capturadoPor?->name ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-10 text-center text-gray-500">
                                    No se ha usado la promoción con esos filtros.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="p-4 border-t">
                {{ $this->abonos->links() }}
            </div>
        </div>
    @endif
</div>
