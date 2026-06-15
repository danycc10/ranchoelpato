<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Recibos agrupados</title>

    <style>
        :root{
            --ticket-width: 72mm;
            --font: 13px;
            --small: 12px;
        }

        * { box-sizing: border-box; }

        body{
            margin:0;
            padding:0;
            font-family: Arial, sans-serif;
            font-size: var(--font);
            line-height: 1.25;
            color:#000;
            background:#fff;
        }

        .ticket-page{
            width: 80mm;
            min-height: 210mm;
            margin: 0 auto;
            page-break-after: always;
            break-after: page;
            overflow: hidden;
        }

        .ticket-page:last-child{
            page-break-after: auto;
            break-after: auto;
        }

        .ticket{
            width: var(--ticket-width);
            margin: 0 auto;
            padding: 8px 8px 10px;
        }

        .center{ text-align:center; }
        .right{ text-align:right; }
        .bold{ font-weight: 800; }
        .small{ font-size: var(--small); }

        .muted{
            font-size: var(--small);
            color:#000;
            font-weight: 600;
        }

        .hr{
            border: none;
            border-top: 2px solid #000;
            margin: 8px 0;
        }

        .block{ margin: 6px 0; }

        .row{
            display: table;
            width: 100%;
        }

        .row > div{
            display: table-cell;
            vertical-align: top;
        }

        .row .right{
            width: 1%;
            white-space: nowrap;
        }

        .logo-empresa{
            max-width: 60mm;
            max-height: 18mm;
            object-fit: contain;
            margin: 0 auto 4px;
            display:block;
            image-rendering: crisp-edges;
        }

        .logo-fracc{
            max-width: 55mm;
            max-height: 18mm;
            object-fit: contain;
            margin: 6px auto 2px;
            display:block;
            image-rendering: crisp-edges;
        }

        table{
            width:100%;
            border-collapse: collapse;
            margin-top: 6px;
        }

        th, td{
            padding: 4px 0;
            vertical-align: top;
        }

        thead th{
            border-bottom: 2px solid #000;
            padding-bottom: 6px;
        }

        tfoot th{
            border-top: 2px solid #000;
            padding-top: 6px;
        }

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
        }

        @page{
            size: 80mm 210mm;
            margin: 0;
        }

        @media print{
            .no-print{ display:none !important; }
            body{ margin:0; }
            *{
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>

@if(($modo ?? 'web') === 'web')
    <div class="no-print">
        <button onclick="window.print()" class="btn">Imprimir</button>

        <a href="{{ route('admin.recibos.pdf-lote', ['token' => $token]) }}"
           class="btn"
           style="margin-left:8px;">
            PDF
        </a>
    </div>
@endif

@foreach($recibos as $recibo)
    <div class="ticket-page">
        @include('admin.recibos.partials.recibo-ticket', [
            'recibo' => $recibo,
            'modo' => $modo ?? 'web',
        ])
    </div>
@endforeach

</body>
</html>