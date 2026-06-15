<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Contrato {{ $contrato->folio_contrato }}</title>
    <style>
        @page { margin: 22px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color:#111; }

        .header{
            text-align:center;
            margin-bottom:10px;
        }
        .header .title{
            font-size:18px;
            font-weight:800;
            letter-spacing:.5px;
        }
        .header .sub{
            font-size:12px;
            margin-top:2px;
            color:#444;
        }

        .row { width:100%; }
        .col { display:inline-block; vertical-align:top; }
        .col-66 { width:66%; }
        .col-33 { width:33%; }

        .card{
            border:1px solid #d9d9d9;
            border-radius:12px;
            padding:12px;
            margin-bottom:10px;
        }
        .muted{ color:#666; }
        .hr{ border-top:1px solid #e7e7e7; margin:10px 0; }

        .k{ font-weight:700; }
        .v{ }

        table{
            width:100%;
            border-collapse:collapse;
            font-size:10.5px;
        }
        thead th{
            background:#f3f4f6;
            padding:8px;
            text-align:left;
            border-bottom:1px solid #e5e7eb;
        }
        tbody td{
            padding:7px 8px;
            border-top:1px solid #eee;
        }
        .right{ text-align:right; }
        .center{ text-align:center; }

        .badge{
            display:inline-block;
            padding:2px 8px;
            border-radius:999px;
            font-size:10px;
            border:1px solid #ddd;
        }

        .section-title{
            font-weight:800;
            background:#f3f4f6;
            padding:8px 12px;
            border:1px solid #e5e7eb;
            border-bottom:none;
            border-top-left-radius:12px;
            border-top-right-radius:12px;
        }
        .table-wrap{
            border:1px solid #e5e7eb;
            border-bottom-left-radius:12px;
            border-bottom-right-radius:12px;
            overflow:hidden;
        }

        .green { color:#0f7a2a; font-weight:700; }
    </style>
</head>
<body>

    <div class="header">
        {{-- Puedes poner "FINCA ..." como en tu foto --}}
        <div class="title">{{ mb_strtoupper($contrato->lote?->fraccionamiento?->nombre ?? 'CONTRATO') }}</div>
        <div class="sub">
            Contrato {{ $contrato->folio_contrato }} <span class="muted">· Detalle del contrato y cuotas</span>
        </div>
    </div>

    <div class="row">
        <div class="col col-66" style="padding-right:8px;">
            <div class="card">
                <div><span class="k">Cliente:</span> <span class="v">{{ $contrato->cliente?->nombre_completo ?? '—' }}</span></div>
                <div><span class="k">Lote:</span> <span class="v">{{ $contrato->lote?->clave ?? '—' }}</span></div>
                <div><span class="k">Fraccionamiento:</span> <span class="v">{{ $contrato->lote?->fraccionamiento?->nombre ?? '—' }}</span></div>
                <div><span class="k">Propietario:</span> <span class="v">{{ $contrato->lote?->fraccionamiento?->propietario?->nombre ?? '—' }}</span></div>

                <div class="hr"></div>

                <div><span class="k">Inicio:</span> <span class="v">{{ optional($contrato->fecha_inicio)->format('d/m/Y') }}</span></div>
                <div>
                    <span class="k">Frecuencia:</span>
                    <span class="v">{{ $contrato->frecuencia === 'semanal' ? 'Semanal' : 'Mensual' }}</span>
                </div>

                @if($contrato->frecuencia === 'semanal')
                    <div><span class="k">Día semanal:</span> <span class="v">{{ $diasSemana[$contrato->dia_semana] ?? $contrato->dia_semana }}</span></div>
                @else
                    <div><span class="k">Día del mes:</span> <span class="v">{{ $contrato->dia_mes }}</span></div>
                @endif

                <div>
                    <span class="k">Estatus:</span>
                    <span class="badge">{{ ucfirst($contrato->estatus) }}</span>
                </div>

                @if($contrato->promocion)
                    <div class="green"><span class="k">Promoción:</span> {{ $contrato->promocion->nombre }}</div>
                @endif
            </div>
        </div>

        <div class="col col-33">
            <div class="card">
                <div><span class="k">Precio total:</span> <span class="v">${{ number_format((float)$contrato->precio_total,2) }}</span></div>
                <div><span class="k">Enganche:</span> <span class="v">${{ number_format((float)$contrato->enganche,2) }}</span></div>
                <div><span class="k">Saldo inicial:</span> <span class="v">${{ number_format((float)$contrato->saldo_inicial,2) }}</span></div>
                <div><span class="k">Saldo actual:</span> <span class="v">${{ number_format((float)$contrato->saldo_actual,2) }}</span></div>
                <div><span class="k">Monto pago:</span> <span class="v">${{ number_format((float)$contrato->monto_pago,2) }}</span></div>

                <div class="hr"></div>

                <div><span class="k">Recargo:</span> <span class="v">{{ $contrato->tipo_recargo }} (${{ number_format((float)$contrato->valor_recargo,2) }})</span></div>
                <div><span class="k">Días gracia:</span> <span class="v">{{ $contrato->dias_gracia }}</span></div>
            </div>
        </div>
    </div>

    <div class="section-title">Cuotas</div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th style="width:38px;">#</th>
                    <th style="width:70px;">Vence</th>
                    <th class="right" style="width:80px;">Monto</th>
                    <th class="right" style="width:80px;">Pagado</th>
                    <th class="right" style="width:80px;">Recargo</th>
                    <th class="right" style="width:95px;">Saldo planeado</th>
                    <th style="width:75px;">Estatus</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $saldoPlaneado = (float) ($contrato->saldo_inicial ?? 0);
                @endphp

                @forelse($contrato->cuotas as $c)
                    @php
                        // ✅ monto planeado: baja por el monto de la cuota (no por pagado)
                        $saldoPlaneado = max(0, round($saldoPlaneado - (float)$c->monto, 2));
                    @endphp
                    <tr>
                        <td>{{ $c->numero }}</td>
                        <td>{{ optional($c->fecha_vencimiento)->format('d/m/Y') }}</td>
                        <td class="right">${{ number_format((float)$c->monto,2) }}</td>
                        <td class="right">${{ number_format((float)$c->pagado_total,2) }}</td>
                        <td class="right">${{ number_format((float)$c->recargo_aplicado,2) }}</td>
                        <td class="right"><b>${{ number_format((float)$saldoPlaneado,2) }}</b></td>
                        <td>{{ ucfirst($c->estatus) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="muted" style="padding:12px;">Sin cuotas.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

</body>
</html>