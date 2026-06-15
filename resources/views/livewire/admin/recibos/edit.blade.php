<div class="max-w-6xl mx-auto p-6 space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-black">Editar recibo</h1>
            <p class="text-sm text-gray-500">Modifica los datos permitidos del recibo y sus pagos.</p>
        </div>

        <div class="flex items-center gap-2">
            
@if(
    $recibo->cuota_id &&
    str_contains(
        mb_strtolower($recibo->tipoCobro?->nombre ?? ''),
        'mensualidad'
    )
)
    <button type="button"
        wire:click="abrirModalRecargo"
        class="px-4 py-2 rounded-xl font-semibold bg-orange-600 text-white hover:bg-orange-700">
        Generar recargo
    </button>
@endif
            <button type="button"
                wire:click="confirmarAnularRecibo"
                class="px-4 py-2 rounded-xl font-semibold bg-red-600 text-white hover:bg-red-700">
                Anular recibo
            </button>

            <a href="{{ route('admin.recibos.index') }}"
                class="px-4 py-2 border rounded-xl font-semibold hover:bg-gray-50">
                Volver
            </a>
        </div>
    </div>

    {{-- DATOS GENERALES --}}
    <div class="bg-white border rounded-2xl shadow-sm p-5">
        <h2 class="text-lg font-black mb-4">Datos generales</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div>
                <label class="block text-xs font-bold uppercase tracking-wide text-gray-500">Folio</label>
                <input type="text"
                    value="{{ $recibo->folio }}"
                    disabled
                    class="w-full mt-1.5 rounded-2xl border-gray-300 bg-gray-100">
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wide text-gray-500">Cliente</label>
                <input type="text"
                    value="{{ $recibo->cliente?->nombre_completo }}"
                    disabled
                    class="w-full mt-1.5 rounded-2xl border-gray-300 bg-gray-100">
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wide text-gray-500">Lote</label>
                <input type="text"
                    value="{{ $recibo->lote?->lote }}"
                    disabled
                    class="w-full mt-1.5 rounded-2xl border-gray-300 bg-gray-100">
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wide text-gray-500">Fraccionamiento</label>
                <input type="text"
                    value="{{ $recibo->lote?->fraccionamiento?->nombre }}"
                    disabled
                    class="w-full mt-1.5 rounded-2xl border-gray-300 bg-gray-100">
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wide text-gray-500">Fecha</label>
                <input type="date"
                    wire:model="fecha"
                    class="w-full mt-1.5 rounded-2xl border-gray-300 focus:border-black focus:ring-black">
                @error('fecha')
                <div class="text-red-600 text-sm mt-1">{{ $message }}</div>
                @enderror
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wide text-gray-500">Tipo de cobro</label>
                <select wire:model="tipos_cobro_id"
                    class="w-full mt-1.5 rounded-2xl border-gray-300 focus:border-black focus:ring-black">
                    <option value="">Selecciona</option>
                    @foreach($this->tiposCobro as $tipo)
                    <option value="{{ $tipo->id }}">{{ $tipo->nombre }}</option>
                    @endforeach
                </select>
                @error('tipos_cobro_id')
                <div class="text-red-600 text-sm mt-1">{{ $message }}</div>
                @enderror
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wide text-gray-500">Periodo</label>
                <input type="text"
                    value="{{ $recibo->periodo?->nombre ?? 'No editable' }}"
                    disabled
                    class="w-full mt-1.5 rounded-2xl border-gray-300 bg-gray-100">
                <div class="text-xs text-gray-400 mt-1">
                    El periodo se muestra solo como referencia y no se puede modificar.
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wide text-gray-500">Monto total actual</label>
                <input type="text"
                    value="${{ number_format((float) collect($pagos)->sum('monto'), 2) }}"
                    disabled
                    class="w-full mt-1.5 rounded-2xl border-gray-300 bg-gray-100 font-bold">
            </div>

            <div class="md:col-span-2">
                <label class="block text-xs font-bold uppercase tracking-wide text-gray-500">Observaciones</label>
                <textarea wire:model="observaciones"
                    rows="4"
                    class="w-full mt-1.5 rounded-2xl border-gray-300 focus:border-black focus:ring-black"></textarea>
                @error('observaciones')
                <div class="text-red-600 text-sm mt-1">{{ $message }}</div>
                @enderror
            </div>
        </div>
    </div>

    {{-- PAGOS DETALLE --}}
    <div class="bg-white border rounded-2xl shadow-sm p-5">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="text-lg font-black">Pagos detalle</h2>
                <p class="text-sm text-gray-500">Edita forma de pago, cuenta, monto y referencia de cada subpago.</p>
            </div>

            <div class="text-sm font-bold text-gray-700">
                Total: ${{ number_format((float) collect($pagos)->sum('monto'), 2) }}
            </div>
        </div>

        <div class="space-y-4">
            @foreach($pagos as $i => $pago)
            @php
            $requiereCuenta = (bool) ($pagos[$i]['requiere_cuenta'] ?? false);
            @endphp

            <div class="border rounded-2xl p-4 bg-gray-50">
                <div class="flex items-center justify-between mb-4">
                    <div class="font-black">
                        Pago {{ $pago['orden'] ?: ($i + 1) }}
                    </div>

                    <div class="text-xs text-gray-500">
                        ID {{ $pago['id'] }}
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wide text-gray-500">Forma de pago</label>
                        <select wire:model.live="pagos.{{ $i }}.forma_pago_id"
                            class="w-full mt-1.5 rounded-2xl border-gray-300 focus:border-black focus:ring-black">
                            <option value="">Selecciona</option>
                            @foreach($this->formasPago as $forma)
                            <option value="{{ $forma->id }}">{{ $forma->nombre }}</option>
                            @endforeach
                        </select>
                        @error("pagos.$i.forma_pago_id")
                        <div class="text-red-600 text-sm mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wide text-gray-500">Cuenta bancaria</label>
                        <select wire:model="pagos.{{ $i }}.cuentas_bancarias_id"
                            class="w-full mt-1.5 rounded-2xl border-gray-300 focus:border-black focus:ring-black {{ !$requiereCuenta ? 'bg-gray-100' : '' }}"
                            @disabled(!$requiereCuenta)>
                            <option value="">Selecciona</option>
                            @foreach($this->cuentas as $cuenta)
                            <option value="{{ $cuenta->id }}">{{ $cuenta->alias }}</option>
                            @endforeach
                        </select>

                        @if(!$requiereCuenta)
                        <div class="text-xs text-gray-400 mt-1">
                            Este pago no requiere cuenta bancaria.
                        </div>
                        @endif

                        @error("pagos.$i.cuentas_bancarias_id")
                        <div class="text-red-600 text-sm mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wide text-gray-500">Monto</label>
                        <input type="number"
                            step="0.01"
                            wire:model="pagos.{{ $i }}.monto"
                            class="w-full mt-1.5 rounded-2xl border-gray-300 focus:border-black focus:ring-black">
                        @error("pagos.$i.monto")
                        <div class="text-red-600 text-sm mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wide text-gray-500">Referencia</label>
                        <input type="text"
                            wire:model="pagos.{{ $i }}.referencia"
                            class="w-full mt-1.5 rounded-2xl border-gray-300 focus:border-black focus:ring-black">
                        @error("pagos.$i.referencia")
                        <div class="text-red-600 text-sm mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        @error('pagos')
        <div class="text-red-600 text-sm mt-4">{{ $message }}</div>
        @enderror
    </div>

    <div class="flex justify-end gap-3">
        <a href="{{ route('admin.recibos.index') }}"
            class="px-5 py-2 border rounded-xl font-semibold hover:bg-gray-50">
            Cancelar
        </a>

        <button wire:click="guardar"
            class="px-5 py-2 rounded-xl bg-black text-white font-semibold">
            Guardar cambios
        </button>
    </div>

  
    @if($showAnularRecibo)
    <div class="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">

        {{-- MODAL --}}
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-hidden flex flex-col">

            {{-- HEADER --}}
            <div class="p-4 border-b shrink-0">
                <h2 class="text-lg font-black text-red-700">
                    Anular recibo
                </h2>

                <p class="text-sm text-gray-500 mt-1">
                    Esta acción anulará el recibo y revertirá el pago de la cuota relacionada,
                    usando el mismo flujo de contratos.
                </p>
            </div>

            {{-- BODY --}}
            <div class="p-4 space-y-4 overflow-y-auto">

                @if($anularPreview)

                {{-- INFO --}}
                <div class="grid grid-cols-2 gap-3">

                    <div class="border rounded-2xl p-3 bg-gray-50">
                        <p class="text-[11px] font-bold uppercase text-gray-500">
                            Folio
                        </p>

                        <p class="font-black text-base mt-1">
                            {{ $anularPreview['folio'] ?? '—' }}
                        </p>
                    </div>

                    <div class="border rounded-2xl p-3 bg-gray-50">
                        <p class="text-[11px] font-bold uppercase text-gray-500">
                            Cliente
                        </p>

                        <p class="font-black text-base mt-1 break-words">
                            {{ $anularPreview['cliente'] ?? '—' }}
                        </p>
                    </div>

                    <div class="border rounded-2xl p-3 bg-gray-50">
                        <p class="text-[11px] font-bold uppercase text-gray-500">
                            Concepto
                        </p>

                        <p class="font-black text-base mt-1 break-words">
                            {{ $anularPreview['concepto'] ?? '—' }}
                        </p>
                    </div>

                    <div class="border rounded-2xl p-3 bg-gray-50">
                        <p class="text-[11px] font-bold uppercase text-gray-500">
                            Monto
                        </p>

                        <p class="font-black text-base mt-1">
                            ${{ number_format((float) ($anularPreview['monto_recibo'] ?? 0), 2) }}
                        </p>
                    </div>

                </div>

                {{-- PAGOS --}}
                <div class="border rounded-2xl overflow-hidden">

                    <div class="px-4 py-3 bg-gray-100 font-black text-sm">
                        Pagos que se anularán
                    </div>

                    <div class="divide-y">

                        @forelse(($anularPreview['pagos'] ?? []) as $pago)

                        <div class="p-3 grid grid-cols-2 gap-3 text-sm">

                            <div>
                                <span class="text-gray-500 text-xs">
                                    Forma:
                                </span>

                                <div class="font-bold mt-1">
                                    {{ $pago['forma_pago'] ?? '—' }}
                                </div>
                            </div>

                            <div>
                                <span class="text-gray-500 text-xs">
                                    Cuenta:
                                </span>

                                <div class="font-bold mt-1">
                                    {{ $pago['cuenta'] ?? '—' }}
                                </div>
                            </div>

                            <div>
                                <span class="text-gray-500 text-xs">
                                    Referencia:
                                </span>

                                <div class="font-bold mt-1 break-all">
                                    {{ $pago['referencia'] ?: '—' }}
                                </div>
                            </div>

                            <div>
                                <span class="text-gray-500 text-xs">
                                    Monto:
                                </span>

                                <div class="font-black mt-1">
                                    ${{ number_format((float) ($pago['monto'] ?? 0), 2) }}
                                </div>
                            </div>

                        </div>

                        @empty

                        <div class="p-4 text-sm text-gray-500">
                            No hay pagos activos relacionados.
                        </div>

                        @endforelse

                    </div>

                </div>

                @endif

                {{-- MOTIVO --}}
                <div>

                    <label class="block text-[11px] font-bold uppercase tracking-wide text-gray-500">
                        Motivo de anulación
                    </label>

                    <textarea wire:model="motivoAnulacion"
                        rows="3"
                        class="w-full mt-1.5 rounded-2xl border-gray-300 focus:border-red-600 focus:ring-red-600"></textarea>

                    @error('motivoAnulacion')
                    <div class="text-red-600 text-sm mt-1">
                        {{ $message }}
                    </div>
                    @enderror

                </div>

                {{-- ALERTA --}}
                <div class="rounded-2xl border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                    <strong>Importante:</strong>
                    esta acción no solo cambia el estatus del recibo;
                    también revierte el pago de la cuota y actualiza el saldo del contrato.
                </div>

            </div>

            {{-- FOOTER --}}
            <div class="p-4 border-t flex justify-end gap-3 bg-white shrink-0">

                <button type="button"
                    wire:click="$set('showAnularRecibo', false)"
                    class="px-5 py-2 border rounded-xl font-semibold hover:bg-gray-50">
                    Cerrar
                </button>

                <button type="button"
                    wire:click="anularReciboConfirmado"
                    wire:loading.attr="disabled"
                    wire:target="anularReciboConfirmado"
                    class="px-5 py-2 rounded-xl bg-red-600 text-white font-semibold hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed">

                    <span wire:loading.remove wire:target="anularReciboConfirmado">
                        Sí, anular recibo
                    </span>

                    <span wire:loading wire:target="anularReciboConfirmado">
                        Anulando...
                    </span>

                </button>

            </div>

        </div>

    </div>
    @endif

