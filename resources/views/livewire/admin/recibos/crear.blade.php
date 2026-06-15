<div class="max-w-6xl mx-auto p-4 sm:p-6 space-y-4 sm:space-y-6">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3 sm:gap-4">
        <div>
            <h1 class="text-2xl font-black">Crear recibo</h1>
            <p class="text-gray-500">Captura un recibo como en Excel o desde una o varias cuotas.</p>
        </div>

        <a href="{{ route('admin.cuotas') }}"
            class="w-full sm:w-auto text-center px-4 py-2 rounded-xl border hover:bg-gray-50 text-sm font-semibold">
            Ir a cuotas
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

        {{-- Columna 1: Datos del recibo --}}
        <div class="p-4 rounded-2xl border bg-white">
            <h2 class="font-black mb-3">Datos del recibo</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="text-xs text-gray-500">Folio</label>
                    <input class="w-full rounded-xl border p-2" wire:model.live="folio" readonly>
                </div>

                <div>
                    <label class="text-xs text-gray-500">Fecha</label>
                    <input type="date" class="w-full rounded-xl border p-2" wire:model.live="fecha">
                    @error('fecha')
                    <div class="text-sm text-red-600">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-3">
                <div>
                    <label class="text-xs text-gray-500">Tipo de cobro</label>
                    <select class="w-full rounded-xl border p-2" wire:model.live="tipos_cobro_id">
                        <option value="">Selecciona…</option>
                        @foreach($tiposCobro as $t)
                        <option value="{{ $t->id }}">{{ $t->nombre }}</option>
                        @endforeach
                    </select>
                    @error('tipos_cobro_id')
                    <div class="text-sm text-red-600">{{ $message }}</div>
                    @enderror
                </div>

                @if($tipoEsServicio)
                <div>
                    <label class="text-xs text-gray-500">Periodo</label>
                    <select class="w-full rounded-xl border p-2" wire:model.live="periodo_id">
                        <option value="">(Opcional)</option>
                        @foreach($periodos as $p)
                        <option value="{{ $p->id }}">{{ $p->nombre }}</option>
                        @endforeach
                    </select>
                    @error('periodo_id')
                    <div class="text-sm text-red-600">{{ $message }}</div>
                    @enderror
                </div>
                @else
                <div class="text-xs text-gray-400 flex items-end">
                    <span>Periodo no requerido.</span>
                </div>
                @endif
            </div>

            <div class="mt-4 rounded-2xl border bg-gray-50 p-4 space-y-4">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h3 class="font-black">Formas de pago</h3>
                        <p class="text-xs text-gray-500">
                            Puedes capturar una o varias formas de pago para el mismo recibo.
                        </p>
                    </div>

                    @if($this->puedeUsarMultiplesFormasPago())
                    <button
                        type="button"
                        wire:click="agregarPago"
                        class="px-3 py-2 rounded-xl bg-black text-white text-xs font-semibold">
                        + Agregar forma
                    </button>
                    @endif
                </div>

                @if($asociarACuota && count(array_filter($cuotaIds ?? [])) > 1)
                <div class="rounded-2xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    Cuando seleccionas más de una cuota, solo se permite una forma de pago.
                    Para usar múltiples formas de pago, debes generar el recibo para una sola cuota.
                </div>
                @endif

                @foreach($pagos as $index => $pago)
                <div wire:key="pago-row-{{ $index }}" class="rounded-2xl border bg-white p-4">
                    <div class="flex items-center justify-between gap-3 mb-3">
                        <div class="text-sm font-semibold">
                            Forma de pago #{{ $index + 1 }}
                        </div>

                        @if(count($pagos) > 1)
                        <button
                            type="button"
                            wire:click="eliminarPago({{ $index }})"
                            class="text-xs px-3 py-1.5 rounded-lg border hover:bg-gray-50">
                            Eliminar
                        </button>
                        @endif
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs text-gray-500">Forma de pago</label>
                            <select class="w-full rounded-xl border p-2"
                                wire:model.live="pagos.{{ $index }}.forma_pago_id">
                                <option value="">Selecciona…</option>
                                @foreach($formasPago as $f)
                                <option value="{{ $f->id }}">{{ $f->nombre }}</option>
                                @endforeach
                            </select>
                            @error('pagos.' . $index . '.forma_pago_id')
                            <div class="text-sm text-red-600">{{ $message }}</div>
                            @enderror
                        </div>

                        <div>
                            <label class="text-xs text-gray-500">Monto</label>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                class="w-full rounded-xl border p-2"
                                wire:model.lazy="pagos.{{ $index }}.monto"
                                placeholder="0.00">
                            @error('pagos.' . $index . '.monto')
                            <div class="text-sm text-red-600">{{ $message }}</div>
                            @enderror
                        </div>

                        @if($this->pagoRequiereCuenta($index))
                        <div>
                            <label class="text-xs text-gray-500">Cuenta bancaria</label>
                            <select class="w-full rounded-xl border p-2"
                                wire:model.live="pagos.{{ $index }}.cuentas_bancarias_id">
                                <option value="">Selecciona…</option>
                                @foreach($cuentas as $c)
                                <option value="{{ $c->id }}">
                                    {{ $c->alias }}{{ $c->banco ? ' - '.$c->banco : '' }}
                                </option>
                                @endforeach
                            </select>
                            @error('pagos.' . $index . '.cuentas_bancarias_id')
                            <div class="text-sm text-red-600">{{ $message }}</div>
                            @enderror
                        </div>
                        @else
                        <div class="text-xs text-gray-400 flex items-end">
                            <span>Cuenta bancaria no requerida.</span>
                        </div>
                        @endif

                        <div>
                            <label class="text-xs text-gray-500">Referencia</label>
                            <input
                                type="text"
                                class="w-full rounded-xl border p-2"
                                wire:model.live="pagos.{{ $index }}.referencia"
                                placeholder="Referencia / autorización (opcional)">
                            @error('pagos.' . $index . '.referencia')
                            <div class="text-sm text-red-600">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    @if($this->pagoDebePedirEvidencia($index))
                    <div class="mt-4 rounded-xl border border-sky-200 bg-sky-50 p-3">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <div>
                                <div class="text-sm font-semibold text-sky-900">Evidencia de esta forma de pago</div>

                                <div class="mt-3">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            class="rounded border-gray-300"
                                            wire:model.live="pagos.{{ $index }}.sin_evidencia">

                                        <span class="text-xs text-gray-700">
                                            Continuar sin subir evidencia
                                        </span>
                                    </label>

                                    @if(data_get($pagos, $index . '.sin_evidencia'))
                                    <div class="mt-2 text-xs text-amber-700">
                                        Este pago será guardado como "sin evidencia".
                                    </div>
                                    @endif
                                </div>

                                <div class="text-xs text-sky-800">
                                    Sube el comprobante correspondiente a este método de pago.
                                </div>

                                <div class="mt-2">
                                    @if(data_get($pagos, $index . '.sin_evidencia'))
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-semibold bg-gray-200 text-gray-700">
                                        Sin evidencia
                                    </span>

                                    @elseif($this->pagoTieneEvidencia($index)))
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-semibold bg-green-100 text-green-700">
                                        Evidencia cargada
                                    </span>

                                    @if($this->pagoNombreEvidencia($index))
                                    <div class="text-[11px] text-gray-500 mt-1">
                                        {{ $this->pagoNombreEvidencia($index) }}
                                    </div>
                                    @endif
                                    @else
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-semibold bg-amber-100 text-amber-700">
                                        Falta evidencia
                                    </span>
                                    @endif
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    wire:click="abrirModalEvidenciaPago({{ $index }})"
                                    class="px-3 py-2 rounded-xl border border-sky-300 bg-white text-sky-700 text-xs font-semibold hover:bg-sky-100">
                                    @if($this->pagoTieneEvidencia($index))
                                    Ver / cambiar evidencia
                                    @else
                                    Agregar evidencia
                                    @endif
                                </button>
                            </div>
                        </div>

                        @error('pagos.' . $index . '.evidencia')
                        <div class="text-sm text-red-600 mt-2">{{ $message }}</div>
                        @enderror
                    </div>
                    @endif
                </div>
                @endforeach

                @error('pagos')
                <div class="text-sm text-red-600">{{ $message }}</div>
                @enderror

                <div class="rounded-xl border bg-white p-3 text-sm">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-gray-500">Suma capturada</span>
                        <span class="font-black">
                            ${{ number_format(collect($pagos)->sum(fn($p) => (float)($p['monto'] ?? 0)), 2) }}
                        </span>
                    </div>

                    <div class="flex items-center justify-between gap-3 mt-2">
                        <span class="text-gray-500">Monto esperado</span>
                        <span class="font-black">
                            ${{ number_format((float)($asociarACuota ? ($this->tipoCobroEsRecargo($tipos_cobro_id) ? $recargoTotalSeleccionado : $montoTotalSeleccionado) : $monto), 2) }}
                        </span>
                    </div>
                </div>
            </div>

            {{-- Pago del recargo debajo de la forma de pago principal --}}
            @if(
            $tipos_cobro_id
            && count($cuotaIds) > 0
            && (float)($recargoTotalSeleccionado ?? 0) > 0
            && $this->debeCapturarPagoRecargoSeparado()
            )
            <div class="mt-4">
                <button
                    type="button"
                    wire:click="toggleRecargoPagoBox"
                    class="w-full flex items-center justify-between px-4 py-3 rounded-2xl border bg-white hover:bg-gray-50 text-sm font-semibold">
                    <span>
                        Configurar pago del recargo (${{ number_format((float) $recargoTotalSeleccionado, 2) }})
                    </span>

                    <span>
                        @if($showRecargoPagoBox)
                        ▲
                        @else
                        ▼
                        @endif
                    </span>
                </button>

                @if($showRecargoPagoBox)
                <div class="mt-3 rounded-2xl border border-red-200 bg-red-50 p-4">
                    <div class="flex items-start justify-between gap-3 mb-3">
                        <div>
                            <h3 class="font-black text-red-900">Pago del recargo</h3>
                            <p class="text-sm text-red-800">
                                Puedes usar una forma de pago distinta al principal.
                            </p>
                        </div>

                        <div class="text-right shrink-0">
                            <div class="text-xs text-gray-500">Monto total</div>
                            <div class="text-lg font-black text-red-700">
                                ${{ number_format((float) $recargoTotalSeleccionado, 2) }}
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs text-gray-500">Forma de pago del recargo</label>
                            <select class="w-full rounded-xl border p-2 bg-white"
                                wire:model.live="recargo_forma_pago_id">
                                <option value="">Usar la misma del principal</option>
                                @foreach($formasPago as $f)
                                <option value="{{ $f->id }}">{{ $f->nombre }}</option>
                                @endforeach
                            </select>
                        </div>

                        @if($recargoRequiereCuentaBancaria)
                        <div>
                            <label class="text-xs text-gray-500">Cuenta bancaria</label>
                            <select class="w-full rounded-xl border p-2 bg-white"
                                wire:model.live="recargo_cuentas_bancarias_id">
                                <option value="">Selecciona…</option>
                                @foreach($cuentas as $c)
                                <option value="{{ $c->id }}">
                                    {{ $c->alias }}{{ $c->banco ? ' - '.$c->banco : '' }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                        @else
                        <div class="text-xs text-gray-500 flex items-end">
                            <span>No requiere cuenta bancaria.</span>
                        </div>
                        @endif
                    </div>

                    <div class="mt-3 text-xs text-gray-600">
                        Si no configuras nada, el recargo se guardará igual que el pago principal.
                    </div>
                </div>
                @endif
            </div>
            @endif
        </div>

        {{-- Columna 2: Cliente / Contrato --}}
        <div class="p-4 rounded-2xl border bg-white">
            <h2 class="font-black mb-3">Cliente / Contrato</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div class="sm:col-span-2 relative">
                    <label class="text-xs text-gray-500">Cliente</label>

                    <input
                        type="text"
                        class="w-full rounded-xl border p-2"
                        placeholder="Escribe nombre o apellido…"
                        wire:model.live.debounce.250ms="clienteSearch"
                        wire:focus="$set('mostrarResultadosClientes', true)"
                        autocomplete="off">

                    @error('cliente_id')
                    <div class="text-sm text-red-600">{{ $message }}</div>
                    @enderror

                    @if($mostrarResultadosClientes && count($clientesResultados) > 0)
                    <div class="absolute z-20 mt-1 w-full bg-white border rounded-xl shadow overflow-hidden max-h-64 overflow-y-auto">
                        @foreach($clientesResultados as $item)
                        <button
                            type="button"
                            class="w-full text-left px-3 py-2 hover:bg-gray-50 text-sm"
                            wire:click="seleccionarClienteDesdeBusqueda({{ $item['id'] }})">
                            {{ $item['label'] }}
                        </button>
                        @endforeach
                    </div>
                    @endif

                    @if($mostrarResultadosClientes && mb_strlen(trim($clienteSearch)) >= 2 && count($clientesResultados) === 0)
                    <div class="absolute z-20 mt-1 w-full bg-white border rounded-xl shadow p-3 text-sm text-gray-500">
                        Sin resultados.
                    </div>
                    @endif

                    @if(!$cliente_id)
                    <div class="mt-1 text-xs text-gray-400">
                        Escribe al menos 2 letras y selecciona un cliente.
                    </div>
                    @endif
                </div>

                <div class="sm:col-span-2">
                    <label class="text-xs text-gray-500">Contrato</label>
                    <select class="w-full rounded-xl border p-2" wire:model.live="contrato_id">
                        <option value="">(Opcional)</option>
                        @foreach($contratosOptions as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('contrato_id')
                    <div class="text-sm text-red-600">{{ $message }}</div>
                    @enderror
                </div>

                <div class="sm:col-span-2">
                    <label class="text-xs text-gray-500">Lote</label>
                    <select class="w-full rounded-xl border p-2" wire:model.live="lote_id">
                        <option value="">Selecciona…</option>
                        @foreach($lotesOptions as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('lote_id')
                    <div class="text-sm text-red-600">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            @if($asociarACuota)
            <div class="mt-3">
                <label class="text-xs text-gray-500">Cuotas a pagar / asociar</label>

                <div class="mt-2 rounded-2xl border divide-y max-h-72 overflow-y-auto">
                    @forelse($cuotasOptions as $id => $label)
                    <label class="flex items-start gap-3 p-3 hover:bg-gray-50 cursor-pointer">
                        <input
                            type="checkbox"
                            value="{{ $id }}"
                            wire:model.lazy="cuotaIds"
                            class="mt-1 rounded border-gray-300">
                        <span class="text-sm">{{ $label }}</span>
                    </label>
                    @empty
                    <div class="p-3 text-sm text-gray-500">
                        No hay cuotas pendientes.
                    </div>
                    @endforelse
                </div>

                @error('cuotaIds')
                <div class="text-sm text-red-600 mt-1">{{ $message }}</div>
                @enderror
                @error('cuotaIds.*')
                <div class="text-sm text-red-600 mt-1">{{ $message }}</div>
                @enderror
            </div>

            @if(count($cuotasSeleccionadasInfo))
            <div class="mt-4 rounded-2xl border bg-gray-50 p-4">
                <div class="flex items-center justify-between gap-3 mb-3">
                    <h3 class="font-black">Resumen de cuotas seleccionadas</h3>
                    <div class="text-xs text-gray-500">
                        {{ count($cuotasSeleccionadasInfo) }} seleccionada(s)
                    </div>
                </div>

                <div class="space-y-2">
                    @foreach($cuotasSeleccionadasInfo as $item)
                    <div class="rounded-xl bg-white border p-3">
                        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                            <div class="text-sm">
                                <div class="font-semibold">
                                    Cuota #{{ $item['numero'] }}
                                </div>

                                <div class="text-xs text-gray-500 mt-1">
                                    Vence: {{ \Carbon\Carbon::parse($item['fecha_vencimiento'])->format('d/m/Y') }}
                                    @if(($item['dias_gracia_total'] ?? 0) > 0)
                                    · Límite gracia:
                                    {{ \Carbon\Carbon::parse($item['cuota_fecha_limite'])->format('d/m/Y') }}
                                    @endif
                                    @if(($item['dias_atraso'] ?? 0) > 0)
                                    · Atraso: {{ $item['dias_atraso'] }} día(s)
                                    @endif
                                </div>

                                @if(!empty($item['recargo_mensaje']))
                                <div class="text-xs mt-2
                                                    @if($item['recargo_condonado']) text-green-700
                                                    @elseif($item['cuota_vencida']) text-red-700
                                                    @elseif($item['cuota_en_gracia']) text-amber-700
                                                    @else text-gray-600
                                                    @endif">
                                    {{ $item['recargo_mensaje'] }}
                                </div>
                                @endif
                            </div>

                            <div class="text-sm text-left sm:text-right">
                                <div>Principal: <b>${{ number_format((float) $item['principal'], 2) }}</b></div>

                                @if($item['recargo_condonado'])
                                <div class="text-green-700">
                                    Recargo:
                                    <b>${{ number_format((float) $item['recargo_monto_original'], 2) }}</b>
                                    <span class="text-[11px]">CONDONADO</span>
                                </div>
                                @else
                                <div class="@if((float)$item['recargo'] > 0) text-red-700 @endif">
                                    Recargo: <b>${{ number_format((float) $item['recargo'], 2) }}</b>
                                </div>
                                @endif

                                <div class="font-black">Total: ${{ number_format((float) $item['total'], 2) }}</div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>

                <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="rounded-xl bg-white border p-3">
                        <div class="text-xs text-gray-500">Principal total</div>
                        <div class="text-lg font-black">${{ number_format((float) $montoTotalSeleccionado, 2) }}</div>
                    </div>

                    <div class="rounded-xl bg-white border p-3">
                        <div class="text-xs text-gray-500">Recargos total</div>
                        <div class="text-lg font-black text-red-600">
                            ${{ number_format((float) $recargoTotalSeleccionado, 2) }}
                        </div>
                    </div>

                    <div class="rounded-xl bg-white border p-3">
                        <div class="text-xs text-gray-500">Total general</div>
                        <div class="text-lg font-black">
                            ${{ number_format((float) ($montoTotalSeleccionado + $recargoTotalSeleccionado), 2) }}
                        </div>
                    </div>
                </div>

                @if(
                count($cuotasSeleccionadasInfo) === 1
                && !$recargoCondonado
                && (float)($recargoMonto ?? 0) > 0
                && ($cuotaVencida ?? false)
                && $tipos_cobro_id
                && str_contains(mb_strtoupper(optional($tiposCobro->firstWhere('id', $tipos_cobro_id))->nombre ?? ''), 'MENSUAL')
                )
                @php
                $recargoFinal = 0;
                if (($recargoModo ?? 'auto') === 'condonar') {
                $recargoFinal = 0;
                } elseif (($recargoModo ?? 'auto') === 'manual') {
                $recargoFinal = (float) ($recargoMontoManual ?? 0);
                } else {
                $recargoFinal = (float) ($recargoMonto ?? 0);
                }
                @endphp

                <div class="mt-4 pt-4 border-t">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="text-xs text-gray-500">Recargo a cobrar</div>
                            <div class="text-sm font-black">
                                ${{ number_format(max(0, $recargoFinal), 2) }}
                            </div>
                            <div class="text-[11px] text-gray-500">
                                En selección múltiple el recargo se usa automático por cuota. La edición manual solo aplica cuando eliges una sola cuota.
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mt-3">
                        <div>
                            <label class="text-xs text-gray-500">Modo</label>
                            <select wire:model.live="recargoModo" class="w-full rounded-xl border p-2">
                                <option value="auto">Automático</option>
                                <option value="manual">Editar cantidad</option>
                                <option value="condonar">Condonar</option>
                            </select>
                        </div>

                        @if(($recargoModo ?? 'auto') === 'manual')
                        <div>
                            <label class="text-xs text-gray-500">Monto manual</label>
                            <input type="number" step="0.01" min="0"
                                class="w-full rounded-xl border p-2"
                                wire:model.lazy="recargoMontoManual">
                            <div class="text-[11px] text-gray-500 mt-1">Puedes bajar o subir el recargo.</div>
                        </div>
                        @else
                        <div class="hidden sm:block"></div>
                        @endif

                        <div class="text-xs text-gray-500 flex items-end">
                            @if(($recargoModo ?? 'auto') === 'condonar')
                            No se generará recibo de recargo.
                            @elseif(($recargoModo ?? 'auto') === 'manual')
                            Se cobrará el monto manual indicado.
                            @else
                            Se cobrará el recargo calculado por el contrato.
                            @endif
                        </div>
                    </div>
                </div>
                @endif
            </div>
            @endif
            @endif

            @if($contrato_id)
            <div class="mt-3 p-3 rounded-xl bg-gray-50 border">
                <div class="text-xs text-gray-500 font-semibold mb-2">Información del contrato</div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm">
                    <div><span class="text-gray-500">Folio:</span> <b>{{ $infoContrato['folio'] ?? '—' }}</b></div>
                    <div><span class="text-gray-500">Tipo:</span> <b>{{ $infoContrato['tipo'] ?? '—' }}</b></div>
                    <div><span class="text-gray-500">Enganche:</span> <b>${{ number_format((float)($infoContrato['enganche'] ?? 0), 2) }}</b></div>
                    <div><span class="text-gray-500">Precio:</span> <b>${{ number_format((float)($infoContrato['precio_total'] ?? 0), 2) }}</b></div>
                    <div><span class="text-gray-500">Saldo:</span> <b>${{ number_format((float)($infoContrato['saldo'] ?? 0), 2) }}</b></div>
                    <div><span class="text-gray-500">Inicio:</span> <b>{{ isset($infoContrato['fecha_inicio']) && $infoContrato['fecha_inicio'] ? \Carbon\Carbon::parse($infoContrato['fecha_inicio'])->format('d/m/Y') : '—' }}</b></div>
                </div>

                <div class="text-xs text-gray-500 font-semibold mt-3 mb-2">Información del lote</div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm">
                    <div><span class="text-gray-500">Fracc:</span> <b>{{ $infoLote['fraccionamiento'] ?? '—' }}</b></div>
                    <div><span class="text-gray-500">Clave:</span> <b>{{ $infoLote['clave'] ?? '—' }}</b></div>
                    <div><span class="text-gray-500">Manzana:</span> <b>{{ $infoLote['manzana'] ?? '—' }}</b></div>
                    <div><span class="text-gray-500">Lote:</span> <b>{{ $infoLote['lote'] ?? '—' }}</b></div>
                </div>
            </div>
            @endif
        </div>
    </div>

    <div class="p-4 rounded-2xl border bg-white">
        <h2 class="font-black mb-3">Importe</h2>

        @if($asociarACuota)
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div>
                <label class="text-xs text-gray-500">Principal total</label>
                <input
                    type="text"
                    class="w-full rounded-xl border p-2 bg-gray-50"
                    value="${{ number_format((float) $montoTotalSeleccionado, 2) }}"
                    readonly>
            </div>

            <div>
                <label class="text-xs text-gray-500">Recargo total</label>
                <input
                    type="text"
                    class="w-full rounded-xl border p-2 bg-gray-50"
                    value="${{ number_format((float) $recargoTotalSeleccionado, 2) }}"
                    readonly>
            </div>

            <div>
                <label class="text-xs text-gray-500">Total general</label>
                <input
                    type="text"
                    class="w-full rounded-xl border p-2 bg-gray-50 font-bold"
                    value="${{ number_format((float) ($montoTotalSeleccionado + $recargoTotalSeleccionado), 2) }}"
                    readonly>
            </div>

            <div class="md:col-span-3">
                <label class="text-xs text-gray-500">Observaciones</label>
                <input class="w-full rounded-xl border p-2" wire:model.live="observaciones" placeholder="Opcional">
                @error('observaciones')
                <div class="text-sm text-red-600">{{ $message }}</div>
                @enderror
            </div>
        </div>
        @else
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div>
                <label class="text-xs text-gray-500">Monto</label>
                <input type="number" step="0.01" class="w-full rounded-xl border p-2" wire:model="monto">
                @error('monto')
                <div class="text-sm text-red-600">{{ $message }}</div>
                @enderror
            </div>

            <div class="md:col-span-2">
                <label class="text-xs text-gray-500">Observaciones</label>
                <input class="w-full rounded-xl border p-2" wire:model.live="observaciones" placeholder="Opcional">
                @error('observaciones')
                <div class="text-sm text-red-600">{{ $message }}</div>
                @enderror
            </div>
        </div>
        @endif

        @if($this->mostrarCampoEvidenciaRecargo)
        <div class="mt-4 p-4 rounded-2xl border border-red-200 bg-red-50">
            <div class="flex items-center justify-between gap-3 mb-3">
                <div>
                    <h3 class="font-black text-red-900">Evidencia del recargo</h3>
                    <p class="text-xs text-red-800">
                        Como el recargo se pagará con una forma de pago o cuenta diferente, sube su comprobante.
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div>
                    <label class="text-xs text-gray-600">Archivo del comprobante</label>

                    <input
                        type="file"
                        accept="image/jpeg,image/png,image/webp,application/pdf,.pdf"
                        wire:model="recargo_evidencia"
                        class="w-full rounded-xl border p-2 bg-white">

                    @error('recargo_evidencia')
                    <div class="text-sm text-red-600 mt-1">{{ $message }}</div>
                    @enderror

                    <div class="text-xs text-gray-500 mt-2">
                        Puedes subir JPG, PNG, WEBP o PDF.
                    </div>

                    <div wire:loading wire:target="recargo_evidencia" class="text-sm text-gray-500 mt-2">
                        Procesando archivo del recargo...
                    </div>
                </div>

                <div>
                    <label class="text-xs text-gray-600">Vista previa</label>

                    <div class="rounded-2xl border bg-white min-h-[220px] flex items-center justify-center overflow-hidden">
                        @if($recargoEvidenciaPreviewUrl)
                        <img
                            src="{{ $recargoEvidenciaPreviewUrl }}"
                            alt="Vista previa evidencia recargo"
                            class="max-h-72 w-auto object-contain">
                        @elseif($recargo_evidencia && strtolower($recargo_evidencia->getClientOriginalExtension()) === 'pdf')
                        <div class="text-sm text-gray-500 text-center px-4">
                            PDF cargado correctamente.
                            <div class="text-xs text-gray-400 mt-1">
                                Se guardará como PDF original.
                            </div>
                        </div>
                        @else
                        <div class="text-sm text-gray-400 text-center px-4">
                            Sin evidencia cargada para el recargo
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @endif

        <div class="grid grid-cols-1 sm:flex sm:flex-row gap-2 mt-4">
            <button
                type="button"
                wire:click="intentarGuardar(false)"
                wire:loading.attr="disabled"
                wire:target="intentarGuardar,evidencia,recargo_evidencia"
                class="w-full sm:w-auto px-4 py-2 rounded-xl bg-black text-white font-semibold disabled:opacity-50">
                <span wire:loading.remove wire:target="intentarGuardar,evidencia,recargo_evidencia">
                    Guardar
                </span>

                <span wire:loading wire:target="intentarGuardar,evidencia,recargo_evidencia">
                    Procesando...
                </span>
            </button>

            <button
                type="button"
                wire:click="intentarGuardar(true)"
                wire:loading.attr="disabled"
                wire:target="intentarGuardar,evidencia,recargo_evidencia"
                class="w-full sm:w-auto px-4 py-2 rounded-xl border font-semibold hover:bg-gray-50 disabled:opacity-50">
                <span wire:loading.remove wire:target="intentarGuardar,evidencia,recargo_evidencia">
                    Guardar e imprimir
                </span>

                <span wire:loading wire:target="intentarGuardar,evidencia,recargo_evidencia">
                    Procesando...
                </span>
            </button>
        </div>
    </div>

    {{-- Modal evidencia por forma de pago --}}
    @if($showPagoEvidenciaModal && $pagoEvidenciaIndex !== null)
    <div class="fixed inset-0 z-[9998] bg-black/60 flex items-center justify-center p-4">
        <div class="bg-white w-full max-w-2xl rounded-2xl shadow-2xl overflow-hidden">
            <div class="px-5 py-4 border-b flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-black">Evidencia de forma de pago #{{ $pagoEvidenciaIndex + 1 }}</h2>
                    <p class="text-sm text-gray-500 mt-1">
                        Adjunta el comprobante correspondiente a esta forma de pago.
                    </p>
                </div>

                <button
                    type="button"
                    wire:click="cerrarModalEvidenciaPago"
                    class="px-3 py-2 rounded-xl border hover:bg-gray-50 text-sm">
                    Cerrar
                </button>
            </div>

            <div class="p-5 grid grid-cols-1 lg:grid-cols-2 gap-5">
                <div>
                    <label class="text-xs text-gray-600">Archivo del comprobante</label>

                    <input
                        type="file"
                        accept="image/jpeg,image/png,image/webp,application/pdf,.pdf"
                        wire:model="pagos.{{ $pagoEvidenciaIndex }}.evidencia"
                        class="w-full rounded-xl border p-2 bg-white mt-1.5">

                    @error('pagos.' . $pagoEvidenciaIndex . '.evidencia')
                    <div class="text-sm text-red-600 mt-1">{{ $message }}</div>
                    @enderror

                    <div class="text-xs text-gray-500 mt-2">
                        Puedes subir JPG, PNG, WEBP o PDF.
                    </div>

                    <div wire:loading wire:target="pagos.{{ $pagoEvidenciaIndex }}.evidencia" class="text-sm text-gray-500 mt-2">
                        Procesando archivo...
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <button
                            type="button"
                            wire:click="quitarEvidenciaPagoActual"
                            class="px-3 py-2 rounded-xl border text-red-600 hover:bg-red-50 text-sm">
                            Quitar evidencia
                        </button>
                    </div>
                </div>

                <div>
                    <label class="text-xs text-gray-600">Vista previa</label>

                    <div class="rounded-2xl border bg-white min-h-[260px] flex items-center justify-center overflow-hidden mt-1.5">
                        @if($pagoEvidenciaPreviewUrl)
                        <img
                            src="{{ $pagoEvidenciaPreviewUrl }}"
                            alt="Vista previa evidencia"
                            class="max-h-80 w-auto object-contain">
                        @elseif($this->pagoTieneEvidencia($pagoEvidenciaIndex) && $this->pagoNombreEvidencia($pagoEvidenciaIndex) && str_ends_with(strtolower($this->pagoNombreEvidencia($pagoEvidenciaIndex)), '.pdf'))
                        <div class="text-sm text-gray-500 text-center px-4">
                            PDF cargado correctamente.
                            <div class="text-xs text-gray-400 mt-1">
                                Se guardará como PDF original.
                            </div>
                        </div>
                        @elseif($this->pagoTieneEvidencia($pagoEvidenciaIndex))
                        <div class="text-sm text-gray-500 text-center px-4">
                            Archivo cargado correctamente.
                        </div>
                        @else
                        <div class="text-sm text-gray-400 text-center px-4">
                            Sin evidencia cargada
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="px-5 py-4 border-t flex justify-end">
                <button
                    type="button"
                    wire:click="cerrarModalEvidenciaPago"
                    class="px-4 py-2 rounded-xl bg-black text-white font-semibold hover:bg-gray-800">
                    Listo
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- Modal confirmación de recargo --}}
    @if($showConfirmRecargoModal)
    <div
        wire:key="confirm-recargo-modal"
        class="fixed inset-0 z-[9999] bg-black/60 flex items-center justify-center p-4">
        <div class="bg-white w-full max-w-lg rounded-2xl shadow-2xl overflow-hidden">
            <div class="px-5 py-4 border-b">
                <h2 class="text-lg font-black">Confirmar recargo</h2>
                <p class="text-sm text-gray-500 mt-1">
                    Se detectó que esta operación incluye recargo.
                </p>
            </div>

            <div class="p-5 space-y-4">
                <div class="rounded-2xl border border-amber-200 bg-amber-50 text-amber-900 p-4 text-sm">
                    {{ $mensajeConfirmacionRecargo }}
                </div>

                @if(count($cuotasSeleccionadasInfo))
                <div class="max-h-72 overflow-y-auto space-y-2">
                    @foreach($cuotasSeleccionadasInfo as $item)
                    <div class="rounded-xl border p-3 bg-gray-50 text-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="font-bold">Cuota #{{ $item['numero'] }}</div>
                                <div class="text-xs text-gray-500">
                                    Vence: {{ \Carbon\Carbon::parse($item['cuota_fecha_vencimiento'])->format('d/m/Y') }}
                                    @if(($item['dias_gracia_total'] ?? 0) > 0)
                                    · Gracia hasta: {{ \Carbon\Carbon::parse($item['cuota_fecha_limite'])->format('d/m/Y') }}
                                    @endif
                                    @if(($item['dias_atraso'] ?? 0) > 0)
                                    · Atraso: {{ $item['dias_atraso'] }} días
                                    @endif
                                </div>
                            </div>

                            <div class="text-right">
                                <div>Principal: <b>${{ number_format((float)$item['principal'], 2) }}</b></div>
                                <div>Recargo: <b>${{ number_format((float)$item['recargo'], 2) }}</b></div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>

            <div class="px-5 py-4 border-t flex flex-col sm:flex-row gap-2 sm:justify-end">
                <button
                    type="button"
                    wire:click="cancelarGuardarConRecargo"
                    class="px-4 py-2 rounded-xl border hover:bg-gray-50 font-semibold">
                    Cancelar
                </button>

                <button
                    type="button"
                    wire:click="confirmarGuardarConRecargo"
                    class="px-4 py-2 rounded-xl bg-red-600 text-white font-semibold hover:bg-red-700">
                    Sí, continuar
                </button>
            </div>
        </div>
    </div>
    @endif
</div>

@script
<script>
    Livewire.on('open-print-tab', (payload) => {
        const url = payload?.url;
        if (!url) return;
        window.open(url, '_blank', 'noopener,noreferrer');
    });
</script>
@endscript