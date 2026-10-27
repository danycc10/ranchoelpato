<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Recibos agrupados</title>

    <style id="ticket-css">
@include('admin.recibos.partials.ticket-css')
    </style>

    <style>
        .no-print{
            padding: 12px;
            text-align:center;
        }

        .btn{
            padding: 10px 14px;
            border-radius: 10px;
            border: 1px solid #000;
            background: #000;
            color: #fff;
            font-weight: 700;
            cursor:pointer;
            text-decoration:none;
            display:inline-block;
            margin: 4px;
        }

        .btn-secondary{
            background: #fff;
            color: #000;
        }

        .status{
            margin-top: 8px;
            font-size: 13px;
            font-weight: 700;
        }

        .status.ok{ color: green; }
        .status.error{ color: red; }
    </style>
</head>
<body>

@if(($modo ?? 'web') === 'web')
    @include('admin.recibos.partials.botones-impresion', [
        'pdfUrl' => route('admin.recibos.pdf-lote', ['token' => $token]),
        'agenteUrl' => route('admin.recibos.print-lote-agente', ['token' => $token]),
        'tieneAgente' => $tieneAgente ?? false,
        'clienteSinImprimir' => $tickets->pluck('recibo.cliente')->first(fn ($c) => $c?->recibo_sin_imprimir),
    ])
@endif

@foreach($tickets as $ticket)
    <div class="ticket-page" id="ticket-{{ $ticket['recibo']->uuid }}">
        @include('admin.recibos.partials.recibo-ticket', [
            'recibo' => $ticket['recibo'],
            'recibosAdjuntos' => $ticket['adjuntos'],
            'modo' => $modo ?? 'web',
        ])
    </div>
@endforeach


</body>
</html>