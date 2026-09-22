<div class="mx-auto w-full max-w-2xl p-4 sm:p-6">

    {{-- La liga no sirve: ni se dice de quién era. --}}
    @if(! $solicitud)
        <div class="rounded-2xl border border-gray-200 bg-white p-6 text-center shadow-sm">
            <h1 class="text-xl font-black text-gray-900">Esta liga ya no está disponible</h1>
            <p class="mt-2 text-sm text-gray-600">
                Puede que haya vencido o que ya se haya usado.
                Pide una nueva a la persona que te la compartió.
            </p>
        </div>

    @elseif($enviado)
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-6 text-center">
            <h1 class="text-xl font-black text-emerald-900">¡Listo, gracias!</h1>
            <p class="mt-2 text-sm text-emerald-800">
                Recibimos tus datos. Los vamos a revisar y con ellos preparamos tu contrato.
                Ya puedes cerrar esta página.
            </p>
        </div>

    @else
        <div class="rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-200 px-5 py-4 sm:px-6">
                <h1 class="text-xl font-black text-gray-900">Completa tus datos</h1>
                <p class="mt-1 text-sm text-gray-600">
                    Hola {{ $solicitud->cliente->nombres }}: con esta información preparamos tu contrato.
                    La liga vence el {{ $solicitud->expira_en->translatedFormat('d \d\e F \d\e Y') }}.
                </p>
            </div>

            <form wire:submit="guardar" class="space-y-4 p-5 sm:p-6">

                <div>
                    <label class="text-sm font-bold text-gray-900">
                        {{ $this->etiquetas['nombre_legal'] }} <span class="text-red-600">*</span>
                    </label>
                    <input type="text" wire:model="datos.nombre_legal"
                           class="mt-1 w-full rounded-xl border-gray-300 px-3 py-2.5">
                    @error('datos.nombre_legal') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="text-sm font-bold text-gray-900">
                            {{ $this->etiquetas['telefono'] }} <span class="text-red-600">*</span>
                        </label>
                        <input type="tel" wire:model="datos.telefono"
                               class="mt-1 w-full rounded-xl border-gray-300 px-3 py-2.5">
                        @error('datos.telefono') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="text-sm font-bold text-gray-900">{{ $this->etiquetas['correo'] }}</label>
                        <input type="email" wire:model="datos.correo"
                               class="mt-1 w-full rounded-xl border-gray-300 px-3 py-2.5">
                        @error('datos.correo') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="text-sm font-bold text-gray-900">{{ $this->etiquetas['curp'] }}</label>
                        <input type="text" wire:model="datos.curp"
                               class="mt-1 w-full rounded-xl border-gray-300 px-3 py-2.5 uppercase">
                        @error('datos.curp') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="text-sm font-bold text-gray-900">{{ $this->etiquetas['rfc'] }}</label>
                        <input type="text" wire:model="datos.rfc"
                               class="mt-1 w-full rounded-xl border-gray-300 px-3 py-2.5 uppercase">
                        @error('datos.rfc') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label class="text-sm font-bold text-gray-900">{{ $this->etiquetas['direccion'] }}</label>
                    <input type="text" wire:model="datos.direccion"
                           placeholder="Calle, número, colonia, ciudad y código postal"
                           class="mt-1 w-full rounded-xl border-gray-300 px-3 py-2.5">
                    @error('datos.direccion') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="text-sm font-bold text-gray-900">{{ $this->etiquetas['estado_civil'] }}</label>
                        <select wire:model="datos.estado_civil" class="mt-1 w-full rounded-xl border-gray-300 px-3 py-2.5">
                            <option value="">Selecciona...</option>
                            @foreach($this->estadosCiviles as $opcion)
                                <option value="{{ $opcion }}">{{ $opcion }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="text-sm font-bold text-gray-900">{{ $this->etiquetas['ocupacion'] }}</label>
                        <input type="text" wire:model="datos.ocupacion"
                               class="mt-1 w-full rounded-xl border-gray-300 px-3 py-2.5">
                    </div>

                    <div>
                        <label class="text-sm font-bold text-gray-900">{{ $this->etiquetas['identificacion_tipo'] }}</label>
                        <select wire:model="datos.identificacion_tipo" class="mt-1 w-full rounded-xl border-gray-300 px-3 py-2.5">
                            <option value="">Selecciona...</option>
                            @foreach($this->identificaciones as $opcion)
                                <option value="{{ $opcion }}">{{ $opcion }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="text-sm font-bold text-gray-900">{{ $this->etiquetas['identificacion_numero'] }}</label>
                        <input type="text" wire:model="datos.identificacion_numero"
                               class="mt-1 w-full rounded-xl border-gray-300 px-3 py-2.5">
                    </div>
                </div>

                {{-- Identificación --}}
                <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
                    <p class="text-sm font-bold text-gray-900">Foto de tu identificación</p>
                    <p class="mt-1 text-xs text-gray-600">
                        Puedes tomarle foto con el celular. Cuida que se lea bien y que salga completa.
                    </p>

                    <div class="mt-3 grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="text-xs font-semibold text-gray-700">Frente</label>
                            <input type="file" wire:model="ine_frente" accept="image/*"
                                   class="mt-1 w-full text-sm">
                            <div wire:loading wire:target="ine_frente" class="mt-1 text-xs text-gray-500">Subiendo...</div>
                            @error('ine_frente') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="text-xs font-semibold text-gray-700">Reverso</label>
                            <input type="file" wire:model="ine_reverso" accept="image/*"
                                   class="mt-1 w-full text-sm">
                            <div wire:loading wire:target="ine_reverso" class="mt-1 text-xs text-gray-500">Subiendo...</div>
                            @error('ine_reverso') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                <button type="submit"
                        wire:loading.attr="disabled"
                        wire:target="guardar, ine_frente, ine_reverso"
                        class="w-full rounded-xl bg-black px-5 py-3 font-bold text-white disabled:opacity-50">
                    <span wire:loading.remove wire:target="guardar">Enviar mis datos</span>
                    <span wire:loading wire:target="guardar">Enviando...</span>
                </button>

                <p class="text-center text-xs text-gray-500">
                    Tus datos se usan únicamente para preparar tu contrato.
                </p>
            </form>
        </div>
    @endif
</div>
