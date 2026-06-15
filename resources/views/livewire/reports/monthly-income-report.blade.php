<div class="max-w-7xl mx-auto p-4 space-y-6">

    {{-- Encabezado --}}
  <div class="rounded-2xl border p-5 bg-white shadow-sm space-y-5">

    {{-- Fila 1: título + filtros principales --}}
    <div class="flex flex-col xl:flex-row xl:items-end xl:justify-between gap-5">
        <div class="xl:min-w-[320px]">
            <div class="text-3xl font-black leading-tight">RESUMEN DE INGRESOS MENSUALES</div>
            <div class="mt-2 text-gray-600 text-base">
                Año: <b>{{ $anio }}</b> |
                Mes: <b>{{ $mesNombre }}</b> |
                Modo: <b>{{ $modoVistaLabel }}</b>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 xl:w-auto w-full">
            <div>
                <label class="text-sm font-medium text-gray-600 mb-1 block">Año</label>
                <input
                    type="number"
                    min="2000"
                    max="2100"
                    wire:model.live="anio"
                    class="block rounded-xl border px-4 py-3 w-full bg-white"
                >
            </div>

            <div>
                <label class="text-sm font-medium text-gray-600 mb-1 block">Mes</label>
                <select
                    wire:model.live="mes"
                    class="block rounded-xl border px-4 py-3 w-full bg-white"
                >
                    @for($m = 1; $m <= 12; $m++)
                        <option value="{{ $m }}">
                            {{ \Carbon\Carbon::create()->month($m)->translatedFormat('F') }}
                        </option>
                    @endfor
                </select>
            </div>

            <div>
                <label class="text-sm font-medium text-gray-600 mb-1 block">Propietario</label>
                <select
                    wire:model.live="propietarioId"
                    class="block rounded-xl border px-4 py-3 w-full bg-white"
                >
                    <option value="">Todos</option>
                    @foreach($propietarios as $p)
                        <option value="{{ $p->id }}">{{ $p->nombre }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    {{-- Fila 2: switch + botones --}}
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div class="rounded-2xl border bg-gray-50 px-4 py-4 w-full lg:max-w-2xl">
            <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide">
                Modo de visualización
            </div>

            <div class="mt-3 flex items-center gap-3">
                <button
                    type="button"
                    wire:click="$set('modoVista', '{{ $modoVista === 'avance_mes' ? 'flujo_real' : 'avance_mes' }}')"
                    class="relative inline-flex h-8 w-16 items-center rounded-full transition {{ $modoVista === 'flujo_real' ? 'bg-blue-600' : 'bg-emerald-500' }}"
                >
                    <span
                        class="inline-block h-6 w-6 transform rounded-full bg-white transition {{ $modoVista === 'flujo_real' ? 'translate-x-9' : 'translate-x-1' }}"
                    ></span>
                </button>

                <div>
                    <div class="text-sm font-semibold text-gray-800">
                        {{ $modoVista === 'flujo_real' ? 'Flujo real mensual' : 'Cómo va el mes' }}
                    </div>
                    <div class="text-xs text-gray-500 mt-1">
                        @if($modoVista === 'flujo_real')
                            Todo se acomoda por fecha real del pago realizado en el mes.
                        @else
                            Mensualidades por periodo de cuota; otros conceptos por fecha del pago.
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="flex flex-col sm:flex-row gap-3 lg:justify-end">
            <button
                wire:click="exportExcel"
                class="rounded-xl bg-black text-white px-5 py-3 hover:opacity-90 font-semibold whitespace-nowrap"
            >
                Exportar Excel
            </button>

            <a
                href="{{ route('reportes.index') }}"
                class="rounded-xl border bg-white px-5 py-3 hover:bg-gray-50 font-semibold text-center whitespace-nowrap"
            >
                Volver
            </a>
        </div>
    </div>

    {{-- Fila 3: cards de conceptos --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <div class="rounded-2xl bg-gray-50 border p-4">
            <div class="text-xs text-gray-600 uppercase tracking-wide">RECARGO</div>
            <div class="text-2xl font-black mt-1">
                ${{ number_format((float) ($headerConceptos->{'Recargo'} ?? 0), 2) }}
            </div>
        </div>

        <div class="rounded-2xl bg-gray-50 border p-4">
            <div class="text-xs text-gray-600 uppercase tracking-wide">CAMBIO DE CONTRATO</div>
            <div class="text-2xl font-black mt-1">
                ${{ number_format((float) ($headerConceptos->{'Cambio de contrato'} ?? 0), 2) }}
            </div>
        </div>

        <div class="rounded-2xl bg-gray-50 border p-4">
            <div class="text-xs text-gray-600 uppercase tracking-wide">PAGO ELECTRICIDAD</div>
            <div class="text-2xl font-black mt-1">
                ${{ number_format((float) ($headerConceptos->{'Pago electricidad'} ?? 0), 2) }}
            </div>
        </div>

        <div class="rounded-2xl bg-gray-50 border p-4">
            <div class="text-xs text-gray-600 uppercase tracking-wide">ENGANCHE</div>
            <div class="text-2xl font-black mt-1">
                ${{ number_format((float) ($headerConceptos->{'Enganche'} ?? 0), 2) }}
            </div>
        </div>
    </div>
</div>

    {{-- Tabla principal --}}
    <div class="rounded-2xl border p-4 bg-white shadow-sm">
        <div class="overflow-auto rounded-xl border bg-white">
            <table class="min-w-full text-sm bg-white">
                <thead class="bg-gray-100 text-gray-700 uppercase text-xs font-semibold">
                    <tr class="border-b">
                        <th class="py-3 px-3 text-left whitespace-nowrap">Fraccionamiento</th>

                        <th class="py-3 px-3 text-right whitespace-nowrap">
                            {{ $modoVista === 'flujo_real' ? 'Esperado mes' : 'Esperado' }}
                        </th>

                        <th class="py-3 px-3 text-right whitespace-nowrap">
                            {{ $modoVista === 'flujo_real' ? 'Flujo real' : 'Recibido' }}
                        </th>

                        <th class="py-3 px-3 text-right whitespace-nowrap">
                            {{ $modoVista === 'flujo_real' ? 'Flujo - esperado' : 'Diferencia' }}
                        </th>

                        <th class="py-3 px-3 text-right whitespace-nowrap">Adelantado</th>
                        <th class="py-3 px-3 text-right whitespace-nowrap">Desfasados</th>

                        @foreach($metodosPago as $m)
                        <th class="py-3 px-3 text-right whitespace-nowrap">
                            {{ strtoupper($m) }}
                        </th>
                        @endforeach
                    </tr>
                </thead>

                <tbody class="[&>tr:nth-child(even)]:bg-gray-50 [&>tr:hover]:bg-gray-100">
                    @forelse($filas as $f)
                    <tr class="border-b">
                        <td class="py-3 px-3 font-semibold whitespace-nowrap">
                            {{ $f->finca }}
                        </td>

                        <td class="py-3 px-3 text-right tabular-nums font-semibold whitespace-nowrap">
                            ${{ number_format((float) $f->esperado, 2) }}
                        </td>

                        <td class="py-3 px-3 text-right tabular-nums font-semibold whitespace-nowrap">
                            ${{ number_format((float) $f->recibido, 2) }}
                        </td>

                        <td class="py-3 px-3 text-right tabular-nums font-black whitespace-nowrap {{ (float) $f->diferencia < 0 ? 'text-red-600' : 'text-emerald-600' }}">
                            ${{ number_format((float) $f->diferencia, 2) }}
                        </td>

                        <td class="py-3 px-3 text-right tabular-nums font-semibold whitespace-nowrap">
                            ${{ number_format((float) ($f->adelantado ?? 0), 2) }}
                        </td>

                        <td class="py-3 px-3 text-right tabular-nums font-semibold whitespace-nowrap">
                            ${{ number_format((float) ($f->atrasado ?? 0), 2) }}
                        </td>

                        @foreach($metodosPago as $m)
                        <td class="py-3 px-3 text-right tabular-nums whitespace-nowrap">
                            ${{ number_format((float) ($f->metodos[$m] ?? 0), 2) }}
                        </td>
                        @endforeach
                    </tr>
                    @empty
                    <tr>
                        <td colspan="{{ 6 + count($metodosPago) }}" class="py-8 px-3 text-center text-gray-500 bg-white">
                            Sin datos para este mes.
                        </td>
                    </tr>
                    @endforelse

                    <tr class="font-black bg-gray-100">
                        <td class="py-3 px-3 whitespace-nowrap">TOTAL DE INGRESOS</td>

                        <td class="py-3 px-3 text-right tabular-nums whitespace-nowrap">
                            ${{ number_format((float) ($totales->esperado ?? 0), 2) }}
                        </td>

                        <td class="py-3 px-3 text-right tabular-nums whitespace-nowrap">
                            ${{ number_format((float) ($totales->recibido ?? 0), 2) }}
                        </td>

                        <td class="py-3 px-3 text-right tabular-nums whitespace-nowrap">
                            ${{ number_format((float) ($totales->diferencia ?? 0), 2) }}
                        </td>

                        <td class="py-3 px-3 text-right tabular-nums whitespace-nowrap">
                            ${{ number_format((float) ($totales->adelantado ?? 0), 2) }}
                        </td>

                        <td class="py-3 px-3 text-right tabular-nums whitespace-nowrap">
                            ${{ number_format((float) ($totales->atrasado ?? 0), 2) }}
                        </td>

                        @foreach($metodosPago as $m)
                        <td class="py-3 px-3 text-right tabular-nums whitespace-nowrap">
                            ${{ number_format((float) ($totales->metodos[$m] ?? 0), 2) }}
                        </td>
                        @endforeach
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

</div>