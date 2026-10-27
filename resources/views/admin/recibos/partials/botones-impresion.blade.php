{{--
    Barra de impresión del ticket. El botón del agente solo existe si el cajero
    tiene su computadora emparejada; si no, únicamente imprime por el navegador.

    @param string $pdfUrl
    @param string|null $agenteUrl
    @param bool $tieneAgente
    @param \App\Models\Cliente|null $clienteSinImprimir el que pidió su recibo solo por WhatsApp
--}}
<div class="no-print">

    @if($clienteSinImprimir ?? null)
        <style>
            .aviso-sin-imprimir{
                max-width: 420px;
                margin: 0 auto 10px;
                padding: 12px;
                border: 2px solid #d97706;
                border-radius: 10px;
                background: #fffbeb;
                color: #78350f;
                font-size: 14px;
            }

            .aviso-sin-imprimir strong{
                display: block;
                font-size: 18px;
                margin-bottom: 4px;
            }
        </style>

        <div class="aviso-sin-imprimir" role="alert">
            <strong>NO IMPRIMIR FÍSICAMENTE</strong>
            @if($clienteSinImprimir->telefonoParaWhatsapp())
                {{ $clienteSinImprimir->nombre_completo }} pidió su recibo solo en PDF:
                se le manda por WhatsApp al {{ $clienteSinImprimir->telefonoParaWhatsapp() }}.
            @else
                {{ $clienteSinImprimir->nombre_completo }} pidió no imprimirlo, pero no tiene
                WhatsApp registrado: imprímelo esta vez y agrega su número en Clientes.
            @endif
        </div>
    @endif

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
