<x-app-layout>
    <div class="max-w-6xl mx-auto p-6">

        {{-- FONDO NEGRO SOLO ESTA VISTA --}}
        <div class="min-h-[calc(100vh-80px)]  rounded-2xl p-6">

            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-black text-black">Administración</h1>
                    <p class="text-gray-400">Selecciona un módulo.</p>
                </div>
            </div>

            @php
            $items = [
            ['title'=>'Cuotas','desc'=>'Calendario y control de pagos.','route'=>'admin.cuotas','icon'=>'clipboard'],
            ['title'=>'Recibos','desc'=>'Consulta y registro de recibos.','route'=>'admin.recibos.index','icon'=>'receipt'],
            ['title'=>'Clientes','desc'=>'Altas, bajas y edición.','route'=>'admin.clientes','icon'=>'users'],
            ['title'=>'Contratos','desc'=>'Crear y administrar contratos.','route'=>'admin.contratos.index','icon'=>'document'],
            ['title'=>'Contratos de servicio','desc'=>'Financiamiento de instalación (agua / electricidad).','route'=>'admin.contratos-servicios.index','icon'=>'bolt'],
            ['title'=>'Promociones','desc'=>'Diferidos y cuotas especiales.','route'=>'admin.promociones','icon'=>'tag'],
            ['title'=>'Lotes','desc'=>'Disponibles, vendidos y datos.','route'=>'admin.lotes','icon'=>'map'],
            ['title'=>'Fraccionamientos','desc'=>'Catálogo de fraccionamientos.','route'=>'admin.fraccionamientos','icon'=>'building'],
            ['title'=>'Cuentas bancarias','desc'=>'Bancos y cuentas destino.','route'=>'admin.cuentas-bancarias','icon'=>'bank'],
            ['title'=>'Tipos de cobro','desc'=>'Catálogo de conceptos.','route'=>'admin.tipos-cobro','icon'=>'tag'],
            ['title'=>'Formas de pago','desc'=>'Efectivo, transferencia, etc.','route'=>'admin.formas-pago','icon'=>'creditcard'],
            [
            'title' => 'Propietario contable', 'desc' => 'Reglas por tipo de cobro, finca y forma de pago.', 'route' => 'admin.configuracion-propietarios-contables.index', 'icon' => 'adjustments',
            ],
            ['title'=>'Propietarios','desc'=>'Dueños / copropietarios.','route'=>'admin.propietarios','icon'=>'user'],
            ['title'=>'Periodos','desc'=>'Mensual, semanal, etc.','route'=>'admin.periodos','icon'=>'calendar'],
            ['title'=>'Clientes excelentes','desc'=>'Detectar adelantos y condonar cuotas finales.','route'=>'admin.clientes-excelentes.index','icon'=>'star'],
            ];

            $icons = [
            'users' => '
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372M4.5 19.128A9.38 9.38 0 0 1 7.125 19.5m7.5-12.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0ZM6.75 10.5a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0ZM3 19.5a6 6 0 0 1 12 0v.375H3V19.5Zm13.5.375V19.5a4.5 4.5 0 0 0-6.9-3.825" />',
            'document' => '
            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5A3.375 3.375 0 0 0 10.125 2.25H8.25A3.375 3.375 0 0 0 4.875 5.625v12.75A3.375 3.375 0 0 0 8.25 21.75h3.75M15 17.25l1.5 1.5 3-3" />',
            'map' => '
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m-6 2.25h6m-12-15 6-2.25 6 2.25 6-2.25v15l-6 2.25-6-2.25-6 2.25v-15Z" />',
            'building' => '
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M6 21V5.25A2.25 2.25 0 0 1 8.25 3h7.5A2.25 2.25 0 0 1 18 5.25V21M9 7.5h.01M9 10.5h.01M9 13.5h.01M12 7.5h.01M12 10.5h.01M12 13.5h.01M15 7.5h.01M15 10.5h.01M15 13.5h.01" />',
            'bank' => '
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 10.5h18M4.5 10.5V19.5m3-9v9m3-9v9m3-9v9m3-9v9M2.25 19.5h19.5M12 3 2.25 7.5h19.5L12 3Z" />',
            'tag' => '
            <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3.062A2.25 2.25 0 0 0 8.25 2.625H4.875A2.25 2.25 0 0 0 2.625 4.875V8.25c0 .597.237 1.17.659 1.591l9.375 9.375a2.25 2.25 0 0 0 3.182 0l4.125-4.125a2.25 2.25 0 0 0 0-3.182L10.591 3.284a2.25 2.25 0 0 0-1.023-.222ZM6 6.75h.01" />',
            'creditcard' => '
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M3 6.75A2.25 2.25 0 0 1 5.25 4.5h13.5A2.25 2.25 0 0 1 21 6.75v10.5A2.25 2.25 0 0 1 18.75 19.5H5.25A2.25 2.25 0 0 1 3 17.25V6.75ZM6 15h3" />',
            'user' => '
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.118a7.5 7.5 0 0 1 15 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.5-1.632Z" />',
            'calendar' => '
            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v1.5M17.25 3v1.5M3 7.5h18M4.5 6A2.25 2.25 0 0 1 6.75 3.75h10.5A2.25 2.25 0 0 1 19.5 6v13.5A2.25 2.25 0 0 1 17.25 21.75H6.75A2.25 2.25 0 0 1 4.5 19.5V6Z" />',
            'receipt' => '
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 3h6M6.75 21 5.25 19.5 3.75 21V3.75A1.5 1.5 0 0 1 5.25 2.25h13.5a1.5 1.5 0 0 1 1.5 1.5V21l-1.5-1.5L18.75 21 17.25 19.5 15.75 21 14.25 19.5 12.75 21 11.25 19.5 9.75 21 8.25 19.5 6.75 21Z" />',
            'clipboard' => '
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 3.75h6m-6 0A2.25 2.25 0 0 0 6.75 6v13.5A2.25 2.25 0 0 0 9 21.75h6A2.25 2.25 0 0 0 17.25 19.5V6A2.25 2.25 0 0 0 15 3.75m-6 0V3a1.5 1.5 0 0 1 1.5-1.5h3A1.5 1.5 0 0 1 15 3v.75" />',
            'star' => '
            <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.75.75 0 0 1 1.04 0l2.356 2.257c.19.182.43.305.688.35l3.24.566a.75.75 0 0 1 .416 1.26l-2.32 2.392a1.125 1.125 0 0 0-.307.957l.41 3.287a.75.75 0 0 1-1.09.79l-2.88-1.517a1.125 1.125 0 0 0-1.047 0l-2.88 1.516a.75.75 0 0 1-1.09-.79l.41-3.287a1.125 1.125 0 0 0-.307-.957l-2.32-2.392a.75.75 0 0 1 .416-1.26l3.24-.566c.258-.045.498-.168.688-.35L11.48 3.5Z" />',
            'adjustments' => '
            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 6h9.75m-9.75 0a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 12H7.5m9 6h3.75m-3.75 0a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 18H13.5"/>',
            'bolt' => '
            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 2.25 4.5 13.5H12l-1.5 8.25L19.5 10.5H12l1.5-8.25Z" />',
            ];
            @endphp

            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5 mt-6">
                @foreach($items as $it)
                <a href="{{ route($it['route']) }}"
                    class="group p-5 rounded-2xl bg-white hover:bg-gray-100 hover:shadow-xl transition flex gap-4">

                    {{-- ICONO --}}
                    <div class="h-11 w-11 rounded-xl bg-gray-100 flex items-center justify-center group-hover:bg-black transition">
                        <svg xmlns="http://www.w3.org/2000/svg"
                            fill="none" viewBox="0 0 24 24"
                            stroke-width="1.8"
                            class="h-6 w-6 text-gray-700 group-hover:text-white transition"
                            stroke="currentColor">
                            {!! $icons[$it['icon']] ?? $icons['document'] !!}
                        </svg>
                    </div>

                    {{-- TEXTO --}}
                    <div class="min-w-0">
                        <div class="font-extrabold text-gray-900">
                            {{ $it['title'] }}
                        </div>
                        <div class="text-sm text-gray-500 mt-0.5">
                            {{ $it['desc'] }}
                        </div>
                    </div>

                </a>
                @endforeach
            </div>

        </div>
    </div>
</x-app-layout>