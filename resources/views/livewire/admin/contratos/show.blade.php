@php
    $contratoCancelado = strtolower((string) ($contrato->estatus ?? '')) === 'cancelado';
    $documentosContrato = $documentosContrato ?? [];
    $documentosCollection = collect($documentosContrato);
    $documentosScanCargados = $documentosCollection
        ->filter(fn($documento) => filled($contrato->{$documento['scan_field']} ?? null))
        ->count();
    $documentosTotal = $documentosCollection->count();
    $documentoSeleccionado = $documentosContrato[$documentoTipoSeleccionado] ?? ($documentosCollection->first() ?: null);
    $scanPathSeleccionado = $documentoSeleccionado
        ? ($contrato->{$documentoSeleccionado['scan_field']} ?? null)
        : null;
    $scanUrlSeleccionado = ($documentoSeleccionado && $scanPathSeleccionado)
        ? route('admin.private.contratos.documentos.scan', [
            'uuid' => $contrato->uuid,
            'tipo' => $documentoSeleccionado['key'],
        ]) . '?v=' . urlencode((string) $scanPathSeleccionado)
        : null;
@endphp

<div class="max-w-6xl mx-auto p-4">
    <div class="flex items-center justify-between mb-4">
        <div>
            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl font-black">Contrato {{ $contrato->folio_contrato }}</h1>

                @if($contratoCancelado)
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold bg-red-100 text-red-800 border border-red-200">
                        Cancelado
                    </span>
                @else
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                        {{ ucfirst($contrato->estatus) }}
                    </span>
                @endif
            </div>

            <p class="text-gray-500 text-sm">Detalle del contrato y cuotas.</p>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
            {{-- DOCUMENTOS --}}
            <button type="button"
                @unless($contratoCancelado) wire:click="abrirModalContrato" @endunless
                @disabled($contratoCancelado)
                class="inline-flex items-center gap-2 px-3 py-2 rounded-lg transition relative font-bold {{ $contratoCancelado ? 'bg-gray-300 text-gray-500 cursor-not-allowed opacity-60' : 'bg-blue-600 text-white hover:bg-blue-700' }}"
                title="{{ $contratoCancelado ? 'Contrato cancelado' : 'Documentos del contrato' }}">

                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M6 2h8l4 4v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2z" />
                    <path d="M14 2v4h4" fill="white" opacity="0.35" />
                </svg>

                <span>Documentos</span>

                <span class="ml-1 rounded-full bg-white/20 px-2 py-0.5 text-xs">
                    {{ $documentosScanCargados }}/{{ $documentosTotal }}
                </span>
            </button>

            @if($contratoCancelado)
                <button
                    type="button"
                    disabled
                    class="px-4 py-2 rounded-xl bg-gray-300 text-gray-500 font-bold cursor-not-allowed opacity-60">
                    PDF
                </button>
            @else
                <a href="{{ route('admin.contratos.pdf', $contrato) }}"
                    target="_blank"
                    class="px-4 py-2 rounded-xl bg-black text-white font-bold">
                    PDF
                </a>
            @endif

            @can('contratos.editar')
                <button
                    @unless($contratoCancelado) wire:click="abrirAbonoCapital" @endunless
                    type="button"
                    @disabled($contratoCancelado)
                    class="px-4 py-2 rounded-xl border font-bold {{ $contratoCancelado ? 'bg-gray-100 text-gray-400 cursor-not-allowed opacity-60' : 'border-emerald-400 text-emerald-700 hover:bg-emerald-50' }}">
                    Abono a capital
                </button>
            @endcan

            @can('sistema.ver')
                <button
                    @unless($contratoCancelado) wire:click="abrirReprogramar" @endunless
                    type="button"
                    @disabled($contratoCancelado)
                    class="px-4 py-2 rounded-xl border font-bold {{ $contratoCancelado ? 'bg-gray-100 text-gray-400 cursor-not-allowed opacity-60' : '' }}">
                    Reprogramar calendario
                </button>
            @endcan

            @can('contratos.editar')
                <a href="{{ route('admin.contratos.edit', $contrato->uuid) }}"
                    class="px-4 py-2 rounded-xl font-bold {{ $contratoCancelado ? 'bg-red-700 text-white hover:bg-red-800' : 'border hover:bg-gray-50' }}">
                    {{ $contratoCancelado ? 'Editar / descancelar' : 'Editar' }}
                </a>
            @endcan

            <a href="{{ route('admin.contratos.index') }}" class="px-4 py-2 rounded-xl border">Volver</a>
        </div>
    </div>

    @if($contratoCancelado)
        <div class="mb-4 overflow-hidden rounded-2xl border-2 border-red-300 bg-red-50">
            <div class="bg-red-700 px-4 py-3 text-white">
                <div class="text-lg font-black uppercase tracking-wide">Contrato cancelado</div>
                <div class="text-sm text-red-100">
                    Registro restaurable: las acciones operativas estan bloqueadas hasta descancelarlo.
                </div>
            </div>

            <div class="grid gap-3 p-4 text-sm text-red-950 sm:grid-cols-3">
                <div>
                    <div class="text-xs font-bold uppercase text-red-700">Estatus</div>
                    <div class="font-black">{{ ucfirst($contrato->estatus ?? 'cancelado') }}</div>
                </div>

                <div>
                    <div class="text-xs font-bold uppercase text-red-700">Cancelado el</div>
                    <div class="font-black">{{ optional($contrato->deleted_at)->format('d/m/Y H:i') ?? '-' }}</div>
                </div>

                <div class="sm:text-right">
                    @can('contratos.editar')
                        <a href="{{ route('admin.contratos.edit', $contrato->uuid) }}"
                            class="inline-flex px-4 py-2 rounded-xl bg-red-700 text-white font-bold hover:bg-red-800">
                            Ir a descancelar
                        </a>
                    @endcan
                </div>
            </div>
        </div>

        <div class="hidden">
            <div class="font-black">Contrato cancelado</div>
            <div class="text-sm mt-1">
                Este contrato está en estatus cancelado. Todas las acciones operativas han sido deshabilitadas.
            </div>
        </div>
    @endif

    @if (session()->has('ok'))
        <div class="mb-4 p-3 rounded-xl bg-green-50 text-green-800 border border-green-200">
            {{ session('ok') }}
        </div>
    @endif

    <div class="grid md:grid-cols-3 gap-4 mb-4">
        <div class="bg-white rounded-2xl border p-4 md:col-span-2">
            <p><b>Cliente:</b> {{ $contrato->cliente?->nombre_completo ?? '—' }}</p>
            <p><b>Lote:</b> {{ $contrato->lote?->clave ?? '—' }}</p>
            <p><b>Fraccionamiento:</b> {{ $contrato->lote?->fraccionamiento?->nombre ?? '—' }}</p>
            <p><b>Propietario:</b> {{ $contrato->lote?->fraccionamiento?->propietario?->nombre ?? '—' }}</p>

            <hr class="my-3">

            <p><b>Inicio:</b> {{ optional($contrato->fecha_inicio)->format('d/m/Y') }}</p>
            <p><b>Frecuencia:</b> {{ $contrato->frecuencia === 'semanal' ? 'Semanal' : 'Mensual' }}</p>

            @if($contrato->frecuencia === 'semanal')
                <p>
                    <b>Día semanal:</b>
                    {{ ucfirst(\Carbon\Carbon::now()->startOfWeek()->addDays($contrato->dia_semana - 1)->locale('es')->isoFormat('dddd')) }}
                </p>
            @else
                <p><b>Día del mes:</b> {{ $contrato->dia_mes }}</p>
            @endif

            <p>
                <b>Estatus:</b>
                @if($contratoCancelado)
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-bold bg-red-100 text-red-800 border border-red-200">
                        Cancelado
                    </span>
                @else
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                        {{ ucfirst($contrato->estatus) }}
                    </span>
                @endif
            </p>

            @if($contrato->promocion)
                <p class="text-green-700"><b>Promoción:</b> {{ $contrato->promocion->nombre }}</p>
            @endif

            <div class="mt-3 rounded-xl border bg-gray-50 p-3">
                <div class="flex items-center justify-between gap-3">
                    <div class="font-semibold text-gray-800">Documentos escaneados</div>
                    <div class="text-sm font-black {{ $documentosScanCargados === $documentosTotal ? 'text-green-700' : 'text-gray-600' }}">
                        {{ $documentosScanCargados }}/{{ $documentosTotal }}
                    </div>
                </div>

                <div class="mt-2 flex flex-wrap gap-1.5">
                    @foreach($documentosContrato as $tipoDocumento => $documento)
                        @php
                            $scanCargado = filled($contrato->{$documento['scan_field']} ?? null);
                        @endphp

                        <span class="inline-flex rounded-full px-2 py-1 text-[11px] font-bold {{ $scanCargado ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-500' }}">
                            {{ $documento['label'] }}
                        </span>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl border p-4">
            <p><b>Precio total:</b> ${{ number_format((float)$contrato->precio_total,2) }}</p>
            <p><b>Enganche:</b> ${{ number_format((float)$contrato->enganche,2) }}</p>
            <p><b>Saldo inicial:</b> ${{ number_format((float)$contrato->saldo_inicial,2) }}</p>
            <p><b>Saldo actual:</b> ${{ number_format((float)$contrato->saldo_actual,2) }}</p>
            <p><b>Monto pago:</b> ${{ number_format((float)$contrato->monto_pago,2) }}</p>

            <hr class="my-3">

            <p><b>Recargo:</b> {{ $contrato->tipo_recargo }} ({{ number_format((float)$contrato->valor_recargo,2) }})</p>
            <p><b>Días gracia:</b> {{ $contrato->dias_gracia }}</p>
        </div>
    </div>

    <div class="bg-white rounded-2xl border overflow-hidden">
        <div class="p-3 font-bold bg-gray-50">Cuotas</div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="p-3 text-left">#</th>
                        <th class="p-3 text-left">Vence</th>
                        <th class="p-3 text-left">Monto</th>
                        <th class="p-3 text-left">Pagado</th>
                        <th class="p-3 text-left">Recargo</th>
                        <th class="p-3 text-left">Saldo planeado</th>
                        <th class="p-3 text-left">Estatus</th>

                        @can('recibos.eliminar')
                            <th class="p-3 text-left">Acciones</th>
                        @endcan
                    </tr>
                </thead>
                <tbody>
                    @php
                        $saldoPlaneado = (float) $contrato->cuotas->sum('monto');
                    @endphp

                    @forelse($contrato->cuotas as $c)
                        @php
                            $saldoPlaneado = max(0, round($saldoPlaneado - (float)$c->monto, 2));
                            $tienePago = ((float)($c->pagado_total ?? 0) > 0) || (strtolower((string)$c->estatus) === 'pagada');
                        @endphp

                        <tr class="border-t">
                            <td class="p-3">{{ $c->numero }}</td>
                            <td class="p-3">{{ optional($c->fecha_vencimiento)->format('d/m/Y') }}</td>
                            <td class="p-3">${{ number_format((float)$c->monto,2) }}</td>
                            <td class="p-3">${{ number_format((float)$c->pagado_total,2) }}</td>
                            <td class="p-3">${{ number_format((float)$c->recargo_aplicado,2) }}</td>

                            <td class="p-3 font-semibold text-gray-800">
                                ${{ number_format($saldoPlaneado, 2) }}
                            </td>

                            <td class="p-3">
                                <div class="flex flex-col gap-1">
                                    <span>{{ ucfirst($c->estatus) }}</span>

                                    @if(($c->origen_pago ?? null) === 'historico')
                                        <span class="inline-flex w-fit px-2 py-1 rounded-full text-xs font-bold bg-yellow-100 text-yellow-800">
                                            Pago histórico
                                        </span>
                                    @endif
                                </div>
                            </td>

                            @can('recibos.eliminar')
                                <td class="p-3">
                                    @if($contratoCancelado)
                                        <span class="inline-flex px-3 py-1 rounded-lg bg-red-50 text-red-700 text-xs font-bold border border-red-200">
                                            Contrato cancelado
                                        </span>
                                    @else
                                        <div class="flex flex-wrap gap-2">
                                            @if(! $tienePago && in_array(strtolower((string)$c->estatus), ['pendiente', 'vencida', 'parcial']))
                                                <button type="button"
                                                    wire:click="confirmarMarcarPagada({{ $c->id }})"
                                                    class="px-3 py-1 rounded-lg bg-emerald-600 text-white font-bold hover:bg-emerald-700">
                                                    Marcar pagado histórico
                                                </button>
                                            @endif

                                            @if($tienePago)
                                                <button type="button"
                                                    wire:click="confirmarAnularPago({{ $c->id }})"
                                                    class="px-3 py-1 rounded-lg border text-red-600 font-bold">
                                                    Anular pago/recibo
                                                </button>
                                            @endif

                                            @if(! $tienePago && ! in_array(strtolower((string)$c->estatus), ['pendiente', 'vencida', 'parcial']))
                                                <span class="text-gray-400">—</span>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                            @endcan
                        </tr>
                    @empty
                        <tr>
                            <td class="p-4 text-gray-500" colspan="8">Sin cuotas.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-white rounded-2xl border overflow-hidden mt-4">
        <div class="p-3 font-bold bg-gray-50 flex items-center justify-between">
            <div>Historial (auditable)</div>
            <div class="text-xs text-gray-500">Últimos 100 movimientos</div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="p-3 text-left">Fecha</th>
                        <th class="p-3 text-left">Usuario</th>
                        <th class="p-3 text-left">Tipo</th>
                        <th class="p-3 text-left">Motivo</th>
                        <th class="p-3 text-left">Detalle</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($contrato->historial as $h)
                        @php
                            $tipo = (string) $h->tipo;
                            $badge = match($tipo) {
                                'reprogramacion' => 'bg-blue-100 text-blue-800',
                                'anular_pago' => 'bg-red-100 text-red-800',
                                'cambio_cliente' => 'bg-purple-100 text-purple-800',
                                'reestructura_b' => 'bg-yellow-100 text-yellow-800',
                                'ambos' => 'bg-green-100 text-green-800',
                                'pago_historico' => 'bg-emerald-100 text-emerald-800',
                                'abono_capital' => 'bg-teal-100 text-teal-800',
                                'archivo_contrato' => 'bg-sky-100 text-sky-800',
                                'archivo_documento_contrato' => 'bg-sky-100 text-sky-800',
                                'cancelacion_contrato' => 'bg-red-100 text-red-800',
                                'descancelacion_contrato' => 'bg-emerald-100 text-emerald-800',
                                default => 'bg-gray-100 text-gray-700',
                            };

                            $antes = $h->antes ?? [];
                            $despues = $h->despues ?? [];
                        @endphp

                        <tr class="border-t">
                            <td class="p-3 whitespace-nowrap">
                                {{ optional($h->created_at)->format('d/m/Y H:i') }}
                            </td>

                            <td class="p-3">
                                {{ $h->user?->name ?? '—' }}
                            </td>

                            <td class="p-3">
                                <span class="px-2 py-1 rounded-full text-xs font-bold {{ $badge }}">
                                    {{ strtoupper($tipo) }}
                                </span>
                            </td>

                            <td class="p-3">
                                {{ $h->motivo ?? '—' }}
                            </td>

                            <td class="p-3 text-gray-700">
                                @if($tipo === 'reprogramacion')
                                    <div class="text-xs">
                                        <b>Primer vencimiento:</b>
                                        {{ $antes['primer_vencimiento'] ?? '—' }}
                                        → {{ $despues['primer_vencimiento'] ?? '—' }}
                                    </div>
                                @elseif($tipo === 'anular_pago')
                                    <div class="text-xs">
                                        <b>Cuota #{{ $antes['cuota_numero'] ?? '—' }}</b>
                                        <br>

                                        @if(!empty($despues['recibos']))
                                            <b>Recibos:</b>
                                            {{ collect($despues['recibos'])->pluck('folio')->filter()->implode(', ') }}
                                        @else
                                            <span class="text-gray-400">Sin recibos</span>
                                        @endif
                                    </div>

                                @elseif($tipo === 'pago_historico')
                                    <div class="text-xs">
                                        <b>Cuota #{{ $despues['cuota_numero'] ?? '—' }}</b>
                                        ({{ $antes['estatus'] ?? '—' }} → {{ $despues['estatus'] ?? '—' }})
                                        <br>
                                        <b>Origen:</b> {{ $despues['origen_pago'] ?? '—' }}
                                    </div>
                                @elseif($tipo === 'abono_capital')
                                    <div class="text-xs">
                                        <b>Monto abonado:</b>
                                        ${{ number_format((float) ($despues['monto_abono'] ?? 0), 2) }}
                                        <br>
                                        <b>Saldo:</b>
                                        ${{ number_format((float) ($h->saldo_anterior ?? 0), 2) }}
                                        → ${{ number_format((float) ($h->saldo_nuevo ?? 0), 2) }}
                                        @if(($despues['cuotas_eliminadas'] ?? 0) > 0)
                                            <br>
                                            <b>Cuotas eliminadas:</b> {{ $despues['cuotas_eliminadas'] }}
                                        @endif
                                        @if($h->nota)
                                            <br><span class="text-gray-500">{{ $h->nota }}</span>
                                        @endif
                                    </div>
                                @elseif(in_array($tipo, ['cancelacion_contrato', 'descancelacion_contrato'], true))
                                    <div class="text-xs">
                                        <b>Contrato:</b>
                                        {{ $antes['estatus_contrato'] ?? '-' }}
                                        -> {{ $despues['estatus_contrato'] ?? '-' }}
                                        <br>
                                        <b>Lote:</b>
                                        {{ $antes['lote_estatus'] ?? '-' }}
                                        -> {{ $despues['lote_estatus'] ?? '-' }}
                                        <br>
                                        <b>Cuotas creadas:</b> {{ $h->cuotas_creadas ?? 0 }}
                                        |
                                        <b>Cuotas eliminadas:</b> {{ $h->cuotas_eliminadas ?? 0 }}
                                    </div>
                                @elseif(in_array($tipo, ['archivo_contrato', 'archivo_documento_contrato'], true))
                                    @php
                                        $antesArchivo = $antes['archivo_contrato'] ?? ($antes['archivo'] ?? null);
                                        $despuesArchivo = $despues['archivo_contrato'] ?? ($despues['archivo'] ?? null);
                                        $documentoLabel = $despues['documento_label'] ?? ($antes['documento_label'] ?? 'Contrato');

                                        $accion = 'actualizado';

                                        if (!$antesArchivo && $despuesArchivo) {
                                            $accion = 'subido';
                                        } elseif ($antesArchivo && !$despuesArchivo) {
                                            $accion = 'eliminado';
                                        } elseif ($antesArchivo && $despuesArchivo) {
                                            $accion = 'reemplazado';
                                        }
                                    @endphp

                                    <div class="text-xs space-y-1">
                                        <div>
                                            <b>Documento:</b> {{ $documentoLabel }}
                                        </div>
                                        <div>
                                            <b>Archivo:</b>
                                            <span class="
                                                px-2 py-0.5 rounded-full text-xs font-bold
                                                @if($accion === 'subido') bg-green-100 text-green-700
                                                @elseif($accion === 'reemplazado') bg-blue-100 text-blue-700
                                                @elseif($accion === 'eliminado') bg-red-100 text-red-700
                                                @else bg-gray-100 text-gray-700
                                                @endif
                                            ">
                                                {{ strtoupper($accion) }}
                                            </span>
                                        </div>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="p-4 text-gray-500 text-center">
                                Sin movimientos registrados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- MODAL DOCUMENTOS LEGALES --}}
    @if($modalContratoOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white w-full max-w-4xl rounded-2xl shadow-xl overflow-hidden">
                <div class="px-5 py-4 border-b flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-black">Documentos del contrato</h2>
                        <p class="text-sm text-gray-500">{{ $contrato->folio_contrato }}</p>
                    </div>

                    <button
                        type="button"
                        wire:click="cerrarModalContrato"
                        class="px-3 py-1 border rounded-lg hover:bg-gray-50">
                        Cerrar
                    </button>
                </div>

                <div class="p-5 space-y-5">
                    @if(!$documentoAccion)
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <button type="button"
                                wire:click="seleccionarAccionDocumento('descargar')"
                                class="text-left rounded-2xl border p-5 hover:border-blue-300 hover:bg-blue-50 transition">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-600 text-white font-black">
                                        W
                                    </div>
                                    <div>
                                        <div class="font-black text-gray-900">Descargar documentos</div>
                                        <div class="text-sm text-gray-500">Genera o baja el Word de cualquiera de los 4 documentos.</div>
                                    </div>
                                </div>
                            </button>

                            <button type="button"
                                wire:click="seleccionarAccionDocumento('subir')"
                                class="text-left rounded-2xl border p-5 hover:border-emerald-300 hover:bg-emerald-50 transition">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-600 text-white font-black">
                                        PDF
                                    </div>
                                    <div>
                                        <div class="font-black text-gray-900">Subir scan</div>
                                        <div class="text-sm text-gray-500">Carga o reemplaza el PDF escaneado de cada documento.</div>
                                    </div>
                                </div>
                            </button>
                        </div>

                        <div class="rounded-2xl border bg-gray-50 p-4 text-sm text-gray-600">
                            Scans cargados: <b>{{ $documentosScanCargados }} de {{ $documentosTotal }}</b>.
                        </div>

                        @php
                            $tieneConvenioScan = filled($contrato->archivo_convenio_pago)
                                || filled($contrato->archivo_convenio_pago_reconocimiento_adeudo);
                        @endphp

                        @if($tieneConvenioScan)
                        <div class="rounded-2xl border p-4 flex items-center justify-between gap-4
                            {{ $contrato->alerta_convenio ? 'border-amber-300 bg-amber-50' : 'bg-white' }}">
                            <div>
                                <div class="font-semibold text-sm {{ $contrato->alerta_convenio ? 'text-amber-900' : 'text-gray-800' }}">
                                    Alerta de convenio al generar recibos
                                </div>
                                <div class="text-xs mt-0.5 {{ $contrato->alerta_convenio ? 'text-amber-700' : 'text-gray-500' }}">
                                    @if($contrato->alerta_convenio)
                                        Activa — se mostrará un aviso al intentar registrar un recibo de este contrato.
                                    @else
                                        Desactivada — actívala para que aparezca un aviso al registrar recibos.
                                    @endif
                                </div>
                            </div>

                            <button type="button"
                                wire:click="toggleAlertaConvenio"
                                wire:loading.attr="disabled"
                                wire:target="toggleAlertaConvenio"
                                class="relative inline-flex h-7 w-12 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none
                                    {{ $contrato->alerta_convenio ? 'bg-amber-500' : 'bg-gray-300' }}"
                                role="switch"
                                aria-checked="{{ $contrato->alerta_convenio ? 'true' : 'false' }}">
                                <span class="pointer-events-none inline-block h-6 w-6 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out
                                    {{ $contrato->alerta_convenio ? 'translate-x-5' : 'translate-x-0' }}">
                                </span>
                            </button>
                        </div>
                        @endif

                    @elseif($documentoAccion === 'descargar')
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <div class="text-sm font-black text-gray-900">Selecciona un Word para descargar</div>
                                <div class="text-xs text-gray-500">Si no existe, se genera con la plantilla correspondiente.</div>
                            </div>

                            <button type="button"
                                wire:click="volverAccionesDocumento"
                                class="px-3 py-1.5 rounded-lg border text-sm hover:bg-gray-50">
                                Cambiar acción
                            </button>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            @foreach($documentosContrato as $tipoDocumento => $documento)
                                @php
                                    $docxGenerado = filled($contrato->{$documento['docx_field']} ?? null);
                                    $scanCargado = filled($contrato->{$documento['scan_field']} ?? null);
                                @endphp

                                <div class="rounded-2xl border p-4 bg-white">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <div class="font-black text-gray-900">{{ $documento['label'] }}</div>
                                            <div class="mt-1 flex flex-wrap gap-1">
                                                <span class="rounded-full px-2 py-0.5 text-[11px] font-bold {{ $docxGenerado ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-500' }}">
                                                    {{ $docxGenerado ? 'Word generado' : 'Word pendiente' }}
                                                </span>
                                                <span class="rounded-full px-2 py-0.5 text-[11px] font-bold {{ $scanCargado ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                                    {{ $scanCargado ? 'Scan cargado' : 'Sin scan' }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <a href="{{ route('admin.private.contratos.documentos.docx.download', ['uuid' => $contrato->uuid, 'tipo' => $tipoDocumento]) }}"
                                        class="mt-4 inline-flex w-full items-center justify-center rounded-xl bg-blue-700 px-4 py-2 font-bold text-white hover:bg-blue-800">
                                        Descargar Word
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    @elseif($documentoAccion === 'subir' && $documentoSeleccionado)
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <div class="text-sm font-black text-gray-900">Subir PDF escaneado</div>
                                <div class="text-xs text-gray-500">Selecciona el documento y carga su scan firmado.</div>
                            </div>

                            <button type="button"
                                wire:click="volverAccionesDocumento"
                                class="px-3 py-1.5 rounded-lg border text-sm hover:bg-gray-50">
                                Cambiar acción
                            </button>
                        </div>

                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                            <div class="space-y-2">
                                @foreach($documentosContrato as $tipoDocumento => $documento)
                                    @php
                                        $scanCargado = filled($contrato->{$documento['scan_field']} ?? null);
                                        $isSelected = $documentoTipoSeleccionado === $tipoDocumento;
                                    @endphp

                                    <button type="button"
                                        wire:click="seleccionarDocumentoContrato('{{ $tipoDocumento }}')"
                                        class="w-full rounded-xl border px-3 py-2 text-left transition {{ $isSelected ? 'border-blue-500 bg-blue-50' : 'hover:bg-gray-50' }}">
                                        <div class="flex items-center justify-between gap-2">
                                            <span class="text-sm font-bold text-gray-800">{{ $documento['label'] }}</span>
                                            <span class="rounded-full px-2 py-0.5 text-[11px] font-bold {{ $scanCargado ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                                {{ $scanCargado ? 'Cargado' : 'Pendiente' }}
                                            </span>
                                        </div>
                                    </button>
                                @endforeach
                            </div>

                            <div class="lg:col-span-2 rounded-2xl border bg-white p-4">
                                <div class="font-black text-gray-900">{{ $documentoSeleccionado['label'] }}</div>

                                <div class="mt-3 rounded-2xl border bg-gray-50 min-h-[150px] flex items-center justify-center overflow-hidden">
                                    @if($scanPathSeleccionado)
                                        <div class="text-center px-4">
                                            <div class="h-16 w-16 mx-auto rounded-2xl border bg-red-50 text-red-700 flex items-center justify-center text-sm font-black">
                                                PDF
                                            </div>
                                            <div class="mt-3 text-sm font-bold text-gray-800">
                                                {{ basename((string) $scanPathSeleccionado) }}
                                            </div>
                                            <a href="{{ $scanUrlSeleccionado }}"
                                                target="_blank"
                                                class="mt-3 inline-flex px-4 py-2 rounded-xl border font-semibold hover:bg-white">
                                                Ver PDF
                                            </a>
                                        </div>
                                    @else
                                        <div class="text-sm text-gray-400 px-4 text-center">
                                            Este documento aún no tiene PDF escaneado.
                                        </div>
                                    @endif
                                </div>

                                <div class="mt-4">
                                    <input
                                        type="file"
                                        accept="application/pdf,.pdf"
                                        wire:model="documentoScan"
                                        wire:key="documento-scan-{{ $documentoTipoSeleccionado }}"
                                        @disabled($contratoCancelado)
                                        class="w-full rounded-xl border p-2 disabled:opacity-50 disabled:cursor-not-allowed">

                                    @error('documentoScan')
                                        <div class="text-sm text-red-600 mt-1">{{ $message }}</div>
                                    @enderror

                                    <div wire:loading wire:target="documentoScan" class="text-sm text-gray-500 mt-2">
                                        Procesando archivo...
                                    </div>

                                    @if($documentoScan)
                                        <div class="mt-3 rounded-xl border bg-gray-50 p-3 text-sm">
                                            Archivo seleccionado:
                                            <span class="font-bold">{{ $documentoScan->getClientOriginalName() }}</span>
                                        </div>
                                    @endif

                                    <div class="text-xs text-gray-400 mt-2">
                                        Solo se permite PDF de hasta 10 MB.
                                    </div>
                                </div>

                                <div class="mt-4 flex flex-col sm:flex-row gap-2 sm:justify-end">
                                    @if($scanPathSeleccionado)
                                        <button
                                            type="button"
                                            wire:click="eliminarArchivoContrato"
                                            wire:loading.attr="disabled"
                                            wire:target="eliminarArchivoContrato"
                                            @disabled($contratoCancelado)
                                            class="px-4 py-2 rounded-xl border border-red-300 text-red-700 hover:bg-red-50 disabled:opacity-50 disabled:cursor-not-allowed">
                                            Eliminar PDF
                                        </button>
                                    @endif

                                    <button
                                        type="button"
                                        wire:click="subirArchivoContrato"
                                        wire:loading.attr="disabled"
                                        wire:target="subirArchivoContrato,documentoScan"
                                        @disabled($contratoCancelado)
                                        class="px-4 py-2 rounded-xl bg-black text-white font-semibold disabled:opacity-50 disabled:cursor-not-allowed">
                                        {{ $scanPathSeleccionado ? 'Reemplazar PDF' : 'Subir PDF' }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- MODAL REPROGRAMAR --}}
    @if($showReprogramar)
        <div class="fixed inset-0 bg-black/40 flex items-center justify-center p-4 z-50">
            <div class="bg-white rounded-2xl w-full max-w-lg p-5">
                <div class="text-xl font-black">Reprogramar primer pago</div>
                <p class="text-sm text-gray-500 mt-1">
                    Cambia la fecha del primer vencimiento y se recorren las demás cuotas.
                </p>

                <div class="mt-4 space-y-3">
                    <div>
                        <label class="text-sm text-gray-600">Nueva fecha del primer vencimiento</label>
                        <input type="date"
                            wire:model.defer="nuevaFechaPrimerPago"
                            @disabled($contratoCancelado)
                            class="w-full rounded-xl border px-3 py-2 disabled:opacity-50 disabled:cursor-not-allowed">
                        @error('nuevaFechaPrimerPago')
                            <div class="text-red-600 text-sm mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="mt-5 flex justify-end gap-2">
                    <button type="button"
                        wire:click="$set('showReprogramar', false)"
                        class="px-4 py-2 rounded-xl border">
                        Cancelar
                    </button>
                    <button type="button"
                        wire:click="guardarReprogramacion"
                        @disabled($contratoCancelado)
                        class="px-4 py-2 rounded-xl bg-black text-white font-bold disabled:opacity-50 disabled:cursor-not-allowed">
                        Guardar
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- MODAL ABONO A CAPITAL --}}
    @if($showAbonoCapital)
        <div class="fixed inset-0 bg-black/40 flex items-center justify-center p-4 z-50">
            <div class="bg-white rounded-2xl w-full max-w-md p-6 space-y-4">

                <div>
                    <div class="text-xl font-black text-emerald-700">Abono a capital</div>
                    <div class="text-sm text-gray-500 mt-1">
                        El monto se descontará directamente del saldo del contrato y quedará registrado en el historial.
                    </div>
                </div>

                <div class="rounded-xl bg-gray-50 border px-4 py-3 text-sm">
                    <span class="text-gray-500">Saldo actual:</span>
                    <span class="font-black text-gray-900 ml-1">${{ number_format((float) ($contrato->saldo_actual ?? 0), 2) }}</span>
                </div>

                <div class="space-y-3">
                    <div>
                        <label class="text-sm font-semibold text-gray-700">Monto del abono <span class="text-red-500">*</span></label>
                        <input
                            type="number"
                            wire:model.defer="abonoCapitalMonto"
                            min="0.01"
                            step="0.01"
                            max="{{ (float) ($contrato->saldo_actual ?? 0) }}"
                            placeholder="0.00"
                            class="mt-1 w-full rounded-xl border px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-400 focus:outline-none">
                        @error('abonoCapitalMonto')
                            <div class="text-red-600 text-xs mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-gray-700">Fecha <span class="text-red-500">*</span></label>
                        <input
                            type="date"
                            wire:model.defer="abonoCapitalFecha"
                            class="mt-1 w-full rounded-xl border px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-400 focus:outline-none">
                        @error('abonoCapitalFecha')
                            <div class="text-red-600 text-xs mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-gray-700">Observaciones</label>
                        <textarea
                            wire:model.defer="abonoCapitalObservaciones"
                            rows="2"
                            placeholder="Ej: Pago extra acordado con el cliente"
                            class="mt-1 w-full rounded-xl border px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-400 focus:outline-none resize-none"></textarea>
                        @error('abonoCapitalObservaciones')
                            <div class="text-red-600 text-xs mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-1">
                    <button
                        type="button"
                        wire:click="$set('showAbonoCapital', false)"
                        wire:loading.attr="disabled"
                        wire:target="confirmarAbonoCapital"
                        class="px-4 py-2 rounded-xl border text-sm disabled:opacity-50">
                        Cancelar
                    </button>
                    <button
                        type="button"
                        wire:click="confirmarAbonoCapital"
                        wire:loading.attr="disabled"
                        wire:target="confirmarAbonoCapital"
                        class="px-4 py-2 rounded-xl bg-emerald-600 text-white font-bold text-sm hover:bg-emerald-700 disabled:opacity-50">
                        <span wire:loading.remove wire:target="confirmarAbonoCapital">Registrar abono</span>
                        <span wire:loading wire:target="confirmarAbonoCapital">Procesando...</span>
                    </button>
                </div>

            </div>
        </div>
    @endif

    {{-- MODAL MARCAR PAGADO HISTÓRICO --}}
    @if($showMarcarPagada)
        <div class="fixed inset-0 bg-black/40 flex items-center justify-center p-4 z-50">
            <div class="bg-white rounded-2xl w-full max-w-lg p-5">
                <div class="text-xl font-black text-emerald-700">Marcar cuota como pagada</div>

                <p class="text-sm text-gray-600 mt-2">
                    Esta acción marcará la cuota como <b>pagada</b> y la registrará como
                    <b>pago histórico</b> para no afectar reportes.
                </p>

                <div class="mt-4">
                    <label class="text-sm text-gray-600">Observaciones</label>
                    <textarea
                        wire:model.defer="observacionPagoHistorico"
                        class="w-full rounded-xl border px-3 py-2"
                        rows="3"
                        placeholder="Ej: Pago registrado antes de arrancar el sistema"
                        wire:loading.attr="disabled"
                        wire:target="marcarPagadaConfirmado"
                        @disabled($contratoCancelado)></textarea>
                    @error('observacionPagoHistorico')
                        <div class="text-red-600 text-sm mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mt-5 flex justify-end gap-2">
                    <button
                        type="button"
                        wire:click="$set('showMarcarPagada', false)"
                        wire:loading.attr="disabled"
                        wire:target="marcarPagadaConfirmado"
                        class="px-4 py-2 rounded-xl border disabled:opacity-50 disabled:cursor-not-allowed">
                        Cancelar
                    </button>

                    <button
                        type="button"
                        wire:click="marcarPagadaConfirmado"
                        wire:loading.attr="disabled"
                        wire:target="marcarPagadaConfirmado"
                        @disabled($contratoCancelado)
                        class="px-4 py-2 rounded-xl bg-emerald-600 text-white font-bold disabled:opacity-50 disabled:cursor-not-allowed">
                        <span wire:loading.remove wire:target="marcarPagadaConfirmado">
                            Sí, marcar como pagada
                        </span>

                        <span wire:loading wire:target="marcarPagadaConfirmado">
                            Procesando...
                        </span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- MODAL ANULAR PAGO/RECIBO --}}
    @if($showAnularPago)
        <div class="fixed inset-0 bg-black/40 flex items-center justify-center p-4 z-50">
            <div class="bg-white rounded-2xl w-full max-w-2xl p-5">

                <div class="text-lg font-black text-red-700">
                    Cancelar recibos y pagos
                </div>

                <p class="text-sm text-gray-600 mt-1">
                    Se cancelarán los recibos y pagos de la cuota.
                </p>

                @if($anularPreview)
                    <div class="mt-3 rounded-xl border bg-red-50 p-3 text-sm">

                        <div class="flex justify-between">
                            <span>Cuota:</span>
                            <b>#{{ $anularPreview['cuota_numero'] }}</b>
                        </div>

                        <div class="flex justify-between">
                            <span>Recibos:</span>
                            <b>{{ $anularPreview['recibos_count'] }}</b>
                        </div>

                        <div class="flex justify-between">
                            <span>Total pagos:</span>
                            <b class="text-red-700">
                                ${{ number_format((float)$anularPreview['pagos_total'], 2) }}
                            </b>
                        </div>

                        <div class="mt-2 space-y-1">
                            @foreach($anularPreview['recibos'] as $recibo)
                                <div class="flex justify-between text-xs border-t pt-1">
                                    <div>
                                        <b>{{ $recibo['folio'] }}</b>
                                        <span class="text-gray-500">
                                            ({{ $recibo['concepto'] }})
                                        </span>
                                    </div>

                                    <div class="text-gray-600">
                                        {{ $recibo['pagos_count'] }} pagos
                                    </div>
                                </div>
                            @endforeach
                        </div>

                    </div>
                @endif

                <div class="mt-3">
                    <input type="text"
                        wire:model.defer="motivoAnulacion"
                        @disabled($contratoCancelado)
                        class="w-full rounded-xl border px-3 py-2 text-sm disabled:opacity-50 disabled:cursor-not-allowed"
                        placeholder="Motivo">
                    @error('motivoAnulacion')
                        <div class="text-red-600 text-xs mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mt-4 flex justify-end gap-2">
                    <button
                        wire:click="$set('showAnularPago', false)"
                        class="px-4 py-2 rounded-xl border text-sm">
                        Cancelar
                    </button>

                    <button
                        wire:click="anularPagoConfirmado"
                        @disabled($contratoCancelado)
                        class="px-4 py-2 rounded-xl bg-red-600 text-white font-bold text-sm disabled:opacity-50 disabled:cursor-not-allowed">
                        Confirmar
                    </button>
                </div>

            </div>
        </div>
    @endif
</div>
