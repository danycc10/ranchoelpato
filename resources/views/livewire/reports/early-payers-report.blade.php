<div class="max-w-7xl mx-auto p-6 space-y-5">

    <div class="flex items-start justify-between">
        <div>
            <h1 class="text-2xl font-black">Participantes de rifa</h1>
            <p class="text-gray-500 text-sm">Mensuales que pagaron antes del día 10 · Semanales con 2+ pagos antes del día 10.</p>
        </div>

        <button
            wire:click="exportExcel"
            wire:loading.attr="disabled"
            wire:target="exportExcel"
            class="flex items-center gap-2 px-4 py-2 border rounded-xl font-semibold hover:bg-gray-50 disabled:opacity-50">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
            </svg>
            Exportar Excel
        </button>
    </div>

    {{-- FILTROS --}}
    <div class="bg-white border rounded-2xl p-5 shadow-sm">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">

            <div>
                <label class="text-xs text-gray-500 font-semibold">Año</label>
                <input
                    type="number"
                    wire:model.live="anio"
                    min="2020"
                    max="2099"
                    class="mt-1 w-full border rounded-xl px-3 py-3 text-sm">
            </div>

            <div>
                <label class="text-xs text-gray-500 font-semibold">Mes</label>
                <select wire:model.live="mes" class="mt-1 w-full border rounded-xl px-3 py-3 text-sm">
                    @foreach(range(1, 12) as $m)
                    <option value="{{ $m }}">{{ mb_strtoupper(now()->setDate($anio, $m, 1)->translatedFormat('F')) }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="text-xs text-gray-500 font-semibold">Propietario</label>
                <select wire:model.live="propietarioId" class="mt-1 w-full border rounded-xl px-3 py-3 text-sm">
                    <option value="">Todos</option>
                    @foreach($propietarios as $p)
                    <option value="{{ $p->id }}">{{ $p->nombre }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="text-xs text-gray-500 font-semibold">Frecuencia</label>
                <select wire:model.live="frecuencia" class="mt-1 w-full border rounded-xl px-3 py-3 text-sm">
                    <option value="ambas">Ambas</option>
                    <option value="mensual">Solo mensuales</option>
                    <option value="semanal">Solo semanales</option>
                </select>
            </div>

        </div>
    </div>

    {{-- TABLA --}}
    <div class="bg-white border rounded-2xl overflow-hidden shadow-sm">

        <div class="px-5 py-3 border-b flex items-center justify-between">
            <span class="font-bold text-sm">{{ $mesNombre }}</span>
            <span class="text-sm text-gray-500">{{ $filas->count() }} {{ $filas->count() === 1 ? 'registro' : 'registros' }}</span>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-[800px] w-full text-sm">
                <thead class="bg-gray-100 text-xs uppercase text-gray-600">
                    <tr>
                        <th class="p-2.5 text-left w-[100px]">Frecuencia</th>
                        <th class="p-2.5 text-left">Cliente</th>
                        <th class="p-2.5 text-left w-[200px]">Finca</th>
                        <th class="p-2.5 text-left w-[70px]">Lote</th>
                        <th class="p-2.5 text-left w-[120px]">Primer pago</th>
                        <th class="p-2.5 text-center w-[60px]">Día</th>
                        <th class="p-2.5 text-right w-[120px]">Monto</th>
                        <th class="p-2.5 text-center w-[100px]">Cuotas ant.</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($filas as $fila)
                    <tr class="border-t hover:bg-gray-50">
                        <td class="p-2.5 align-middle">
                            @if($fila->frecuencia === 'mensual')
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-[11px] font-bold bg-blue-100 text-blue-700">
                                Mensual
                            </span>
                            @else
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-[11px] font-bold bg-purple-100 text-purple-700">
                                Semanal
                            </span>
                            @endif
                        </td>
                        <td class="p-2.5 align-middle font-semibold">
                            {{ $fila->cliente }}
                            <div class="text-[11px] text-gray-400 font-normal">{{ $fila->folio_contrato }}</div>
                        </td>
                        <td class="p-2.5 align-middle text-gray-600 text-xs">{{ $fila->fraccionamiento }}</td>
                        <td class="p-2.5 align-middle text-gray-600">{{ $fila->lote }}</td>
                        <td class="p-2.5 align-middle text-gray-700">
                            {{ \Carbon\Carbon::parse($fila->fecha_pago)->format('d/m/Y') }}
                        </td>
                        <td class="p-2.5 align-middle text-center">
                            <span class="inline-flex items-center justify-center h-7 w-7 rounded-full text-xs font-bold
                                {{ (int)$fila->dia_pago <= 5 ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                                {{ $fila->dia_pago }}
                            </span>
                        </td>
                        <td class="p-2.5 align-middle text-right font-semibold">
                            ${{ number_format((float)$fila->monto, 2) }}
                        </td>
                        <td class="p-2.5 align-middle text-center">
                            @if($fila->cuotas_anticipadas !== null)
                            <span class="inline-flex items-center justify-center h-7 w-7 rounded-full bg-purple-100 text-purple-700 text-xs font-bold">
                                {{ $fila->cuotas_anticipadas }}
                            </span>
                            @else
                            <span class="text-gray-400">—</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="p-8 text-center text-gray-400">
                            No se encontraron participantes de rifa para este periodo.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
