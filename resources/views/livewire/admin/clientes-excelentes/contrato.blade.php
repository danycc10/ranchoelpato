<div class="max-w-6xl mx-auto p-4 sm:p-6 space-y-4 sm:space-y-6">
    <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black">Detalle / Condonación</h1>
            <div class="text-gray-500 mt-1">
                <b>{{ $contrato->cliente?->nombre_completo }}</b> ·
                Contrato: <b>{{ $contrato->folio_contrato }}</b>
                @if($contrato->lote)
                    · Lote: <b>{{ $contrato->lote->clave }}</b>
                @endif
            </div>
        </div>

        <div class="flex flex-col sm:flex-row gap-2 w-full lg:w-auto">
            <a href="{{ route('admin.clientes-excelentes.index') }}"
               class="w-full sm:w-auto text-center px-4 py-2 rounded-xl border hover:bg-gray-50 font-semibold">
                Volver
            </a>

            <button wire:click="abrirModalCondonar"
                    class="w-full sm:w-auto px-4 py-2 rounded-xl bg-black text-white font-semibold">
                Condonar cuotas finales
            </button>
        </div>
    </div>

    <div class="p-4 rounded-2xl border bg-white">
        <div class="grid lg:grid-cols-3 gap-3">
            <div>
                <div class="text-xs text-gray-500">Saldo contrato</div>
                <div class="text-lg font-black">
                    ${{ number_format((float)($contrato->saldo_actual ?? $contrato->saldo ?? 0), 2) }}
                </div>
            </div>

            <div class="lg:col-span-2">
                <div class="text-xs text-gray-500">Reglas</div>
                <div class="mt-2 flex flex-col sm:flex-row sm:flex-wrap gap-3 text-sm">
                    <label class="inline-flex items-center gap-2 font-semibold">
                        <input type="checkbox" class="rounded" wire:model.live="bajarSaldoContrato">
                        La condonación baja saldo del contrato
                    </label>

                    <label class="inline-flex items-center gap-2 font-semibold">
                        <input type="checkbox" class="rounded" wire:model.live="generarReciboCondonacion">
                        Generar recibo de condonación (monto 0)
                    </label>

                    <div class="flex items-center gap-2">
                        <span class="text-xs text-gray-500">Últimas cuotas:</span>
                        <input type="number" min="1" class="w-20 rounded-xl border p-2"
                               wire:model.live="ultimasCuotas">
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- =========================
        TABLA (md+)
    ========================== --}}
    <div class="hidden md:block overflow-x-auto rounded-2xl border bg-white">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr class="text-left">
                    <th class="p-3">Cuota</th>
                    <th class="p-3">Vence</th>
                    <th class="p-3">Monto</th>
                    <th class="p-3">Pagado real</th>
                    <th class="p-3">Condonado</th>
                    <th class="p-3">Estatus</th>
                </tr>
            </thead>
            <tbody>
                @foreach($cuotas as $c)
                    @php
                        $pendiente = max(
                            0,
                            (float)$c->monto - (float)($c->pagado_total ?? 0) - (float)($c->condonado_total ?? 0)
                        );
                    @endphp

                    <tr class="border-t" wire:key="cx-det-row-{{ $c->id ?? $c->uuid ?? $c->numero }}">
                        <td class="p-3 font-semibold">#{{ $c->numero }}</td>
                        <td class="p-3">{{ \Carbon\Carbon::parse($c->fecha_vencimiento)->format('d/m/Y') }}</td>
                        <td class="p-3">${{ number_format((float)$c->monto, 2) }}</td>
                        <td class="p-3">${{ number_format((float)($c->pagado_total ?? 0), 2) }}</td>

                        <td class="p-3">
                            @if($c->condonada)
                                <span class="px-2 py-1 rounded-lg border text-xs font-semibold bg-green-50 border-green-200 text-green-700">
                                    ${{ number_format((float)($c->condonado_total ?? 0), 2) }}
                                </span>
                            @else
                                ${{ number_format((float)($c->condonado_total ?? 0), 2) }}
                            @endif

                            @if($pendiente > 0)
                                <div class="text-[11px] text-gray-500">
                                    Pendiente: ${{ number_format($pendiente, 2) }}
                                </div>
                            @endif
                        </td>

                        <td class="p-3">
                            <span class="px-2 py-1 rounded-lg border text-xs font-semibold">
                                {{ $c->estatus }}
                            </span>
                            @if($c->condonada)
                                <span class="ml-2 text-[11px] text-green-700 font-semibold">CONDONADA</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- =========================
        CARDS (mobile)
    ========================== --}}
    <div class="md:hidden space-y-3">
        @foreach($cuotas as $c)
            @php
                $pendiente = max(
                    0,
                    (float)$c->monto - (float)($c->pagado_total ?? 0) - (float)($c->condonado_total ?? 0)
                );
            @endphp

            <div class="rounded-2xl border bg-white p-4" wire:key="cx-det-card-{{ $c->id ?? $c->uuid ?? $c->numero }}">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="font-black">Cuota #{{ $c->numero }}</div>
                        <div class="text-xs text-gray-500 mt-1">
                            Vence: <span class="font-semibold text-gray-800">{{ \Carbon\Carbon::parse($c->fecha_vencimiento)->format('d/m/Y') }}</span>
                        </div>
                    </div>

                    <div class="text-right">
                        <div class="text-xs text-gray-500">Estatus</div>
                        <span class="inline-flex px-2 py-1 rounded-lg border text-xs font-semibold">
                            {{ $c->estatus }}
                        </span>
                        @if($c->condonada)
                            <div class="mt-1 text-[11px] text-green-700 font-semibold">CONDONADA</div>
                        @endif
                    </div>
                </div>

                <div class="mt-3 grid grid-cols-2 gap-2 text-sm">
                    <div>
                        <div class="text-xs text-gray-500">Monto</div>
                        <div class="font-black">${{ number_format((float)$c->monto, 2) }}</div>
                    </div>

                    <div class="text-right">
                        <div class="text-xs text-gray-500">Pagado real</div>
                        <div class="font-semibold">${{ number_format((float)($c->pagado_total ?? 0), 2) }}</div>
                    </div>

                    <div class="col-span-2">
                        <div class="text-xs text-gray-500">Condonado</div>
                        @if($c->condonada)
                            <span class="inline-flex px-2 py-1 rounded-lg border text-xs font-semibold bg-green-50 border-green-200 text-green-700">
                                ${{ number_format((float)($c->condonado_total ?? 0), 2) }}
                            </span>
                        @else
                            <div class="font-semibold">
                                ${{ number_format((float)($c->condonado_total ?? 0), 2) }}
                            </div>
                        @endif

                        @if($pendiente > 0)
                            <div class="text-[11px] text-gray-500">
                                Pendiente: ${{ number_format($pendiente, 2) }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- MODAL CONDONAR --}}
    @if($modalCondonar)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/40" wire:click="cerrarModalCondonar"></div>

            <div class="relative w-full max-w-xl bg-white rounded-2xl border shadow-xl overflow-hidden">
                <div class="p-5 border-b flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-black">Condonar cuotas finales</h3>
                        <p class="text-sm text-gray-500 mt-1">
                            Selecciona las cuotas elegibles (últimas {{ $ultimasCuotas }} pendientes).
                        </p>
                    </div>

                    <button class="px-2 py-1 rounded-lg border hover:bg-gray-50"
                            wire:click="cerrarModalCondonar">✕</button>
                </div>

                {{-- ✅ Scroll interno para móvil --}}
                <div class="p-5 space-y-2 max-h-[70vh] overflow-auto">
                    @if(count($cuotasElegibles) === 0)
                        <div class="p-3 rounded-xl bg-gray-50 border text-sm text-gray-600">
                            No hay cuotas elegibles para condonar con las reglas actuales.
                        </div>
                    @else
                        @foreach($cuotasElegibles as $id => $info)
                            <label class="flex items-center justify-between gap-3 p-3 rounded-xl border hover:bg-gray-50">
                                <div class="flex items-center gap-3">
                                    <input type="checkbox" class="rounded"
                                           wire:model.live="seleccionadas"
                                           value="{{ $id }}">
                                    <div>
                                        <div class="font-semibold">{{ $info['label'] }}</div>
                                        <div class="text-xs text-gray-500">Pendiente a condonar</div>
                                    </div>
                                </div>
                                <div class="font-black">
                                    ${{ number_format((float)$info['monto'], 2) }}
                                </div>
                            </label>
                        @endforeach

                        <div class="mt-3">
                            <label class="text-xs text-gray-500">Motivo</label>
                            <input class="w-full rounded-xl border p-2"
                                   wire:model.live="motivo"
                                   placeholder="Ej. Beneficio por puntualidad / adelanto de pagos">
                        </div>
                    @endif

                    <div class="text-xs text-gray-500 pt-2">
                        * La condonación se registra con auditoría (quién, cuándo, motivo) y marca la cuota como pagada + condonada.
                    </div>
                </div>

                <div class="p-4 border-t flex flex-col sm:flex-row justify-end gap-2">
                    <button class="w-full sm:w-auto px-4 py-2 rounded-xl border hover:bg-gray-50 font-semibold"
                            wire:click="cerrarModalCondonar">
                        Cancelar
                    </button>

                    <button class="w-full sm:w-auto px-4 py-2 rounded-xl bg-black text-white font-semibold"
                            wire:click="confirmarCondonacion">
                        Confirmar condonación
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
