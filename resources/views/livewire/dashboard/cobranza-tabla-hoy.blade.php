<div class="rounded-3xl border border-slate-200 bg-white shadow-sm overflow-hidden">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 p-5 border-b bg-slate-50">
        <div>
            <h2 class="text-lg font-black text-slate-900">Cuotas a cobrar hoy</h2>
            <p class="text-sm text-slate-500">
                Pendientes con vencimiento exacto del día seleccionado.
            </p>
        </div>
    </div>

    <div class="hidden lg:block overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-white sticky top-0 z-10 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr class="border-b">
                    <th class="px-4 py-3">Cliente</th>
                    <th class="px-4 py-3">Lote</th>
                    <th class="px-4 py-3">Fraccionamiento</th>
                    <th class="px-4 py-3">Vence</th>
                    <th class="px-4 py-3 text-right">Monto</th>
                    <th class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>

            <tbody class="divide-y divide-slate-100">
                @forelse($cuotasHoy as $c)
                    @php
                        $cliente = $c->contrato?->cliente;
                        $lote = $c->contrato?->lote;
                        $fraccionamiento = $lote?->fraccionamiento?->nombre ?? '—';
                        $loteTexto = $lote?->lote ?? '—';
                        $nombreCliente = trim(($cliente?->nombres ?? '') . ' ' . ($cliente?->apellidos ?? '')) ?: '—';
                    @endphp

                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <div class="font-black text-slate-900">{{ $nombreCliente }}</div>
                            <div class="text-xs text-slate-500">
                                {{ $cliente?->telefono ?? 'Sin teléfono' }}
                            </div>
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

                        <td class="px-4 py-3 text-right font-black text-slate-950">
                            ${{ number_format((float) $c->monto, 2) }}
                        </td>

                        <td class="px-4 py-3">
                            <div class="flex justify-end">
                                <button
                                    type="button"
                                    wire:click="abrirWhatsapp({{ $c->id }})"
                                    class="h-9 w-9 flex items-center justify-center rounded-full bg-green-600 text-white hover:bg-green-700 shadow"
                                    title="Abrir WhatsApp"
                                >
                                    <i class="fab fa-whatsapp text-lg"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-10 text-center text-slate-500">
                            Sin cuotas para la fecha seleccionada.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="border-t px-5 py-4">
        {{ $cuotasHoy->links(data: ['scrollTo' => false]) }}
    </div>
</div>