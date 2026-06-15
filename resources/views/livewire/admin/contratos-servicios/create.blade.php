<div class="max-w-6xl mx-auto p-6 space-y-6">

    {{-- Header --}}
    <div class="flex items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-gray-900">Financiamiento de instalación</h1>
            <p class="text-gray-500">Agua / Electricidad · Genera cuotas igual que un contrato normal.</p>
        </div>

        <a href="{{ route('admin.contratos-servicios.index') }}"
           class="hidden sm:inline-flex px-4 py-2 rounded-xl border bg-white hover:bg-gray-50 font-semibold">
            Volver
        </a>
    </div>

    {{-- Stepper (premium) --}}
    <div class="bg-white border rounded-2xl p-4">
        <div class="flex items-center gap-3">
            @php
                $steps = [
                    1 => ['t' => 'Base', 'd' => 'Contrato y servicio'],
                    2 => ['t' => 'Pagos', 'd' => 'Precio, enganche, recargo'],
                    3 => ['t' => 'Preview', 'd' => 'Revisar cuotas'],
                ];
            @endphp

            @foreach($steps as $k => $s)
                <div class="flex items-center gap-3">
                    <div class="flex items-center gap-3">
                        <div class="h-9 w-9 rounded-xl flex items-center justify-center font-extrabold
                            {{ $step === $k ? 'bg-black text-white' : ($step > $k ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-700') }}">
                            {{ $k }}
                        </div>
                        <div class="leading-tight">
                            <div class="font-extrabold text-gray-900">{{ $s['t'] }}</div>
                            <div class="text-xs text-gray-500">{{ $s['d'] }}</div>
                        </div>
                    </div>

                    @if($k < 3)
                        <div class="hidden sm:block w-10 h-[2px] bg-gray-200 mx-1"></div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    {{-- CARD principal --}}
    <div class="bg-white border rounded-2xl p-5 sm:p-6 shadow-sm space-y-6">

        {{-- STEP 1 --}}
        @if($step === 1)
            <div class="space-y-4">

                <div class="grid lg:grid-cols-12 gap-4">

                    {{-- Autocomplete contrato base --}}
                    <div class="lg:col-span-6">
                        <label class="block text-sm font-bold text-gray-900 mb-1">
                            Contrato base (terreno)
                        </label>

                        <div class="relative">
                            <input
                                wire:model.live="contrato_q"
                                type="text"
                                class="w-full border rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-black/10 focus:border-black transition"
                                placeholder="Buscar por folio o cliente..."
                                autocomplete="off"
                            />

                            {{-- dropdown de sugerencias --}}
                            @if(!empty($contratos_suggest))
                                <div class="absolute z-20 mt-2 w-full bg-white border rounded-xl shadow-lg overflow-hidden">
                                    @foreach($contratos_suggest as $s)
                                        <button type="button"
                                            wire:click="selectContratoBase({{ $s['id'] }})"
                                            class="w-full text-left px-3 py-2 hover:bg-gray-50 border-b last:border-b-0">
                                            <div class="font-semibold text-gray-900">{{ $s['label'] }}</div>
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        @error('contrato_base_id')
                            <div class="text-red-600 text-sm mt-1 font-semibold">{{ $message }}</div>
                        @enderror

                        <p class="text-xs text-gray-500 mt-2">
                            Tip: escribe el folio del contrato o el nombre del cliente.
                        </p>
                    </div>

                    {{-- Servicio --}}
                    <div class="lg:col-span-3">
                        <label class="block text-sm font-bold text-gray-900 mb-1">Servicio</label>
                        <select wire:model.lazy="servicio_tipo"
                                class="w-full border rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-black/10 focus:border-black transition">
                            <option value="agua">Agua (instalación)</option>
                            <option value="electricidad">Electricidad (instalación)</option>
                        </select>
                        @error('servicio_tipo')
                            <div class="text-red-600 text-sm mt-1 font-semibold">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Fecha inicio --}}
                    <div class="lg:col-span-3">
                        <label class="block text-sm font-bold text-gray-900 mb-1">Fecha inicio</label>
                        <input wire:model.lazy="fecha_inicio" type="date"
                               class="w-full border rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-black/10 focus:border-black transition" />
                        @error('fecha_inicio')
                            <div class="text-red-600 text-sm mt-1 font-semibold">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Frecuencia --}}
                    <div class="lg:col-span-3">
                        <label class="block text-sm font-bold text-gray-900 mb-1">Frecuencia</label>
                        <select wire:model.lazy="frecuencia"
                                class="w-full border rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-black/10 focus:border-black transition">
                            <option value="mensual">Mensual</option>
                            <option value="semanal">Semanal</option>
                        </select>
                    </div>

                    {{-- Día --}}
                    <div class="lg:col-span-3">
                        @if($frecuencia === 'semanal')
                            <label class="block text-sm font-bold text-gray-900 mb-1">Día semana</label>
                            <select wire:model.lazy="dia_semana"
                                    class="w-full border rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-black/10 focus:border-black transition">
                                @foreach($this->diasSemana as $k=>$v)
                                    <option value="{{ $k }}">{{ $v }}</option>
                                @endforeach
                            </select>
                            @error('dia_semana')
                                <div class="text-red-600 text-sm mt-1 font-semibold">{{ $message }}</div>
                            @enderror
                        @else
                            <label class="block text-sm font-bold text-gray-900 mb-1">Día del mes</label>
                            <input wire:model.lazy="dia_mes" type="number" min="1" max="31"
                                   class="w-full border rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-black/10 focus:border-black transition" />
                            @error('dia_mes')
                                <div class="text-red-600 text-sm mt-1 font-semibold">{{ $message }}</div>
                            @enderror
                        @endif
                    </div>

                    {{-- Promoción --}}
                    <div class="lg:col-span-6">
                        <label class="block text-sm font-bold text-gray-900 mb-1">Promoción (opcional)</label>
                        <select wire:model.lazy="promocion_id"
                                class="w-full border rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-black/10 focus:border-black transition">
                            <option value="">Sin promoción</option>
                            @foreach($promociones as $p)
                                <option value="{{ $p->id }}">{{ $p->nombre }}</option>
                            @endforeach
                        </select>
                    </div>

                </div>

                {{-- mini resumen (para que se vea premium) --}}
                <div class="grid sm:grid-cols-3 gap-3">
                    <div class="rounded-2xl bg-gray-50 border p-4">
                        <div class="text-xs text-gray-500 font-semibold">Contrato base</div>
                        <div class="font-extrabold text-gray-900 truncate">
                            {{ $contrato_base_id ? 'Seleccionado ✅' : 'No seleccionado' }}
                        </div>
                    </div>
                    <div class="rounded-2xl bg-gray-50 border p-4">
                        <div class="text-xs text-gray-500 font-semibold">Servicio</div>
                        <div class="font-extrabold text-gray-900">
                            {{ $servicio_tipo === 'agua' ? 'Agua' : 'Electricidad' }}
                        </div>
                    </div>
                    <div class="rounded-2xl bg-gray-50 border p-4">
                        <div class="text-xs text-gray-500 font-semibold">Inicio</div>
                        <div class="font-extrabold text-gray-900">
                            {{ $fecha_inicio ?: '-' }}
                        </div>
                    </div>
                </div>

            </div>
        @endif

        {{-- STEP 2 --}}
        @if($step === 2)
            <div class="space-y-5">

                <div class="grid lg:grid-cols-12 gap-4">
                    <div class="lg:col-span-4">
                        <label class="block text-sm font-bold text-gray-900 mb-1">Precio instalación</label>
                        <input wire:model.lazy="precio_total" type="number" step="0.01"
                               class="w-full border rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-black/10 focus:border-black transition" />
                        @error('precio_total') <div class="text-red-600 text-sm mt-1 font-semibold">{{ $message }}</div> @enderror
                    </div>

                    <div class="lg:col-span-4">
                        <label class="block text-sm font-bold text-gray-900 mb-1">Enganche</label>
                        <input wire:model.lazy="enganche" type="number" step="0.01"
                               class="w-full border rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-black/10 focus:border-black transition" />
                        @error('enganche') <div class="text-red-600 text-sm mt-1 font-semibold">{{ $message }}</div> @enderror
                    </div>

                    <div class="lg:col-span-4">
                        <label class="block text-sm font-bold text-gray-900 mb-1">Monto pago</label>
                        <input wire:model.lazy="monto_pago" type="number" step="0.01"
                               class="w-full border rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-black/10 focus:border-black transition" />
                        @error('monto_pago') <div class="text-red-600 text-sm mt-1 font-semibold">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="grid lg:grid-cols-12 gap-4">
                    <div class="lg:col-span-4">
                        <label class="block text-sm font-bold text-gray-900 mb-1">Tipo recargo</label>
                        <select wire:model.lazy="tipo_recargo"
                                class="w-full border rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-black/10 focus:border-black transition">
                            <option value="fijo">Fijo</option>
                            <option value="porcentaje">Porcentaje</option>
                        </select>
                    </div>

                    <div class="lg:col-span-4">
                        <label class="block text-sm font-bold text-gray-900 mb-1">Valor recargo</label>
                        <input wire:model.lazy="valor_recargo" type="number" step="0.01"
                               class="w-full border rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-black/10 focus:border-black transition" />
                        @error('valor_recargo') <div class="text-red-600 text-sm mt-1 font-semibold">{{ $message }}</div> @enderror
                    </div>

                    <div class="lg:col-span-4">
                        <label class="block text-sm font-bold text-gray-900 mb-1">Días de gracia</label>
                        <input wire:model.lazy="dias_gracia" type="number" min="0"
                               class="w-full border rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-black/10 focus:border-black transition" />
                        @error('dias_gracia') <div class="text-red-600 text-sm mt-1 font-semibold">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="grid sm:grid-cols-3 gap-3">
                    <div class="rounded-2xl bg-gray-50 border p-4">
                        <div class="text-xs text-gray-500 font-semibold">Saldo inicial</div>
                        <div class="text-xl font-black text-gray-900">
                            ${{ number_format((float)$saldo_inicial, 2) }}
                        </div>
                    </div>
                    <div class="rounded-2xl bg-gray-50 border p-4">
                        <div class="text-xs text-gray-500 font-semibold">Frecuencia</div>
                        <div class="text-xl font-black text-gray-900">
                            {{ $frecuencia === 'mensual' ? 'Mensual' : 'Semanal' }}
                        </div>
                    </div>
                    <div class="rounded-2xl bg-gray-50 border p-4">
                        <div class="text-xs text-gray-500 font-semibold">Recargo</div>
                        <div class="text-xl font-black text-gray-900">
                            {{ $tipo_recargo === 'fijo' ? '$' : '%' }}{{ number_format((float)$valor_recargo, 2) }}
                        </div>
                    </div>
                </div>

            </div>
        @endif

        {{-- STEP 3 --}}
        @if($step === 3)
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-xl font-black text-gray-900">Preview de cuotas</h2>
                        <p class="text-gray-500 text-sm">Primeras 12 cuotas generadas.</p>
                    </div>
                    <span class="px-3 py-1 rounded-full bg-gray-100 font-semibold text-sm">
                        {{ $servicio_tipo === 'agua' ? 'Agua' : 'Electricidad' }}
                    </span>
                </div>

                <div class="border rounded-2xl overflow-hidden">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="p-3 text-left">#</th>
                                <th class="p-3 text-left">Vencimiento</th>
                                <th class="p-3 text-right">Monto</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($planPreview as $r)
                                <tr class="border-t">
                                    <td class="p-3 font-bold">{{ $r['numero'] }}</td>
                                    <td class="p-3">{{ $r['fecha_vencimiento'] }}</td>
                                    <td class="p-3 text-right font-extrabold">
                                        ${{ number_format((float)$r['monto'], 2) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="p-6 text-center text-gray-500" colspan="3">
                                        No hay preview. Revisa el saldo y el monto de pago.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="rounded-2xl bg-gray-50 border p-4 text-sm text-gray-700">
                    Al confirmar, se creará un <span class="font-bold">Contrato tipo servicio</span> y sus
                    <span class="font-bold">cuotas</span> en el calendario de pagos.
                </div>
            </div>
        @endif

    </div>

    {{-- Footer acciones (compacto) --}}
    <div class="flex items-center justify-between">
        <button type="button" wire:click="back"
                class="px-4 py-2 rounded-xl border bg-white hover:bg-gray-50 font-semibold"
                @disabled($step===1)>
            Atrás
        </button>

        <div class="flex items-center gap-2">
            @if($step < 3)
                <button type="button" wire:click="next"
                        class="px-5 py-2.5 rounded-xl bg-black text-white font-extrabold hover:opacity-90">
                    Siguiente
                </button>
            @else
                <button type="button" wire:click="guardar"
                        class="px-5 py-2.5 rounded-xl bg-black text-white font-extrabold hover:opacity-90">
                    Crear contrato
                </button>
            @endif
        </div>
    </div>

</div>
