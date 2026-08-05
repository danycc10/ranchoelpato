<div class="max-w-7xl mx-auto p-4 sm:p-6 space-y-6">
    @php
        $entero = fn ($valor) => number_format((int) $valor, 0, '.', ',');
        $dinero = fn ($valor) => $valor === null ? '—' : '$'.number_format((float) $valor, 6, '.', ',');
        $totalFlujo = max(1, (int) $resumen->tokens_entrada + (int) $resumen->tokens_salida);
        $porcentajeEntrada = ((int) $resumen->tokens_entrada / $totalFlujo) * 100;
        $porcentajeSalida = ((int) $resumen->tokens_salida / $totalFlujo) * 100;
    @endphp

    <header class="relative overflow-hidden rounded-3xl bg-slate-950 px-5 py-6 text-white shadow-xl sm:px-8 sm:py-8">
        <div class="absolute -right-20 -top-24 h-64 w-64 rounded-full bg-emerald-400/15 blur-3xl"></div>
        <div class="absolute -bottom-28 left-1/3 h-56 w-56 rounded-full bg-amber-300/10 blur-3xl"></div>

        <div class="relative flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-2xl">
                <a href="{{ route('admin.sistema.index') }}" class="mb-3 inline-flex items-center gap-1 text-xs font-bold text-slate-400 transition hover:text-white focus:outline-none focus:ring-2 focus:ring-emerald-400">
                    <span aria-hidden="true">←</span> Sistema
                </a>
                <div class="mb-3 flex items-center gap-2 text-xs font-black uppercase tracking-[0.2em] text-emerald-300">
                    <span class="h-2 w-2 rounded-full bg-emerald-400 shadow-[0_0_12px_rgba(52,211,153,0.9)]"></span>
                    Monitor OpenAI
                </div>
                <h1 class="text-2xl font-black tracking-tight sm:text-3xl">Consumo de tokens</h1>
                <p class="mt-2 max-w-xl text-sm leading-6 text-slate-300">
                    Uso local del bot y de la lectura de comprobantes. Los costos son estimados en USD con la tarifa guardada en cada solicitud.
                </p>
            </div>

            <button wire:click="mesActual"
                class="w-full rounded-xl border border-white/15 bg-white/10 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-white/15 focus:outline-none focus:ring-2 focus:ring-emerald-400 sm:w-auto">
                Volver al mes actual
            </button>
        </div>
    </header>

    <section aria-label="Filtros del monitor" class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-3">
        <label class="space-y-1.5 text-sm font-bold text-slate-700">
            <span>Desde</span>
            <input type="date" wire:model.live="desde" class="w-full rounded-xl border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
        </label>
        <label class="space-y-1.5 text-sm font-bold text-slate-700">
            <span>Hasta</span>
            <input type="date" wire:model.live="hasta" class="w-full rounded-xl border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
        </label>
        <label class="space-y-1.5 text-sm font-bold text-slate-700">
            <span>Modelo</span>
            <select wire:model.live="modelo" class="w-full rounded-xl border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                <option value="">Todos los modelos</option>
                @foreach ($modelos as $nombreModelo)
                    <option value="{{ $nombreModelo }}">{{ $nombreModelo }}</option>
                @endforeach
            </select>
        </label>
    </section>

    <section aria-label="Resumen del periodo" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-black uppercase tracking-wider text-slate-500">Tokens totales</p>
            <p class="mt-2 text-3xl font-black tabular-nums text-slate-950">{{ $entero($resumen->tokens_total) }}</p>
            <p class="mt-2 text-xs text-slate-500">Entrada + salida del periodo</p>
        </article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-black uppercase tracking-wider text-slate-500">Solicitudes</p>
            <p class="mt-2 text-3xl font-black tabular-nums text-slate-950">{{ $entero($resumen->solicitudes) }}</p>
            <p class="mt-2 text-xs text-slate-500">Llamadas exitosas registradas</p>
        </article>
        <article class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 shadow-sm">
            <p class="text-xs font-black uppercase tracking-wider text-emerald-800">Costo estimado</p>
            <p class="mt-2 text-3xl font-black tabular-nums text-emerald-950">{{ $dinero($resumen->costo_estimado_usd) }}</p>
            <p class="mt-2 text-xs text-emerald-800/70">USD; puede diferir de la factura final</p>
        </article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-black uppercase tracking-wider text-slate-500">Entrada en caché</p>
            <p class="mt-2 text-3xl font-black tabular-nums text-slate-950">{{ $entero($resumen->tokens_cache) }}</p>
            <p class="mt-2 text-xs text-slate-500">Tokens cobrados a tarifa reducida</p>
        </article>
    </section>

    <section class="grid gap-5 lg:grid-cols-[minmax(0,1.65fr)_minmax(280px,0.85fr)]">
        <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-4">
                <h2 class="font-black text-slate-950">Consumo por modelo</h2>
                <p class="mt-1 text-xs text-slate-500">Comparación exacta de entrada, salida y costo.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[720px] text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-5 py-3">Modelo</th>
                            <th class="px-4 py-3 text-right">Llamadas</th>
                            <th class="px-4 py-3 text-right">Entrada</th>
                            <th class="px-4 py-3 text-right">Caché</th>
                            <th class="px-4 py-3 text-right">Salida</th>
                            <th class="px-4 py-3 text-right">Total</th>
                            <th class="px-5 py-3 text-right">Costo</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($porModelo as $fila)
                            <tr class="hover:bg-slate-50/70">
                                <td class="px-5 py-4 font-black text-slate-900">{{ $fila->modelo }}</td>
                                <td class="px-4 py-4 text-right tabular-nums">{{ $entero($fila->solicitudes) }}</td>
                                <td class="px-4 py-4 text-right tabular-nums">{{ $entero($fila->tokens_entrada) }}</td>
                                <td class="px-4 py-4 text-right tabular-nums text-slate-500">{{ $entero($fila->tokens_cache) }}</td>
                                <td class="px-4 py-4 text-right tabular-nums">{{ $entero($fila->tokens_salida) }}</td>
                                <td class="px-4 py-4 text-right font-bold tabular-nums">{{ $entero($fila->tokens_total) }}</td>
                                <td class="px-5 py-4 text-right font-bold tabular-nums text-emerald-700">{{ $dinero($fila->costo_estimado_usd) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-5 py-10 text-center text-slate-500">Todavía no hay consumo en este periodo.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </article>

        <aside class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="font-black text-slate-950">Flujo de tokens</h2>
            <p class="mt-1 text-xs text-slate-500">Qué parte entra al modelo y qué parte genera.</p>

            <div class="mt-6 flex h-3 overflow-hidden rounded-full bg-slate-100" aria-label="Distribución de tokens de entrada y salida">
                <div class="bg-slate-800" style="width: {{ $porcentajeEntrada }}%"></div>
                <div class="bg-emerald-400" style="width: {{ $porcentajeSalida }}%"></div>
            </div>
            <div class="mt-3 grid grid-cols-2 gap-3 text-sm">
                <div>
                    <div class="flex items-center gap-2 text-slate-500"><span class="h-2.5 w-2.5 rounded-full bg-slate-800"></span> Entrada</div>
                    <div class="mt-1 font-black tabular-nums">{{ $entero($resumen->tokens_entrada) }}</div>
                </div>
                <div>
                    <div class="flex items-center gap-2 text-slate-500"><span class="h-2.5 w-2.5 rounded-full bg-emerald-400"></span> Salida</div>
                    <div class="mt-1 font-black tabular-nums">{{ $entero($resumen->tokens_salida) }}</div>
                </div>
            </div>

            <div class="mt-6 border-t border-slate-100 pt-5">
                <h3 class="text-xs font-black uppercase tracking-wider text-slate-500">Por actividad</h3>
                <div class="mt-3 space-y-3">
                    @forelse ($porOperacion as $actividad)
                        <div class="rounded-xl bg-slate-50 p-3">
                            <div class="flex items-start justify-between gap-3">
                                <span class="font-bold text-slate-800">{{ \App\Models\OpenAiUso::etiquetaOperacion($actividad->operacion) }}</span>
                                <span class="text-xs font-black tabular-nums text-slate-500">{{ $entero($actividad->solicitudes) }}</span>
                            </div>
                            <div class="mt-1 flex justify-between text-xs text-slate-500">
                                <span>{{ $entero($actividad->tokens_total) }} tokens</span>
                                <span>{{ $dinero($actividad->costo_estimado_usd) }}</span>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Sin actividad registrada.</p>
                    @endforelse
                </div>
            </div>
        </aside>
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-5 py-4">
            <h2 class="font-black text-slate-950">Solicitudes recientes</h2>
            <p class="mt-1 text-xs text-slate-500">No se guardan conversaciones, imágenes, teléfonos ni claves en este registro.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[760px] text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Fecha</th>
                        <th class="px-4 py-3">Modelo</th>
                        <th class="px-4 py-3">Actividad</th>
                        <th class="px-4 py-3 text-right">Entrada</th>
                        <th class="px-4 py-3 text-right">Salida</th>
                        <th class="px-4 py-3 text-right">Total</th>
                        <th class="px-5 py-3 text-right">Costo</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($recientes as $uso)
                        <tr class="hover:bg-slate-50/70">
                            <td class="whitespace-nowrap px-5 py-4">
                                <div class="font-bold text-slate-800">{{ $uso->consumido_en->format('d/m/Y') }}</div>
                                <div class="text-xs text-slate-500">{{ $uso->consumido_en->format('H:i:s') }}</div>
                            </td>
                            <td class="px-4 py-4 font-bold">{{ $uso->modelo }}</td>
                            <td class="px-4 py-4 text-slate-600">{{ \App\Models\OpenAiUso::etiquetaOperacion($uso->operacion) }}</td>
                            <td class="px-4 py-4 text-right tabular-nums">
                                {{ $entero($uso->tokens_entrada) }}
                                @if ($uso->tokens_entrada_cache > 0)
                                    <div class="text-[11px] text-slate-400">{{ $entero($uso->tokens_entrada_cache) }} caché</div>
                                @endif
                            </td>
                            <td class="px-4 py-4 text-right tabular-nums">
                                {{ $entero($uso->tokens_salida) }}
                                @if ($uso->tokens_razonamiento > 0)
                                    <div class="text-[11px] text-slate-400">{{ $entero($uso->tokens_razonamiento) }} razonamiento</div>
                                @endif
                            </td>
                            <td class="px-4 py-4 text-right font-bold tabular-nums">{{ $entero($uso->tokens_total) }}</td>
                            <td class="px-5 py-4 text-right font-bold tabular-nums text-emerald-700">{{ $dinero($uso->costo_estimado_usd) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-5 py-10 text-center text-slate-500">Las próximas llamadas a OpenAI aparecerán aquí.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($recientes->hasPages())
            <div class="border-t border-slate-100 px-5 py-4">{{ $recientes->links() }}</div>
        @endif
    </section>
</div>