@if($showRecargoModal)
<div class="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-xl max-h-[90vh] overflow-hidden flex flex-col">

        <div class="p-4 border-b">
            <h2 class="text-lg font-black text-orange-700">Generar recargo</h2>
            <p class="text-sm text-gray-500 mt-1">
                Se generará un recibo de recargo relacionado a la misma cuota de este recibo.
            </p>
        </div>

        <div class="p-4 space-y-4 overflow-y-auto">
            <div class="grid grid-cols-2 gap-3">
                <div class="border rounded-2xl p-3 bg-gray-50">
                    <p class="text-[11px] font-bold uppercase text-gray-500">Recibo origen</p>
                    <p class="font-black mt-1">{{ $recibo->folio }}</p>
                </div>

                <div class="border rounded-2xl p-3 bg-gray-50">
                    <p class="text-[11px] font-bold uppercase text-gray-500">Cuota</p>
                    <p class="font-black mt-1">
                        #{{ $recibo->cuota?->numero ?? '—' }}
                    </p>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wide text-gray-500">
                    Monto del recargo
                </label>
                <input type="number"
                    step="0.01"
                    min="0"
                    wire:model="recargo_monto"
                    class="w-full mt-1.5 rounded-2xl border-gray-300 focus:border-orange-600 focus:ring-orange-600">
                @error('recargo_monto')
                    <div class="text-red-600 text-sm mt-1">{{ $message }}</div>
                @enderror
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wide text-gray-500">
                    Forma de pago
                </label>
                <select wire:model.live="recargo_forma_pago_id"
                    class="w-full mt-1.5 rounded-2xl border-gray-300 focus:border-orange-600 focus:ring-orange-600">
                    <option value="">Selecciona</option>
                    @foreach($this->formasPago as $forma)
                        <option value="{{ $forma->id }}">{{ $forma->nombre }}</option>
                    @endforeach
                </select>
                @error('recargo_forma_pago_id')
                    <div class="text-red-600 text-sm mt-1">{{ $message }}</div>
                @enderror
            </div>

            @if($recargo_requiere_cuenta)
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wide text-gray-500">
                        Cuenta bancaria
                    </label>
                    <select wire:model="recargo_cuentas_bancarias_id"
                        class="w-full mt-1.5 rounded-2xl border-gray-300 focus:border-orange-600 focus:ring-orange-600">
                        <option value="">Selecciona</option>
                        @foreach($this->cuentas as $cuenta)
                            <option value="{{ $cuenta->id }}">{{ $cuenta->alias }}</option>
                        @endforeach
                    </select>
                    @error('recargo_cuentas_bancarias_id')
                        <div class="text-red-600 text-sm mt-1">{{ $message }}</div>
                    @enderror
                </div>
            @endif

            <div>
                <label class="block text-xs font-bold uppercase tracking-wide text-gray-500">
                    Referencia
                </label>
                <input type="text"
                    wire:model="recargo_referencia"
                    class="w-full mt-1.5 rounded-2xl border-gray-300 focus:border-orange-600 focus:ring-orange-600">
                @error('recargo_referencia')
                    <div class="text-red-600 text-sm mt-1">{{ $message }}</div>
                @enderror
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wide text-gray-500">
                    Observaciones
                </label>
                <textarea wire:model="recargo_observaciones"
                    rows="3"
                    class="w-full mt-1.5 rounded-2xl border-gray-300 focus:border-orange-600 focus:ring-orange-600"></textarea>
                @error('recargo_observaciones')
                    <div class="text-red-600 text-sm mt-1">{{ $message }}</div>
                @enderror
            </div>

            <div class="rounded-2xl border border-orange-200 bg-orange-50 p-3 text-sm text-orange-800">
                Este recargo no vuelve a pagar la mensualidad. Solo crea un recibo nuevo tipo RECARGO relacionado a la misma cuota.
            </div>
        </div>

        <div class="p-4 border-t flex justify-end gap-3 bg-white">
            <button type="button"
                wire:click="cerrarModalRecargo"
                class="px-5 py-2 border rounded-xl font-semibold hover:bg-gray-50">
                Cerrar
            </button>

            <button type="button"
                wire:click="guardarRecargo"
                wire:loading.attr="disabled"
                wire:target="guardarRecargo"
                class="px-5 py-2 rounded-xl bg-orange-600 text-white font-semibold hover:bg-orange-700 disabled:opacity-50">
                <span wire:loading.remove wire:target="guardarRecargo">
                    Generar recargo
                </span>
                <span wire:loading wire:target="guardarRecargo">
                    Generando...
                </span>
            </button>
        </div>
    </div>
</div>
@endif
</div>