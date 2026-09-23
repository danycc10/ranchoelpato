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
                        Tómale foto con el celular o elige una que ya tengas.
                        Cuida que se lea bien, que salga completa y sin reflejos.
                    </p>

                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        @foreach([
                            ['campo' => 'ine_frente', 'titulo' => 'Frente', 'archivo' => $ine_frente, 'quitar' => 'quitarFrente'],
                            ['campo' => 'ine_reverso', 'titulo' => 'Reverso', 'archivo' => $ine_reverso, 'quitar' => 'quitarReverso'],
                        ] as $lado)
                            <div wire:key="lado-{{ $lado['campo'] }}">
                                <p class="mb-1 text-xs font-bold text-gray-700">{{ $lado['titulo'] }}</p>

                                {{-- Toda la caja es el disparador: en el celular
                                     el input de archivo es muy chico para atinarle. --}}
                                <label class="relative flex aspect-[1.6/1] cursor-pointer items-center justify-center overflow-hidden
                                              rounded-xl border-2 border-dashed border-gray-300 bg-white transition
                                              hover:border-gray-900 hover:bg-gray-50">

                                    <input type="file" class="sr-only"
                                           wire:model="{{ $lado['campo'] }}"
                                           accept="image/*">

                                    {{-- Subiendo --}}
                                    <div wire:loading wire:target="{{ $lado['campo'] }}"
                                         class="absolute inset-0 z-10 flex flex-col items-center justify-center gap-2 bg-white/90">
                                        <svg class="h-6 w-6 animate-spin text-gray-900" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                            <circle class="opacity-20" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"></path>
                                        </svg>
                                        <span class="text-xs font-semibold text-gray-700">Subiendo la foto...</span>
                                    </div>

                                    <div wire:loading.remove wire:target="{{ $lado['campo'] }}" class="h-full w-full">
                                        @php
                                            // Un archivo que no es imagen no se puede previsualizar:
                                            // pedirle la miniatura tumbaría la página.
                                            $sePuedeVer = $lado['archivo']
                                                && method_exists($lado['archivo'], 'isPreviewable')
                                                && $lado['archivo']->isPreviewable();
                                        @endphp

                                        @if($sePuedeVer)
                                            <img src="{{ $lado['archivo']->temporaryUrl() }}"
                                                 alt="Foto del {{ mb_strtolower($lado['titulo']) }} de tu identificación"
                                                 class="h-full w-full object-cover">

                                            <span class="absolute bottom-2 left-2 rounded-lg bg-emerald-600 px-2 py-1 text-[11px] font-bold text-white">
                                                Foto lista · toca para cambiarla
                                            </span>
                                        @elseif($lado['archivo'])
                                            <div class="flex h-full flex-col items-center justify-center gap-2 px-3 text-center">
                                                <svg class="h-8 w-8 text-red-400" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                                                </svg>
                                                <span class="text-xs font-bold text-red-700">Eso no es una foto</span>
                                                <span class="text-[11px] text-gray-600">Toca para elegir una imagen</span>
                                            </div>
                                        @else
                                            <div class="flex h-full flex-col items-center justify-center gap-2 px-3 text-center">
                                                <svg class="h-8 w-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0Z" />
                                                </svg>
                                                <span class="text-xs font-bold text-gray-700">Toca para agregar la foto</span>
                                                <span class="text-[11px] text-gray-500">Cámara o galería</span>
                                            </div>
                                        @endif
                                    </div>
                                </label>

                                @if($lado['archivo'])
                                    <button type="button" wire:click="{{ $lado['quitar'] }}"
                                            class="mt-1 text-xs font-semibold text-red-600 underline">
                                        Quitar foto
                                    </button>
                                @endif

                                @error($lado['campo'])
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        @endforeach
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
