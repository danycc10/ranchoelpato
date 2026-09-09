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

        <button id="btnPrintLocal" class="btn">
            Impresora local ({{ $tickets->count() }})
        </button>

        <a href="{{ route('admin.recibos.pdf-lote', ['token' => $token]) }}" class="btn btn-secondary">
            PDF
        </a>

        <div id="printStatus" class="status"></div>
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

@if(($modo ?? 'web') === 'web')
@php
    $recibosParaImprimir = $tickets->map(fn ($ticket) => [
        'folio' => $ticket['recibo']->folio,
        'id' => 'ticket-'.$ticket['recibo']->uuid,
        'tokenUrl' => route('admin.recibos.print-token', $ticket['recibo']),
        'dataUrl' => route('admin.recibos.print-data', $ticket['recibo']),
    ])->values();
@endphp

@include('admin.recibos.partials.ticket-print-js')

<script>
    // Se manda un ticket a la vez, con el token de su propio recibo: es la
    // misma llamada que la impresión individual, repetida.
    const RECIBOS = @json($recibosParaImprimir);

    const btnPrintLocal = document.getElementById('btnPrintLocal');
    const printStatus = document.getElementById('printStatus');

    async function printLocal()
    {
        btnPrintLocal.disabled = true;

        const fallidos = [];
        let enviados = 0;

        for (const recibo of RECIBOS) {

            printStatus.className = 'status';
            printStatus.textContent = `Enviando ${enviados + fallidos.length + 1} de ${RECIBOS.length}: ${recibo.folio}...`;

            try {

                await enviarTicketAImpresoraLocal({
                    tokenUrl: recibo.tokenUrl,
                    dataUrl: recibo.dataUrl,
                    elemento: document.getElementById(recibo.id)
                });

                enviados++;

            } catch (error) {

                console.error(recibo.folio, error);
                fallidos.push(`${recibo.folio} (${error.message})`);

            }
        }

        if (fallidos.length === 0) {
            printStatus.className = 'status ok';
            printStatus.textContent = `${enviados} ticket(s) enviados correctamente.`;
        } else {
            printStatus.className = 'status error';
            printStatus.textContent = `${enviados} enviados. Fallaron: ${fallidos.join(' · ')}`;
        }

        btnPrintLocal.disabled = false;
    }

    btnPrintLocal?.addEventListener('click', printLocal);
</script>
@endif

</body>
</html>