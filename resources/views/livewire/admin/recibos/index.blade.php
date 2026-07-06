<div class="max-w-7xl mx-auto p-6 space-y-5">

    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-black">Recibos</h1>
            <p class="text-gray-500 text-sm">Consulta y control de recibos.</p>
        </div>

        <a href="{{ route('admin.recibos.crear') }}"
            class="px-4 py-2 bg-black text-white rounded-xl font-bold">
            + Crear recibo
        </a>
    </div>

    {{-- FILTROS --}}
    <div class="bg-white border rounded-2xl p-5 shadow-sm">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">

            <div class="lg:col-span-2">
                <label class="text-xs text-gray-500 font-semibold">Buscar</label>
                <input
                    type="text"
                    wire:model.live="q"
                    placeholder="Cliente, lote, folio o cuenta"
                    class="mt-1 w-full border rounded-xl px-4 py-3 text-sm">
            </div>

            <div>
                <label class="text-xs text-gray-500 font-semibold">Propietario</label>
                <select
                    wire:model.live="propietario_id"
                    class="mt-1 w-full border rounded-xl px-3 py-3 text-sm">
                    <option value="">Todos</option>
                    @foreach($propietarios as $p)
                    <option value="{{ $p->id }}">{{ $p->nombre }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="text-xs text-gray-500 font-semibold">Mes</label>
                <input
                    type="month"
                    wire:model.live="mes"
                    class="mt-1 w-full border rounded-xl px-3 py-3 text-sm">
            </div>

            <div>
                <label class="text-xs text-gray-500 font-semibold">Desde</label>
                <input
                    type="date"
                    wire:model.live="desde"
                    class="mt-1 w-full border rounded-xl px-3 py-3 text-sm">
            </div>

            <div>
                <label class="text-xs text-gray-500 font-semibold">Hasta</label>
                <input
                    type="date"
                    wire:model.live="hasta"
                    class="mt-1 w-full border rounded-xl px-3 py-3 text-sm">
            </div>

            <div>
                <label class="text-xs text-gray-500 font-semibold">Tipo cobro</label>
                <select
                    wire:model.live="tipo_cobro_id"
                    class="mt-1 w-full border rounded-xl px-3 py-3 text-sm">
                    <option value="">Todos</option>
                    @foreach($tiposCobro as $t)
                    <option value="{{ $t->id }}">{{ $t->nombre }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="text-xs text-gray-500 font-semibold">Forma pago</label>
                <select
                    wire:model.live="forma_pago_id"
                    class="mt-1 w-full border rounded-xl px-3 py-3 text-sm">
                    <option value="">Todas</option>
                    @foreach($formasPago as $f)
                    <option value="{{ $f->id }}">{{ $f->nombre }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="text-xs text-gray-500 font-semibold">Cuenta bancaria</label>
                <select
                    wire:model.live="cuenta_id"
                    class="mt-1 w-full border rounded-xl px-3 py-3 text-sm">
                    <option value="">Todas</option>
                    @foreach($cuentas as $c)
                    <option value="{{ $c->id }}">{{ $c->alias }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="text-xs text-gray-500 font-semibold">Mostrar</label>
                <select
                    wire:model.live="verEliminados"
                    class="mt-1 w-full border rounded-xl px-3 py-3 text-sm">
                    <option value="activos">Solo activos</option>
                    <option value="eliminados">Solo eliminados</option>
                    <option value="todos">Todos</option>
                </select>
            </div>

            <div>
                <label class="text-xs text-gray-500 font-semibold">Validación</label>
                <select
                    wire:model.live="validacion"
                    class="mt-1 w-full border rounded-xl px-3 py-3 text-sm">
                    <option value="">Todos</option>
                    <option value="validado">Validados</option>
                    <option value="sin_validar">Sin validar</option>
                </select>
            </div>

        </div>

        <div class="mt-4 flex items-center justify-between gap-3">
            <button
                wire:click="exportExcel"
                wire:loading.attr="disabled"
                wire:target="exportExcel"
                class="px-5 py-2 border rounded-xl font-semibold hover:bg-gray-50 disabled:opacity-50 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                </svg>
                Exportar Excel
            </button>

            <button
                wire:click="limpiarFiltros"
                class="px-5 py-2 border rounded-xl font-semibold hover:bg-gray-50">
                Limpiar filtros
            </button>
        </div>
    </div>

    {{-- TABLA --}}
    <div class="bg-white border rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-[1500px] w-full text-sm table-auto">
                <thead class="bg-gray-100 text-xs uppercase text-gray-600">
                    <tr>
                        <th class="p-2.5 text-left w-[150px] whitespace-nowrap">Folio</th>
                        <th class="p-2.5 text-left w-[240px] whitespace-nowrap">Nombre</th>
                        <th class="p-2.5 text-left w-[90px] whitespace-nowrap">Lote</th>
                        <th class="p-2.5 text-left w-[220px] whitespace-nowrap">Fraccionamiento</th>
                        <th class="p-2.5 text-left w-[80px] whitespace-nowrap">Cuota</th>
                        <th class="p-2.5 text-left w-[120px] whitespace-nowrap">Fecha</th>
                        <th class="p-2.5 text-left w-[120px] whitespace-nowrap">Monto</th>
                        <th class="p-2.5 text-left w-[150px] whitespace-nowrap">Tipo cobro</th>
                        <th class="p-2.5 text-left w-[220px] whitespace-nowrap">Pagos</th>
                        <th class="p-2.5 text-left w-[90px] whitespace-nowrap">Ev.</th>
                        <th class="p-2.5 text-left w-[110px] whitespace-nowrap">Val.</th>
                        <th class="p-2.5 text-left w-[90px] whitespace-nowrap">Firma</th>
                        <th class="p-2.5 text-left w-[90px] whitespace-nowrap">Estado</th>
                        <th class="p-2.5 text-center w-[150px] whitespace-nowrap">Acc.</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($recibos as $recibo)
                    @php
                    $pagos = $recibo->pagosDetalle ?? collect();
                    $primerPago = $pagos->first();
                    $pagosConEv = $pagos->filter(fn($p) => (bool)($p->formaPago?->requiere_cuenta) && !empty($p->evidencia_path));
                    $totalConEv = $pagosConEv->count();
                    $validadosRow = $pagosConEv->filter(fn($p) => $p->validado_at)->count();
                    if ($recibo->trashed()) {
                        $rowClass = 'bg-red-50/40';
                    } elseif ($totalConEv === 0) {
                        $rowClass = '';
                    } elseif ($validadosRow === $totalConEv) {
                        $rowClass = 'bg-emerald-50/60';
                    } else {
                        $rowClass = 'bg-amber-50/60';
                    }
                    @endphp

                    <tr class="border-t hover:brightness-95 {{ $rowClass }}">
                        <td class="p-2.5 font-semibold align-middle">
                            {{ $recibo->folio }}
                        </td>

                        <td class="p-2.5 align-middle">
                            {{ $recibo->cliente?->nombre_completo }}
                        </td>

                        <td class="p-2.5 align-middle">
                            {{ $recibo->lote?->lote }}
                        </td>

                        <td class="p-2.5 align-middle">
                            {{ $recibo->lote?->fraccionamiento?->nombre }}
                        </td>

                        <td class="p-2.5 align-middle">
                            #{{ $recibo->cuota?->numero ?? '-' }}
                        </td>

                        <td class="p-2.5 align-middle">
                            {{ \Carbon\Carbon::parse($recibo->fecha)->format('d/m/Y') }}
                        </td>

                        <td class="p-2.5 font-bold align-middle whitespace-nowrap">
                            ${{ number_format($recibo->monto, 2) }}
                        </td>

                        <td class="p-2.5 align-middle">
                            <span class="inline-block px-2 py-1 text-[11px] rounded-full bg-blue-100 text-blue-700 font-semibold leading-none">
                                {{ $recibo->tipoCobro?->nombre }}
                            </span>
                        </td>

                        <td class="p-2.5 align-middle">
                            @if($pagos->count() === 0)
                            <span class="text-xs text-gray-400">Sin pagos relacionados</span>
                            @elseif($pagos->count() === 1)
                            <div class="space-y-1">
                                <div>
                                    <span class="inline-block px-2 py-1 text-[11px] rounded-full bg-gray-100 text-gray-700 leading-none">
                                        {{ $primerPago->formaPago?->nombre ?? '—' }}
                                    </span>
                                </div>

                                <div class="text-xs text-gray-500">
                                    {{ $primerPago->cuentaBancaria?->alias ?? 'Sin cuenta' }}
                                </div>

                                <div class="text-xs font-semibold text-gray-700">
                                    ${{ number_format((float) ($primerPago->monto ?? 0), 2) }}
                                </div>
                            </div>
                            @else
                            <div class="space-y-1">
                                <div class="text-xs font-semibold text-gray-700">
                                    {{ $pagos->count() }} movimientos
                                </div>

                                @foreach($pagos->take(2) as $pago)
                                <div class="text-xs text-gray-500 leading-tight">
                                    {{ $pago->formaPago?->nombre ?? '—' }}
                                    —
                                    {{ $pago->cuentaBancaria?->alias ?? 'Sin cuenta' }}
                                    —
                                    ${{ number_format((float) ($pago->monto ?? 0), 2) }}
                                </div>
                                @endforeach

                                @if($pagos->count() > 2)
                                <div class="text-[11px] text-gray-400">
                                    + {{ $pagos->count() - 2 }} más
                                </div>
                                @endif
                            </div>
                            @endif
                        </td>

                        <td class="p-2.5 align-middle">
                            @if($pagos->count() === 0)
                            <span class="text-xs text-gray-400">—</span>

                            @elseif($pagos->count() === 1)
                            @php
                            $pago = $primerPago;
                            $requiereEvidencia = (bool) ($pago->formaPago?->requiere_cuenta);
                            $tieneEvidencia = !empty($pago->evidencia_path);
                            @endphp

                            @if(!$requiereEvidencia)
                            <span class="text-xs text-gray-400">—</span>
                            @else
                            @if($tieneEvidencia)
                            <div class="relative inline-block">
                                <button
                                    type="button"
                                    wire:click="abrirModalEvidencia({{ $pago->id }})"
                                    class="block"
                                    title="{{ $pago->validado_at ? 'Validado · Ver evidencia' : 'No validado · Ver evidencia' }}">
                                    @if($pago->evidencia_mime === 'application/pdf')
                                    <div class="inline-flex h-9 w-9 items-center justify-center rounded-lg border bg-red-50 text-red-700 hover:opacity-90">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 21h-15A2.25 2.25 0 0 1 2.25 18.75V5.25A2.25 2.25 0 0 1 4.5 3h9.879a2.25 2.25 0 0 1 1.591.659l3.371 3.371A2.25 2.25 0 0 1 20 8.621v10.129A2.25 2.25 0 0 1 17.75 21Z" />
                                        </svg>
                                    </div>
                                    @else
                                    <img
                                        src="{{ route('admin.recibo-pagos.evidencia.show', $pago->id) }}?v={{ urlencode($pago->evidencia_path) }}"
                                        alt="Evidencia"
                                        class="h-10 w-10 rounded-lg object-cover border hover:opacity-90">
                                    @endif
                                </button>
                                <span class="absolute -top-1 -right-1 h-3 w-3 rounded-full border-2 border-white {{ $pago->validado_at ? 'bg-emerald-500' : 'bg-amber-400' }}"></span>
                            </div>
                            @else
                            <button
                                type="button"
                                wire:click="abrirModalEvidencia({{ $pago->id }})"
                                title="Subir evidencia"
                                class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-300 bg-white text-gray-600 hover:bg-gray-50">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m18.375 12.739-7.693 7.693a3 3 0 1 1-4.243-4.243l8.754-8.754a2 2 0 1 1 2.829 2.829l-8.047 8.046a1 1 0 0 1-1.414-1.414l7.34-7.339" />
                                </svg>
                            </button>
                            @endif
                            @endif

                            @else
                            <div class="flex flex-wrap gap-1">
                                @foreach($pagos->take(4) as $pago)
                                @php
                                $requiereEvidencia = (bool) ($pago->formaPago?->requiere_cuenta);
                                $tieneEvidencia = !empty($pago->evidencia_path);
                                @endphp

                                @if(!$requiereEvidencia)
                                <span
                                    title="{{ $pago->formaPago?->nombre ?? 'Pago' }} · No requiere evidencia"
                                    class="inline-flex h-8 min-w-8 px-2 items-center justify-center rounded-lg bg-gray-100 text-gray-400 text-[10px] font-bold">
                                    —
                                </span>
                                @elseif($tieneEvidencia)
                                <div class="relative inline-block">
                                    <button
                                        type="button"
                                        wire:click="abrirModalEvidencia({{ $pago->id }})"
                                        title="{{ $pago->formaPago?->nombre ?? 'Pago' }} · {{ $pago->validado_at ? 'Validado' : 'No validado' }} · Ver evidencia"
                                        class="inline-flex h-8 w-8 items-center justify-center rounded-lg border bg-white text-emerald-600 hover:bg-gray-50 overflow-hidden">
                                        @if($pago->evidencia_mime === 'application/pdf')
                                        <div class="h-14 w-14 rounded-lg border bg-red-50 text-red-700 flex items-center justify-center text-[10px] font-bold hover:opacity-90">
                                            PDF
                                        </div>
                                        @else
                                        <img
                                            src="{{ route('admin.recibo-pagos.evidencia.show', $pago->id) }}?v={{ urlencode($pago->evidencia_path) }}"
                                            alt="Evidencia"
                                            class="h-8 w-8 object-cover">
                                        @endif
                                    </button>
                                    <span class="absolute -top-1 -right-1 h-2.5 w-2.5 rounded-full border-2 border-white {{ $pago->validado_at ? 'bg-emerald-500' : 'bg-amber-400' }}"></span>
                                </div>
                                @else
                                <button
                                    type="button"
                                    wire:click="abrirModalEvidencia({{ $pago->id }})"
                                    title="{{ $pago->formaPago?->nombre ?? 'Pago' }} · Subir evidencia"
                                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg border bg-white text-gray-600 hover:bg-gray-50">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m18.375 12.739-7.693 7.693a3 3 0 1 1-4.243-4.243l8.754-8.754a2 2 0 1 1 2.829 2.829l-8.047 8.046a1 1 0 0 1-1.414-1.414l7.34-7.339" />
                                    </svg>
                                </button>
                                @endif
                                @endforeach

                                @if($pagos->count() > 4)
                                <span class="inline-flex h-8 min-w-8 px-2 items-center justify-center rounded-lg bg-gray-100 text-gray-500 text-[10px] font-bold">
                                    +{{ $pagos->count() - 4 }}
                                </span>
                                @endif
                            </div>
                            @endif
                        </td>

                        <td class="p-2.5 align-middle">
                            @php
                            $pagosConEvidencia = $pagos->filter(fn($p) => (bool)($p->formaPago?->requiere_cuenta) && !empty($p->evidencia_path));
                            $totalConEv = $pagosConEvidencia->count();
                            $validados = $pagosConEvidencia->filter(fn($p) => $p->validado_at)->count();
                            @endphp
                            @if($totalConEv === 0)
                            <span class="text-xs text-gray-400">—</span>
                            @elseif($validados === $totalConEv)
                            <span class="inline-flex items-center gap-1 px-2 py-1 text-[11px] font-bold rounded-full bg-emerald-100 text-emerald-700">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                Validado
                            </span>
                            @elseif($validados === 0)
                            <span class="inline-flex items-center gap-1 px-2 py-1 text-[11px] font-bold rounded-full bg-amber-100 text-amber-700">
                                <span class="h-1.5 w-1.5 rounded-full bg-amber-400"></span>
                                Sin validar
                            </span>
                            @else
                            <span class="inline-flex items-center gap-1 px-2 py-1 text-[11px] font-bold rounded-full bg-yellow-100 text-yellow-700">
                                <span class="h-1.5 w-1.5 rounded-full bg-yellow-400"></span>
                                {{ $validados }}/{{ $totalConEv }}
                            </span>
                            @endif
                        </td>

                        <td class="p-2.5 align-middle">
                            @if($recibo->firma_path)
                            <button
                                type="button"
                                wire:click="abrirModalFirma({{ $recibo->id }})"
                                title="Firma guardada"
                                class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700 hover:bg-emerald-200">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m6 2.25a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                            </button>
                            @else
                            @if($recibo->trashed())
                            <span
                                class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-gray-100 text-gray-400">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 6 6 18M6 6l12 12" />
                                </svg>
                            </span>
                            @else
                            <button
                                type="button"
                                wire:click="abrirModalFirma({{ $recibo->id }})"
                                title="Firma pendiente"
                                class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-amber-100 text-amber-700 hover:bg-amber-200">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                            </button>
                            @endif
                            @endif
                        </td>

                        <td class="p-2.5 align-middle">
                            @if($recibo->trashed())
                            <span class="inline-flex items-center rounded-full px-2 py-1 text-[11px] font-bold bg-red-100 text-red-700">
                                Baja
                            </span>
                            @else
                            <span class="inline-flex items-center rounded-full px-2 py-1 text-[11px] font-bold bg-green-100 text-green-700">
                                OK
                            </span>
                            @endif
                        </td>

                        <td class="p-2.5 text-center align-middle">
                            <div class="flex items-center justify-center gap-2">
                                @if(!$recibo->trashed())
                                <a href="{{ route('admin.recibos.imprimir', $recibo->uuid) }}"
                                    target="_blank"
                                    title="Ver recibo"
                                    class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7Z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 15.5A3.5 3.5 0 1 0 12 8.5a3.5 3.5 0 0 0 0 7Z" />
                                    </svg>
                                </a>

                                <a href="{{ route('admin.recibos.edit', $recibo->uuid) }}"
                                    title="Editar recibo"
                                    class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-amber-500 text-white hover:bg-amber-600">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 3.487 1.65-1.65a2.25 2.25 0 1 1 3.182 3.182l-1.65 1.65M16.862 3.487 6.75 13.599a4.5 4.5 0 0 0-1.13 1.869l-1.09 3.274a.75.75 0 0 0 .949.949l3.274-1.09a4.5 4.5 0 0 0 1.869-1.13L20.735 7.36M16.862 3.487l3.873 3.873" />
                                    </svg>
                                </a>

                                <a href="{{ route('admin.recibos.pdf', $recibo->uuid) }}"
                                    target="_blank"
                                    title="Descargar PDF"
                                    class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-black text-white hover:bg-gray-800">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v2.625A2.625 2.625 0 0 1 16.875 19.5H7.125A2.625 2.625 0 0 1 4.5 16.875V14.25" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v10.5m0 0 3.75-3.75M12 13.5 8.25 9.75" />
                                    </svg>
                                </a>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="13" class="p-6 text-center text-gray-500">
                            No hay recibos registrados.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4">
            {{ $recibos->links() }}
        </div>
    </div>

    {{-- MODAL EVIDENCIA --}}
    @if($modalEvidenciaOpen)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div class="bg-white w-full max-w-3xl rounded-2xl shadow-xl overflow-hidden">
            <div class="px-5 py-4 border-b flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-black">Evidencia del pago</h2>
                    <p class="text-sm text-gray-500">
                        Recibo: {{ $reciboEvidenciaFolio }}
                        @if($reciboEvidenciaFormaPago)
                        · {{ $reciboEvidenciaFormaPago }}
                        @endif
                        @if($reciboEvidenciaCuenta)
                        · {{ $reciboEvidenciaCuenta }}
                        @endif
                    </p>
                </div>

                <button
                    type="button"
                    wire:click="cerrarModalEvidencia"
                    class="px-3 py-1 border rounded-lg hover:bg-gray-50">
                    Cerrar
                </button>
            </div>

            <div class="p-5 space-y-5">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                    <div>
                        <div class="text-sm font-bold mb-2">Actual</div>

                        <div class="rounded-2xl border bg-gray-50 min-h-[280px] flex items-center justify-center overflow-hidden">
                            @if($reciboEvidenciaPath)
                            @php
                            $evidenciaUrl = route('admin.recibo-pagos.evidencia.show', $reciboPagoEvidenciaId) . '?v=' . urlencode($reciboEvidenciaPath);
                            $esPdfActual = strtolower(pathinfo((string) $reciboEvidenciaPath, PATHINFO_EXTENSION)) === 'pdf';
                            @endphp

                            @if($esPdfActual)
                            <div class="text-center px-4">
                                <div class="text-sm font-bold text-red-700">Archivo PDF</div>
                                <div class="text-xs text-gray-500 mt-1">La evidencia actual está guardada como PDF.</div>

                                <a
                                    href="{{ $evidenciaUrl }}"
                                    target="_blank"
                                    class="inline-flex mt-3 px-4 py-2 rounded-xl border font-semibold hover:bg-gray-50">
                                    Ver PDF
                                </a>
                            </div>
                            @else
                            <a href="{{ $evidenciaUrl }}" target="_blank">
                                <img
                                    wire:key="evidencia-modal-{{ md5((string) $reciboEvidenciaPath) }}"
                                    src="{{ $evidenciaUrl }}"
                                    alt="Evidencia actual"
                                    class="max-h-[420px] w-auto object-contain">
                            </a>
                            @endif
                            @else
                            <div class="text-sm text-gray-400">Este pago no tiene evidencia</div>
                            @endif
                        </div>
                    </div>

                    <div>
                        <div class="text-sm font-bold mb-2">Nueva evidencia</div>

                        @if($reciboEvidenciaEditable)
                        <input
                            type="file"
                            accept="image/jpeg,image/png,image/webp,application/pdf,.pdf"
                            wire:model="nuevaEvidencia"
                            class="w-full rounded-xl border p-2">

                        @error('nuevaEvidencia')
                        <div class="text-sm text-red-600 mt-1">{{ $message }}</div>
                        @enderror

                        <div wire:loading wire:target="nuevaEvidencia" class="text-sm text-gray-500 mt-2">
                            Procesando archivo...
                        </div>

                        <div class="mt-3 rounded-2xl border bg-gray-50 min-h-[220px] flex items-center justify-center overflow-hidden">
                            @if($nuevaEvidenciaPreviewUrl)
                            <img
                                src="{{ $nuevaEvidenciaPreviewUrl }}"
                                alt="Vista previa nueva"
                                class="max-h-[320px] w-auto object-contain">
                            @elseif($nuevaEvidencia && strtolower($nuevaEvidencia->getClientOriginalExtension()) === 'pdf')
                            <div class="text-sm text-gray-500 px-4 text-center">
                                PDF cargado correctamente.
                                <div class="text-xs text-gray-400 mt-1">
                                    Se guardará como PDF original.
                                </div>
                            </div>
                            @else
                            <div class="text-sm text-gray-400 px-4 text-center">
                                Selecciona una imagen o PDF para reemplazar o subir.
                            </div>
                            @endif
                        </div>

                        <div class="text-xs text-gray-400 mt-2">
                            Puedes subir JPG, PNG, WEBP o PDF.
                        </div>

                        <div class="text-xs text-gray-400 mt-2">
                            La imagen se optimiza automáticamente para ahorrar espacio.
                        </div>
                        @else
                        <div class="rounded-2xl border bg-gray-50 min-h-[220px] flex items-center justify-center px-4 text-center text-sm text-gray-400">
                            Este pago no admite evidencia por su forma de pago.
                        </div>
                        @endif
                    </div>
                </div>

                {{-- Validación --}}
                @if($reciboEvidenciaPath)
                <div class="flex items-center gap-3 p-3 rounded-xl {{ $reciboEvidenciaValidado ? 'bg-emerald-50 border border-emerald-200' : 'bg-amber-50 border border-amber-200' }}">
                    <span class="h-2.5 w-2.5 rounded-full flex-shrink-0 {{ $reciboEvidenciaValidado ? 'bg-emerald-500' : 'bg-amber-400' }}"></span>
                    <div class="flex-1 min-w-0">
                        @if($reciboEvidenciaValidado)
                        <p class="text-sm font-semibold text-emerald-800">Validado</p>
                        <p class="text-xs text-emerald-600">{{ $reciboEvidenciaValidadoAt }}{{ $reciboEvidenciaValidadoPor ? ' · ' . $reciboEvidenciaValidadoPor : '' }}</p>
                        @else
                        <p class="text-sm font-semibold text-amber-800">No validado</p>
                        <p class="text-xs text-amber-600">Pendiente de cotejar con movimientos bancarios</p>
                        @endif
                    </div>
                    @if($reciboEvidenciaValidado)
                    <button
                        type="button"
                        wire:click="desvalidarPago"
                        wire:loading.attr="disabled"
                        wire:target="desvalidarPago"
                        class="text-xs text-emerald-700 hover:text-red-600 underline whitespace-nowrap">
                        Quitar validación
                    </button>
                    @else
                    <button
                        type="button"
                        wire:click="validarPago"
                        wire:loading.attr="disabled"
                        wire:target="validarPago"
                        class="px-3 py-1.5 rounded-lg bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700 disabled:opacity-50 whitespace-nowrap">
                        Marcar validado
                    </button>
                    @endif
                </div>
                @endif

                <div class="flex flex-col sm:flex-row gap-2 sm:justify-end">
                    @if($reciboEvidenciaEditable && $reciboEvidenciaPath)
                    <button
                        type="button"
                        wire:click="eliminarEvidencia"
                        wire:loading.attr="disabled"
                        wire:target="eliminarEvidencia"
                        class="px-4 py-2 rounded-xl border border-red-300 text-red-700 hover:bg-red-50">
                        Eliminar evidencia
                    </button>
                    @endif

                    @if($reciboEvidenciaEditable)
                    <button
                        type="button"
                        wire:click="reemplazarEvidencia"
                        wire:loading.attr="disabled"
                        wire:target="reemplazarEvidencia,nuevaEvidencia"
                        class="px-4 py-2 rounded-xl bg-black text-white font-semibold disabled:opacity-50">
                        @if($reciboEvidenciaPath)
                        Reemplazar evidencia
                        @else
                        Subir evidencia
                        @endif
                    </button>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- MODAL FIRMA --}}
    @if($modalFirmaOpen)
    <div class="fixed inset-0 z-[60] bg-black/60">

        <div class="w-full h-full sm:flex sm:items-center sm:justify-center">

            <div class="
                    bg-white w-full h-full
                    sm:h-auto sm:max-h-[95vh]
                    sm:max-w-4xl
                    sm:rounded-2xl sm:shadow-xl
                    flex flex-col
                ">

                <div class="px-3 sm:px-5 py-3 border-b flex items-center justify-between">
                    <div class="min-w-0">
                        <h2 class="text-base sm:text-lg font-black">Firma de recibido</h2>
                        <p class="text-xs sm:text-sm text-gray-500 truncate">
                            {{ $reciboFirmaFolio }}
                        </p>
                    </div>

                    <button
                        type="button"
                        wire:click="cerrarModalFirma"
                        class="px-3 py-1 border rounded-lg hover:bg-gray-50 text-sm">
                        Cerrar
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto p-3 sm:p-5 space-y-4">

                    @if($reciboFirmaPath)
                    <div>
                        <div class="text-sm font-bold mb-2">Firma guardada</div>

                        <div class="rounded-2xl border bg-gray-50 flex items-center justify-center overflow-hidden p-2 min-h-[180px] sm:min-h-[280px]">
                            <a href="{{ route('admin.recibos.firma.show', $reciboFirmaUuid) }}" target="_blank">
                                <img
                                    src="{{ route('admin.recibos.firma.show', $reciboFirmaUuid) }}"
                                    alt="Firma guardada"
                                    class="max-h-[60vh] sm:max-h-[320px] w-auto object-contain">
                            </a>
                        </div>
                    </div>
                    @else
                    <div>
                        <div class="text-sm font-bold mb-2">
                            Solicite al cliente firmar dentro del recuadro
                        </div>

                        <div class="text-xs text-amber-600 font-medium mb-2">
                            En celular horizontal, mantenga el teléfono fijo mientras firma.
                        </div>

                        <div wire:ignore class="rounded-2xl border bg-gray-50 p-2 sm:p-3">
                            <canvas
                                id="signature-pad"
                                class="block w-full rounded-xl bg-white border"
                                style="
                                            width: 100%;
                                            height: min(55vh, 320px);
                                            min-height: 180px;
                                            touch-action: none;
                                            display: block;
                                        ">
                            </canvas>
                        </div>

                        @error('firmaData')
                        <div class="text-sm text-red-600 mt-2">
                            {{ $message }}
                        </div>
                        @enderror

                        <div class="text-xs text-gray-400 mt-2">
                            Compatible con mouse y pantalla táctil.
                        </div>
                    </div>

                    <div class="flex flex-col sm:flex-row gap-2 sm:justify-end">
                        <button
                            type="button"
                            id="btn-clear-signature"
                            class="w-full sm:w-auto px-4 py-2 rounded-xl border hover:bg-gray-50">
                            Limpiar
                        </button>

                        <button
                            type="button"
                            id="btn-save-signature"
                            class="w-full sm:w-auto px-4 py-2 rounded-xl bg-black text-white font-semibold">
                            Guardar firma
                        </button>
                    </div>
                    @endif

                </div>
            </div>
        </div>
    </div>
    @endif

    @if($modalFirmaOpen && !$reciboFirmaPath)
    <script>
        setTimeout(() => {
            if (window.initReciboSignaturePad) {
                window.initReciboSignaturePad();
            }
        }, 250);
    </script>
    @endif

</div>

@push('scripts')
<script>
    (() => {
        let canvas = null;
        let ctx = null;
        let drawing = false;
        let hasContent = false;
        let activePointerId = null;
        let lastPoint = null;

        function getCanvas() {
            return document.getElementById('signature-pad');
        }

        function setupCanvas() {
            canvas = getCanvas();
            if (!canvas) return false;

            const rect = canvas.getBoundingClientRect();
            if (!rect.width || !rect.height) return false;

            const ratio = 1.15;

            canvas.width = Math.floor(rect.width * ratio);
            canvas.height = Math.floor(rect.height * ratio);

            ctx = canvas.getContext('2d');
            if (!ctx) return false;

            ctx.setTransform(1, 0, 0, 1, 0, 0);
            ctx.scale(ratio, ratio);

            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, rect.width, rect.height);

            ctx.lineWidth = 2;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            ctx.strokeStyle = '#111827';

            drawing = false;
            activePointerId = null;
            lastPoint = null;

            return true;
        }

        function getPoint(e) {
            const rect = canvas.getBoundingClientRect();
            return {
                x: e.clientX - rect.left,
                y: e.clientY - rect.top,
            };
        }

        function isInsideCanvas(point) {
            if (!canvas) return false;
            const rect = canvas.getBoundingClientRect();
            return point.x >= 0 && point.y >= 0 && point.x <= rect.width && point.y <= rect.height;
        }

        function pointerDown(e) {
            if (!ctx || !canvas) return;

            if (activePointerId !== null) return;

            e.preventDefault();

            const p = getPoint(e);
            if (!isInsideCanvas(p)) return;

            drawing = true;
            activePointerId = e.pointerId;
            lastPoint = p;

            ctx.beginPath();
            ctx.moveTo(p.x, p.y);

            if (canvas.setPointerCapture) {
                try {
                    canvas.setPointerCapture(e.pointerId);
                } catch (_) {}
            }
        }

        function pointerMove(e) {
            if (!drawing || !ctx || activePointerId !== e.pointerId) return;

            e.preventDefault();

            const p = getPoint(e);
            if (!isInsideCanvas(p)) {
                pointerUp(e);
                return;
            }

            if (lastPoint) {
                const dx = p.x - lastPoint.x;
                const dy = p.y - lastPoint.y;
                const distance = Math.sqrt(dx * dx + dy * dy);

                if (distance > 80) {
                    drawing = false;
                    activePointerId = null;
                    lastPoint = null;
                    return;
                }
            }

            ctx.lineTo(p.x, p.y);
            ctx.stroke();

            hasContent = true;
            lastPoint = p;
        }

        function pointerUp(e) {
            if (activePointerId !== null && e.pointerId !== undefined && activePointerId !== e.pointerId) {
                return;
            }

            drawing = false;
            activePointerId = null;
            lastPoint = null;

            if (canvas && e.pointerId !== undefined && canvas.releasePointerCapture) {
                try {
                    canvas.releasePointerCapture(e.pointerId);
                } catch (_) {}
            }
        }

        function hardStopDrawing() {
            drawing = false;
            activePointerId = null;
            lastPoint = null;
        }

        function clearSignature() {
            if (!canvas || !ctx) return;

            const rect = canvas.getBoundingClientRect();
            ctx.clearRect(0, 0, rect.width, rect.height);
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, rect.width, rect.height);

            hasContent = false;
            hardStopDrawing();
        }

        function bindCanvasEvents() {
            if (!canvas) return;

            canvas.onpointerdown = null;
            canvas.onpointermove = null;
            canvas.onpointerup = null;
            canvas.onpointerleave = null;
            canvas.onpointercancel = null;

            canvas.onpointerdown = pointerDown;
            canvas.onpointermove = pointerMove;
            canvas.onpointerup = pointerUp;
            canvas.onpointerleave = pointerUp;
            canvas.onpointercancel = pointerUp;
        }

        function bindButtons() {
            const clearBtn = document.getElementById('btn-clear-signature');
            const saveBtn = document.getElementById('btn-save-signature');

            if (clearBtn) {
                clearBtn.onclick = () => clearSignature();
            }

            if (saveBtn) {
                saveBtn.onclick = () => {
                    if (!canvas || !ctx || !hasContent) {
                        window.dispatchEvent(new CustomEvent('toast', {
                            detail: {
                                type: 'warning',
                                message: 'Primero capture la firma.'
                            }
                        }));
                        return;
                    }

                    const dataUrl = canvas.toDataURL('image/png');

                    @this.set('firmaData', dataUrl);
                    @this.call('guardarFirma');
                };
            }
        }

        function initWithRetry(attempt = 0) {
            if (attempt > 15) return;

            const ok = setupCanvas();

            if (!ok) {
                setTimeout(() => initWithRetry(attempt + 1), 120);
                return;
            }

            bindCanvasEvents();
            bindButtons();
        }

        window.addEventListener('orientationchange', () => {
            hardStopDrawing();
        });

        window.addEventListener('resize', () => {
            hardStopDrawing();
        });

        window.addEventListener('blur', () => {
            hardStopDrawing();
        });

        window.initReciboSignaturePad = function() {
            initWithRetry();
        };
    })();
</script>
@endpush
