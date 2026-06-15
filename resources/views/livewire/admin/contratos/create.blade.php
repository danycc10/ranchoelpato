<div class="max-w-5xl mx-auto p-4 sm:p-6 space-y-4 sm:space-y-6">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 sm:gap-4">
        <div class="min-w-0">
            <h1 class="text-xl sm:text-2xl font-black leading-tight">Nuevo Contrato</h1>
            <p class="text-gray-500 text-sm">Captura por pasos y generación automática de documento legal.</p>

            <div class="mt-3 -mx-1 px-1 flex items-center gap-2 text-sm flex-wrap sm:flex-nowrap sm:overflow-x-auto sm:whitespace-nowrap">
                <span class="px-3 py-1 rounded-full font-semibold
                    {{ $step === 1 ? 'bg-black text-white' : 'bg-gray-100 text-gray-700' }}">
                    1) Datos
                </span>

                <span class="px-3 py-1 rounded-full font-semibold
                    {{ $step === 2 ? 'bg-black text-white' : 'bg-gray-100 text-gray-700' }}">
                    2) Condiciones y documento
                </span>

                <span class="px-3 py-1 rounded-full font-semibold
                    {{ $step === 3 ? 'bg-black text-white' : 'bg-gray-100 text-gray-700' }}">
                    3) Confirmación
                </span>
            </div>
        </div>

        <a href="{{ route('admin.contratos.index') }}"
            class="w-full sm:w-auto text-center px-4 py-2 rounded-xl border bg-white hover:bg-gray-50 font-semibold">
            Volver
        </a>
    </div>

    <div class="bg-white rounded-2xl border p-4 sm:p-5">
        {{-- STEP 1 --}}
        @if($step === 1)
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

            {{-- Cliente buscador --}}
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
            <div class="md:col-span-2">
                <label class="text-sm font-semibold">Fraccionamiento</label>
                <select class="w-full mt-1 rounded-xl border-gray-300"
                    wire:model.lazy="fraccionamiento_id">
                    <option value="">— Selecciona —</option>
                    @foreach($fraccionamientos as $f)
                    <option value="{{ $f->id }}">{{ $f->nombre }}</option>
                    @endforeach
                </select>
                @error('fraccionamiento_id')
                <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- Manzana --}}
            <div>
                <label class="text-sm font-semibold">Manzana</label>
                <select class="w-full mt-1 rounded-xl border-gray-300"
                    wire:model.lazy="manzana"
                    @disabled(!$fraccionamiento_id)>
                    <option value="">— Selecciona —</option>
                    @foreach($manzanasDisponibles as $m)
                    <option value="{{ $m }}">{{ $m }}</option>
                    @endforeach
                </select>
                @error('manzana')
                <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- Lote disponible --}}
            <div>
                <label class="text-sm font-semibold">Lote (disponible)</label>
                <select class="w-full mt-1 rounded-xl border-gray-300"
                    wire:model.lazy="lote_id"
                    @disabled(!$fraccionamiento_id)>
                    <option value="">— Selecciona —</option>
                    @foreach($lotesDisponibles as $l)
                    <option value="{{ $l['id'] }}">{{ $l['label'] }}</option>
                    @endforeach
                </select>
                @error('lote_id')
                <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                @enderror

                @if($lote_id)
                <p class="text-xs text-gray-500 mt-1">
                    Precio sugerido y datos del lote cargados automáticamente.
                </p>
                @endif
            </div>

            {{-- SWITCH DONACION --}}
            <div>
                <label class="text-sm font-semibold block mb-2">Tipo de operación</label>

                <div class="rounded-2xl border p-4 bg-white">
                    <div class="flex items-center justify-between gap-4">

                        <div>
                            <p class="font-bold text-sm">Contrato por donación</p>
                            <p class="text-xs text-gray-500">
                                No generará cuotas ni cobranza.
                            </p>
                        </div>

                        <button
                            type="button"
                            wire:click="$toggle('es_donacion')"
                            class="relative inline-flex h-8 w-14 items-center rounded-full transition
                {{ $es_donacion ? 'bg-emerald-500' : 'bg-gray-300' }}">
                            <span class="inline-block h-6 w-6 transform rounded-full bg-white transition
                {{ $es_donacion ? 'translate-x-7' : 'translate-x-1' }}">
                            </span>
                        </button>

                    </div>

                    @if($es_donacion)
                    <div class="mt-3 rounded-xl bg-emerald-50 border border-emerald-200 p-3">
                        <p class="text-sm font-semibold text-emerald-800">
                            Donación activada
                        </p>
                        <p class="text-xs text-emerald-700 mt-1">
                            Se guardará como contrato legal sin cuotas.
                        </p>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Fecha inicio --}}
            <div>
                <label class="text-sm font-semibold">Fecha inicio</label>
                <input type="date"
                    wire:model.lazy="fecha_inicio"
                    class="w-full mt-1 rounded-xl border-gray-300">

                @if($this->diaNombre)
                <p class="text-xs text-gray-600 mt-1">Día: {{ ucfirst($this->diaNombre) }}</p>
                @endif

                @error('fecha_inicio')
                <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- Frecuencia --}}
            <div>
                <label class="text-sm font-semibold">Frecuencia</label>
                <select class="w-full mt-1 rounded-xl border-gray-300"
                    wire:model.lazy="frecuencia">
                    <option value="mensual">Mensual</option>
                    <option value="semanal">Semanal</option>
                </select>
                @error('frecuencia')
                <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- Día de pago --}}
            @if($frecuencia === 'mensual')
            <div class="md:col-span-2">
                <label class="text-sm font-semibold">Día del mes de pago (1-31)</label>
                <input type="number" min="1" max="31"
                    wire:model.lazy="dia_mes"
                    class="w-full mt-1 rounded-xl border-gray-300">
                @error('dia_mes')
                <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>
            @else
            <div class="md:col-span-2">
                <label class="text-sm font-semibold">Día de la semana de pago</label>
                <select class="w-full mt-1 rounded-xl border-gray-300"
                    wire:model.lazy="dia_semana">
                    <option value="">— Selecciona —</option>
                    <option value="1">Lunes</option>
                    <option value="2">Martes</option>
                    <option value="3">Miércoles</option>
                    <option value="4">Jueves</option>
                    <option value="5">Viernes</option>
                    <option value="6">Sábado</option>
                    <option value="7">Domingo</option>
                </select>
                @error('dia_semana')
                <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>
            @endif

            {{-- Promoción --}}
            <div class="md:col-span-2">
                <label class="text-sm font-semibold">Promoción (opcional)</label>
                <select class="w-full mt-1 rounded-xl border-gray-300"
                    wire:model.lazy="promocion_id">
                    <option value="">— Sin promoción —</option>
                    @foreach($promociones as $p)
                    <option value="{{ $p->id }}">{{ $p->nombre }}</option>
                    @endforeach
                </select>
                @error('promocion_id')
                <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                @enderror

                @if($this->promocion)
                <p class="text-xs text-green-700 mt-2">
                    Promo aplicada:
                    <b>{{ $this->promocion->nombre }}</b>
                    <span class="text-gray-500">({{ $this->promocion->tipo }})</span>

                    @if($this->promocion->tipo === 'diferir_primer_pago')
                    — difiere {{ (int)$this->promocion->dias_diferidos }} días
                    @endif

                    @if($this->promocion->tipo === 'cuotas_fijas')
                    — {{ (int)$this->promocion->numero_cuotas }} cuotas
                    @endif
                </p>
                @endif
            </div>

        </div>
        @endif

        {{-- STEP 2 --}}
        @if($step === 2)
        <div class="space-y-6">

            {{-- Condiciones económicas --}}
            <div class="rounded-2xl border p-4 sm:p-5">
                <div class="mb-4">
                    <h3 class="text-base sm:text-lg font-black">Condiciones económicas</h3>
                    <p class="text-xs sm:text-sm text-gray-600">
                        Define el precio, enganche, pagos y condiciones del contrato.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm font-semibold">Precio total</label>
                        <input type="number" step="0.01"
                            wire:model.lazy.fill="precio_total"
                            class="w-full mt-1 rounded-xl border-gray-300">
                        @error('precio_total')
                        <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="text-sm font-semibold">Enganche</label>
                        <input type="number" step="0.01"
                            wire:model.lazy.fill="enganche"
                            class="w-full mt-1 rounded-xl border-gray-300">
                        @error('enganche')
                        <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="text-sm font-semibold">Saldo inicial</label>
                        <input type="text"
                            value="{{ number_format($saldo_inicial ?? 0, 2) }}"
                            class="w-full mt-1 rounded-xl border-gray-200 bg-gray-50"
                            disabled>
                    </div>

                    <div>
                        <label class="text-sm font-semibold">Monto de pago</label>
                        <input type="number" step="0.01"
                            wire:model.lazy.fill="monto_pago"
                            class="w-full mt-1 rounded-xl border-gray-300">
                        @error('monto_pago')
                        <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="text-sm font-semibold">Tipo recargo</label>
                        <select class="w-full mt-1 rounded-xl border-gray-300"
                            wire:model.lazy="tipo_recargo">
                            <option value="fijo">Fijo</option>
                            <option value="porcentaje">Porcentaje</option>
                        </select>
                        @error('tipo_recargo')
                        <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="text-sm font-semibold">Valor recargo</label>
                        <input type="number" step="0.01"
                            wire:model.lazy.fill="valor_recargo"
                            class="w-full mt-1 rounded-xl border-gray-300">
                        @error('valor_recargo')
                        <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="md:col-span-2">
                        <label class="text-sm font-semibold">Días de gracia</label>
                        <input type="number"
                            wire:model.lazy.fill="dias_gracia"
                            class="w-full mt-1 rounded-xl border-gray-300">
                        @error('dias_gracia')
                        <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            {{-- Documento legal --}}
            <div class="rounded-2xl border bg-gray-50 p-4 sm:p-5 space-y-4">
                <div>
                    <h3 class="text-base sm:text-lg font-black">Información legal del documento</h3>
                    <p class="text-xs sm:text-sm text-gray-600">
                        Estos datos se usarán para generar el contrato Word y PDF con el formato legal.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold mb-1">Área m²</label>
                        <input type="number" step="0.01" wire:model.live="area_m2" class="w-full rounded-xl border-gray-300">
                        @error('area_m2') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-1">Vendedor legal</label>
                        <input type="text" wire:model.live="vendedor_nombre_legal" class="w-full rounded-xl border-gray-300">
                        @error('vendedor_nombre_legal') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-1">Medida norte</label>
                        <input type="text" wire:model.live="medida_norte" class="w-full rounded-xl border-gray-300" placeholder="Ej: 10.50 m">
                        @error('medida_norte') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-1">CURP vendedor</label>
                        <input type="text" wire:model.live="vendedor_curp" class="w-full rounded-xl border-gray-300">
                        @error('vendedor_curp') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-1">Medida sur</label>
                        <input type="text" wire:model.live="medida_sur" class="w-full rounded-xl border-gray-300" placeholder="Ej: 10.50 m">
                        @error('medida_sur') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-1">Comprador legal</label>
                        <input type="text" wire:model.live="comprador_nombre_legal" class="w-full rounded-xl border-gray-300">
                        @error('comprador_nombre_legal') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-1">Medida este</label>
                        <input type="text" wire:model.live="medida_este" class="w-full rounded-xl border-gray-300" placeholder="Ej: 20.00 m">
                        @error('medida_este') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-1">CURP comprador</label>
                        <input type="text" wire:model.live="comprador_curp" class="w-full rounded-xl border-gray-300">
                        @error('comprador_curp') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-1">Medida oeste</label>
                        <input type="text" wire:model.live="medida_oeste" class="w-full rounded-xl border-gray-300" placeholder="Ej: 20.00 m">
                        @error('medida_oeste') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                    <div>
                        <label class="block text-sm font-semibold mb-1">Colindancia norte</label>
                        <input type="text" wire:model.live="colindancia_norte" class="w-full rounded-xl border-gray-300">
                        @error('colindancia_norte') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-1">Colindancia sur</label>
                        <input type="text" wire:model.live="colindancia_sur" class="w-full rounded-xl border-gray-300">
                        @error('colindancia_sur') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-1">Colindancia este</label>
                        <input type="text" wire:model.live="colindancia_este" class="w-full rounded-xl border-gray-300">
                        @error('colindancia_este') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-1">Colindancia oeste</label>
                        <input type="text" wire:model.live="colindancia_oeste" class="w-full rounded-xl border-gray-300">
                        @error('colindancia_oeste') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>

            {{-- Credenciales --}}
            <div class="rounded-2xl border bg-gray-50 p-4 sm:p-5 space-y-4">
                <div>
                    <h3 class="text-base sm:text-lg font-black">Credenciales para anexar al contrato</h3>
                    <p class="text-xs sm:text-sm text-gray-600">
                        Estas imágenes se guardan en privado y se insertan en la última hoja del documento.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-2">
                    {{-- Comprador frente --}}
                    <div class="rounded-2xl border bg-white p-4 space-y-3">
                        <div>
                            <label class="block text-sm font-semibold mb-1">INE comprador - frente</label>
                            <input type="file"
                                wire:model="comprador_ine_frente"
                                accept="image/*"
                                class="w-full rounded-xl border-gray-300">
                            @error('comprador_ine_frente')
                            <span class="text-red-600 text-sm">{{ $message }}</span>
                            @enderror
                        </div>

                        @if($this->compradorIneFrentePreview)
                        <div class="rounded-xl overflow-hidden border bg-gray-100">
                            <img src="{{ $this->compradorIneFrentePreview }}"
                                alt="Preview INE comprador frente"
                                class="w-full h-52 object-contain bg-white">
                        </div>
                        @endif

                        @if($comprador_ine_frente_path && !$comprador_ine_frente)
                        <p class="text-xs text-green-700">
                            Ya existe una credencial cargada por defecto para el comprador.
                        </p>
                        @endif
                    </div>

                    {{-- Comprador reverso --}}
                    <div class="rounded-2xl border bg-white p-4 space-y-3">
                        <div>
                            <label class="block text-sm font-semibold mb-1">INE comprador - reverso</label>
                            <input type="file"
                                wire:model="comprador_ine_reverso"
                                accept="image/*"
                                class="w-full rounded-xl border-gray-300">
                            @error('comprador_ine_reverso')
                            <span class="text-red-600 text-sm">{{ $message }}</span>
                            @enderror
                        </div>

                        @if($this->compradorIneReversoPreview)
                        <div class="rounded-xl overflow-hidden border bg-gray-100">
                            <img src="{{ $this->compradorIneReversoPreview }}"
                                alt="Preview INE comprador reverso"
                                class="w-full h-52 object-contain bg-white">
                        </div>
                        @endif

                        @if($comprador_ine_reverso_path && !$comprador_ine_reverso)
                        <p class="text-xs text-green-700">
                            Ya existe una credencial cargada por defecto para el comprador.
                        </p>
                        @endif
                    </div>

                    {{-- Vendedor frente --}}
                    <div class="rounded-2xl border bg-white p-4 space-y-3">
                        <div>
                            <label class="block text-sm font-semibold mb-1">INE vendedor - frente</label>
                            <input type="file"
                                wire:model="vendedor_ine_frente"
                                accept="image/*"
                                class="w-full rounded-xl border-gray-300">
                            @error('vendedor_ine_frente')
                            <span class="text-red-600 text-sm">{{ $message }}</span>
                            @enderror
                        </div>

                        @if($this->vendedorIneFrentePreview)
                        <div class="rounded-xl overflow-hidden border bg-gray-100">
                            <img src="{{ $this->vendedorIneFrentePreview }}"
                                alt="Preview INE vendedor frente"
                                class="w-full h-52 object-contain bg-white">
                        </div>
                        @endif

                        @if($vendedor_ine_frente_path && !$vendedor_ine_frente)
                        <p class="text-xs text-green-700">
                            Ya existe una credencial cargada por defecto para el vendedor.
                        </p>
                        @endif
                    </div>

                    {{-- Vendedor reverso --}}
                    <div class="rounded-2xl border bg-white p-4 space-y-3">
                        <div>
                            <label class="block text-sm font-semibold mb-1">INE vendedor - reverso</label>
                            <input type="file"
                                wire:model="vendedor_ine_reverso"
                                accept="image/*"
                                class="w-full rounded-xl border-gray-300">
                            @error('vendedor_ine_reverso')
                            <span class="text-red-600 text-sm">{{ $message }}</span>
                            @enderror
                        </div>

                        @if($this->vendedorIneReversoPreview)
                        <div class="rounded-xl overflow-hidden border bg-gray-100">
                            <img src="{{ $this->vendedorIneReversoPreview }}"
                                alt="Preview INE vendedor reverso"
                                class="w-full h-52 object-contain bg-white">
                        </div>
                        @endif

                        @if($vendedor_ine_reverso_path && !$vendedor_ine_reverso)
                        <p class="text-xs text-green-700">
                            Ya existe una credencial cargada por defecto para el vendedor.
                        </p>
                        @endif
                    </div>
                </div>

                <div wire:loading wire:target="comprador_ine_frente,comprador_ine_reverso,vendedor_ine_frente,vendedor_ine_reverso"
                    class="text-xs text-gray-500">
                    Subiendo imágenes...
                </div>
            </div>

            {{-- ANUALIDAD --}}
            <div class="rounded-2xl border bg-gray-50 p-4">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <p class="text-sm font-black">Anualidad</p>
                        <p class="text-xs text-gray-600">
                            Abono extra que se aplica cada 12 meses y <b>reduce el saldo</b> del terreno,
                            además de los pagos {{ $frecuencia === 'semanal' ? 'semanales' : 'mensuales' }}.
                        </p>
                    </div>

                    <label class="inline-flex items-center gap-2 select-none">
                        <span class="text-xs font-semibold text-gray-700">Activar</span>
                        <input type="checkbox"
                            wire:model.live="tiene_anualidad"
                            class="rounded border-gray-300"
                            @disabled($this->promocion?->tipo === 'cuotas_fijas')>
                    </label>
                </div>

                @if($this->promocion?->tipo === 'cuotas_fijas')
                <p class="text-xs text-amber-700 mt-2">
                    Esta promoción usa <b>cuotas fijas</b>. La anualidad no se puede combinar
                    porque el saldo se liquidaría antes y rompería el número fijo de cuotas.
                </p>
                @endif

                @if($tiene_anualidad)
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                    <div>
                        <label class="text-sm font-semibold">Fecha anualidad (primer abono)</label>
                        <input type="date"
                            wire:model.lazy="anualidad_fecha"
                            class="w-full mt-1 rounded-xl border-gray-300">
                        @error('anualidad_fecha')
                        <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="text-sm font-semibold">Monto anualidad</label>
                        <input type="number" step="0.01"
                            wire:model.lazy="anualidad_monto"
                            class="w-full mt-1 rounded-xl border-gray-300">
                        @error('anualidad_monto')
                        <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="md:col-span-2">
                        <p class="text-xs text-gray-500">
                            Se agregará un abono marcado como <b>ANUALIDAD</b> cada año desde la fecha indicada.
                            Este abono <b>reduce el saldo</b>, por lo que el plan terminará en menos pagos.
                        </p>
                    </div>
                </div>
                @else
                <p class="text-xs text-gray-500 mt-3">
                    Desactivada. El plan se calculará solo con los pagos {{ $frecuencia === 'semanal' ? 'semanales' : 'mensuales' }}.
                </p>
                @endif
            </div>

        </div>
        @endif

        {{-- STEP 3 --}}
        @if($step === 3)
        <div class="space-y-4 text-sm">
            <p class="font-bold text-lg">Confirmación</p>

            @if($es_donacion)
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
                <p class="text-sm font-black text-emerald-800">
                    Contrato por donación
                </p>
                <p class="text-xs text-emerald-700 mt-1">
                    Este contrato se guardará únicamente como registro legal. No generará cuotas, saldo pendiente, recargos ni anualidad.
                </p>
            </div>
            @endif

            <div class="rounded-xl border p-4 bg-gray-50">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                    <div><b>Cliente:</b> {{ $cliente_q ?: '—' }}</div>
                    <div><b>Fecha inicio:</b> {{ \Carbon\Carbon::parse($fecha_inicio)->format('d/m/Y') }} ({{ ucfirst($this->diaNombre) }})</div>

                    <div class="md:col-span-2">
                        <b>Tipo de operación:</b>
                        @if($es_donacion)
                        <span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 text-xs font-bold">
                            DONACIÓN
                        </span>
                        @else
                        <span class="px-2 py-0.5 rounded-full bg-blue-100 text-blue-800 text-xs font-bold">
                            VENTA / FINANCIAMIENTO
                        </span>
                        @endif
                    </div>

                    @if(!$es_donacion)
                    <div><b>Frecuencia:</b> {{ ucfirst($frecuencia) }}</div>

                    <div>
                        <b>Día pago:</b>
                        @if($frecuencia === 'mensual')
                        {{ $dia_mes }}
                        @else
                        {{ $dia_semana }}
                        @endif
                    </div>

                    <div><b>Precio:</b> ${{ number_format($precio_total ?? 0, 2) }}</div>
                    <div><b>Enganche:</b> ${{ number_format($enganche ?? 0, 2) }}</div>
                    <div><b>Saldo:</b> ${{ number_format($saldo_inicial ?? 0, 2) }}</div>
                    <div><b>Monto pago base:</b> ${{ number_format($monto_pago ?? 0, 2) }}</div>

                    <div class="md:col-span-2">
                        <b>Promoción:</b>
                        {{ $this->promocion?->nombre ?? 'Sin promoción' }}
                    </div>
                    @else
                    <div class="md:col-span-2 text-emerald-700">
                        <b>Condiciones económicas:</b> No aplica para contrato por donación.
                    </div>
                    @endif

                    <div class="md:col-span-2">
                        <b>Documento legal:</b>
                        {{ $vendedor_nombre_legal ?: '—' }} / {{ $comprador_nombre_legal ?: '—' }}
                    </div>

                    @if(!$es_donacion)
                    <div class="md:col-span-2">
                        <b>Anualidad:</b>
                        @if($tiene_anualidad)
                        <span class="inline-flex items-center gap-2">
                            <span class="px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-800 text-xs font-bold">
                                ACTIVADA
                            </span>
                            <span class="text-gray-700 text-sm">
                                {{ \Carbon\Carbon::parse($anualidad_fecha)->format('d/m/Y') }} — ${{ number_format($anualidad_monto ?? 0, 2) }}
                                <span class="text-gray-500 text-xs">(abono anual al saldo)</span>
                            </span>
                        </span>
                        @else
                        <span class="px-2 py-0.5 rounded-full bg-gray-200 text-gray-700 text-xs font-bold">
                            NO
                        </span>
                        @endif
                    </div>
                    @endif
                </div>
            </div>

            @if(!$es_donacion)
            {{-- Configuración manual --}}
            <div class="rounded-2xl border bg-white p-4 sm:p-5 space-y-4">
                <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
                    <div>
                        <h3 class="text-base sm:text-lg font-black">Configuración de cuotas</h3>
                        <p class="text-xs sm:text-sm text-gray-600">
                            El sistema generó un calendario automático. Puedes dejarlo así o editarlo manualmente.
                        </p>
                    </div>

                    <label class="inline-flex items-center gap-2 select-none">
                        <input type="checkbox"
                            wire:model.live="usar_configuracion_manual"
                            class="rounded border-gray-300">
                        <span class="text-sm font-semibold">Editar manualmente</span>
                    </label>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button type="button"
                        wire:click="regenerarPreviewAutomatico"
                        class="px-3 py-2 rounded-xl border bg-white hover:bg-gray-50 text-sm font-semibold">
                        Regenerar automático
                    </button>

                    @if($usar_configuracion_manual)
                    <button type="button"
                        wire:click="agregarCuotaManual"
                        class="px-3 py-2 rounded-xl border bg-white hover:bg-gray-50 text-sm font-semibold">
                        Agregar cuota
                    </button>

                    <button type="button"
                        wire:click="ordenarCuotasPorFecha"
                        class="px-3 py-2 rounded-xl border bg-white hover:bg-gray-50 text-sm font-semibold">
                        Ordenar por fecha
                    </button>
                    @endif
                </div>

                @error('previewCuotas')
                <p class="text-red-600 text-xs">{{ $message }}</p>
                @enderror

                <div class="overflow-x-auto border rounded-2xl">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left font-bold">#</th>
                                <th class="px-3 py-2 text-left font-bold">Fecha</th>
                                <th class="px-3 py-2 text-left font-bold">Monto</th>
                                <th class="px-3 py-2 text-left font-bold">Concepto</th>
                                <th class="px-3 py-2 text-center font-bold">Anualidad</th>
                                @if($usar_configuracion_manual)
                                <th class="px-3 py-2 text-right font-bold">Acciones</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @forelse($previewCuotas as $i => $cuota)
                            <tr wire:key="preview-cuota-{{ $i }}">
                                <td class="px-3 py-2 align-top">{{ $i + 1 }}</td>

                                <td class="px-3 py-2 align-top min-w-[150px]">
                                    @if($usar_configuracion_manual)
                                    <input type="date"
                                        wire:model.lazy="previewCuotas.{{ $i }}.fecha_vencimiento"
                                        class="w-full rounded-xl border-gray-300">
                                    @error("previewCuotas.$i.fecha_vencimiento")
                                    <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                                    @enderror
                                    @else
                                    {{ \Carbon\Carbon::parse($cuota['fecha_vencimiento'])->format('d/m/Y') }}
                                    @endif
                                </td>

                                <td class="px-3 py-2 align-top min-w-[140px]">
                                    @if($usar_configuracion_manual)
                                    <input type="number"
                                        step="0.01"
                                        wire:model.lazy="previewCuotas.{{ $i }}.monto"
                                        class="w-full rounded-xl border-gray-300">
                                    @error("previewCuotas.$i.monto")
                                    <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                                    @enderror
                                    @else
                                    ${{ number_format((float)($cuota['monto'] ?? 0), 2) }}
                                    @endif
                                </td>

                                <td class="px-3 py-2 align-top min-w-[220px]">
                                    @if($usar_configuracion_manual)
                                    <input type="text"
                                        wire:model.lazy="previewCuotas.{{ $i }}.concepto"
                                        class="w-full rounded-xl border-gray-300"
                                        placeholder="Opcional">
                                    @else
                                    {{ $cuota['concepto'] ?: '—' }}
                                    @endif
                                </td>

                                <td class="px-3 py-2 align-top text-center">
                                    @if($usar_configuracion_manual)
                                    <input type="checkbox"
                                        wire:model.live="previewCuotas.{{ $i }}.es_anualidad"
                                        class="rounded border-gray-300">
                                    @else
                                    @if(!empty($cuota['es_anualidad']))
                                    <span class="px-2 py-1 rounded-full bg-indigo-100 text-indigo-800 text-xs font-bold">
                                        Sí
                                    </span>
                                    @else
                                    —
                                    @endif
                                    @endif
                                </td>

                                @if($usar_configuracion_manual)
                                <td class="px-3 py-2 align-top">
                                    <div class="flex justify-end gap-2">
                                        <button type="button"
                                            wire:click="duplicarCuotaManual({{ $i }})"
                                            class="px-2 py-1 rounded-lg border text-xs font-semibold hover:bg-gray-50">
                                            Duplicar
                                        </button>

                                        <button type="button"
                                            wire:click="eliminarCuotaManual({{ $i }})"
                                            class="px-2 py-1 rounded-lg border text-xs font-semibold text-red-600 hover:bg-red-50">
                                            Eliminar
                                        </button>
                                    </div>
                                </td>
                                @endif
                            </tr>
                            @empty
                            <tr>
                                <td colspan="{{ $usar_configuracion_manual ? 6 : 5 }}" class="px-4 py-6 text-center text-gray-500">
                                    No hay cuotas generadas todavía.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>

                        @if(!empty($previewCuotas))
                        <tfoot class="bg-gray-50 border-t">
                            <tr>
                                <td colspan="2" class="px-3 py-3 font-black text-right">Total preview</td>
                                <td class="px-3 py-3 font-black">
                                    ${{ number_format($this->totalPreview, 2) }}
                                </td>
                                <td colspan="{{ $usar_configuracion_manual ? 3 : 2 }}"></td>
                            </tr>
                        </tfoot>
                        @endif
                    </table>
                </div>

                <div class="text-xs text-gray-500">
                    @if($usar_configuracion_manual)
                    Se guardará exactamente el calendario que ves en esta tabla.
                    @else
                    Se guardará el calendario generado automáticamente.
                    @endif
                </div>
            </div>
            @endif
        </div>
        @endif
    </div>

    {{-- Footer buttons --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 sm:gap-3 mt-4">
        <button type="button"
            wire:click="back"
            wire:loading.attr="disabled"
            wire:target="guardar"
            class="w-full sm:w-auto px-4 py-2 rounded-xl border bg-white hover:bg-gray-50 font-semibold disabled:opacity-60 disabled:cursor-not-allowed"
            @disabled($step===1)>
            Atrás
        </button>

        @if($step < 3)
            <button type="button"
            wire:click="next"
            wire:loading.attr="disabled"
            wire:target="guardar"
            class="w-full sm:w-auto px-5 py-2 rounded-xl bg-black text-white font-bold disabled:opacity-60 disabled:cursor-not-allowed">
            Siguiente
            </button>
            @else
            <button type="button"
                wire:click="guardar"
                wire:loading.attr="disabled"
                wire:target="guardar"
                class="w-full sm:w-auto px-5 py-2 rounded-xl bg-black text-white font-bold disabled:opacity-70 disabled:cursor-not-allowed flex items-center justify-center gap-2">

                <span wire:loading.remove wire:target="guardar">
                    Guardar contrato
                </span>

                <span wire:loading wire:target="guardar" class="inline-flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor"
                            d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z">
                        </path>
                    </svg>
                    Creando contrato...
                </span>
            </button>
            @endif
    </div>

    <div wire:loading wire:target="guardar" class="text-sm text-gray-600 flex items-center gap-2 mt-2">
        <svg class="animate-spin h-4 w-4 text-gray-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor"
                d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z">
            </path>
        </svg>
        Creando contrato y generando documento...
    </div>

    {{-- LOADING FULLSCREEN --}}
    <div wire:loading.flex
        wire:target="guardar"
        class="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm items-center justify-center">

        <div class="bg-white rounded-2xl shadow-2xl px-6 py-5 flex flex-col items-center gap-3">

            {{-- Spinner --}}
            <svg class="animate-spin h-8 w-8 text-black" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-20" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-80" fill="currentColor"
                    d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z">
                </path>
            </svg>

            {{-- Texto --}}
            <div class="text-center">
                <p class="font-bold text-sm">Creando contrato...</p>
                <p class="text-xs text-gray-500">
                    Generando documento y guardando información
                </p>
            </div>

        </div>
    </div>
</div>