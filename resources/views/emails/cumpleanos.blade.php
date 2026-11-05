@php
    // Incrustado en el correo: se ve aunque el cliente no pueda abrir el sitio.
    $logo = resource_path('images/logo-rancho-el-pato.png');
    $logoSrc = isset($message) ? $message->embed($logo) : 'data:image/png;base64,'.base64_encode(file_get_contents($logo));
@endphp
{{-- Sin el encabezado de texto de Laravel: el logo ya dice quiénes somos. --}}
<x-mail::layout>
<x-slot:header>
<tr><td style="height: 32px;"></td></tr>
</x-slot:header>

<div style="text-align: center; margin-bottom: 24px;">
<img src="{{ $logoSrc }}" alt="{{ config('app.name') }}" width="220" style="width: 220px; max-width: 100%; height: auto;">
</div>

# ¡Feliz cumpleaños, {{ $nombre }}! 🎂

Hoy es un día especial y en {{ config('app.name') }} queremos celebrarlo contigo.

Te deseamos un año lleno de salud, alegría y muchos logros, rodeado de las personas que más quieres.

Gracias por tu confianza y por ser parte de nuestra familia. ¡Que pases un excelente día!

Con cariño,  
El equipo de {{ config('app.name') }}

<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} {{ config('app.name') }}. {{ __('All rights reserved.') }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
