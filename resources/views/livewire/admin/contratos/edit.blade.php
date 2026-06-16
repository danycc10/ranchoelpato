<div class="space-y-6">

    {{-- Header --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-black text-gray-900">Editar contrato</h1>
            <p class="text-sm text-gray-500">
                Cambia titular, fraccionamiento, estatus, calendario, recargos y datos financieros del contrato.
            </p>
        </div>

        <a href="{{ route('admin.contratos.show', $contrato->uuid) }}"
           class="inline-flex items-center justify-center px-4 py-2 rounded-xl border font-semibold hover:bg-gray-50">
            Volver
        </a>
    </div>

    {{-- Resumen actual --}}
    <div class="bg-white rounded-2xl border p-5">
        <div class="flex items-center justify-between gap-3 mb-4">
            <div>
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-400">Resumen actual</div>
                <div class="text-lg font-bold text-gray-900">Contrato</div>
            </div>

            <div class="text-xs px-3 py-1 rounded-full border bg-gray-50 text-gray-700">
                Estatus: <span class="font-bold">{{ ucfirst($contrato->estatus ?? '—') }}</span>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 text-sm">
            <div class="rounded-2xl border bg-gray-50 p-4 space-y-2">
                <div>
                    <span class="text-gray-500">Cliente actual:</span>
                    <span class="font-semibold text-gray-900">{{ $contrato->cliente?->nombre_completo ?? '—' }}</span>
                </div>

                <div>
                    <span class="text-gray-500">Lote actual:</span>
                    <span class="font-semibold text-gray-900">{{ $contrato->lote?->clave ?? '—' }}</span>
                </div>

                <div>
                    <span class="text-gray-500">Fraccionamiento:</span>
                    <span class="font-semibold text-gray-900">{{ $contrato->lote?->fraccionamiento?->nombre ?? '—' }}</span>
                </div>

                <div>
                    <span class="text-gray-500">Estatus lote:</span>
                    <span class="font-semibold text-gray-900">{{ $contrato->lote?->estatus ?? '—' }}</span>
                </div>
            </div>

            <div class="rounded-2xl border bg-gray-50 p-4 space-y-2">
                <div>
                    <span class="text-gray-500">Frecuencia:</span>
                    <span class="font-semibold text-gray-900">
                        {{ $contrato->frecuencia === 'semanal' ? 'Semanal' : 'Mensual' }}
                    </span>
                </div>

                <div>
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

                    @if($contrato->frecuencia === 'mensual')
                        <span class="text-gray-500">Día del mes:</span>
                        <span class="font-semibold text-gray-900">{{ $contrato->dia_mes ?? '—' }}</span>
                    @else
                        <span class="text-gray-500">Día semanal:</span>
                        <span class="font-semibold text-gray-900">
                            {{ $diasSemana[$contrato->dia_semana] ?? $contrato->dia_semana ?? '—' }}
                        </span>
                    @endif
                </div>

                <div>
                    <span class="text-gray-500">Inicio:</span>
                    <span class="font-semibold text-gray-900">
                        {{ optional($contrato->fecha_inicio)->format('d/m/Y') ?? '—' }}
                    </span>
                </div>

                <div>
                    <span class="text-gray-500">Días de gracia:</span>
                    <span class="font-semibold text-gray-900">{{ (int)($contrato->dias_gracia ?? 0) }}</span>
                </div>

                <div>
                    <span class="text-gray-500">Recargo actual:</span>
                    <span class="font-semibold text-gray-900">
                        {{ ucfirst($contrato->tipo_recargo ?? 'fijo') }}
                        —
                        @if(($contrato->tipo_recargo ?? 'fijo') === 'porcentaje')
                            {{ number_format((float)($contrato->valor_recargo ?? 0), 2) }}%
                        @else
                            ${{ number_format((float)($contrato->valor_recargo ?? 0), 2) }}
                        @endif
                    </span>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-4">
            <div class="rounded-2xl border p-4 bg-white">
                <div class="text-xs text-gray-500">Precio total</div>
                <div class="text-base font-bold text-gray-900">
                    ${{ number_format((float)($contrato->precio_total ?? 0), 2) }}
                </div>
            </div>

            <div class="rounded-2xl border p-4 bg-white">
                <div class="text-xs text-gray-500">Enganche</div>
                <div class="text-base font-bold text-gray-900">
                    ${{ number_format((float)($contrato->enganche ?? 0), 2) }}
                </div>
            </div>

            <div class="rounded-2xl border p-4 bg-white">
                <div class="text-xs text-gray-500">Saldo inicial</div>
                <div class="text-base font-bold text-gray-900">
                    ${{ number_format((float)($contrato->saldo_inicial ?? 0), 2) }}
                </div>
            </div>

            <div class="rounded-2xl border p-4 bg-black text-white">
                <div class="text-xs text-white/70">Saldo actual</div>
                <div class="text-base font-black">
                    ${{ number_format((float)($contrato->saldo_actual ?? 0), 2) }}
                </div>
            </div>
        </div>
    </div>

    {{-- Formulario --}}
    <div class="bg-white rounded-2xl border p-5 space-y-6">

        {{-- Titular / Ubicación --}}
        <div class="space-y-4">
            <div>
                <h2 class="text-sm font-black uppercase tracking-wide text-gray-700">Titular y ubicación</h2>
                <div class="h-px bg-gray-100 mt-2"></div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                {{-- Cliente --}}
                <div class="md:col-span-2 relative"
                    x-data="{
                        open: false,
                        search: '',
                        selectedId: @entangle('cliente_id').live,
                        selectedLabel: '',
                        clientes: [
                            @foreach($clientes as $c)
                                @if($c->id != $contrato->cliente_id)
                                    {
                                        id: {{ $c->id }},
                                        name: @js($c->nombre_completo ?? trim(($c->nombres ?? '') . ' ' . ($c->apellidos ?? '')))
                                    },
                                @endif
                            @endforeach
                        ],
                        get filteredClientes() {
                            let items = this.clientes;

                            if (this.search.trim() !== '') {
                                items = items.filter(c =>
                                    c.name.toLowerCase().includes(this.search.toLowerCase())
                                );
                            }

                            return items.slice(0, 8);
                        },
                        selectCliente(cliente) {
                            this.selectedId = cliente.id;
                            this.selectedLabel = cliente.name;
                            this.search = cliente.name;
                            this.open = false;
                        },
                        clearCliente() {
                            this.selectedId = null;
                            this.selectedLabel = '';
                            this.search = '';
                            this.open = false;
                        },
                        init() {
                            if (this.selectedId) {
                                let found = this.clientes.find(c => c.id == this.selectedId);
                                if (found) {
                                    this.selectedLabel = found.name;
                                    this.search = found.name;
                                }
                            }
                        }
                    }">

                    <label class="text-xs text-gray-500 font-semibold">Nuevo titular (Cliente)</label>

                    <div class="mt-1 relative">
                        <input type="text"
                            x-model="search"
                            @focus="open = true"
                            @input="open = true"
                            @click="open = true"
                            @keydown.escape.window="open = false"
                            placeholder="Buscar cliente..."
                            class="w-full border rounded-xl px-4 py-3 text-sm pr-24">

                        <div class="absolute inset-y-0 right-2 flex items-center gap-2">
                            <button type="button"
                                x-show="selectedId || search"
                                @click="clearCliente()"
                                class="text-xs px-2 py-1 rounded-lg border hover:bg-gray-50">
                                Limpiar
                            </button>
                        </div>
                    </div>

                    <div x-show="selectedLabel" class="mt-2 text-sm text-gray-600">
                        Seleccionado:
                        <span class="font-semibold" x-text="selectedLabel"></span>
                    </div>

                    <div x-show="open"
                        @click.away="open = false"
                        class="relative mt-2 z-50 bg-white border rounded-2xl shadow-lg">

                        <template x-if="filteredClientes.length > 0">
                            <div class="max-h-64 overflow-y-auto">
                                <template x-for="cliente in filteredClientes" :key="cliente.id">
                                    <button type="button"
                                        @click="selectCliente(cliente)"
                                        class="block w-full text-left px-4 py-3 hover:bg-gray-50 text-sm border-b last:border-b-0">
                                        <span x-text="cliente.name"></span>
                                    </button>
                                </template>
                            </div>
                        </template>

                        <template x-if="filteredClientes.length === 0">
                            <div class="px-4 py-3 text-sm text-gray-500">
                                No se encontraron clientes.
                            </div>
                        </template>
                    </div>

                    @error('cliente_id')
                        <div class="text-xs text-red-600 mt-1">{{ $message }}</div>
                    @enderror
                </div>

                {{-- Fraccionamiento --}}
                <div>
                    <label class="text-xs text-gray-500 font-semibold">Fraccionamiento</label>
                    <select wire:model.live="fraccionamiento_id"
                        class="mt-1 w-full border rounded-xl px-3 py-3">
                        <option value="">Selecciona un fraccionamiento</option>
                        @foreach($fraccionamientos as $fraccionamiento)
                            <option value="{{ $fraccionamiento->id }}">
                                {{ $fraccionamiento->nombre }}
                            </option>
                        @endforeach
                    </select>

                    @error('fraccionamiento_id')
                        <div class="text-xs text-red-600 mt-1">{{ $message }}</div>
                    @enderror

                    <div class="text-xs text-gray-500 mt-1">
                        Este cambio moverá el lote actual a otro fraccionamiento, sin cambiar el lote.
                    </div>
                </div>

                {{-- Estatus --}}
                <div>
                    <label class="text-xs text-gray-500 font-semibold">Estatus del contrato</label>
                    <select wire:model.live="nuevo_estatus"
                        class="mt-1 w-full border rounded-xl px-3 py-3">
                        <option value="activo">Activo</option>
                        <option value="moroso">Moroso</option>
                        <option value="liquidado">Liquidado</option>
                         <option value="donacion">Donacion</option>
                    </select>

                    @error('nuevo_estatus')
                        <div class="text-xs text-red-600 mt-1">{{ $message }}</div>
                    @enderror

                    <div class="text-xs text-gray-500 mt-1">
                        El estatus cancelado se aplica desde el bloque de cancelación.
                    </div>
                </div>
            </div>
        </div>

        {{-- Calendario --}}
        <div class="space-y-4">
            <div>
                <h2 class="text-sm font-black uppercase tracking-wide text-gray-700">Calendario y pagos</h2>
                <div class="h-px bg-gray-100 mt-2"></div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                <div>
                    <label class="text-xs text-gray-500 font-semibold">Nueva fecha de inicio</label>
                    <input type="date"
                        wire:model.lazy="nueva_fecha_inicio"
                        class="mt-1 w-full border rounded-xl px-3 py-3">
                    @error('nueva_fecha_inicio')
                        <div class="text-xs text-red-600 mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <label class="text-xs text-gray-500 font-semibold">Nueva frecuencia</label>
                    <select wire:model.live="nueva_frecuencia"
                        class="mt-1 w-full border rounded-xl px-3 py-3">
                        <option value="mensual">Mensual</option>
                        <option value="semanal">Semanal</option>
                    </select>
                    @error('nueva_frecuencia')
                        <div class="text-xs text-red-600 mt-1">{{ $message }}</div>
                    @enderror
                </div>

                @if($nueva_frecuencia === 'mensual')
                    <div>
                        <label class="text-xs text-gray-500 font-semibold">Nuevo día del mes</label>
                        <input type="number"
                            min="1"
                            max="31"
                            wire:model.live="nuevo_dia_mes"
                            class="mt-1 w-full border rounded-xl px-3 py-3"
                            placeholder="Ej: 10">
                        @error('nuevo_dia_mes')
                            <div class="text-xs text-red-600 mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                @else
                    <div>
                        <label class="text-xs text-gray-500 font-semibold">Nuevo día de la semana</label>
                        <select wire:model.live="nuevo_dia_semana"
                            class="mt-1 w-full border rounded-xl px-3 py-3">
                            <option value="1">Lunes</option>
                            <option value="2">Martes</option>
                            <option value="3">Miércoles</option>
                            <option value="4">Jueves</option>
                            <option value="5">Viernes</option>
                            <option value="6">Sábado</option>
                            <option value="7">Domingo</option>
                        </select>
                        @error('nuevo_dia_semana')
                            <div class="text-xs text-red-600 mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                @endif

                <div>
                    <label class="text-xs text-gray-500 font-semibold">Nuevo monto por pago</label>
                    <input type="number"
                        step="0.01"
                        wire:model.debounce.350ms="nuevo_monto_pago"
                        class="mt-1 w-full border rounded-xl px-3 py-3"
                        placeholder="Ej: 1500.00">
                    @error('nuevo_monto_pago')
                        <div class="text-xs text-red-600 mt-1">{{ $message }}</div>
                    @enderror

                    <div class="text-xs text-gray-500 mt-1">
                        Si cambia, se recalculan cuotas pendientes.
                    </div>
                </div>

                <div class="md:col-span-2">
                    <label class="text-xs text-gray-500 font-semibold">Fecha de la primera cuota nueva</label>
                    <input type="date"
                        wire:model.lazy="nueva_fecha_primera_cuota"
                        class="mt-1 w-full border rounded-xl px-3 py-3">

                    @error('nueva_fecha_primera_cuota')
                        <div class="text-xs text-red-600 mt-1">{{ $message }}</div>
                    @enderror

                    <div class="text-xs text-gray-500 mt-1">
                        Debe coincidir con el día seleccionado. A partir de esa fecha se regeneran las siguientes cuotas.
                    </div>
                </div>
            </div>
        </div>

        {{-- Finanzas y recargos --}}
        <div class="space-y-4">
            <div>
                <h2 class="text-sm font-black uppercase tracking-wide text-gray-700">Finanzas y recargos</h2>
                <div class="h-px bg-gray-100 mt-2"></div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                <div>
                    <label class="text-xs text-gray-500 font-semibold">Precio total</label>
                    <input type="number"
                        step="0.01"
                        wire:model.lazy="nuevo_precio_total"
                        class="mt-1 w-full border rounded-xl px-3 py-3"
                        placeholder="Ej: 150000.00">
                    @error('nuevo_precio_total')
                        <div class="text-xs text-red-600 mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <label class="text-xs text-gray-500 font-semibold">Enganche</label>
                    <input type="number"
                        step="0.01"
                        wire:model.lazy="nuevo_enganche"
                        class="mt-1 w-full border rounded-xl px-3 py-3"
                        placeholder="Ej: 10000.00">
                    @error('nuevo_enganche')
                        <div class="text-xs text-red-600 mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <label class="text-xs text-gray-500 font-semibold">Saldo inicial</label>
                    <input type="number"
                        step="0.01"
                        wire:model.lazy="nuevo_saldo_inicial"
                        class="mt-1 w-full border rounded-xl px-3 py-3"
                        placeholder="Ej: 120000.00">
                    @error('nuevo_saldo_inicial')
                        <div class="text-xs text-red-600 mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <label class="text-xs text-gray-500 font-semibold">Saldo actual</label>
                    <input type="number"
                        step="0.01"
                        wire:model.lazy="nuevo_saldo_actual"
                        class="mt-1 w-full border rounded-xl px-3 py-3"
                        placeholder="Ej: 85000.00">
                    @error('nuevo_saldo_actual')
                        <div class="text-xs text-red-600 mt-1">{{ $message }}</div>
                    @enderror

                    <div class="text-xs text-gray-500 mt-1">
                        Si cambias el saldo actual, se regeneran las cuotas pendientes con ese nuevo saldo.
                    </div>
                </div>

                <div>
                    <label class="text-xs text-gray-500 font-semibold">Días de gracia</label>
                    <input type="number"
                        min="0"
                        max="365"
                        wire:model.lazy="dias_gracia"
                        class="mt-1 w-full border rounded-xl px-3 py-3"
                        placeholder="Ej: 3">
                    @error('dias_gracia')
                        <div class="text-xs text-red-600 mt-1">{{ $message }}</div>
                    @enderror

                    <div class="text-xs text-gray-500 mt-1">
                        Define cuántos días puede pasar la cuota antes de empezar a generar recargo.
                    </div>
                </div>

                <div>
                    <label class="text-xs text-gray-500 font-semibold">Tipo de recargo</label>
                    <select wire:model.live="tipo_recargo"
                        class="mt-1 w-full border rounded-xl px-3 py-3">
                        <option value="fijo">Monto fijo</option>
                        <option value="porcentaje">Porcentaje</option>
                    </select>

                    @error('tipo_recargo')
                        <div class="text-xs text-red-600 mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="text-xs text-gray-500 font-semibold">Monto de recargo</label>
                    <input type="number"
                        step="0.01"
                        wire:model.lazy="valor_recargo"
                        class="mt-1 w-full border rounded-xl px-3 py-3"
                        placeholder="{{ $tipo_recargo === 'porcentaje' ? 'Ej: 10' : 'Ej: 100.00' }}">

                    @error('valor_recargo')
                        <div class="text-xs text-red-600 mt-1">{{ $message }}</div>
                    @enderror

                    <div class="text-xs text-gray-500 mt-1">
                        @if($tipo_recargo === 'porcentaje')
                            Se aplicará como porcentaje sobre la cuota. Ejemplo: 10 = 10%.
                        @else
                            Se aplicará como monto fijo por recargo.
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Aviso --}}
        <div class="bg-yellow-50 border border-yellow-200 rounded-2xl p-4">
            <div class="font-bold text-yellow-900">⚠️ Importante</div>

            <ul class="text-sm text-yellow-900 mt-2 list-disc pl-5 space-y-1">
                <li>Se conservan cuotas pagadas.</li>
                <li>Se eliminan cuotas pendientes solo si no tienen pagos confirmados.</li>
                <li>Si cambias frecuencia, día, fecha de primera cuota, monto por pago o saldo actual, se regenera el calendario pendiente.</li>
                <li>Si solo cambias la fecha de inicio, únicamente se actualiza el dato del contrato.</li>
                <li>Precio total, enganche, saldo inicial, saldo actual, días de gracia, tipo de recargo y monto de recargo actualizan los datos financieros del contrato.</li>
                <li>Si cambias el fraccionamiento, el lote conserva su identidad pero queda asociado al nuevo fraccionamiento.</li>
                <li>El estatus cancelado solo se aplica desde la sección de cancelación.</li>
            </ul>

            <label class="mt-4 inline-flex items-center gap-2 text-sm font-semibold">
                <input type="checkbox" class="rounded" wire:model.live="confirmar_reestructura">
                Confirmo aplicar cambios
            </label>

            @error('confirmar_reestructura')
                <div class="text-xs text-red-600 mt-1">{{ $message }}</div>
            @enderror
        </div>

        {{-- Acciones --}}
        <div class="grid grid-cols-1 xl:grid-cols-[1fr_auto] gap-4 items-end">

            <div class="bg-red-50 border border-red-200 rounded-2xl p-4">
                <div class="font-bold text-red-900">Cancelar contrato</div>
                <div class="text-sm text-red-800 mt-1">
                    Esta acción eliminará las cuotas pendientes, marcará el contrato como cancelado
                    y pondrá el lote nuevamente como disponible.
                </div>

                <label class="mt-3 inline-flex items-center gap-2 text-sm font-semibold text-red-900">
                    <input type="checkbox" class="rounded" wire:model.live="confirmar_cancelacion">
                    Confirmo cancelar este contrato
                </label>

                @error('confirmar_cancelacion')
                    <div class="text-xs text-red-600 mt-1">{{ $message }}</div>
                @enderror

                <div class="mt-4">
                    <button wire:click="cancelarContrato"
                        wire:loading.attr="disabled"
                        wire:target="cancelarContrato"
                        @disabled(($contrato->estatus ?? null) === 'liquidado' || (float)($contrato->saldo_actual ?? 0) <= 0)
                        class="px-5 py-2.5 rounded-xl bg-red-600 text-white font-bold hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed">
                        <span wire:loading.remove wire:target="cancelarContrato">Cancelar contrato</span>
                        <span wire:loading wire:target="cancelarContrato">Cancelando...</span>
                    </button>
                </div>
            </div>

            <div class="flex justify-end">
                <button wire:click="guardarCambios"
                    wire:loading.attr="disabled"
                    wire:target="guardarCambios"
                    class="w-full sm:w-auto px-6 py-3 rounded-xl bg-black text-white font-bold disabled:opacity-50">
                    <span wire:loading.remove wire:target="guardarCambios">Guardar cambios</span>
                    <span wire:loading wire:target="guardarCambios">Guardando...</span>
                </button>
            </div>

        </div>
    </div>

</div>