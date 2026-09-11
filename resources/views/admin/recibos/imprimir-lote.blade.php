<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
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
    <div class="no-print">
        <button onclick="window.print()" class="btn">Imprimir navegador</button>

        <a href="{{ route('admin.recibos.pdf-lote', ['token' => $token]) }}" class="btn btn-secondary">
            PDF
        </a>
    </div>
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