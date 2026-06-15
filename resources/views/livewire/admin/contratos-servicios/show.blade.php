<div class="max-w-6xl mx-auto p-4">
    @php
        $diasSemana = [
            1 => 'Lunes',
            2 => 'Martes',
            3 => 'Miércoles',
            4 => 'Jueves',
            5 => 'Viernes',
            6 => 'Sábado',
            7 => 'Domingo',
        ];
    @endphp

    <div class="flex items-center justify-between mb-4">
        <div>
            <h1 class="text-2xl font-black">
                {{ $contrato->folio_contrato ?? ('Servicio #'.$contrato->id) }}
            </h1>
            <p class="text-gray-500 text-sm">
                Detalle del contrato de servicio y cuotas.
            </p>
        </div>

        <div class="flex items-center gap-2 flex-wrap justify-end">
            @if($this->base)
                <a href="{{ route('admin.contratos.show', $this->base->id) }}"
                   class="px-4 py-2 rounded-xl bg-black text-white font-bold">
                    Abrir contrato base
                </a>
            @endif

            @can('sistema.ver')
                <button wire:click="abrirReprogramar"
                        type="button"
                        class="px-4 py-2 rounded-xl border font-bold">
                    Reprogramar calendario
                </button>
            @endcan

            <a href="{{ route('admin.cuotas', ['contrato_id' => $contrato->id]) }}"
               class="px-4 py-2 rounded-xl border">
                Ver en Cuotas
            </a>

            <a href="{{ route('admin.contratos-servicios.index') }}"
               class="px-4 py-2 rounded-xl border">
                Volver
            </a>
        </div>
    </div>

    @if (session()->has('ok'))
        <div class="mb-4 p-3 rounded-xl bg-green-50 text-green-800 border border-green-200">
            {{ session('ok') }}
        </div>
    @endif

    <div class="grid md:grid-cols-3 gap-4 mb-4">
        <div class="bg-white rounded-2xl border p-4 md:col-span-2">
            <p><b>Cliente:</b> {{ $contrato->cliente?->nombre_completo ?? '—' }}</p>
            <p><b>Lote:</b> {{ $contrato->lote?->clave ?? '—' }}</p>
            <p><b>Contrato base:</b> {{ $this->base?->folio_contrato ?? '—' }}</p>
            <p><b>Servicio:</b> {{ $this->servicioNombre ?? '—' }}</p>

            <hr class="my-3">

            <p><b>Inicio:</b> {{ optional($contrato->fecha_inicio)->format('d/m/Y') }}</p>
            <p><b>Frecuencia:</b> {{ ($contrato->frecuencia ?? '') === 'semanal' ? 'Semanal' : 'Mensual' }}</p>

            @if(($contrato->frecuencia ?? '') === 'semanal')
                <p>
                    <b>Día semanal:</b>
                    {{ $diasSemana[(int) $contrato->dia_semana] ?? '—' }}
                </p>
            @else
                <p><b>Día del mes:</b> {{ $contrato->dia_mes ?? '—' }}</p>
            @endif

            <p><b>Estatus:</b> {{ ucfirst($contrato->estatus ?? '—') }}</p>
        </div>

        <div class="bg-white rounded-2xl border p-4">
            <p><b>Precio total:</b> ${{ number_format((float)$contrato->precio_total, 2) }}</p>
            <p><b>Enganche:</b> ${{ number_format((float)$contrato->enganche, 2) }}</p>
            <p><b>Saldo inicial:</b> ${{ number_format((float)$contrato->saldo_inicial, 2) }}</p>
            <p><b>Saldo actual:</b> ${{ number_format((float)$contrato->saldo_actual, 2) }}</p>
            <p><b>Monto pago:</b> ${{ number_format((float)$contrato->monto_pago, 2) }}</p>

            <hr class="my-3">

            <p><b>Recargo:</b> {{ $contrato->tipo_recargo ?? '—' }} ({{ number_format((float)$contrato->valor_recargo, 2) }})</p>
            <p><b>Días gracia:</b> {{ $contrato->dias_gracia ?? 0 }}</p>
        </div>
    </div>

    <div class="bg-white rounded-2xl border overflow-hidden">
        <div class="p-3 font-bold bg-gray-50">Cuotas</div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="p-3 text-left">#</th>
                        <th class="p-3 text-left">Vence</th>
                        <th class="p-3 text-left">Monto</th>
                        <th class="p-3 text-left">Pagado</th>
                        <th class="p-3 text-left">Recargo</th>
                        <th class="p-3 text-left">Saldo planeado</th>
                        <th class="p-3 text-left">Estatus</th>
                        @can('recibos.eliminar')
                            <th class="p-3 text-left">Acciones</th>
                        @endcan
                    </tr>
                </thead>
                <tbody>
                    @php
                        $saldoPlaneado = (float) ($contrato->saldo_inicial ?? 0);
                    @endphp

                    @forelse($cuotas as $c)
                        @php
                            $estatusCuota = strtolower((string) $c->estatus);
                            $saldoPlaneado = max(0, round($saldoPlaneado - (float) $c->monto, 2));
                            $tienePago = ((float)($c->pagado_total ?? 0) > 0) || $estatusCuota === 'pagada';
                        @endphp

                        <tr class="border-t">
                            <td class="p-3">{{ $c->numero }}</td>
                            <td class="p-3">{{ optional($c->fecha_vencimiento)->format('d/m/Y') }}</td>
                            <td class="p-3">${{ number_format((float)$c->monto, 2) }}</td>
                            <td class="p-3">${{ number_format((float)$c->pagado_total, 2) }}</td>
                            <td class="p-3">${{ number_format((float)$c->recargo_aplicado, 2) }}</td>

                            <td class="p-3 font-semibold text-gray-800">
                                ${{ number_format($saldoPlaneado, 2) }}
                            </td>

                            <td class="p-3">
                                <div class="flex flex-col gap-1">
                                    <span>{{ ucfirst($estatusCuota) }}</span>

                                    @if(($c->origen_pago ?? null) === 'historico')
                                        <span class="inline-flex w-fit px-2 py-1 rounded-full text-xs font-bold bg-yellow-100 text-yellow-800">
                                            Pago histórico
                                        </span>
                                    @endif
                                </div>
                            </td>

                            @can('recibos.eliminar')
                                <td class="p-3">
                                    <div class="flex flex-wrap gap-2">
                                        @if(! $tienePago && in_array($estatusCuota, ['pendiente', 'vencida', 'parcial']))
                                            <button type="button"
                                                    wire:click="confirmarMarcarPagada({{ $c->id }})"
                                                    class="px-3 py-1 rounded-lg bg-emerald-600 text-white font-bold hover:bg-emerald-700">
                                                Marcar pagado histórico
                                            </button>
                                        @endif

                                        @if($tienePago)
                                            <button type="button"
                                                    wire:click="confirmarAnularPago({{ $c->id }})"
                                                    class="px-3 py-1 rounded-lg border text-red-600 font-bold">
                                                Anular pago/recibo
                                            </button>
                                        @endif

                                        @if(! $tienePago && ! in_array($estatusCuota, ['pendiente', 'vencida', 'parcial']))
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </div>
                                </td>
                            @endcan
                        </tr>
                    @empty
                        <tr>
                            <td class="p-4 text-gray-500" colspan="@can('recibos.eliminar') 8 @else 7 @endcan">
                                Sin cuotas.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-white rounded-2xl border overflow-hidden mt-4">
        <div class="p-3 font-bold bg-gray-50 flex items-center justify-between">
            <div>Historial (auditable)</div>
            <div class="text-xs text-gray-500">Últimos 100 movimientos</div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="p-3 text-left">Fecha</th>
                        <th class="p-3 text-left">Usuario</th>
                        <th class="p-3 text-left">Tipo</th>
                        <th class="p-3 text-left">Motivo</th>
                        <th class="p-3 text-left">Detalle</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($contrato->historial as $h)
                        @php
                            $tipo = (string) $h->tipo;
                            $badge = match($tipo) {
                                'reprogramacion' => 'bg-blue-100 text-blue-800',
                                'anular_pago' => 'bg-red-100 text-red-800',
                                'pago_historico' => 'bg-emerald-100 text-emerald-800',
                                default => 'bg-gray-100 text-gray-700',
                            };

                            $tipoLabel = match($tipo) {
                                'reprogramacion' => 'Reprogramación',
                                'anular_pago' => 'Anulación de pago',
                                'pago_historico' => 'Pago histórico',
                                default => ucfirst(str_replace('_', ' ', $tipo)),
                            };

                            $antes = $h->antes ?? [];
                            $despues = $h->despues ?? [];
                        @endphp

                        <tr class="border-t">
                            <td class="p-3 whitespace-nowrap">
                                {{ optional($h->created_at)->format('d/m/Y H:i') }}
                            </td>

                            <td class="p-3">
                                {{ $h->user?->name ?? '—' }}
                            </td>

                            <td class="p-3">
                                <span class="px-2 py-1 rounded-full text-xs font-bold {{ $badge }}">
                                    {{ $tipoLabel }}
                                </span>
                            </td>

                            <td class="p-3">
                                {{ $h->motivo ?? '—' }}
                            </td>

                            <td class="p-3 text-gray-700">
                                @if($tipo === 'reprogramacion')
                                    <div class="text-xs">
                                        <b>Primer vencimiento:</b>
                                        {{ $antes['primer_vencimiento'] ?? '—' }}
                                        → {{ $despues['primer_vencimiento'] ?? '—' }}
                                    </div>
                                @elseif($tipo === 'anular_pago')
                          <div class="text-xs">
        <b>Cuota #{{ $antes['cuota_numero'] ?? '—' }}</b>
        ({{ $antes['estatus'] ?? '—' }} → {{ $despues['estatus'] ?? '—' }})
        <br>
        <b>Recibo anulado:</b> {{ $antes['folio_recibo'] ?? $despues['folio_recibo_anulado'] ?? '—' }}
    </div>
                                @elseif($tipo === 'pago_historico')
                                    <div class="text-xs">
                                        <b>Cuota #{{ $despues['cuota_numero'] ?? '—' }}</b>
                                        ({{ $antes['estatus'] ?? '—' }} → {{ $despues['estatus'] ?? '—' }})
                                        <br>
                                        <b>Origen:</b> {{ $despues['origen_pago'] ?? '—' }}
                                    </div>
                                @else
                                    <div class="text-xs">
                                        <b>Antes:</b> {{ json_encode($antes, JSON_UNESCAPED_UNICODE) }}
                                        <br>
                                        <b>Después:</b> {{ json_encode($despues, JSON_UNESCAPED_UNICODE) }}
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="p-4 text-gray-500 text-center">
                                Sin movimientos registrados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- MODAL REPROGRAMAR --}}
    @if($showReprogramar)
        <div class="fixed inset-0 bg-black/40 flex items-center justify-center p-4 z-50">
            <div class="bg-white rounded-2xl w-full max-w-lg p-5">
                <div class="text-xl font-black">Reprogramar primer pago</div>
                <p class="text-sm text-gray-500 mt-1">
                    Cambia la fecha del primer vencimiento y se recorren las demás cuotas.
                </p>

                <div class="mt-4 space-y-3">
                    <div>
                        <label class="text-sm text-gray-600">Nueva fecha del primer vencimiento</label>
                        <input type="date"
                               wire:model.defer="nuevaFechaPrimerPago"
                               class="w-full rounded-xl border px-3 py-2">
                        @error('nuevaFechaPrimerPago')
                            <div class="text-red-600 text-sm mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="mt-5 flex justify-end gap-2">
                    <button type="button"
                            wire:click="$set('showReprogramar', false)"
                            class="px-4 py-2 rounded-xl border">
                        Cancelar
                    </button>
                    <button type="button"
                            wire:click="guardarReprogramacion"
                            class="px-4 py-2 rounded-xl bg-black text-white font-bold">
                        Guardar
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- MODAL MARCAR PAGADO HISTÓRICO --}}
    @if($showMarcarPagada)
        <div class="fixed inset-0 bg-black/40 flex items-center justify-center p-4 z-50">
            <div class="bg-white rounded-2xl w-full max-w-lg p-5">
                <div class="text-xl font-black text-emerald-700">Marcar cuota como pagada</div>

                <p class="text-sm text-gray-600 mt-2">
                    Esta acción marcará la cuota como <b>pagada</b> y la registrará como
                    <b>pago histórico</b> para no afectar reportes.
                </p>

                <div class="mt-4">
                    <label class="text-sm text-gray-600">Observaciones</label>
                    <textarea
                        wire:model.defer="observacionPagoHistorico"
                        class="w-full rounded-xl border px-3 py-2"
                        rows="3"
                        placeholder="Ej: Pago registrado antes de arrancar el sistema"
                        wire:loading.attr="disabled"
                        wire:target="marcarPagadaConfirmado"
                    ></textarea>
                    @error('observacionPagoHistorico')
                        <div class="text-red-600 text-sm mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mt-5 flex justify-end gap-2">
                    <button
                        type="button"
                        wire:click="$set('showMarcarPagada', false)"
                        wire:loading.attr="disabled"
                        wire:target="marcarPagadaConfirmado"
                        class="px-4 py-2 rounded-xl border disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        Cancelar
                    </button>

                    <button
                        type="button"
                        wire:click="marcarPagadaConfirmado"
                        wire:loading.attr="disabled"
                        wire:target="marcarPagadaConfirmado"
                        class="px-4 py-2 rounded-xl bg-emerald-600 text-white font-bold disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <span wire:loading.remove wire:target="marcarPagadaConfirmado">
                            Sí, marcar como pagada
                        </span>
                        <span wire:loading wire:target="marcarPagadaConfirmado">
                            Procesando...
                        </span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- MODAL ANULAR PAGO/RECIBO --}}
    @if($showAnularPago)
        <div class="fixed inset-0 bg-black/40 flex items-center justify-center p-4 z-50">
            <div class="bg-white rounded-2xl w-full max-w-lg p-5">
                <div class="text-xl font-black text-red-700">Anular pago/recibo</div>
                <p class="text-sm text-gray-600 mt-2">
                    Esto marcará el pago/recibo como <b>anulado</b> y regresará la cuota a <b>pendiente</b>.
                </p>

                <div class="mt-4">
                    <label class="text-sm text-gray-600">Motivo</label>
                    <input type="text"
                           wire:model.defer="motivoAnulacion"
                           class="w-full rounded-xl border px-3 py-2"
                           placeholder="Ej: Pago duplicado, error de captura, ajuste, etc."
                           wire:loading.attr="disabled"
                           wire:target="anularPagoConfirmado">
                    @error('motivoAnulacion')
                        <div class="text-red-600 text-sm mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mt-5 flex justify-end gap-2">
                    <button type="button"
                            wire:click="$set('showAnularPago', false)"
                            wire:loading.attr="disabled"
                            wire:target="anularPagoConfirmado"
                            class="px-4 py-2 rounded-xl border disabled:opacity-50 disabled:cursor-not-allowed">
                        Cancelar
                    </button>
                    <button type="button"
                            wire:click="anularPagoConfirmado"
                            wire:loading.attr="disabled"
                            wire:target="anularPagoConfirmado"
                            class="px-4 py-2 rounded-xl bg-red-600 text-white font-bold disabled:opacity-50 disabled:cursor-not-allowed">
                        <span wire:loading.remove wire:target="anularPagoConfirmado">
                            Sí, anular
                        </span>
                        <span wire:loading wire:target="anularPagoConfirmado">
                            Procesando...
                        </span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>