<div class="max-w-6xl mx-auto p-4 space-y-4">
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
            ])
            : null;

        $diasSemana = [
            1 => 'Lunes',
            2 => 'Martes',
            3 => 'Miercoles',
            4 => 'Jueves',
            5 => 'Viernes',
            6 => 'Sabado',
            7 => 'Domingo',
        ];
    @endphp

    <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
        <div class="min-w-0">
            <h1 class="text-2xl font-black break-words">
                {{ $contrato->folio_contrato ?? ('Servicio #'.$contrato->id) }}
            </h1>
            <p class="text-gray-500 text-sm">
                Contrato de servicio, documentos y calendario de cuotas.
            </p>
        </div>

        <div class="flex items-center gap-2 flex-wrap justify-start lg:justify-end">
            @can('sistema.ver')
                <button
                    type="button"
                    @unless($contratoCancelado) wire:click="abrirModalContrato" @endunless
                    @disabled($contratoCancelado)
                    class="px-4 py-2 rounded-xl bg-black text-white font-bold disabled:opacity-50 disabled:cursor-not-allowed">
                    Documentos
                    <span class="ml-1 text-xs text-white/70">{{ $documentosScanCargados }}/{{ $documentosTotal }}</span>
                </button>
            @endcan

            <a href="{{ route('admin.contratos.pdf', $contrato) }}"
               target="_blank"
               class="px-4 py-2 rounded-xl border font-bold hover:bg-gray-50">
                PDF
            </a>

            @if($this->base)
                <a href="{{ route('admin.contratos.show', $this->base->uuid) }}"
                   class="px-4 py-2 rounded-xl border font-bold hover:bg-gray-50">
                    Contrato base
                </a>
            @endif

            @can('contratos_servicios.editar')
                <a href="{{ route('admin.contratos-servicios.edit', $contrato->uuid) }}"
                   class="px-4 py-2 rounded-xl border font-bold hover:bg-gray-50">
                    Editar
                </a>
            @endcan

            @can('sistema.ver')
                <button
                    type="button"
                    @unless($contratoCancelado) wire:click="abrirReprogramar" @endunless
                    @disabled($contratoCancelado)
                    class="px-4 py-2 rounded-xl border font-bold hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                    Reprogramar
                </button>
            @endcan

            <a href="{{ route('admin.cuotas', ['contrato_id' => $contrato->id]) }}"
               class="px-4 py-2 rounded-xl border hover:bg-gray-50">
                Ver en Cuotas
            </a>

            <a href="{{ route('admin.contratos-servicios.index') }}"
               class="px-4 py-2 rounded-xl border hover:bg-gray-50">
                Volver
            </a>
        </div>
    </div>

    @if (session()->has('ok'))
        <div class="p-3 rounded-xl bg-green-50 text-green-800 border border-green-200">
            {{ session('ok') }}
        </div>
    @endif

    @if($contratoCancelado)
        <div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-red-800">
            <div class="font-black">Contrato cancelado</div>
            <div class="text-sm">Las acciones operativas estan deshabilitadas.</div>
        </div>
    @endif

    <div class="grid md:grid-cols-3 gap-4">
        <div class="bg-white rounded-2xl border p-4 md:col-span-2 space-y-2">
            <p><b>Cliente:</b> {{ $contrato->cliente?->nombre_completo ?? '-' }}</p>
            <p><b>Lote:</b> {{ $contrato->lote?->clave ?? '-' }}</p>
            <p><b>Fraccionamiento:</b> {{ $contrato->lote?->fraccionamiento?->nombre ?? '-' }}</p>
            <p><b>Contrato base:</b> {{ $this->base?->folio_contrato ?? '-' }}</p>
            <p><b>Servicio:</b> {{ $this->servicioNombre ?? '-' }}</p>

            <hr class="my-3">

            <p><b>Inicio:</b> {{ optional($contrato->fecha_inicio)->format('d/m/Y') }}</p>
            <p><b>Frecuencia:</b> {{ ($contrato->frecuencia ?? '') === 'semanal' ? 'Semanal' : 'Mensual' }}</p>

            @if(($contrato->frecuencia ?? '') === 'semanal')
                <p><b>Dia semanal:</b> {{ $diasSemana[(int) $contrato->dia_semana] ?? '-' }}</p>
            @else
                <p><b>Dia del mes:</b> {{ $contrato->dia_mes ?? '-' }}</p>
            @endif

            <p><b>Estatus:</b> {{ ucfirst($contrato->estatus ?? '-') }}</p>
        </div>

        <div class="bg-white rounded-2xl border p-4 space-y-2">
            <p><b>Precio total:</b> ${{ number_format((float) $contrato->precio_total, 2) }}</p>
            <p><b>Enganche:</b> ${{ number_format((float) $contrato->enganche, 2) }}</p>
            <p><b>Saldo inicial:</b> ${{ number_format((float) $contrato->saldo_inicial, 2) }}</p>
            <p><b>Saldo actual:</b> ${{ number_format((float) $contrato->saldo_actual, 2) }}</p>
            <p><b>Monto pago:</b> ${{ number_format((float) $contrato->monto_pago, 2) }}</p>

            <hr class="my-3">

            <p><b>Recargo:</b> {{ $contrato->tipo_recargo ?? '-' }} ({{ number_format((float) $contrato->valor_recargo, 2) }})</p>
            <p><b>Dias gracia:</b> {{ $contrato->dias_gracia ?? 0 }}</p>
        </div>
    </div>

    @can('sistema.ver')
        <div class="bg-white rounded-2xl border p-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <div class="font-black">Documentos legales</div>
                    <div class="text-sm text-gray-500">Word y PDF firmado por documento.</div>
                </div>

                <div class="text-sm font-black {{ $documentosScanCargados === $documentosTotal ? 'text-green-700' : 'text-gray-600' }}">
                    {{ $documentosScanCargados }}/{{ $documentosTotal }}
                </div>
            </div>

            <div class="mt-3 grid sm:grid-cols-2 lg:grid-cols-4 gap-2">
                @foreach($documentosContrato as $tipoDocumento => $documento)
                    @php
                        $scanCargado = filled($contrato->{$documento['scan_field']} ?? null);
                    @endphp
                    <div class="rounded-xl border p-3">
                        <div class="text-sm font-bold text-gray-900">{{ $documento['label'] }}</div>
                        <div class="mt-2 text-xs font-bold {{ $scanCargado ? 'text-green-700' : 'text-gray-500' }}">
                            {{ $scanCargado ? 'PDF firmado cargado' : 'PDF pendiente' }}
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endcan

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
                        $saldoPlaneado = (float) ($contrato->saldo_inicial ?? 0);
                    @endphp

                    @forelse($cuotas as $c)
                        @php
                            $estatusCuota = strtolower((string) $c->estatus);
                            $saldoPlaneado = max(0, round($saldoPlaneado - (float) $c->monto, 2));
                            $tienePago = ((float) ($c->pagado_total ?? 0) > 0) || $estatusCuota === 'pagada';
                        @endphp

                        <tr class="border-t">
                            <td class="p-3">{{ $c->numero }}</td>
                            <td class="p-3">{{ optional($c->fecha_vencimiento)->format('d/m/Y') }}</td>
                            <td class="p-3">${{ number_format((float) $c->monto, 2) }}</td>
                            <td class="p-3">${{ number_format((float) $c->pagado_total, 2) }}</td>
                            <td class="p-3">${{ number_format((float) $c->recargo_aplicado, 2) }}</td>
                            <td class="p-3 font-semibold text-gray-800">${{ number_format($saldoPlaneado, 2) }}</td>
                            <td class="p-3">
                                <div class="flex flex-col gap-1">
                                    <span>{{ ucfirst($estatusCuota) }}</span>

                                    @if(($c->origen_pago ?? null) === 'historico')
                                        <span class="inline-flex w-fit px-2 py-1 rounded-full text-xs font-bold bg-yellow-100 text-yellow-800">
                                            Pago historico
                                        </span>
                                    @endif
                                </div>
                            </td>

                            @can('recibos.eliminar')
                                <td class="p-3">
                                    <div class="flex flex-wrap gap-2">
                                        @if($contratoCancelado)
                                            <span class="text-gray-400">Contrato cancelado</span>
                                        @elseif(! $tienePago && in_array($estatusCuota, ['pendiente', 'vencida', 'parcial'], true))
                                            <button
                                                type="button"
                                                wire:click="confirmarMarcarPagada({{ $c->id }})"
                                                class="px-3 py-1 rounded-lg bg-emerald-600 text-white font-bold hover:bg-emerald-700">
                                                Marcar historico
                                            </button>
                                        @elseif($tienePago)
                                            <button
                                                type="button"
                                                wire:click="confirmarAnularPago({{ $c->id }})"
                                                class="px-3 py-1 rounded-lg border text-red-600 font-bold hover:bg-red-50">
                                                Anular pago/recibo
                                            </button>
                                        @else
                                            <span class="text-gray-400">-</span>
                                        @endif
                                    </div>
                                </td>
                            @endcan
                        </tr>
                    @empty
                        <tr>
                            <td class="p-4 text-gray-500" colspan="@can('recibos.eliminar') 8 @else 7 @endcan">
                                Sin cuotas.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-white rounded-2xl border overflow-hidden">
        <div class="p-3 font-bold bg-gray-50 flex items-center justify-between">
            <div>Historial</div>
            <div class="text-xs text-gray-500">Ultimos 100 movimientos</div>
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
                                'pago_historico' => 'bg-emerald-100 text-emerald-800',
                                'archivo_documento_contrato' => 'bg-sky-100 text-sky-800',
                                'cancelacion_contrato' => 'bg-red-100 text-red-800',
                                default => 'bg-gray-100 text-gray-700',
                            };

                            $tipoLabel = match($tipo) {
                                'reprogramacion' => 'Reprogramacion',
                                'anular_pago' => 'Anulacion de pago',
                                'pago_historico' => 'Pago historico',
                                'archivo_documento_contrato' => 'Documento',
                                'cancelacion_contrato' => 'Cancelacion',
                                default => ucfirst(str_replace('_', ' ', $tipo)),
                            };

                            $antes = $h->antes ?? [];
                            $despues = $h->despues ?? [];
                        @endphp

                        <tr class="border-t">
                            <td class="p-3 whitespace-nowrap">{{ optional($h->created_at)->format('d/m/Y H:i') }}</td>
                            <td class="p-3">{{ $h->user?->name ?? '-' }}</td>
                            <td class="p-3">
                                <span class="px-2 py-1 rounded-full text-xs font-bold {{ $badge }}">
                                    {{ $tipoLabel }}
                                </span>
                            </td>
                            <td class="p-3">{{ $h->motivo ?? '-' }}</td>
                            <td class="p-3 text-gray-700">
                                @if($tipo === 'reprogramacion')
                                    <div class="text-xs">
                                        <b>Primer vencimiento:</b>
                                        {{ $antes['primer_vencimiento'] ?? '-' }}
                                        -> {{ $despues['primer_vencimiento'] ?? '-' }}
                                    </div>
                                @elseif($tipo === 'anular_pago')
                                    <div class="text-xs">
                                        <b>Cuota #{{ $antes['cuota_numero'] ?? '-' }}</b>
                                        ({{ $antes['estatus'] ?? '-' }} -> {{ $despues['estatus'] ?? '-' }})
                                        <br>
                                        <b>Recibo anulado:</b> {{ $antes['folio_recibo'] ?? $despues['folio_recibo_anulado'] ?? '-' }}
                                    </div>
                                @elseif($tipo === 'pago_historico')
                                    <div class="text-xs">
                                        <b>Cuota #{{ $despues['cuota_numero'] ?? '-' }}</b>
                                        ({{ $antes['estatus'] ?? '-' }} -> {{ $despues['estatus'] ?? '-' }})
                                        <br>
                                        <b>Origen:</b> {{ $despues['origen_pago'] ?? '-' }}
                                    </div>
                                @elseif($tipo === 'archivo_documento_contrato')
                                    @php
                                        $documentoLabel = $despues['documento_label'] ?? ($antes['documento_label'] ?? 'Documento');
                                    @endphp
                                    <div class="text-xs">
                                        <b>Documento:</b> {{ $documentoLabel }}
                                        <br>
                                        <b>Antes:</b> {{ $antes['archivo'] ?? '-' }}
                                        <br>
                                        <b>Despues:</b> {{ $despues['archivo'] ?? '-' }}
                                    </div>
                                @else
                                    <div class="text-xs">
                                        <b>Antes:</b> {{ json_encode($antes, JSON_UNESCAPED_UNICODE) }}
                                        <br>
                                        <b>Despues:</b> {{ json_encode($despues, JSON_UNESCAPED_UNICODE) }}
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

    @if($modalContratoOpen)
        <div class="fixed inset-0 bg-black/40 flex items-center justify-center p-4 z-50">
            <div class="bg-white rounded-2xl w-full max-w-4xl p-5 max-h-[90vh] overflow-y-auto">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="text-xl font-black">Documentos del contrato de servicio</div>
                        <p class="text-sm text-gray-500 mt-1">{{ $contrato->folio_contrato }}</p>
                    </div>

                    <button type="button" wire:click="cerrarModalContrato" class="px-3 py-2 rounded-xl border">
                        Cerrar
                    </button>
                </div>

                <div class="mt-5">
                    @if(!$documentoAccion)
                        <div class="grid md:grid-cols-2 gap-3">
                            <button
                                type="button"
                                wire:click="seleccionarAccionDocumento('descargar')"
                                class="text-left rounded-2xl border p-4 hover:bg-gray-50">
                                <div class="font-black text-gray-900">Descargar documentos</div>
                                <div class="text-sm text-gray-500">Word de contrato, constancia y convenios.</div>
                            </button>

                            <button
                                type="button"
                                wire:click="seleccionarAccionDocumento('subir')"
                                class="text-left rounded-2xl border p-4 hover:bg-gray-50">
                                <div class="font-black text-gray-900">Subir PDF firmado</div>
                                <div class="text-sm text-gray-500">Carga o reemplaza el scan firmado.</div>
                            </button>
                        </div>
                    @elseif($documentoAccion === 'descargar')
                        <div class="space-y-3">
                            <div class="flex items-center justify-between gap-3">
                                <div class="text-sm font-black text-gray-900">Selecciona un Word</div>
                                <button type="button" wire:click="volverAccionesDocumento" class="px-3 py-2 rounded-xl border">
                                    Volver
                                </button>
                            </div>

                            @foreach($documentosContrato as $tipoDocumento => $documento)
                                @php
                                    $docxGenerado = filled($contrato->{$documento['docx_field']} ?? null);
                                @endphp
                                <div class="rounded-2xl border p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                    <div>
                                        <div class="font-black text-gray-900">{{ $documento['label'] }}</div>
                                        <div class="text-xs text-gray-500">
                                            {{ $docxGenerado ? 'Word generado' : 'Word pendiente' }}
                                        </div>
                                    </div>

                                    <a href="{{ route('admin.private.contratos.documentos.docx.download', ['uuid' => $contrato->uuid, 'tipo' => $tipoDocumento]) }}"
                                       class="px-4 py-2 rounded-xl bg-black text-white font-bold text-center">
                                        Descargar Word
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    @elseif($documentoAccion === 'subir' && $documentoSeleccionado)
                        <div class="space-y-4">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <div class="text-sm font-black text-gray-900">Subir PDF firmado</div>
                                    <div class="text-xs text-gray-500">Selecciona el documento y carga su scan.</div>
                                </div>

                                <button type="button" wire:click="volverAccionesDocumento" class="px-3 py-2 rounded-xl border">
                                    Volver
                                </button>
                            </div>

                            <div class="grid md:grid-cols-4 gap-2">
                                @foreach($documentosContrato as $tipoDocumento => $documento)
                                    @php
                                        $scanCargado = filled($contrato->{$documento['scan_field']} ?? null);
                                        $isSelected = $documentoTipoSeleccionado === $tipoDocumento;
                                    @endphp

                                    <button
                                        type="button"
                                        wire:click="seleccionarDocumentoContrato('{{ $tipoDocumento }}')"
                                        class="rounded-xl border p-3 text-left {{ $isSelected ? 'border-black bg-gray-50' : 'hover:bg-gray-50' }}">
                                        <span class="text-sm font-bold text-gray-800">{{ $documento['label'] }}</span>
                                        <span class="block mt-1 text-xs {{ $scanCargado ? 'text-green-700' : 'text-gray-500' }}">
                                            {{ $scanCargado ? 'Cargado' : 'Pendiente' }}
                                        </span>
                                    </button>
                                @endforeach
                            </div>

                            <div class="rounded-2xl border p-4">
                                <div class="font-black text-gray-900">{{ $documentoSeleccionado['label'] }}</div>

                                <div class="mt-3">
                                    @if($scanUrlSeleccionado)
                                        <a href="{{ $scanUrlSeleccionado }}" target="_blank" class="inline-flex px-3 py-2 rounded-xl border font-bold hover:bg-gray-50">
                                            Ver PDF
                                        </a>
                                    @else
                                        <div class="text-sm text-gray-500">Este documento aun no tiene PDF firmado.</div>
                                    @endif
                                </div>

                                <div class="mt-4">
                                    <input
                                        type="file"
                                        accept="application/pdf"
                                        wire:model="documentoScan"
                                        wire:key="documento-scan-{{ $documentoTipoSeleccionado }}"
                                        class="block w-full text-sm">

                                    @error('documentoScan')
                                        <div class="text-sm text-red-600 mt-2">{{ $message }}</div>
                                    @enderror

                                    <div wire:loading wire:target="documentoScan" class="text-sm text-gray-500 mt-2">
                                        Cargando archivo...
                                    </div>

                                    @if($documentoScan)
                                        <div class="mt-2 text-sm text-gray-700">
                                            Archivo seleccionado:
                                            <span class="font-bold">{{ $documentoScan->getClientOriginalName() }}</span>
                                        </div>
                                    @endif

                                    <p class="mt-2 text-xs text-gray-500">Solo PDF hasta 10 MB.</p>
                                </div>

                                <div class="mt-5 flex flex-col sm:flex-row sm:justify-between gap-2">
                                    @if($scanPathSeleccionado)
                                        <button
                                            type="button"
                                            wire:click="eliminarArchivoContrato"
                                            wire:loading.attr="disabled"
                                            wire:target="eliminarArchivoContrato"
                                            class="px-4 py-2 rounded-xl border text-red-600 font-bold disabled:opacity-50">
                                            Eliminar PDF
                                        </button>
                                    @else
                                        <div></div>
                                    @endif

                                    <button
                                        type="button"
                                        wire:click="subirArchivoContrato"
                                        wire:loading.attr="disabled"
                                        wire:target="subirArchivoContrato,documentoScan"
                                        class="px-4 py-2 rounded-xl bg-black text-white font-bold disabled:opacity-50">
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

    @if($showReprogramar)
        <div class="fixed inset-0 bg-black/40 flex items-center justify-center p-4 z-50">
            <div class="bg-white rounded-2xl w-full max-w-lg p-5">
                <div class="text-xl font-black">Reprogramar primer pago</div>
                <p class="text-sm text-gray-500 mt-1">
                    Cambia la fecha del primer vencimiento y se recorren las demas cuotas.
                </p>

                <div class="mt-4">
                    <label class="text-sm text-gray-600">Nueva fecha del primer vencimiento</label>
                    <input type="date" wire:model.defer="nuevaFechaPrimerPago" class="w-full rounded-xl border px-3 py-2">
                    @error('nuevaFechaPrimerPago')
                        <div class="text-red-600 text-sm mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" wire:click="$set('showReprogramar', false)" class="px-4 py-2 rounded-xl border">
                        Cancelar
                    </button>
                    <button type="button" wire:click="guardarReprogramacion" class="px-4 py-2 rounded-xl bg-black text-white font-bold">
                        Guardar
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if($showMarcarPagada)
        <div class="fixed inset-0 bg-black/40 flex items-center justify-center p-4 z-50">
            <div class="bg-white rounded-2xl w-full max-w-lg p-5">
                <div class="text-xl font-black text-emerald-700">Marcar cuota como pagada</div>

                <p class="text-sm text-gray-600 mt-2">
                    Esta accion marcara la cuota como <b>pagada</b> y la registrara como
                    <b>pago historico</b> para no afectar reportes.
                </p>

                <div class="mt-4">
                    <label class="text-sm text-gray-600">Observaciones</label>
                    <textarea
                        wire:model.defer="observacionPagoHistorico"
                        class="w-full rounded-xl border px-3 py-2"
                        rows="3"
                        placeholder="Ej: Pago registrado antes de arrancar el sistema"
                        wire:loading.attr="disabled"
                        wire:target="marcarPagadaConfirmado"></textarea>
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
                        class="px-4 py-2 rounded-xl border disabled:opacity-50">
                        Cancelar
                    </button>

                    <button
                        type="button"
                        wire:click="marcarPagadaConfirmado"
                        wire:loading.attr="disabled"
                        wire:target="marcarPagadaConfirmado"
                        class="px-4 py-2 rounded-xl bg-emerald-600 text-white font-bold disabled:opacity-50">
                        <span wire:loading.remove wire:target="marcarPagadaConfirmado">Si, marcar como pagada</span>
                        <span wire:loading wire:target="marcarPagadaConfirmado">Procesando...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if($showAnularPago)
        <div class="fixed inset-0 bg-black/40 flex items-center justify-center p-4 z-50">
            <div class="bg-white rounded-2xl w-full max-w-3xl p-5 max-h-[90vh] overflow-y-auto">
                <div class="text-xl font-black text-red-700">Anular pago/recibo</div>
                <p class="text-sm text-gray-600 mt-2">
                    Se cancelaran los recibos y pagos de la cuota.
                </p>

                @if($anularPreview)
                    <div class="mt-4 rounded-2xl border bg-red-50 border-red-200 p-4 text-sm text-red-900">
                        <div class="grid sm:grid-cols-3 gap-3">
                            <div>
                                <div class="text-xs font-bold uppercase">Cuota</div>
                                <b>#{{ $anularPreview['cuota_numero'] ?? '-' }}</b>
                            </div>
                            <div>
                                <div class="text-xs font-bold uppercase">Recibos</div>
                                <b>{{ $anularPreview['recibos_count'] ?? 0 }}</b>
                            </div>
                            <div>
                                <div class="text-xs font-bold uppercase">Pagos</div>
                                <b>${{ number_format((float) ($anularPreview['pagos_total'] ?? 0), 2) }}</b>
                            </div>
                        </div>

                        <div class="mt-3 space-y-2">
                            @foreach($anularPreview['recibos'] as $recibo)
                                <div class="rounded-xl bg-white border border-red-100 p-3">
                                    <div class="font-bold">
                                        Recibo {{ $recibo['folio'] ?? '-' }} -
                                        ${{ number_format((float) ($recibo['monto_recibo'] ?? 0), 2) }}
                                    </div>
                                    <div class="text-xs text-red-800">
                                        {{ $recibo['concepto'] ?? '-' }} |
                                        {{ $recibo['pagos_count'] ?? 0 }} pago(s) |
                                        ${{ number_format((float) ($recibo['pagos_total'] ?? 0), 2) }}
                                    </div>

                                    @if(! empty($recibo['pagos']))
                                        <div class="mt-2 space-y-1">
                                            @foreach($recibo['pagos'] as $pago)
                                                <div class="text-xs">
                                                    ${{ number_format((float) ($pago['monto'] ?? 0), 2) }}
                                                    - {{ $pago['forma_pago'] ?? '-' }}
                                                    - {{ $pago['cuenta'] ?? '-' }}
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="mt-4">
                    <label class="text-sm text-gray-600">Motivo</label>
                    <input
                        type="text"
                        wire:model.defer="motivoAnulacion"
                        class="w-full rounded-xl border px-3 py-2"
                        placeholder="Ej: Pago duplicado, error de captura, ajuste"
                        wire:loading.attr="disabled"
                        wire:target="anularPagoConfirmado">
                    @error('motivoAnulacion')
                        <div class="text-red-600 text-sm mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mt-5 flex justify-end gap-2">
                    <button
                        type="button"
                        wire:click="$set('showAnularPago', false)"
                        wire:loading.attr="disabled"
                        wire:target="anularPagoConfirmado"
                        class="px-4 py-2 rounded-xl border disabled:opacity-50">
                        Cancelar
                    </button>
                    <button
                        type="button"
                        wire:click="anularPagoConfirmado"
                        wire:loading.attr="disabled"
                        wire:target="anularPagoConfirmado"
                        class="px-4 py-2 rounded-xl bg-red-600 text-white font-bold disabled:opacity-50">
                        <span wire:loading.remove wire:target="anularPagoConfirmado">Si, anular</span>
                        <span wire:loading wire:target="anularPagoConfirmado">Procesando...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
