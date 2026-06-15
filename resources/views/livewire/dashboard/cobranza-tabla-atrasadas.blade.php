<div class="rounded-3xl border border-slate-200 bg-white shadow-sm overflow-hidden">
    <div class="flex flex-col xl:flex-row xl:items-center xl:justify-between gap-3 p-5 border-b bg-slate-50">
        <div>
            <h2 class="text-lg font-black text-slate-900">Cuotas atrasadas</h2>
            <p class="text-sm text-slate-500">
                Seguimiento de cobranza, notificaciones y WhatsApp manual.
            </p>
        </div>

        <div class="flex flex-col sm:flex-row gap-2">
            <button
                type="button"
                wire:click="notificarSeleccionadasAtrasadas"
                wire:loading.attr="disabled"
                wire:target="notificarSeleccionadasAtrasadas"
                class="rounded-2xl bg-slate-900 px-4 py-2.5 text-sm font-black text-white hover:bg-black disabled:opacity-60"
            >
                Notificar seleccionadas
            </button>
        </div>
    </div>

    {{-- Desktop --}}
    <div class="hidden lg:block overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-white sticky top-0 z-10 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr class="border-b">
                    <th class="px-4 py-3 w-10"></th>
                    <th class="px-4 py-3">Cliente</th>
                    <th class="px-4 py-3">Lote</th>
                    <th class="px-4 py-3">Fraccionamiento</th>
                    <th class="px-4 py-3">Vence</th>
                    <th class="px-4 py-3">Riesgo</th>
                    <th class="px-4 py-3 text-right">Monto</th>
                    <th class="px-4 py-3">Contacto</th>
                    <th class="px-4 py-3">Estatus</th>
                    <th class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>

            <tbody class="divide-y divide-slate-100">
                @forelse($cuotasAtrasadas as $c)
                    @php
                        $cliente = $c->contrato?->cliente;
                        $lote = $c->contrato?->lote;
                        $fraccionamiento = $lote?->fraccionamiento?->nombre ?? '—';
                        $loteTexto = $lote?->lote ?? '—';
                        $dias = \Carbon\Carbon::parse($c->fecha_vencimiento)->diffInDays(\Carbon\Carbon::parse($hoy));
                        $status = $notificadasHoyMap[$c->id] ?? null;
                        $nombreCliente = trim(($cliente?->nombres ?? '') . ' ' . ($cliente?->apellidos ?? '')) ?: '—';

                        if ($dias <= 3) {
                            $riesgoClass = 'bg-emerald-50 text-emerald-700 border-emerald-200';
                            $riesgoText = 'Leve';
                            $riesgoIcon = '🟢';
                        } elseif ($dias <= 7) {
                            $riesgoClass = 'bg-amber-50 text-amber-700 border-amber-200';
                            $riesgoText = 'Medio';
                            $riesgoIcon = '🟡';
                        } else {
                            $riesgoClass = 'bg-red-50 text-red-700 border-red-200';
                            $riesgoText = 'Urgente';
                            $riesgoIcon = '🔴';
                        }
                    @endphp

                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <input
                                type="checkbox"
                                value="{{ $c->id }}"
                                wire:model.live="selectedAtrasadas"
                                class="rounded border-slate-300 text-slate-900 focus:ring-slate-900"
                            >
                        </td>

                        <td class="px-4 py-3">
                            <div class="font-black text-slate-900">{{ $nombreCliente }}</div>
                        </td>

                        <td class="px-4 py-3">
                            <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-700">
                                {{ $loteTexto }}
                            </span>
                        </td>

                        <td class="px-4 py-3 text-slate-700">
                            {{ $fraccionamiento }}
                        </td>

                        <td class="px-4 py-3">
                            {{ \Carbon\Carbon::parse($c->fecha_vencimiento)->format('d/m/Y') }}
                        </td>

                        <td class="px-4 py-3">
                            <span class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-black {{ $riesgoClass }}">
                                {{ $riesgoIcon }} {{ $riesgoText }} / {{ $dias }} días
                            </span>
                        </td>

                        <td class="px-4 py-3 text-right font-black text-slate-950">
                            ${{ number_format((float) $c->monto, 2) }}
                        </td>

                        <td class="px-4 py-3 text-xs text-slate-600">
                            <div>{{ $cliente?->telefono ?? 'Sin teléfono' }}</div>
                            <div>{{ $cliente?->correo ?? 'Sin correo' }}</div>
                        </td>

                        <td class="px-4 py-3">
                            @if($status === 'enviado')
                                <span class="inline-flex items-center gap-2 text-xs font-black px-2.5 py-1 rounded-full bg-green-50 text-green-700 border border-green-200">
                                    ✅ Enviado hoy
                                </span>
                            @elseif($status === 'en_cola')
                                <span class="inline-flex items-center gap-2 text-xs font-black px-2.5 py-1 rounded-full bg-amber-50 text-amber-700 border border-amber-200">
                                    ⏳ En cola hoy
                                </span>
                            @else
                                <span class="inline-flex items-center gap-2 text-xs font-black px-2.5 py-1 rounded-full bg-slate-50 text-slate-600 border border-slate-200">
                                    Sin envío hoy
                                </span>
                            @endif
                        </td>

                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-2">
                                <button
                                    type="button"
                                    wire:click="abrirWhatsapp({{ $c->id }})"
                                    class="h-9 w-9 flex items-center justify-center rounded-full bg-green-600 text-white hover:bg-green-700 shadow"
                                    title="Abrir WhatsApp"
                                >
                                    <i class="fab fa-whatsapp text-lg"></i>
                                </button>

                                @if($status === 'enviado' || $status === 'en_cola')
                                    <button
                                        type="button"
                                        disabled
                                        class="h-9 w-9 flex items-center justify-center rounded-full bg-slate-100 text-slate-400 cursor-not-allowed"
                                        title="{{ $status === 'enviado' ? 'Ya notificado' : 'En cola' }}"
                                    >
                                        {{ $status === 'enviado' ? '✅' : '⏳' }}
                                    </button>
                                @else
                                    <button
                                        type="button"
                                        wire:click="notificarCuota({{ $c->id }})"
                                        class="h-9 w-9 flex items-center justify-center rounded-full bg-blue-600 text-white hover:bg-blue-700 shadow"
                                        title="Notificar"
                                    >
                                        <i class="fas fa-envelope text-sm"></i>
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="px-4 py-10 text-center text-slate-500">
                            Sin cuotas atrasadas.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Mobile --}}
    <div class="lg:hidden p-4 space-y-3">
        @forelse($cuotasAtrasadas as $c)
            @php
                $cliente = $c->contrato?->cliente;
                $lote = $c->contrato?->lote;
                $fraccionamiento = $lote?->fraccionamiento?->nombre ?? '—';
                $loteTexto = $lote?->lote ?? '—';
                $dias = \Carbon\Carbon::parse($c->fecha_vencimiento)->diffInDays(\Carbon\Carbon::parse($hoy));
                $status = $notificadasHoyMap[$c->id] ?? null;
                $nombreCliente = trim(($cliente?->nombres ?? '') . ' ' . ($cliente?->apellidos ?? '')) ?: '—';

                if ($dias <= 3) {
                    $riesgoClass = 'bg-emerald-50 text-emerald-700 border-emerald-200';
                    $riesgoText = 'Leve';
                    $riesgoIcon = '🟢';
                } elseif ($dias <= 7) {
                    $riesgoClass = 'bg-amber-50 text-amber-700 border-amber-200';
                    $riesgoText = 'Medio';
                    $riesgoIcon = '🟡';
                } else {
                    $riesgoClass = 'bg-red-50 text-red-700 border-red-200';
                    $riesgoText = 'Urgente';
                    $riesgoIcon = '🔴';
                }
            @endphp

            <div class="rounded-3xl border border-slate-200 p-4 shadow-sm">
                <div class="flex items-start gap-3">
                    <input
                        type="checkbox"
                        value="{{ $c->id }}"
                        wire:model.live="selectedAtrasadas"
                        class="mt-1 rounded border-slate-300 text-slate-900 focus:ring-slate-900"
                    >

                    <div class="flex-1 min-w-0">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="font-black text-slate-900 truncate">
                                    {{ $nombreCliente }}
                                </div>
                                <div class="text-xs text-slate-500">
                                    Contrato: {{ $c->contrato?->folio_contrato ?? '—' }}
                                </div>
                            </div>

                            <div class="text-right shrink-0">
                                <div class="text-xs text-slate-500">Monto</div>
                                <div class="font-black">${{ number_format((float) $c->monto, 2) }}</div>
                            </div>
                        </div>

                        <div class="mt-3 flex flex-wrap gap-2">
                            <span class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-black {{ $riesgoClass }}">
                                {{ $riesgoIcon }} {{ $riesgoText }} / {{ $dias }} días
                            </span>

                            @if($status === 'enviado')
                                <span class="inline-flex items-center gap-1 rounded-full bg-green-50 text-green-700 border border-green-200 px-2.5 py-1 text-xs font-black">
                                    ✅ Enviado hoy
                                </span>
                            @elseif($status === 'en_cola')
                                <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 text-amber-700 border border-amber-200 px-2.5 py-1 text-xs font-black">
                                    ⏳ En cola hoy
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 rounded-full bg-slate-50 text-slate-600 border border-slate-200 px-2.5 py-1 text-xs font-black">
                                    Sin envío hoy
                                </span>
                            @endif
                        </div>

                        <div class="mt-3 grid grid-cols-2 gap-2 text-sm">
                            <div>
                                <div class="text-xs text-slate-500">Lote</div>
                                <div class="font-bold">{{ $loteTexto }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-slate-500">Vence</div>
                                <div class="font-bold">
                                    {{ \Carbon\Carbon::parse($c->fecha_vencimiento)->format('d/m/Y') }}
                                </div>
                            </div>
                        </div>

                        <div class="mt-2 text-sm">
                            <div class="text-xs text-slate-500">Fraccionamiento</div>
                            <div class="font-bold">{{ $fraccionamiento }}</div>
                        </div>

                        <div class="mt-2 text-sm text-slate-600">
                            <div>{{ $cliente?->telefono ?? 'Sin teléfono' }}</div>
                            <div>{{ $cliente?->correo ?? 'Sin correo' }}</div>
                        </div>

                        <div class="mt-4 flex items-center justify-between gap-3">
                            <div>
                                @if($status === 'enviado')
                                    <span class="inline-flex items-center gap-2 text-xs font-black px-3 py-1.5 rounded-full bg-green-50 text-green-700 border border-green-200">
                                        ✅ Enviado hoy
                                    </span>
                                @elseif($status === 'en_cola')
                                    <span class="inline-flex items-center gap-2 text-xs font-black px-3 py-1.5 rounded-full bg-amber-50 text-amber-700 border border-amber-200">
                                        ⏳ En cola
                                    </span>
                                @endif
                            </div>

                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    wire:click="abrirWhatsapp({{ $c->id }})"
                                    class="h-10 w-10 flex items-center justify-center rounded-full bg-green-600 text-white hover:bg-green-700 shadow"
                                    title="Abrir WhatsApp"
                                >
                                    <i class="fab fa-whatsapp text-xl"></i>
                                </button>

                                @if($status === 'enviado' || $status === 'en_cola')
                                    <button
                                        type="button"
                                        disabled
                                        class="h-10 w-10 flex items-center justify-center rounded-full bg-slate-100 text-slate-400 cursor-not-allowed"
                                        title="{{ $status === 'enviado' ? 'Ya notificado' : 'En cola' }}"
                                    >
                                        {{ $status === 'enviado' ? '✅' : '⏳' }}
                                    </button>
                                @else
                                    <button
                                        type="button"
                                        wire:click="notificarCuota({{ $c->id }})"
                                        class="h-10 w-10 flex items-center justify-center rounded-full bg-blue-600 text-white hover:bg-blue-700 shadow"
                                        title="Notificar"
                                    >
                                        <i class="fas fa-envelope text-sm"></i>
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="py-8 text-center text-slate-500">
                Sin cuotas atrasadas.
            </div>
        @endforelse
    </div>

    <div class="border-t px-5 py-4">
        {{ $cuotasAtrasadas->links(data: ['scrollTo' => false]) }}
    </div>
</div>