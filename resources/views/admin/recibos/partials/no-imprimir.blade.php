{{-- El recibo se mandó por WhatsApp: no se imprime. $estatus: enviado | en_cola | fallido --}}
@php
    [$detalle, $color] = match ($estatus) {
        'enviado' => ['Enviado por WhatsApp', 'text-emerald-700'],
        'en_cola' => ['WhatsApp pendiente, se reintenta', 'text-amber-700'],
        default => ['WhatsApp no salió', 'text-red-700'],
    };
@endphp
<span class="inline-flex flex-col items-start leading-tight" title="Este recibo se mandó en PDF por WhatsApp: no lo imprimas. {{ $detalle }}.">
    <span class="inline-flex items-center gap-1 rounded-full bg-violet-600 px-2 py-0.5 text-[11px] font-bold text-white whitespace-nowrap">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z" />
            <path stroke-linecap="round" d="M4 4l16 16" />
        </svg>
        No imprimir
    </span>
    <span class="mt-0.5 text-[10px] font-semibold {{ $color }} whitespace-nowrap">{{ $detalle }}</span>
</span>
