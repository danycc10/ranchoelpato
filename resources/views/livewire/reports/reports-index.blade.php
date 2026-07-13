<div class="max-w-6xl mx-auto p-4 space-y-6">
    <div class="flex items-end justify-between">
        <div>
            <h1 class="text-2xl font-black">Reportes</h1>
            <p class="text-gray-600">Selecciona el reporte que quieras generar.</p>
        </div>
    </div>

    <div class="grid md:grid-cols-2 gap-4">
        <a href="{{ route('reportes.diario.recibos') }}"
           class="block rounded-2xl border p-5 bg-white hover:bg-gray-50 transition">
            <div class="text-lg font-bold">Reporte diario de recibos</div>
            <div class="text-gray-600 mt-1">
                Desglosado por método de pago (efectivo, transferencia, etc.) y resumen por finca y concepto.
            </div>
        </a>

        <a href="{{ route('reportes.cliente.pagos') }}"
           class="block rounded-2xl border p-5 bg-white hover:bg-gray-50 transition">
            <div class="text-lg font-bold">Pagos por cliente</div>
            <div class="text-gray-600 mt-1">
                Historial de pagos del cliente + resumen de contratos, abonado y saldo restante.
            </div>
        </a>

            <a href="{{ route('reportes.movimientos.bancarios') }}"
       class="block rounded-2xl border p-5 bg-white hover:bg-gray-50 transition">
        <div class="text-lg font-bold">Movimientos por cuenta bancaria</div>
        <div class="text-gray-600 mt-1">
            Recibos capturados a una cuenta bancaria (filtro por cuenta + rango de fechas) con total del periodo.
        </div>
    </a>

        <a href="{{ route('reportes.ingresos.mensuales') }}"
           class="block rounded-2xl border p-5 bg-white hover:bg-gray-50 transition">
            <div class="text-lg font-bold">Resumen de ingresos mensuales</div>
            <div class="text-gray-600 mt-1">
                Flujo esperado vs recibido por finca, diferencia y desglose por método de pago (Año/Mes + Propietario).
            </div>
        </a>

        <a href="{{ route('reportes.pagadores.adelantados') }}"
           class="block rounded-2xl border p-5 bg-white hover:bg-gray-50 transition">
            <div class="text-lg font-bold">Participantes de rifa</div>
            <div class="text-gray-600 mt-1">
                Clientes que pagaron antes del día 10 del mes (mensual) o con 2+ pagos antes del día 10 (semanal).
            </div>
        </a>
    </div>
</div>
