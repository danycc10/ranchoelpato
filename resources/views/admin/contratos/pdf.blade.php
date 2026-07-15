<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>Contrato {{ $contrato->folio_contrato }}</title>

    <style>
        @page {
            margin: 18px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #111;
        }

        .header {
            text-align: center;
            margin-bottom: 12px;
        }

        .header h1 {
            margin: 0;
            font-size: 20px;
            font-weight: 800;
            letter-spacing: 1px;
        }

        .header h2 {
            margin: 2px 0 0 0;
            font-size: 13px;
            font-weight: 600;
        }

        .info-box {
            border: 1px solid #ccc;
            border-radius: 8px;
            padding: 10px 12px;
            margin-bottom: 12px;
        }

        .info-grid {
            width: 100%;
            border-collapse: collapse;
        }

        .info-grid td {
            padding: 4px 6px;
            vertical-align: top;
        }

        .label {
            font-weight: 700;
            color: #333;
            width: 140px;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            table-layout: fixed;
        }

        .table th {
            background: #f2f2f2;
            font-weight: 700;
            border: 1px solid #ddd;
            padding: 5px;
            text-align: center;
        }

        .table td {
            border: 1px solid #ddd;
            padding: 4px;
            font-size: 10px;
        }

        .right {
            text-align: right;
        }

        .center {
            text-align: center;
        }

        .saldo {
            font-weight: 800;
            color: #000;
        }

        /* Sombreado alternado por mes (solo contratos semanales) */
        .band-b {
            background-color: #EFF6FF;
        }

        .month-change td {
            border-top: 2px solid #93C5FD !important;
        }
    </style>
</head>

<body>

    @php
        $finca = strtoupper($contrato->lote?->fraccionamiento?->nombre ?? 'CONTRATO');
        $cliente = strtoupper($contrato->cliente?->nombre_completo ?? '—');

        $diaPago = '—';

        if ($contrato->frecuencia === 'semanal' && $contrato->dia_semana) {

            $map = [
                1 => 'Lunes',
                2 => 'Martes',
                3 => 'Miércoles',
                4 => 'Jueves',
                5 => 'Viernes',
                6 => 'Sábado',
                7 => 'Domingo',
            ];

            $diaPago = $map[(int) $contrato->dia_semana] ?? '—';

        } elseif ($contrato->frecuencia === 'mensual' && $contrato->dia_mes) {

            $diaPago = 'Día ' . $contrato->dia_mes;
        }

        $totalPagos = $contrato->cuotas?->count() ?? 0;
    @endphp

    <div class="header">
        <h1>{{ $finca }}</h1>
        <h2>{{ $cliente }}</h2>
    </div>

    <div class="info-box">
        <table class="info-grid">

            <tr>
                <td class="label">Fecha inicio:</td>
                <td>{{ optional($contrato->fecha_inicio)->format('d/m/Y') }}</td>

                <td class="label">Lote:</td>
                <td>{{ $contrato->lote?->clave }}</td>
            </tr>

            <tr>
                <td class="label">Precio:</td>
                <td>${{ number_format($contrato->precio_total, 2) }}</td>

                <td class="label">Enganche:</td>
                <td>${{ number_format($contrato->enganche, 2) }}</td>
            </tr>

            <tr>
                <td class="label">Saldo inicial:</td>
                <td>${{ number_format($contrato->saldo_inicial, 2) }}</td>

                <td class="label">Monto pago:</td>
                <td>${{ number_format($contrato->monto_pago, 2) }}</td>
            </tr>

            <tr>
                <td class="label">Frecuencia:</td>
                <td>{{ ucfirst($contrato->frecuencia) }}</td>

                <td class="label">Recargo:</td>
                <td>${{ number_format($contrato->valor_recargo, 2) }}</td>
            </tr>

            <tr>
                <td class="label">Día de pago:</td>
                <td>{{ $diaPago }}</td>

                <td class="label">Total de pagos:</td>
                <td>{{ $totalPagos }}</td>
            </tr>

        </table>
    </div>

    <table class="table">

        <thead>
            <tr>

                <th style="width:5%;">#</th>

                <th style="width:14%;">
                    Fecha
                </th>

                {{-- MÁS ANGOSTAS --}}
                <th style="width:14%;">
                    Activo
                </th>

                <th style="width:14%;">
                    Abono
                </th>

                <th style="width:12%;">
                    Resto
                </th>

                {{-- MÁS GRANDES --}}
                <th style="width:25%;">
                    Firma
                </th>

                <th style="width:15%;">
                    Fecha
                </th>

            </tr>
        </thead>

        <tbody>

            @php
                $saldo           = (float) $contrato->saldo_inicial;
                $saldoActualReal = (float) ($contrato->saldo_actual ?? 0);
                $primerPendiente = true;
                $prevMonth       = null;
                $monthBand       = false; // false = blanco, true = azul claro
            @endphp

            @foreach($contrato->cuotas as $c)

                @php
                    $esPagada = strtolower((string)$c->estatus) === 'pagada';

                    // Al llegar a la primera cuota no pagada, saltar al saldo actual
                    // real del contrato (incorpora abonos a capital y otros ajustes)
                    if (!$esPagada && $primerPendiente) {
                        $saldo           = $saldoActualReal;
                        $primerPendiente = false;
                    }

                    $vence = $c->fecha_vencimiento;
                    $month = $vence?->format('Y-m');

                    $activo = $saldo;
                    $monto  = (float) $c->monto;
                    $resto  = max(0, round($saldo - $monto, 2));
                    $saldo  = $resto;

                    $monthChanged = $prevMonth !== null && $month !== $prevMonth;

                    if ($contrato->frecuencia === 'semanal' && $monthChanged) {
                        $monthBand = !$monthBand;
                    }

                    $prevMonth = $month;

                    $rowClass = '';
                    if ($contrato->frecuencia === 'semanal') {
                        $rowClass = $monthBand ? 'band-b' : '';
                        if ($monthChanged) {
                            $rowClass .= ' month-change';
                        }
                    }
                @endphp

                <tr class="{{ trim($rowClass) }}">

                    {{-- NUMERO --}}
                    <td class="center">
                        {{ $c->numero }}
                    </td>

                    {{-- FECHA --}}
                    <td class="center">
                        {{ optional($c->fecha_vencimiento)->format('d/m/Y') }}
                    </td>

                    {{-- ACTIVO --}}
                    <td class="right">
                        ${{ number_format($activo, 2) }}
                    </td>

                    {{-- ABONO --}}
                    <td class="right">
                        ${{ number_format($monto, 2) }}
                    </td>

                    {{-- RESTO --}}
                    <td class="right saldo">
                        ${{ number_format($resto, 2) }}
                    </td>

                    {{-- FIRMA MÁS GRANDE --}}
                    <td style="height:20px;"></td>

                    {{-- FECHA FIRMA --}}
                    <td style="height:20px;"></td>

                </tr>

            @endforeach

        </tbody>

    </table>

</body>

</html>