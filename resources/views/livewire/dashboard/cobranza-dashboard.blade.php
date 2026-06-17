<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

    {{-- Header --}}
    <div class="rounded-3xl bg-gradient-to-r from-slate-950 via-slate-900 to-slate-800 text-white p-6 shadow-xl">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-5">
            <div>
                <div class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs font-bold mb-3">
                    <span class="h-2 w-2 rounded-full bg-emerald-400"></span>
                    Panel operativo
                </div>

                <h1 class="text-2xl sm:text-3xl font-black tracking-tight">
                    Dashboard de cobranza
                </h1>

                <p class="text-slate-300 mt-1">
                    Cuotas por cobrar hoy y cuotas atrasadas para notificación.
                </p>
            </div>

            <div class="flex flex-col sm:flex-row gap-2">
                <button
                    type="button"
                    wire:click="exportarPendientes"
                    wire:loading.attr="disabled"
                    wire:target="exportarPendientes"
                    class="inline-flex items-center justify-center gap-2 rounded-2xl bg-emerald-500 px-4 py-2.5 text-sm font-black text-white shadow hover:bg-emerald-600 disabled:opacity-60"
                >
                    <span wire:loading.remove wire:target="exportarPendientes">⬇️ Exportar pendientes</span>
                    <span wire:loading wire:target="exportarPendientes">Exportando...</span>
                </button>

                <button
                    type="button"
                    wire:click="notificarAtrasadasMasivo"
                    wire:loading.attr="disabled"
                    wire:target="notificarAtrasadasMasivo"
                    class="inline-flex items-center justify-center gap-2 rounded-2xl bg-white px-4 py-2.5 text-sm font-black text-slate-900 shadow hover:bg-slate-100 disabled:opacity-60"
                >
                    <span wire:loading.remove wire:target="notificarAtrasadasMasivo">📨 Notificar atrasadas</span>
                    <span wire:loading wire:target="notificarAtrasadasMasivo">Procesando...</span>
                </button>
            </div>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="relative overflow-hidden rounded-3xl border border-slate-200 bg-white p-4 sm:p-5 shadow-sm">
        <div
            wire:loading.flex
            wire:target="hoy,diasTolerancia,soloConContacto,propietarioId,fraccionamientoId,tipoCuota,search"
            class="absolute inset-x-0 top-0 z-10 hidden h-1 bg-slate-100"
        >
            <div class="h-full w-1/3 animate-pulse rounded-r-full bg-emerald-500"></div>
        </div>

        <div
            wire:loading.flex
            wire:target="hoy,diasTolerancia,soloConContacto,propietarioId,fraccionamientoId,tipoCuota,search"
            class="absolute right-4 top-4 z-10 hidden items-center gap-2 rounded-full border border-emerald-100 bg-white/95 px-3 py-1.5 text-xs font-black text-emerald-700 shadow-sm"
        >
            <svg class="h-3.5 w-3.5 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-20" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-80" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
            </svg>
            Actualizando
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-6 gap-4 items-end">

            <div>
                <label class="text-xs font-bold uppercase tracking-wide text-slate-500">
                    Fecha de revisión
                </label>
                <input
                    type="date"
                    wire:model.live="hoy"
                    class="mt-1 w-full rounded-2xl border-slate-300 px-3 py-2.5 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900"
                >
            </div>

            <div>
                <label class="text-xs font-bold uppercase tracking-wide text-slate-500">
                    Días de tolerancia
                </label>
                <input
                    type="number"
                    min="0"
                    wire:model.live="diasTolerancia"
                    class="mt-1 w-full rounded-2xl border-slate-300 px-3 py-2.5 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900"
                >
            </div>

            <div>
                <label class="text-xs font-bold uppercase tracking-wide text-slate-500">
                    Propietario
                </label>
                <select
                    wire:model.live="propietarioId"
                    class="mt-1 w-full rounded-2xl border-slate-300 px-3 py-2.5 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900"
                >
                    <option value="">Todos</option>
                    @foreach($propietarios as $propietario)
                        <option value="{{ $propietario->id }}">
                            {{ $propietario->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="text-xs font-bold uppercase tracking-wide text-slate-500">
                    Fraccionamiento
                </label>
                <select
                    wire:model.live="fraccionamientoId"
                    class="mt-1 w-full rounded-2xl border-slate-300 px-3 py-2.5 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900"
                >
                    <option value="">Todos</option>
                    @foreach($fraccionamientos as $fraccionamiento)
                        <option value="{{ $fraccionamiento->id }}">
                            {{ $fraccionamiento->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>
            
            <div>
    <label class="text-xs font-bold uppercase tracking-wide text-slate-500">
        Tipo de cuota
    </label>
    <select
        wire:model.live="tipoCuota"
        class="mt-1 w-full rounded-2xl border-slate-300 px-3 py-2.5 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900"
    >
        <option value="todos">Todas</option>
        <option value="terreno">Terrenos</option>
        <option value="servicio">Servicios</option>
    </select>
</div>

            <div>
                <label class="text-xs font-bold uppercase tracking-wide text-slate-500">
                    Buscar
                </label>
                <input
                    type="text"
                    wire:model.live.debounce.400ms="search"
                    placeholder="Cliente, teléfono, contrato, lote..."
                    class="mt-1 w-full rounded-2xl border-slate-300 px-3 py-2.5 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900"
                >
            </div>

            <label class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold text-slate-700">
                <input
                    type="checkbox"
                    wire:model.live="soloConContacto"
                    class="rounded border-slate-300 text-slate-900 focus:ring-slate-900"
                >
                Solo atrasadas con contacto
            </label>
        </div>
    </div>

    {{-- KPIs --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

        <div class="rounded-3xl border border-blue-100 bg-gradient-to-br from-blue-50 to-white p-5 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-sm font-bold text-blue-700">Por cobrar hoy</p>
                    <p class="text-3xl font-black text-slate-950 mt-1">
                        ${{ number_format($kpis->hoy_monto, 2) }}
                    </p>
                    <p class="text-sm text-slate-500 mt-1">
                        {{ $kpis->hoy_count }} cuotas pendientes
                    </p>
                </div>
                <div class="h-12 w-12 rounded-2xl bg-blue-600 text-white flex items-center justify-center text-xl shadow">
                    📅
                </div>
            </div>
        </div>

        <div class="rounded-3xl border border-red-100 bg-gradient-to-br from-red-50 to-white p-5 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-sm font-bold text-red-700">Atrasadas</p>
                    <p class="text-3xl font-black text-slate-950 mt-1">
                        ${{ number_format($kpis->atr_monto, 2) }}
                    </p>
                    <p class="text-sm text-slate-500 mt-1">
                        {{ $kpis->atr_count }} cuotas atrasadas
                    </p>
                </div>
                <div class="h-12 w-12 rounded-2xl bg-red-600 text-white flex items-center justify-center text-xl shadow">
                    ⚠️
                </div>
            </div>
        </div>

        <div class="rounded-3xl border border-slate-200 bg-gradient-to-br from-slate-100 to-white p-5 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-sm font-bold text-slate-700">Total pendiente</p>
                    <p class="text-3xl font-black text-slate-950 mt-1">
                        ${{ number_format($kpis->total_monto, 2) }}
                    </p>
                    <p class="text-sm text-slate-500 mt-1">
                        {{ $kpis->total_count }} cuotas en seguimiento
                    </p>
                </div>
                <div class="h-12 w-12 rounded-2xl bg-slate-900 text-white flex items-center justify-center text-xl shadow">
                    💰
                </div>
            </div>
        </div>

    </div>

    {{-- Tablas separadas --}}
<livewire:dashboard.cobranza-tabla-hoy
    :hoy="$hoy"
    :search="$search"
    :propietario-id="$propietarioId"
    :fraccionamiento-id="$fraccionamientoId"
    :tipo-cuota="$tipoCuota"
    wire:key="tabla-hoy-{{ $hoy }}-{{ md5((string) $search) }}-{{ $propietarioId ?? 'todos' }}-{{ $fraccionamientoId ?? 'todos' }}-{{ $tipoCuota }}"
/>

<livewire:dashboard.cobranza-tabla-atrasadas
    :hoy="$hoy"
    :dias-tolerancia="$diasTolerancia"
    :solo-con-contacto="$soloConContacto"
    :search="$search"
    :propietario-id="$propietarioId"
    :fraccionamiento-id="$fraccionamientoId"
    :tipo-cuota="$tipoCuota"
    wire:key="tabla-atrasadas-{{ $hoy }}-{{ $diasTolerancia }}-{{ $soloConContacto ? 1 : 0 }}-{{ md5((string) $search) }}-{{ $propietarioId ?? 'todos' }}-{{ $fraccionamientoId ?? 'todos' }}-{{ $tipoCuota }}"
/>

    {{-- Loading overlay --}}
    <div
        wire:loading.flex
        wire:target="exportarPendientes,notificarAtrasadasMasivo"
        class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 backdrop-blur-sm"
    >
        <div class="rounded-3xl bg-white px-6 py-5 shadow-2xl flex flex-col items-center gap-3">
            <svg class="h-8 w-8 animate-spin text-slate-900" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-20" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-80" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
            </svg>

            <div class="text-center">
                <p class="text-sm font-black text-slate-900">Procesando...</p>
                <p class="text-xs text-slate-500">Espera un momento.</p>
            </div>
        </div>
    </div>

    {{-- Abrir WhatsApp --}}
    @push('scripts')
        <script>
            document.addEventListener('livewire:init', () => {
                Livewire.on('open-url', (payload) => {
                    const data = Array.isArray(payload) ? payload[0] : payload;

                    if (data?.url) {
                        window.open(data.url, '_blank');
                    }
                });
            });
        </script>
    @endpush

</div>
