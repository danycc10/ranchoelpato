<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Recibo {{ $recibo->folio }}</title>

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

    <button onclick="window.print()" class="btn">
        Imprimir navegador
    </button>

    <a href="{{ route('admin.recibos.pdf', $recibo) }}" class="btn btn-secondary">
        PDF
    </a>

</div>
@endif

<div id="ticket-print-area">
    @include('admin.recibos.partials.recibo-ticket', [
        'recibo' => $recibo,
        'modo' => $modo ?? 'web',
    ])
</div>


</body>
</html>
