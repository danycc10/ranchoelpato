{{--
    Aviso del ticket de una venta de contado.

    El alta del contrato redirige aquí, así que el ticket no se puede mandar
    con un evento de Livewire: viaja en la sesión y se atiende al llegar.
--}}
@if(session('ticket_contado_aviso'))
    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
        {{ session('ticket_contado_aviso') }}
    </div>
@endif

@if(session('ticket_contado_url'))
    @php $ticketContadoUrl = session('ticket_contado_url'); @endphp

    <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-indigo-200 bg-indigo-50 p-4">
        <div class="text-sm text-indigo-900">
            <span class="font-bold">Recibo de contado generado.</span>
            Se abrió en otra pestaña para imprimirlo.
        </div>

        {{-- Por si el navegador bloqueó la pestaña. --}}
        <a href="{{ $ticketContadoUrl }}" target="_blank" rel="noopener"
           class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-bold text-white hover:bg-indigo-700">
            Abrir el ticket
        </a>
    </div>

    <script>
        window.open(@json($ticketContadoUrl), '_blank');
    </script>
@endif
