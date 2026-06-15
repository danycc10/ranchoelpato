<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Prueba de impresión HTML</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 24px;
        }
        button {
            padding: 12px 18px;
            border: 0;
            border-radius: 10px;
            background: #111827;
            color: white;
            cursor: pointer;
            margin-right: 8px;
            margin-bottom: 8px;
        }
        button:disabled {
            opacity: .6;
            cursor: not-allowed;
        }
        .ok { color: green; }
        .error { color: red; }

        .ticket-wrap {
            margin-top: 20px;
        }

        .ticket {
            width: 72mm;
            margin: 0 auto;
            padding: 8px 8px 10px;
            background: #fff;
            color: #000;
            font-family: Arial, sans-serif;
            font-size: 13px;
            line-height: 1.25;
        }

        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: 800; }

        .hr {
            border: none;
            border-top: 2px solid #000;
            margin: 8px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }

        th, td {
            padding: 4px 0;
            vertical-align: top;
        }

        thead th {
            border-bottom: 2px solid #000;
            padding-bottom: 6px;
        }

        @page {
            size: 80mm auto;
            margin: 0;
        }
    </style>
</head>
<body>
    <h1>Prueba de impresión local</h1>

    <button id="btnPrintHtml">Imprimir HTML por Windows</button>
    <p id="status"></p>

    <div class="ticket-wrap">
        <div id="ticketHtmlTest" class="ticket">
            <div class="center bold">ESPINOZA Y ASOCIADOS</div>
            <div class="center">RECIBO DE PAGO</div>

            <hr class="hr">

            <div>Folio: REC-000001</div>
            <div>Fecha: 23/04/2026 12:00</div>
            <div>Cliente: Juan Pérez</div>
            <div>Lote: A-12</div>

            <hr class="hr">

            <table>
                <thead>
                    <tr>
                        <th align="left">Concepto</th>
                        <th align="right">Importe</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Mensualidad</td>
                        <td class="right">$1,500.00</td>
                    </tr>
                    <tr>
                        <td>Recargo</td>
                        <td class="right">$50.00</td>
                    </tr>
                </tbody>
            </table>

            <hr class="hr">

            <div class="bold right">TOTAL: $1,550.00</div>

            <hr class="hr">

            <div class="center">Gracias por su pago</div>
        </div>
    </div>

<script>
    const NODE_URL = 'http://192.168.100.15:3001';
    const API_KEY = 'mi-clave-segura';
    const PRINTER_NAME = 'POS-80 (1)';

    const btn = document.getElementById('btnPrintHtml');
    const statusEl = document.getElementById('status');

    async function getJsonOrThrow(response) {
        const raw = await response.text();

        try {
            return JSON.parse(raw);
        } catch (e) {
            throw new Error(`La respuesta no fue JSON. Recibido: ${raw.substring(0, 300)}`);
        }
    }

    async function printHtml() {
        btn.disabled = true;
        statusEl.className = '';
        statusEl.textContent = 'Preparando HTML...';

        try {
            const ticketEl = document.getElementById('ticketHtmlTest');

            const html = `
                <!doctype html>
                <html lang="es">
                <head>
                    <meta charset="utf-8">
                    <title>Ticket</title>
                    <style>
                        body {
                            margin: 0;
                            padding: 0;
                            background: #fff;
                        }
                        .ticket {
                            width: 72mm;
                            margin: 0 auto;
                            padding: 8px 8px 10px;
                            background: #fff;
                            color: #000;
                            font-family: Arial, sans-serif;
                            font-size: 13px;
                            line-height: 1.25;
                        }
                        .center { text-align: center; }
                        .right { text-align: right; }
                        .bold { font-weight: 800; }
                        .hr {
                            border: none;
                            border-top: 2px solid #000;
                            margin: 8px 0;
                        }
                        table {
                            width: 100%;
                            border-collapse: collapse;
                            margin-top: 6px;
                        }
                        th, td {
                            padding: 4px 0;
                            vertical-align: top;
                        }
                        thead th {
                            border-bottom: 2px solid #000;
                            padding-bottom: 6px;
                        }
                        @page {
                            size: 80mm auto;
                            margin: 0;
                        }
                    </style>
                </head>
                <body>
                    ${ticketEl.outerHTML}
                </body>
                </html>
            `;

            statusEl.textContent = 'Enviando a Windows...';

            const response = await fetch(`${NODE_URL}/print-html`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'x-api-key': API_KEY
                },
                body: JSON.stringify({
                    html,
                    printerName: PRINTER_NAME
                })
            });

            const result = await getJsonOrThrow(response);

            if (!response.ok) {
                throw new Error(result.error || result.message || 'No se pudo imprimir.');
            }

            statusEl.className = 'ok';
            statusEl.textContent = 'HTML enviado correctamente a la impresora.';
        } catch (error) {
            console.error(error);
            statusEl.className = 'error';
            statusEl.textContent = 'Error: ' + error.message;
        } finally {
            btn.disabled = false;
        }
    }

    btn.addEventListener('click', printHtml);
</script>
</body>
</html>