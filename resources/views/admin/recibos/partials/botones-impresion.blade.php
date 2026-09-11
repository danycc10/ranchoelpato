{{--
    Barra de impresión del ticket. El botón del agente solo existe si el cajero
    tiene su computadora emparejada; si no, únicamente imprime por el navegador.

    @param string $pdfUrl
    @param string|null $agenteUrl
    @param bool $tieneAgente
--}}
<div class="no-print">

    <button type="button" onclick="window.print()" class="btn">
        Imprimir navegador
    </button>

    @if(($tieneAgente ?? false) && ($agenteUrl ?? null))
        <button type="button" class="btn" data-print-agent="{{ $agenteUrl }}">
            Imprimir por agente
        </button>
    @endif

    <a href="{{ $pdfUrl }}" class="btn btn-secondary">
        PDF
    </a>

    <p class="status" id="print-agent-status" role="status" hidden></p>
</div>

@if(($tieneAgente ?? false) && ($agenteUrl ?? null))
    <script>
        document.querySelectorAll('[data-print-agent]').forEach(function (boton) {
            boton.addEventListener('click', function () {
                var estado = document.getElementById('print-agent-status');

                boton.disabled = true;
                estado.hidden = false;
                estado.className = 'status';
                estado.textContent = 'Enviando a tu impresora...';

                fetch(boton.dataset.printAgent, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                })
                    .then(function (respuesta) {
                        return respuesta.json().then(function (datos) {
                            return { ok: respuesta.ok, datos: datos };
                        });
                    })
                    .then(function (resultado) {
                        estado.className = 'status ' + (resultado.ok ? 'ok' : 'error');
                        estado.textContent = resultado.datos.message
                            || (resultado.ok ? 'Ticket enviado a tu impresora.' : 'No se pudo enviar el ticket.');
                    })
                    .catch(function () {
                        estado.className = 'status error';
                        estado.textContent = 'No se pudo contactar al sistema. Imprime desde el navegador.';
                    })
                    .finally(function () {
                        boton.disabled = false;
                    });
            });
        });
    </script>
@endif
