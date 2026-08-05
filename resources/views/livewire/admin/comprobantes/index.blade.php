<div class="max-w-7xl mx-auto p-6 space-y-5">

    <div class="flex justify-between items-center flex-wrap gap-3">
        <div>
            <h1 class="text-2xl font-black">Comprobantes por WhatsApp</h1>
            <p class="text-gray-500 text-sm">
                Los datos los pre-llena la IA. Revisa la imagen antes de validar: nada se aplica hasta que confirmas.
            </p>
        </div>

        <div class="flex items-center gap-2 text-sm">
            <span class="px-3 py-1.5 rounded-xl bg-amber-100 text-amber-900 font-bold">
                {{ $conteos['por_validar'] ?? 0 }} por validar
            </span>
            @if(($conteos['error'] ?? 0) + ($conteos['sin_archivo'] ?? 0) > 0)
                <span class="px-3 py-1.5 rounded-xl bg-red-100 text-red-900 font-bold">
                    {{ ($conteos['error'] ?? 0) + ($conteos['sin_archivo'] ?? 0) }} con problema
                </span>
            @endif
        </div>
    </div>

    {{-- FILTROS --}}
    <div class="bg-white border rounded-2xl p-4 shadow-sm">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-1">Estatus</label>
                <select wire:model.live="filtroEstatus" class="w-full rounded-xl border-gray-300">
                    <option value="por_validar">Por validar</option>
                    <option value="recibido">Esperando lectura</option>
                    <option value="error">Con error de lectura</option>
                    <option value="sin_archivo">Sin archivo</option>
                    <option value="validado">Validados</option>
                    <option value="rechazado">Rechazados</option>
                    <option value="todos">Todos</option>
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs font-bold text-gray-600 mb-1">Buscar</label>
                <input wire:model.live.debounce.400ms="q" type="text"
                    placeholder="Teléfono, nombre de WhatsApp o referencia"
                    class="w-full rounded-xl border-gray-300">
            </div>
        </div>
    </div>

    {{-- DETALLE / VALIDACIÓN --}}
    @if($comprobante)
        <div class="bg-white border-2 border-black rounded-2xl shadow-lg overflow-hidden">
            <div class="flex justify-between items-center px-5 py-3 bg-black text-white">
                <div class="font-black">
                    Comprobante #{{ $comprobante->id }}
                    <span class="font-normal text-gray-300 text-sm ml-2">
                        {{ $comprobante->nombre_whatsapp ?: 'Sin nombre' }} · {{ $comprobante->telefono }}
                    </span>
                </div>
                <button wire:click="cerrar" class="text-white/80 hover:text-white text-sm font-bold">Cerrar ✕</button>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-0">

                {{-- IMAGEN --}}
                <div class="p-5 bg-gray-50 border-r">
                    <div class="text-xs font-bold text-gray-600 mb-2">Lo que envió el cliente</div>

                    @if($this->imagenUrl)
                        <a href="{{ $this->imagenUrl }}" target="_blank" rel="noopener">
                            <img src="{{ $this->imagenUrl }}" alt="Comprobante"
                                class="w-full rounded-xl border bg-white object-contain max-h-[560px]">
                        </a>
                        <p class="text-xs text-gray-500 mt-2">Clic en la imagen para verla en grande.</p>
                    @else
                        <div class="rounded-xl border-2 border-dashed border-red-300 bg-red-50 p-6 text-center">
                            <p class="font-bold text-red-800">No hay imagen disponible</p>
                            <p class="text-sm text-red-700 mt-1">{{ $comprobante->error ?: 'El archivo no llegó o se perdió.' }}</p>
                            <p class="text-xs text-red-600 mt-2">Pídele al cliente que reenvíe el comprobante.</p>
                        </div>
                    @endif

                    @if($comprobante->texto_mensaje)
                        <div class="mt-3 p-3 bg-white border rounded-xl">
                            <div class="text-xs font-bold text-gray-600 mb-1">Mensaje que acompañó la foto</div>
                            <p class="text-sm text-gray-800">{{ $comprobante->texto_mensaje }}</p>
                        </div>
                    @endif

                    {{-- LO QUE LEYÓ LA IA --}}
                    @if($comprobante->extraccion)
                        <div class="mt-3 p-3 bg-white border rounded-xl text-sm">
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-xs font-bold text-gray-600">Lectura de la IA</span>
                                @php $conf = (float) $comprobante->confianza; @endphp
                                <span class="text-xs font-bold px-2 py-0.5 rounded-lg
                                    {{ $conf >= 80 ? 'bg-green-100 text-green-800' : ($conf >= 50 ? 'bg-amber-100 text-amber-800' : 'bg-red-100 text-red-800') }}">
                                    confianza {{ number_format($conf, 0) }}%
                                </span>
                            </div>
                            <dl class="grid grid-cols-2 gap-x-3 gap-y-1 text-gray-700">
                                <dt class="text-gray-500">Banco</dt><dd>{{ $comprobante->banco_detectado ?: '—' }}</dd>
                                <dt class="text-gray-500">Fecha</dt><dd>{{ optional($comprobante->fecha_detectada)->format('d/m/Y') ?: '—' }}</dd>
                                <dt class="text-gray-500">Referencia</dt><dd>{{ $comprobante->referencia_detectada ?: '—' }}</dd>
                                <dt class="text-gray-500">Últimos dígitos</dt><dd>{{ $comprobante->ultimos_digitos ?: '—' }}</dd>
                            </dl>
                            @if(($comprobante->extraccion['notas'] ?? null))
                                <p class="mt-2 text-xs text-gray-600 italic">{{ $comprobante->extraccion['notas'] }}</p>
                            @endif
                            @if(($comprobante->extraccion['es_comprobante'] ?? true) === false)
                                <p class="mt-2 text-xs font-bold text-red-700">
                                    La IA considera que esto NO es un comprobante de pago.
                                </p>
                            @endif
                            @if($conf < 50)
                                <p class="mt-2 text-xs font-bold text-red-700">
                                    Confianza baja: verifica cada campo contra la imagen antes de validar.
                                </p>
                            @endif
                        </div>
                    @endif
                </div>

                {{-- FORMULARIO --}}
                <div class="p-5">
                    @if($comprobante->estatus === 'validado')
                        <div class="p-4 bg-green-50 border border-green-300 rounded-xl">
                            <p class="font-bold text-green-900">Ya validado</p>
                            <p class="text-sm text-green-800 mt-1">
                                @php
                                    $recibosDelComprobante = $comprobante->recibosGenerados->isNotEmpty()
                                        ? $comprobante->recibosGenerados
                                        : collect(array_filter([$comprobante->recibo]));
                                @endphp
                                Generó {{ $recibosDelComprobante->count() === 1 ? 'el recibo' : $recibosDelComprobante->count().' recibos' }}
                                <strong>{{ $recibosDelComprobante->pluck('folio')->join(', ') ?: '—' }}</strong>,
                                validado por {{ optional($comprobante->validadoPor)->name ?? '—' }}
                                el {{ optional($comprobante->validado_en)->format('d/m/Y H:i') }}.
                            </p>
                        </div>
                    @elseif($comprobante->estatus === 'rechazado')
                        <div class="p-4 bg-gray-100 border rounded-xl">
                            <p class="font-bold text-gray-900">Rechazado</p>
                            <p class="text-sm text-gray-700 mt-1">{{ $comprobante->nota_validacion }}</p>
                        </div>
                    @else

                        @if(! $cliente_id)
                            <div class="mb-4 p-3 bg-amber-50 border border-amber-300 rounded-xl text-sm">
                                <p class="font-bold text-amber-900">No se identificó al cliente</p>
                                <p class="text-amber-800">
                                    @if($comprobante->telefono)
                                        No hubo una coincidencia única para el teléfono
                                        <strong>{{ $comprobante->telefono }}</strong>. Busca al cliente y selecciónalo aquí.
                                    @else
                                        WhatsApp entregó un identificador privado y no incluyó el teléfono.
                                        Busca al cliente y selecciónalo aquí; su número registrado se usará para enviar el recibo.
                                    @endif
                                </p>
                            </div>
                        @else
                            <div class="mb-4 flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm">
                                <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                                <span class="text-emerald-800">Cliente vinculado:</span>
                                <strong class="text-emerald-950">{{ $comprobante->cliente?->nombre_completo }}</strong>
                            </div>
                        @endif

                        <div class="space-y-3">
                            <div class="relative">
                                <label class="block text-xs font-bold text-gray-600 mb-1">Cliente</label>
                                <input
                                    type="text"
                                    wire:model.live.debounce.250ms="clienteSearch"
                                    wire:focus="$set('mostrarResultadosClientes', true)"
                                    autocomplete="off"
                                    placeholder="Busca por nombre, apellido o teléfono"
                                    class="w-full rounded-xl border-gray-300">
                                @error('cliente_id') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror

                                @if($mostrarResultadosClientes && count($clientesResultados) > 0)
                                    <div class="absolute z-30 mt-1 max-h-64 w-full overflow-y-auto rounded-xl border bg-white shadow-xl">
                                        @foreach($clientesResultados as $item)
                                            <button
                                                type="button"
                                                wire:click="seleccionarClienteDesdeBusqueda({{ $item['id'] }})"
                                                class="block w-full border-b px-3 py-2.5 text-left text-sm last:border-b-0 hover:bg-gray-50 focus:bg-gray-50 focus:outline-none">
                                                <span class="block font-bold text-gray-900">{{ $item['label'] }}</span>
                                                <span class="block text-xs text-gray-500">{{ $item['telefono'] ?: 'Sin teléfono registrado' }}</span>
                                            </button>
                                        @endforeach
                                    </div>
                                @elseif($mostrarResultadosClientes && mb_strlen(trim($clienteSearch)) >= 2 && count($clientesResultados) === 0)
                                    <div class="absolute z-30 mt-1 w-full rounded-xl border bg-white p-3 text-sm text-gray-500 shadow-xl">
                                        No se encontraron clientes. Revisa el nombre o el teléfono.
                                    </div>
                                @endif

                                @if(!$cliente_id)
                                    <p class="mt-1 text-xs text-gray-500">Escribe al menos 2 caracteres y selecciona una coincidencia.</p>
                                @endif
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-gray-600 mb-1">Contrato</label>
                                <select wire:model.live="contrato_id" @disabled(!$cliente_id) class="w-full rounded-xl border-gray-300 disabled:bg-gray-100 disabled:text-gray-400">
                                    <option value="">— Selecciona —</option>
                                    @foreach($contratos as $c)
                                        <option value="{{ $c->id }}">
                                            {{ $c->lote?->fraccionamiento?->nombre ?? 'Sin finca' }} · Lote {{ $c->lote?->lote ?? 'sin número' }} ({{ $c->estatus }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('contrato_id') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                            </div>

                            <div class="rounded-xl border border-gray-200 bg-gray-50 p-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-xs font-bold text-gray-700">Cuotas que pagó el cliente</p>
                                        <p class="mt-0.5 text-xs text-gray-500">Selección manual, ordenadas de la más antigua a la más reciente.</p>
                                    </div>
                                    <span class="shrink-0 rounded-lg bg-white px-2 py-1 text-xs font-black text-gray-700 ring-1 ring-gray-200">
                                        {{ count($cuotaIds) }} seleccionada{{ count($cuotaIds) === 1 ? '' : 's' }}
                                    </span>
                                </div>

                                <div class="mt-3 max-h-52 divide-y overflow-y-auto rounded-xl border bg-white">
                                    @forelse($cuotas as $cu)
                                        @php $pend = max(0, (float) $cu->monto - (float) ($cu->pagado_total ?? 0)); @endphp
                                        <label class="flex cursor-pointer items-start gap-3 p-3 hover:bg-gray-50">
                                            <input type="checkbox" value="{{ $cu->id }}" wire:model.live="cuotaIds"
                                                class="mt-1 rounded border-gray-300 text-black focus:ring-black">
                                            <span class="min-w-0 flex-1 text-sm">
                                                <span class="block font-bold text-gray-900">
                                                    Cuota #{{ $cu->numero }} · {{ optional($cu->fecha_vencimiento)->format('d/m/Y') }}
                                                </span>
                                                <span class="block text-xs text-gray-500">
                                                    Pendiente ${{ number_format($pend, 2) }}
                                                    @if((float)($cu->recargo_aplicado ?? 0) > 0) · tiene recargo @endif
                                                </span>
                                            </span>
                                        </label>
                                    @empty
                                        <p class="p-4 text-center text-sm text-gray-500">No hay cuotas pendientes en este contrato.</p>
                                    @endforelse
                                </div>
                                @error('cuotaIds') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                                @error('cuotaIds.*') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror

                                <label class="flex items-start gap-2 mt-3 text-xs text-gray-700">
                                    <input type="checkbox" wire:model="fueraDeOrdenAutorizado" class="rounded">
                                    <span>Autorizo esta selección aunque deje cuotas anteriores pendientes.</span>
                                </label>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-bold text-gray-600 mb-1">Monto del comprobante</label>
                                    <input wire:model.live.debounce.300ms="monto" type="number" step="0.01" min="0"
                                        class="w-full rounded-xl border-gray-300 font-bold">
                                    @error('monto') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-600 mb-1">Fecha del pago</label>
                                    <input wire:model="fecha" type="date" class="w-full rounded-xl border-gray-300">
                                    @error('fecha') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                                </div>
                            </div>

                            @if(count($cuotaIds) > 0)
                                @php
                                    $montoCapturado = is_numeric($monto) ? (float) $monto : 0;
                                    $coincideTotal = abs($montoCapturado - (float) $totalDistribucion) <= 0.01;
                                    $recibosEsperados = count($cuotaIds) + collect($recargoPorCuota)
                                        ->only($cuotaIds)
                                        ->filter(fn($valor) => is_numeric($valor) && (float) $valor > 0)
                                        ->count();
                                @endphp

                                <section class="overflow-hidden rounded-xl border border-gray-200 bg-white" aria-labelledby="desglose-pago">
                                    <div class="flex items-start justify-between gap-3 border-b bg-gray-950 px-3 py-2.5 text-white">
                                        <div>
                                            <p id="desglose-pago" class="text-sm font-black">Distribución manual del pago</p>
                                            <p class="mt-0.5 text-xs text-gray-300">Ajusta principal y recargo para cada cuota.</p>
                                        </div>
                                        <span class="shrink-0 rounded-lg bg-white/10 px-2 py-1 text-xs font-bold">
                                            {{ $recibosEsperados }} recibo{{ $recibosEsperados === 1 ? '' : 's' }}
                                        </span>
                                    </div>

                                    <div class="divide-y divide-gray-200">
                                        @foreach($cuotasSeleccionadas as $cu)
                                            @php
                                                $pendienteCuota = max(0, (float) $cu->monto - (float) ($cu->pagado_total ?? 0));
                                                $principalAsignado = is_numeric($principalPorCuota[$cu->id] ?? null) ? (float) $principalPorCuota[$cu->id] : 0;
                                                $recargoAsignado = is_numeric($recargoPorCuota[$cu->id] ?? null) ? (float) $recargoPorCuota[$cu->id] : 0;
                                                $sugerencia = $recargoSugeridoPorCuota[$cu->id] ?? ['monto' => 0, 'dias_atraso' => 0, 'mensaje' => null];
                                            @endphp
                                            <div wire:key="distribucion-cuota-{{ $cu->id }}" class="p-3">
                                                <div class="mb-2 flex items-center justify-between gap-3">
                                                    <p class="text-sm font-black text-gray-900">
                                                        Cuota #{{ $cu->numero }}
                                                        <span class="font-normal text-gray-500">· {{ optional($cu->fecha_vencimiento)->format('d/m/Y') }}</span>
                                                    </p>
                                                    <p class="text-sm font-black tabular-nums text-gray-950">
                                                        ${{ number_format($principalAsignado + $recargoAsignado, 2) }}
                                                    </p>
                                                </div>

                                                <div class="grid grid-cols-1 gap-2 sm:grid-cols-[1fr_auto_1fr_auto] sm:items-end">
                                                    <div>
                                                        <label class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-gray-500">Principal</label>
                                                        <input wire:model.live.debounce.300ms="principalPorCuota.{{ $cu->id }}"
                                                            type="number" step="0.01" min="0.01" max="{{ $pendienteCuota }}"
                                                            class="w-full rounded-lg border-gray-300 font-bold tabular-nums">
                                                        <p class="mt-1 text-[11px] text-gray-500">Máximo ${{ number_format($pendienteCuota, 2) }}</p>
                                                        @error('principalPorCuota.'.$cu->id) <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                                    </div>

                                                    <span class="hidden pb-7 text-gray-400 sm:block" aria-hidden="true">+</span>

                                                    <div>
                                                        <label class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-gray-500">Recargo</label>
                                                        <input wire:model.live.debounce.300ms="recargoPorCuota.{{ $cu->id }}"
                                                            type="number" step="0.01" min="0"
                                                            class="w-full rounded-lg border-amber-300 bg-amber-50 font-bold tabular-nums focus:border-amber-500 focus:ring-amber-500">
                                                        <p class="mt-1 text-[11px] text-amber-800">
                                                            Sugerido ${{ number_format((float) $sugerencia['monto'], 2) }}
                                                            @if((int) $sugerencia['dias_atraso'] > 0)
                                                                · {{ (int) $sugerencia['dias_atraso'] }} día{{ (int) $sugerencia['dias_atraso'] === 1 ? '' : 's' }} de atraso
                                                            @endif
                                                        </p>
                                                        @error('recargoPorCuota.'.$cu->id) <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                                    </div>

                                                    <span class="hidden pb-7 font-black text-gray-500 sm:block" aria-hidden="true">=</span>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>

                                    <div class="grid grid-cols-3 gap-px border-t bg-gray-200 text-center">
                                        <div class="bg-gray-50 px-2 py-2">
                                            <p class="text-[10px] font-bold uppercase tracking-wide text-gray-500">Principal</p>
                                            <p class="font-black tabular-nums text-gray-900">${{ number_format((float) $totalPrincipalSeleccionado, 2) }}</p>
                                        </div>
                                        <div class="bg-amber-50 px-2 py-2">
                                            <p class="text-[10px] font-bold uppercase tracking-wide text-amber-700">Recargos</p>
                                            <p class="font-black tabular-nums text-amber-950">${{ number_format((float) $totalRecargosSeleccionados, 2) }}</p>
                                        </div>
                                        <div class="px-2 py-2 {{ $coincideTotal ? 'bg-emerald-50' : 'bg-red-50' }}">
                                            <p class="text-[10px] font-bold uppercase tracking-wide {{ $coincideTotal ? 'text-emerald-700' : 'text-red-700' }}">Distribuido</p>
                                            <p class="font-black tabular-nums {{ $coincideTotal ? 'text-emerald-950' : 'text-red-950' }}">${{ number_format((float) $totalDistribucion, 2) }}</p>
                                        </div>
                                    </div>
                                </section>

                                <div class="rounded-xl border px-3 py-2.5 text-sm {{ $coincideTotal ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-red-300 bg-red-50 text-red-900' }}">
                                    <p class="font-bold">
                                        {{ $coincideTotal ? 'El desglose coincide con el comprobante.' : 'El desglose todavía no coincide con el comprobante.' }}
                                    </p>
                                    <p class="mt-0.5 text-xs">
                                        Comprobante ${{ number_format($montoCapturado, 2) }} · Distribuido ${{ number_format((float) $totalDistribucion, 2) }}
                                    </p>
                                </div>
                                @error('distribucion') <p class="text-xs font-bold text-red-600">{{ $message }}</p> @enderror
                            @endif

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-bold text-gray-600 mb-1">Tipo de cobro</label>
                                    <select wire:model="tipos_cobro_id" class="w-full rounded-xl border-gray-300">
                                        @foreach($tiposCobro as $t)
                                            <option value="{{ $t->id }}">{{ $t->nombre }}</option>
                                        @endforeach
                                    </select>
                                    @error('tipos_cobro_id') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-600 mb-1">Forma de pago</label>
                                    <select wire:model="forma_pago_id" class="w-full rounded-xl border-gray-300">
                                        @foreach($formasPago as $f)
                                            <option value="{{ $f->id }}">{{ $f->nombre }}</option>
                                        @endforeach
                                    </select>
                                    @error('forma_pago_id') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-bold text-gray-600 mb-1">Cuenta bancaria</label>
                                    <select wire:model="cuentas_bancarias_id" class="w-full rounded-xl border-gray-300">
                                        <option value="">— Ninguna —</option>
                                        @foreach($cuentas as $cta)
                                            <option value="{{ $cta->id }}">{{ $cta->alias }} · {{ $cta->banco }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-600 mb-1">Referencia</label>
                                    <input wire:model="referencia" type="text" class="w-full rounded-xl border-gray-300">
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-gray-600 mb-1">Observaciones</label>
                                <textarea wire:model="observaciones" rows="2" class="w-full rounded-xl border-gray-300"></textarea>
                            </div>
                        </div>

                        {{-- ACCIONES --}}
                        <div class="mt-5 flex flex-wrap gap-2">
                            <button wire:click="validarYGenerar" wire:loading.attr="disabled"
                                class="px-4 py-2 bg-black text-white rounded-xl font-bold disabled:opacity-50">
                                <span wire:loading.remove wire:target="validarYGenerar">
                                    Validar y generar {{ ($recibosEsperados ?? count($cuotaIds)) }} recibo{{ ($recibosEsperados ?? count($cuotaIds)) === 1 ? '' : 's' }}
                                </span>
                                <span wire:loading wire:target="validarYGenerar">Generando…</span>
                            </button>

                            <button wire:click="$toggle('mostrarRechazo')"
                                class="px-4 py-2 border border-red-300 text-red-700 rounded-xl font-bold">
                                Rechazar
                            </button>

                            @if($comprobante->estatus === 'error' && $comprobante->archivo_path)
                                <button wire:click="reintentarLectura"
                                    class="px-4 py-2 border rounded-xl font-bold text-gray-700">
                                    Reintentar lectura
                                </button>
                            @endif

                            <a href="{{ route('admin.recibos.crear') }}"
                                class="px-4 py-2 border rounded-xl font-bold text-gray-700 ml-auto">
                                Caso complejo → captura completa
                            </a>
                        </div>

                        <p class="text-xs text-gray-500 mt-2">
                            Cada principal y cada recargo genera su propio recibo. Para condonación o varias formas de pago usa la captura completa.
                        </p>

                        @if($mostrarRechazo)
                            <div class="mt-4 p-4 border border-red-300 bg-red-50 rounded-xl">
                                <label class="block text-xs font-bold text-red-800 mb-1">Motivo del rechazo</label>
                                <textarea wire:model="motivoRechazo" rows="2"
                                    class="w-full rounded-xl border-red-300"
                                    placeholder="Ej. La imagen no corresponde a un pago de este rancho."></textarea>
                                @error('motivoRechazo') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                                <button wire:click="rechazar"
                                    class="mt-2 px-4 py-2 bg-red-600 text-white rounded-xl font-bold">
                                    Confirmar rechazo
                                </button>
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- LISTADO --}}
    <div class="bg-white border rounded-2xl shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-600">
                <tr>
                    <th class="text-left px-4 py-3 font-bold">Recibido</th>
                    <th class="text-left px-4 py-3 font-bold">De</th>
                    <th class="text-left px-4 py-3 font-bold">Cliente</th>
                    <th class="text-right px-4 py-3 font-bold">Monto leído</th>
                    <th class="text-center px-4 py-3 font-bold">Confianza</th>
                    <th class="text-center px-4 py-3 font-bold">Estatus</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($comprobantes as $c)
                    <tr class="{{ $comprobanteId === $c->id ? 'bg-amber-50' : '' }}">
                        <td class="px-4 py-3 whitespace-nowrap">{{ optional($c->recibido_en)->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-3">
                            <div class="font-bold">{{ $c->nombre_whatsapp ?: '—' }}</div>
                            <div class="text-xs text-gray-500">{{ $c->telefono }}</div>
                        </td>
                        <td class="px-4 py-3">
                            @if($c->cliente)
                                {{ $c->cliente->nombres }} {{ $c->cliente->apellidos }}
                            @else
                                <span class="text-amber-700 text-xs font-bold">Sin identificar</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right font-bold">
                            {{ $c->monto_detectado !== null ? '$'.number_format((float) $c->monto_detectado, 2) : '—' }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if($c->confianza !== null)
                                @php $cf = (float) $c->confianza; @endphp
                                <span class="text-xs font-bold px-2 py-0.5 rounded-lg
                                    {{ $cf >= 80 ? 'bg-green-100 text-green-800' : ($cf >= 50 ? 'bg-amber-100 text-amber-800' : 'bg-red-100 text-red-800') }}">
                                    {{ number_format($cf, 0) }}%
                                </span>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center">
                            @php
                                $badge = match($c->estatus) {
                                    'por_validar' => 'bg-amber-100 text-amber-900',
                                    'validado' => 'bg-green-100 text-green-800',
                                    'rechazado' => 'bg-gray-200 text-gray-700',
                                    'recibido' => 'bg-blue-100 text-blue-800',
                                    default => 'bg-red-100 text-red-800',
                                };
                            @endphp
                            <span class="text-xs font-bold px-2 py-0.5 rounded-lg {{ $badge }}">
                                {{ str_replace('_', ' ', $c->estatus) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button wire:click="abrir({{ $c->id }})"
                                class="px-3 py-1.5 bg-black text-white rounded-lg font-bold text-xs">
                                Revisar
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-10 text-center text-gray-500">
                            No hay comprobantes con este filtro.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $comprobantes->links() }}</div>
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
