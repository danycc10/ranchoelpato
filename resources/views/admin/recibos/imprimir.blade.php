<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Recibo {{ $recibo->folio }}</title>

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

        @page{
            size: 80mm auto;
            margin: 0;
        }

        @media print{
            .no-print{ display:none !important; }

            body{ margin:0; }

            *{
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .ticket{ margin:0; }
        }
    </style>
</head>
<body>

@if(($modo ?? 'web') === 'web')
<div class="no-print">

    <button onclick="window.print()" class="btn">
        Imprimir navegador
    </button>

    <button id="btnPrintLocal" class="btn">
        Impresora local
    </button>

    <a href="{{ route('admin.recibos.pdf', $recibo) }}" class="btn btn-secondary">
        PDF
    </a>

    <div id="printStatus" class="status"></div>

</div>
@endif

<div id="ticket-print-area">
    @include('admin.recibos.partials.recibo-ticket', [
        'recibo' => $recibo,
        'modo' => $modo ?? 'web',
    ])
</div>

@if(($modo ?? 'web') === 'web')
<script>
    const NODE_URL = 'http://192.168.100.16:3001';
    const PRINT_TOKEN_URL = @json(route('admin.recibos.print-token', $recibo));
    const PRINT_DATA_URL = @json(route('admin.recibos.print-data', $recibo));

    const btnPrintLocal = document.getElementById('btnPrintLocal');
    const printStatus = document.getElementById('printStatus');

    async function getJsonOrThrow(response)
    {
        const raw = await response.text();

        try {
            return JSON.parse(raw);
        } catch (e) {
            throw new Error(`La respuesta no fue JSON. Recibido: ${raw.substring(0, 300)}`);
        }
    }

    async function printLocal()
    {
        btnPrintLocal.disabled = true;
        printStatus.className = 'status';
        printStatus.textContent = 'Preparando ticket...';

        try {

            const tokenResponse = await fetch(PRINT_TOKEN_URL, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json'
                },
                credentials: 'same-origin'
            });

            const tokenResult = await getJsonOrThrow(tokenResponse);

            if (!tokenResponse.ok || !tokenResult.ok) {
                throw new Error(tokenResult.message || 'No se pudo generar token.');
            }

            const dataResponse = await fetch(PRINT_DATA_URL, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json'
                },
                credentials: 'same-origin'
            });

            const dataResult = await getJsonOrThrow(dataResponse);

            if (!dataResponse.ok || !dataResult.ok) {
                throw new Error(dataResult.message || 'No se pudo preparar el ticket.');
            }

            const ticketEl = document.getElementById('ticket-print-area');

            const html = `
                <!doctype html>
                <html lang="es">
                <head>
                    <meta charset="utf-8">
                    <style>
                        body{
                            margin:0;
                            padding:0;
                            background:#fff;
                            color:#000;
                            font-family:Arial,sans-serif;
                            font-size:13px;
                            line-height:1.25;
                        }

                        .ticket{
                            width:72mm;
                            margin:0 auto;
                            padding:8px 8px 10px;
                        }

                        .center{text-align:center;}
                        .right{text-align:right;}
                        .bold{font-weight:800;}
                        .small{font-size:12px;}

                        .muted{
                            font-size:12px;
                            color:#000;
                            font-weight:600;
                        }

                        .hr{
                            border:none;
                            border-top:2px solid #000;
                            margin:8px 0;
                        }

                        .block{ margin:6px 0; }

                        .row{
                            display:table;
                            width:100%;
                        }

                        .row > div{
                            display:table-cell;
                            vertical-align:top;
                        }

                        .row .right{
                            width:1%;
                            white-space:nowrap;
                        }

                        .logo-empresa{
                            max-width:60mm;
                            max-height:18mm;
                            object-fit:contain;
                            margin:0 auto 4px;
                            display:block;
                        }

                        .logo-fracc{
                            max-width:55mm;
                            max-height:18mm;
                            object-fit:contain;
                            margin:6px auto 2px;
                            display:block;
                        }

                        table{
                            width:100%;
                            border-collapse:collapse;
                            margin-top:6px;
                        }

                        th,td{
                            padding:4px 0;
                            vertical-align:top;
                        }

                        thead th{
                            border-bottom:2px solid #000;
                            padding-bottom:6px;
                        }

                        tfoot th{
                            border-top:2px solid #000;
                            padding-top:6px;
                        }

                        @page{
                            size:80mm auto;
                            margin:0;
                        }
                    </style>
                </head>
                <body>
                    ${ticketEl.innerHTML}
                </body>
                </html>
            `;

            printStatus.textContent = 'Enviando a impresora local...';

            const printResponse = await fetch(`${NODE_URL}/print-html`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'x-print-payload': tokenResult.payload,
                    'x-print-signature': tokenResult.signature
                },
                body: JSON.stringify({
                    html: html
                })
            });

            const printResult = await getJsonOrThrow(printResponse);

            if (!printResponse.ok || !printResult.ok) {
                throw new Error(printResult.error || printResult.message || 'No se pudo imprimir.');
            }

            printStatus.className = 'status ok';
            printStatus.textContent = 'Ticket enviado correctamente.';

        } catch (error) {

            console.error(error);

            printStatus.className = 'status error';
            printStatus.textContent = 'Error: ' + error.message;

        } finally {

            btnPrintLocal.disabled = false;

        }
    }

    btnPrintLocal?.addEventListener('click', printLocal);
</script>
@endif

</body>
</html>