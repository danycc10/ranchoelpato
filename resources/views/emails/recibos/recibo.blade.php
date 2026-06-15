<x-mail::layout>

    {{-- HEADER --}}
    <x-slot:header>
        <x-mail::header :url="config('app.url')">

            {{-- ✅ LOGO ESTÁTICO (mejor con url() para que sea absoluto) --}}
            <img
                src="{{ url('storage/images/RanchoElPato.png') }}"
                style="height:120px; max-height:120px; object-fit:contain;"
                alt="Rancho El Pato"
            >

        </x-mail::header>
    </x-slot:header>

    {{-- BODY --}}
# 🧾 Recibo {{ $recibo->folio }}

Hola {{ $recibo->cliente?->nombre_completo ?? 'Cliente' }},

Tu pago fue registrado correctamente.  
Se adjunta tu recibo en PDF.

---

<x-mail::table>
| Concepto | Información |
|---------|-------------|
| **Contrato** | {{ $recibo->contrato?->folio_contrato ?? '—' }} |
| **Fecha** | {{ \Carbon\Carbon::parse($recibo->fecha)->format('d/m/Y') }} |
| **Tipo de cobro** | {{ $recibo->tipoCobro?->nombre ?? '—' }} |
| **Monto** | **${{ number_format((float)$recibo->monto, 2) }}** |
</x-mail::table>

Gracias por tu confianza.  
**{{ config('app.name') }}**

    {{-- FOOTER --}}
    <x-slot:footer>
        <x-mail::footer>
            © {{ date('Y') }} {{ config('app.name') }}. Todos los derechos reservados.
        </x-mail::footer>
    </x-slot:footer>

</x-mail::layout>