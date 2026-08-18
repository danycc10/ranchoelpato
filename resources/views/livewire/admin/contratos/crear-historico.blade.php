<div class="max-w-3xl mx-auto p-4 sm:p-6 space-y-4 sm:space-y-6">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 sm:gap-4">
        <div class="min-w-0">
            <h1 class="text-xl sm:text-2xl font-black leading-tight">Contrato histórico</h1>
            <p class="text-gray-500 text-sm">
                Alta de un contrato ya terminado, solo para resguardar sus documentos.
            </p>
        </div>

        <a href="{{ route('admin.contratos.index') }}"
            class="w-full sm:w-auto text-center px-4 py-2 rounded-xl border bg-white hover:bg-gray-50 font-semibold">
            Volver
        </a>
    </div>

    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
        <div class="font-black">No genera cuotas ni saldos</div>
        <div>
            Se registra como liquidado y en ceros, así que no aparece en ingresos, esperado ni cobranza.
            Úsalo solo para contratos que ya se terminaron de pagar fuera del sistema.
            El folio se genera automáticamente, igual que en el alta normal.
        </div>
    </div>

    <div class="bg-white rounded-2xl border p-4 sm:p-5">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

            {{-- Cliente --}}
            <div class="md:col-span-2 relative">
                <label class="text-sm font-semibold">Cliente</label>

                <input type="text"
                    wire:model.live="cliente_q"
                    class="w-full mt-1 rounded-xl border-gray-300"
                    placeholder="Buscar cliente por nombre, teléfono o correo...">

                @error('cliente_id')
                <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                @enderror

                @if(!empty($clientes_suggest))
                <div class="absolute z-10 mt-1 w-full bg-white border rounded-xl shadow overflow-hidden max-h-64 overflow-y-auto">
                    @foreach($clientes_suggest as $s)
                    <button type="button"
                        wire:click="selectCliente({{ $s['id'] }})"
                        class="w-full text-left px-3 py-2 hover:bg-gray-50">
                        {{ $s['label'] }}
                    </button>
                    @endforeach
                </div>
                @endif

                @if($cliente_id)
                <p class="text-xs text-gray-500 mt-1">Seleccionado.</p>
                @endif
            </div>

            {{-- Fraccionamiento --}}
            <div>
                <label class="text-sm font-semibold">Fraccionamiento</label>
                <select wire:model.live="fraccionamiento_id" class="w-full mt-1 rounded-xl border-gray-300">
                    <option value="">Selecciona...</option>
                    @foreach($fraccionamientos as $f)
                    <option value="{{ $f->id }}">{{ $f->nombre }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Manzana --}}
            <div>
                <label class="text-sm font-semibold">Manzana</label>
                <select wire:model.live="manzana" class="w-full mt-1 rounded-xl border-gray-300"
                    @disabled(!$fraccionamiento_id)>
                    <option value="">Todas</option>
                    @foreach($manzanasDisponibles as $mz)
                    <option value="{{ $mz }}">{{ $mz }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Lote --}}
            <div class="md:col-span-2">
                <label class="text-sm font-semibold">Lote</label>
                <select wire:model.live="lote_id" class="w-full mt-1 rounded-xl border-gray-300"
                    @disabled(!$fraccionamiento_id)>
                    <option value="">Selecciona...</option>
                    @foreach($lotesDisponibles as $l)
                    <option value="{{ $l['id'] }}">{{ $l['label'] }}</option>
                    @endforeach
                </select>

                <p class="text-xs text-gray-500 mt-1">
                    Solo aparecen lotes disponibles. Al guardar, el lote queda marcado como vendido.
                </p>

                @error('lote_id')
                <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- Fechas --}}
            <div>
                <label class="text-sm font-semibold">Fecha de inicio <span class="text-gray-400">(opcional)</span></label>
                <input type="date" wire:model.defer="fecha_inicio" class="w-full mt-1 rounded-xl border-gray-300">
                @error('fecha_inicio')
                <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="text-sm font-semibold">Fecha de terminación</label>
                <input type="date" wire:model.defer="fecha_terminacion" class="w-full mt-1 rounded-xl border-gray-300">
                @error('fecha_terminacion')
                <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="mt-6 border-t pt-5">
            <div class="font-black text-gray-900">Datos para los documentos</div>
            <p class="text-sm text-gray-500">Se usan para llenar el Word de la constancia. Puedes corregirlos.</p>

            <div class="mt-3 grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="text-sm font-semibold">Nombre legal del comprador</label>
                    <input type="text" wire:model.defer="comprador_nombre_legal" class="w-full mt-1 rounded-xl border-gray-300">
                </div>

                <div>
                    <label class="text-sm font-semibold">CURP del comprador</label>
                    <input type="text" wire:model.defer="comprador_curp" class="w-full mt-1 rounded-xl border-gray-300">
                </div>

                <div>
                    <label class="text-sm font-semibold">Nombre legal del vendedor</label>
                    <input type="text" wire:model.defer="vendedor_nombre_legal" class="w-full mt-1 rounded-xl border-gray-300">
                </div>

                <div>
                    <label class="text-sm font-semibold">CURP del vendedor</label>
                    <input type="text" wire:model.defer="vendedor_curp" class="w-full mt-1 rounded-xl border-gray-300">
                </div>
            </div>
        </div>

        <div class="mt-6 flex flex-col sm:flex-row sm:justify-end gap-2">
            <a href="{{ route('admin.contratos.index') }}"
                class="text-center px-4 py-2 rounded-xl border bg-white hover:bg-gray-50 font-semibold">
                Cancelar
            </a>

            <button type="button"
                wire:click="guardar"
                wire:loading.attr="disabled"
                wire:target="guardar"
                class="px-4 py-2 rounded-xl bg-black text-white font-bold disabled:opacity-50">
                <span wire:loading.remove wire:target="guardar">Dar de alta</span>
                <span wire:loading wire:target="guardar">Guardando...</span>
            </button>
        </div>
    </div>
</div>
