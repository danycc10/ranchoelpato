@php
    use App\Livewire\Dashboard\Cumpleaneros;

    $hoy = $this->hoy;
    $proximos = $this->proximos;
    $yaPasoLaHoraDelCorreo = now()->hour >= Cumpleaneros::HORA_CORREO;
@endphp

<div>
    @if ($hoy->isNotEmpty() || $proximos->isNotEmpty())
        <div class="rounded-3xl border border-slate-200 bg-white p-4 sm:p-5 shadow-sm">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <div class="text-lg font-black text-slate-900">
                    🎂 Cumpleañeros de hoy
                    @if ($hoy->isNotEmpty())
                        <span class="ml-1 rounded-full bg-slate-100 px-2 py-0.5 text-sm font-bold text-slate-700">{{ $hoy->count() }}</span>
                    @endif
                </div>
                <div class="text-xs text-slate-500">Sale de la fecha de nacimiento del CURP.</div>
            </div>

            @if ($hoy->isEmpty())
                <div class="mt-3 text-sm text-slate-500">Hoy no cumple años ningún cliente.</div>
            @else
                <div class="mt-3 divide-y divide-slate-100">
                    @foreach ($hoy as $c)
                        @php $cliente = $c['cliente']; @endphp
                        <div wire:key="cumple-{{ $cliente->id }}" class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 py-3">
                            <div>
                                <div class="font-bold text-slate-900">{{ $cliente->nombre_completo }}</div>
                                <div class="text-sm text-slate-600">Cumple {{ $c['edad'] }} años</div>
                                <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs">
                                    @if ($c['correo'])
                                        <span class="text-emerald-700">✓ Correo enviado</span>
                                    @elseif (! $c['tiene_correo'])
                                        <span class="text-slate-500">Sin correo registrado</span>
                                    @elseif ($yaPasoLaHoraDelCorreo)
                                        <span class="text-amber-700">⚠ El correo no se ha enviado</span>
                                    @else
                                        <span class="text-slate-500">El correo sale a las {{ Cumpleaneros::HORA_CORREO }}:00</span>
                                    @endif

                                    @if ($c['whatsapp'])
                                        <span class="text-emerald-700">
                                            ✓ Felicitado por WhatsApp{{ $c['whatsapp']->usuario ? ' por '.$c['whatsapp']->usuario->name : '' }}, {{ $c['whatsapp']->created_at->format('H:i') }}
                                        </span>
                                    @endif
                                </div>
                            </div>

                            @if ($c['enlace'])
                                <a href="{{ $c['enlace'] }}" target="_blank" rel="noopener"
                                   wire:click="marcarFelicitado({{ $cliente->id }})"
                                   class="inline-flex items-center justify-center gap-2 rounded-2xl px-4 py-2 text-sm font-black shadow-sm self-start sm:self-auto {{ $c['whatsapp'] ? 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50' : 'bg-emerald-500 text-white hover:bg-emerald-600' }}">
                                    💬 {{ $c['whatsapp'] ? 'Abrir de nuevo' : 'Felicitar' }}
                                </a>
                            @else
                                <span class="text-xs text-slate-500 self-start sm:self-auto">Sin WhatsApp registrado</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            @if ($proximos->isNotEmpty())
                <div class="mt-3 border-t border-slate-100 pt-3">
                    <div class="text-xs font-bold uppercase tracking-wide text-slate-500">Próximos 7 días</div>
                    <div class="mt-1 flex flex-wrap gap-x-6 gap-y-1 text-sm text-slate-700">
                        @foreach ($proximos as $c)
                            <span>
                                <span class="font-semibold">{{ Cumpleaneros::fechaCorta($c['dia']) }}</span>
                                · {{ $c['cliente']->nombre_completo }} ({{ $c['edad'] }})
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @endif
</div>
