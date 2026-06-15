<div class="space-y-4">

    <div class="flex items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-black">Editar contrato de servicio</h1>
            <p class="text-gray-500 text-sm">
                Cambia titular, fecha de inicio, calendario y monto del contrato de servicio.
            </p>
        </div>

        <a href="{{ route('admin.contratos-servicios.show', $contrato->uuid) }}"
           class="px-4 py-2 rounded-xl border font-semibold hover:bg-gray-50">
            Volver
        </a>
    </div>

    {{-- Resumen --}}
    <div class="bg-white rounded-2xl border p-5 space-y-2">
        <div class="text-xs text-gray-500">Contrato de servicio</div>

        <div class="text-sm text-gray-700">
            Cliente actual:
            <span class="font-semibold">{{ $contrato->cliente?->nombre_completo ?? '—' }}</span>
        </div>

        <div class="text-sm text-gray-700">
            Lote:
            <span class="font-semibold">{{ $contrato->lote?->clave ?? '—' }}</span>
        </div>

        <div class="text-sm text-gray-700">
            Contrato base:
            <span class="font-semibold">{{ $contrato->contratoBase?->folio_contrato ?? '—' }}</span>
        </div>

        <div class="text-sm text-gray-700">
            Servicio:
            <span class="font-semibold">
                {{ ($contrato->servicio_tipo ?? '') === 'agua' ? 'Agua' : 'Electricidad' }}
            </span>
        </div>

        <div class="text-sm text-gray-700">
            Estatus contrato:
            <span class="font-semibold">{{ $contrato->estatus ?? '—' }}</span>
        </div>

        <div class="text-sm text-gray-700">
            Frecuencia actual:
            <span class="font-semibold">{{ $contrato->frecuencia === 'semanal' ? 'Semanal' : 'Mensual' }}</span>

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
                | Día del mes actual:
                <span class="font-semibold">{{ $contrato->dia_mes ?? '—' }}</span>
            @else
                | Día semanal actual:
                <span class="font-semibold">{{ $diasSemana[$contrato->dia_semana] ?? $contrato->dia_semana ?? '—' }}</span>
            @endif

            |
            Inicio:
            <span class="font-semibold">{{ optional($contrato->fecha_inicio)->format('d/m/Y') ?? '—' }}</span>
        </div>

        <div class="text-sm">
            <span class="text-gray-500">Saldo actual:</span>
            <span class="font-black">${{ number_format((float)($contrato->saldo_actual ?? 0), 2) }}</span>
        </div>
    </div>

    <div class="bg-white rounded-2xl border p-5 space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

            {{-- Nuevo titular con buscador --}}
            <div class="md:col-span-2 relative"
                 x-data="{
                    open: false,
                    search: '',
                    selectedId: @entangle('cliente_id').live,
                    selectedLabel: '',
                    clientes: [
                        @foreach($clientes as $c)
                            {
                                id: {{ $c->id }},
                                name: @js($c->nombre_completo ?? trim(($c->nombres ?? '') . ' ' . ($c->apellidos ?? '')))
                            },
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

            {{-- Fecha inicio --}}
            <div>
                <label class="text-xs text-gray-500 font-semibold">Nueva fecha de inicio</label>
                <input type="date"
                       wire:model.lazy="nueva_fecha_inicio"
                       class="mt-1 w-full border rounded-xl px-3 py-3">
                @error('nueva_fecha_inicio')
                    <div class="text-xs text-red-600 mt-1">{{ $message }}</div>
                @enderror

                <div class="text-xs text-gray-500 mt-1">
                    Si solo cambias esta fecha, únicamente se actualiza el dato del contrato.
                </div>
            </div>

            {{-- Nueva frecuencia --}}
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

            {{-- Día del mes / semana --}}
            @if($nueva_frecuencia === 'mensual')
                <div>
                    <label class="text-xs text-gray-500 font-semibold">Nuevo día del mes</label>
                    <input type="number"
                           min="1"
                           max="31"
                           wire:model.lazy="nuevo_dia_mes"
                           class="mt-1 w-full border rounded-xl px-3 py-3"
                           placeholder="Ej: 10">
                    @error('nuevo_dia_mes')
                        <div class="text-xs text-red-600 mt-1">{{ $message }}</div>
                    @enderror
                </div>
            @else
                <div>
                    <label class="text-xs text-gray-500 font-semibold">Nuevo día de la semana</label>
                    <select wire:model.lazy="nuevo_dia_semana"
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

            {{-- Nuevo monto --}}
            <div class="md:col-span-2">
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
                    Si cambias frecuencia, día o monto, se recalculan solo cuotas pendientes en adelante.
                </div>
            </div>

            {{-- Aviso + confirmación edición --}}
            <div class="md:col-span-2 bg-yellow-50 border border-yellow-200 rounded-2xl p-4">
                <div class="font-bold text-yellow-900">⚠️ Importante</div>
                <ul class="text-sm text-yellow-900 mt-2 list-disc pl-5 space-y-1">
                    <li>Se conservan cuotas pagadas.</li>
                    <li>Se eliminan cuotas pendientes solo si no tienen pagos confirmados.</li>
                    <li>Si cambias frecuencia, día o monto por pago, se regenera el calendario pendiente.</li>
                    <li>Si solo cambias la fecha de inicio, únicamente se actualiza el dato del contrato.</li>
                </ul>

                <label class="mt-3 inline-flex items-center gap-2 text-sm font-semibold">
                    <input type="checkbox" class="rounded" wire:model.live="confirmar_reestructura">
                    Confirmo aplicar cambios
                </label>

                @error('confirmar_reestructura')
                    <div class="text-xs text-red-600 mt-1">{{ $message }}</div>
                @enderror
            </div>

        </div>

        <div class="pt-2 flex flex-col md:flex-row gap-3 justify-between">
            <div class="bg-red-50 border border-red-200 rounded-2xl p-4 w-full md:max-w-xl">
                <div class="font-bold text-red-900">Cancelar contrato de servicio</div>
                <div class="text-sm text-red-800 mt-1">
                    Esta acción eliminará las cuotas pendientes y pondrá el contrato en estatus cancelado.
                </div>

                <label class="mt-3 inline-flex items-center gap-2 text-sm font-semibold text-red-900">
                    <input type="checkbox" class="rounded" wire:model.live="confirmar_cancelacion">
                    Confirmo cancelar este contrato
                </label>

                @error('confirmar_cancelacion')
                    <div class="text-xs text-red-600 mt-1">{{ $message }}</div>
                @enderror

                @if(($contrato->estatus ?? null) === 'liquidado' || (float)($contrato->saldo_actual ?? 0) <= 0)
                    <div class="text-xs text-red-600 mt-2">
                        Este contrato está liquidado y no puede cancelarse.
                    </div>
                @endif

                <div class="mt-3">
                    <button wire:click="cancelarContrato"
                            wire:loading.attr="disabled"
                            wire:target="cancelarContrato"
                            @disabled(($contrato->estatus ?? null) === 'liquidado' || (float)($contrato->saldo_actual ?? 0) <= 0)
                            class="px-5 py-2 rounded-xl bg-red-600 text-white font-bold hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed">
                        <span wire:loading.remove wire:target="cancelarContrato">Cancelar contrato</span>
                        <span wire:loading wire:target="cancelarContrato">Cancelando...</span>
                    </button>
                </div>
            </div>

            <div class="flex justify-end items-end">
                <button wire:click="guardarCambios"
                        wire:loading.attr="disabled"
                        wire:target="guardarCambios"
                        class="px-5 py-2 rounded-xl bg-black text-white font-bold disabled:opacity-50">
                    <span wire:loading.remove wire:target="guardarCambios">Guardar cambios</span>
                    <span wire:loading wire:target="guardarCambios">Guardando...</span>
                </button>
            </div>
        </div>
    </div>

</div>