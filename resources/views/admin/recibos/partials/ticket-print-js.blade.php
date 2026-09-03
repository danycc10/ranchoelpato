{{-- Envío a la impresora local. Lo comparten la impresión individual y la de
     lote: en las dos se manda un ticket a la vez, con el mismo HTML y el mismo
     token por recibo que ya valida el puente de impresión. --}}
<script>
    const NODE_URL = @json(config('services.print_bridge.url'));

    /**
     * Documento de impresión de un ticket, con el CSS de esta misma página.
     */
    function crearDocumentoImpresion(elemento)
    {
        // Siempre se manda el ticket en sí, venga suelto o dentro de su hoja.
        const ticketEl = elemento.classList.contains('ticket')
            ? elemento
            : (elemento.querySelector('.ticket') || elemento);

        const printDocument = document.implementation.createHTMLDocument('Recibo');
        printDocument.documentElement.lang = 'es';

        const meta = printDocument.createElement('meta');
        meta.setAttribute('charset', 'utf-8');
        printDocument.head.appendChild(meta);

        const style = printDocument.createElement('style');
        style.textContent = document.getElementById('ticket-css').textContent;
        printDocument.head.appendChild(style);

        printDocument.body.appendChild(ticketEl.cloneNode(true));

        return '<!doctype html>' + printDocument.documentElement.outerHTML;
    }

    async function getJsonOrThrow(response)
    {
        const raw = await response.text();

        try {
            return JSON.parse(raw);
        } catch (e) {
            throw new Error(`La respuesta no fue JSON. Recibido: ${raw.substring(0, 300)}`);
        }
    }

    /**
     * Manda un ticket a la impresora local.
     *
     * Recibe un objeto con tokenUrl, dataUrl y elemento (el nodo del ticket).
     */
    async function enviarTicketAImpresoraLocal(recibo)
    {
        const tokenResponse = await fetch(recibo.tokenUrl, {
            method: 'GET',
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin'
        });

        const tokenResult = await getJsonOrThrow(tokenResponse);

        if (!tokenResponse.ok || !tokenResult.ok) {
            throw new Error(tokenResult.message || 'No se pudo generar token.');
        }

        const dataResponse = await fetch(recibo.dataUrl, {
            method: 'GET',
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin'
        });

        const dataResult = await getJsonOrThrow(dataResponse);

        if (!dataResponse.ok || !dataResult.ok) {
            throw new Error(dataResult.message || 'No se pudo preparar el ticket.');
        }

        const printResponse = await fetch(NODE_URL + '/print-html', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'x-print-payload': tokenResult.payload,
                'x-print-signature': tokenResult.signature
            },
            body: JSON.stringify({
                html: crearDocumentoImpresion(recibo.elemento)
            })
        });

        const printResult = await getJsonOrThrow(printResponse);

        if (!printResponse.ok || !printResult.ok) {
            throw new Error(printResult.error || printResult.message || 'No se pudo imprimir.');
        }
    }
</script>
