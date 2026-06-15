<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Venta de Terrenos') }}</title>

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>

<body class="min-h-screen flex items-center justify-center bg-white">

    <div class="text-center">
        <img
            src="{{ asset('storage/images/RanchoElPato.png') }}"
            alt="Logo Rancho El Pato"
            class="mx-auto w-48 sm:w-64 md:w-72 object-contain"
        />
    </div>

</body>
</html>