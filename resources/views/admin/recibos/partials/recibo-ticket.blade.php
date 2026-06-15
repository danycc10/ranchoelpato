@php
use Illuminate\Support\Facades\Storage;

$modo = $modo ?? 'web';

$toBase64Public = function (?string $relativePath) {
if (!$relativePath) return null;
if (!Storage::disk('public')->exists($relativePath)) return null;

$abs = Storage::disk('public')->path($relativePath);
$mime = @mime_content_type($abs) ?: 'image/jpeg';
$data = base64_encode(@file_get_contents($abs));
if (!$data) return null;

return "data:{$mime};base64,{$data}";
};
@endphp

@php
$fracc = $recibo->lote?->fraccionamiento;

$logoEmpresaSrc = $toBase64Public('images/logo-espinoza.jpeg');

$logoFraccSrc = null;

if (!empty($fracc?->logo_path)) {
$logoFraccSrc = $toBase64Public($fracc->logo_path);
}

if (!$logoFraccSrc && !empty($fracc?->logo_url)) {
$url = (string) $fracc->logo_url;

$pos = mb_stripos($url, '/storage/');
if ($pos !== false) {
$relative = mb_substr($url, $pos + mb_strlen('/storage/'));
$logoFraccSrc = $toBase64Public($relative);
} else {
$pos2 = mb_stripos($url, 'storage/');
if ($pos2 !== false) {
$relative = mb_substr($url, $pos2 + mb_strlen('storage/'));
$logoFraccSrc = $toBase64Public($relative);
}
}
}

$saldoRestante = is_null($recibo->saldo_posterior)
? (float) ($recibo->contrato?->saldo_actual ?? 0)
: (float) $recibo->saldo_posterior;

$tcUpper = mb_strtoupper((string)($recibo->tipoCobro?->nombre ?? ''));
$esRecargo = str_contains($tcUpper, 'RECARGO');

$esMensualidad = str_contains($tcUpper, 'MENSUAL');
$frecuenciaContrato = mb_strtolower((string)($recibo->contrato?->frecuencia ?? ''));
$contratoEsSemanal = str_contains($frecuenciaContrato, 'seman');

$tipoCobroMostrar = $recibo->tipoCobro?->nombre ?? '—';
if ($esMensualidad && $contratoEsSemanal) {
$tipoCobroMostrar = 'SEMANAL';
}

$cuota = $recibo->cuota ?? null;

$labelPeriodo = $contratoEsSemanal ? 'Semana' : 'Mes';

$numeroPeriodo = $cuota?->numero;
if (!$numeroPeriodo) {
$numeroPeriodo = $contratoEsSemanal
? ($recibo->semana_pago ?? null)
: ($recibo->mes_del_anio ?? null);
}

$mesPagadoTexto = null;
if (!$contratoEsSemanal) {
$fechaRef = $cuota?->fecha_vencimiento
? \Carbon\Carbon::parse($cuota->fecha_vencimiento)
: \Carbon\Carbon::parse($recibo->fecha);

$mesPagadoTexto = mb_strtoupper($fechaRef->translatedFormat('F Y'));
}

$conceptoBase = $esRecargo ? 'Recargo' : 'Pago';
$conceptoPeriodo = null;

if ($numeroPeriodo) {
if ($contratoEsSemanal) {
$conceptoPeriodo = "{$conceptoBase} de Semana #{$numeroPeriodo}";
} else {
$conceptoPeriodo = "{$conceptoBase} de Mes #{$numeroPeriodo}";
if ($mesPagadoTexto) {
$conceptoPeriodo .= " · {$mesPagadoTexto}";
}
}
} else {
$conceptoPeriodo = $esRecargo ? 'Recargo' : 'Pago registrado';
}

$pagosDetalle = collect($recibo->pagosDetalle ?? [])
->filter(fn ($p) => is_null($p->deleted_at ?? null))
->values();

if ($pagosDetalle->isEmpty()) {
$pagosDetalle = collect([
(object) [
'formaPago' => $recibo->formaPago,
'cuentaBancaria' => $recibo->cuentaBancaria,
'monto' => (float) $recibo->monto,
'referencia' => null,
]
]);
}

$totalPagosDetalle = (float) $pagosDetalle->sum(fn ($p) => (float) ($p->monto ?? 0));
@endphp

<div class="ticket">

    <div class="center">
        @if($logoEmpresaSrc)
        <img class="logo-empresa" src="{{ $logoEmpresaSrc }}" alt="Espinoza y Asociados">
        @endif

        <div class="bold" style="font-size:14px; letter-spacing:.2px;">ESPINOZA Y ASOCIADOS</div>
        <div class="bold small">Tel: 878-785-1461</div>
    </div>

    <hr class="hr">

    <div class="center">
        <div class="bold" style="font-size:18px; letter-spacing: .5px;">RECIBO</div>

        <div class="small">
            Folio: <span class="bold">{{ $recibo->folio }}</span>
        </div>

        <div class="small">
            Fecha:
            <span class="bold">
                {{ \Carbon\Carbon::parse($recibo->fecha)->format('d/m/Y') }}
                {{ $recibo->created_at ? '· '.$recibo->created_at->format('h:i A') : '' }}
            </span>
        </div>
    </div>

    <hr class="hr">

    <div class="block">
        <div class="muted">Cliente:</div>
        <div class="bold" style="font-size:14px;">{{ $recibo->cliente?->nombre_completo }}</div>
        <div class="small">
            Tel: <span class="bold">{{ $recibo->cliente?->telefono ?? '—' }}</span>
            @if($recibo->cliente?->correo)
            · <span class="bold">{{ $recibo->cliente->correo }}</span>
            @endif
        </div>
    </div>

    <div class="block">
        <div class="row">
            <div>
                <div class="muted">Lote:</div>
                <div class="bold">{{ $recibo->lote?->clave ?? '—' }}</div>
            </div>
        </div>
    </div>

    <hr class="hr">

    <div class="block small">
        <div class="row">
            <div class="muted">Tipo cobro:</div>
            <div class="right bold">{{ $tipoCobroMostrar }}</div>
        </div>

        @if($numeroPeriodo)
        <div class="row">
            <div class="muted">{{ $labelPeriodo }}:</div>
            <div class="right bold">#{{ $numeroPeriodo }}</div>
        </div>
        @endif

        @if(!$contratoEsSemanal && $mesPagadoTexto)
        <div class="row">
            <div class="muted">Mes:</div>
            <div class="right bold">{{ $mesPagadoTexto }}</div>
        </div>
        @endif

        @if($pagosDetalle->count() === 1)
        @php $pagoUnico = $pagosDetalle->first(); @endphp

        <div class="row">
            <div class="muted">Forma pago:</div>
            <div class="right bold">{{ $pagoUnico->formaPago?->nombre ?? '—' }}</div>
        </div>

        @if($pagoUnico->cuentaBancaria)
        <div class="row">
            <div class="muted">Cuenta:</div>
            <div class="right bold">{{ $pagoUnico->cuentaBancaria->alias }}</div>
        </div>
        @endif

        @if(!empty($pagoUnico->referencia))
        <div class="row">
            <div class="muted">Referencia:</div>
            <div class="right bold">{{ $pagoUnico->referencia }}</div>
        </div>
        @endif
        @else
        <div class="row">
            <div class="muted">Forma pago:</div>
            <div class="right bold">MÚLTIPLE</div>
        </div>
        @endif

        @if($recibo->periodo)
        <div class="row">
            <div class="muted">Periodo:</div>
            <div class="right bold">{{ $recibo->periodo->nombre }}</div>
        </div>
        @endif
    </div>

    <hr class="hr">

    <table>
        <thead>
            <tr>
                <th class="bold">Concepto</th>
                <th class="right bold">Importe</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="bold">{{ $conceptoPeriodo }}</td>
                <td class="right bold">${{ number_format((float)$recibo->monto, 2) }}</td>
            </tr>
        </tbody>
        <tfoot>
            <tr>
                <th class="right bold">TOTAL</th>
                <th class="right bold">${{ number_format((float)$recibo->monto, 2) }}</th>
            </tr>
        </tfoot>
    </table>

    @if($pagosDetalle->count() > 1)
    <hr class="hr">

    <div class="block small">
        <div class="bold" style="margin-bottom:6px;">Desglose de formas de pago</div>

        @foreach($pagosDetalle as $pago)
        <div class="row" style="margin-bottom:4px;">
            <div>
                <span class="bold">{{ $pago->formaPago?->nombre ?? '—' }}</span>
                @if($pago->cuentaBancaria)
                <span class="muted"> · {{ $pago->cuentaBancaria->alias }}</span>
                @endif
                @if(!empty($pago->referencia))
                <div class="muted">Ref: {{ $pago->referencia }}</div>
                @endif
            </div>
            <div class="right bold">
                ${{ number_format((float) ($pago->monto ?? 0), 2) }}
            </div>
        </div>
        @endforeach

        <div class="row" style="margin-top:6px; border-top:1px dashed #000; padding-top:6px;">
            <div class="bold">Total formas de pago</div>
            <div class="right bold">${{ number_format($totalPagosDetalle, 2) }}</div>
        </div>
    </div>
    @endif

    @if(!$esRecargo)
    <hr class="hr">

    <div class="block">
        <div class="row">
            <div class="bold" style="font-size:14px;">SALDO RESTANTE</div>
            <div class="right bold" style="font-size:14px;">
                ${{ number_format($saldoRestante, 2) }}
            </div>
        </div>
        <div class="small">(Saldo después de aplicar este pago)</div>
    </div>
    @endif

    @if($recibo->observaciones)
    <hr class="hr">
    <div class="block small">
        <div class="bold">Observaciones:</div>
        <div class="bold">{{ $recibo->observaciones }}</div>
    </div>
    @endif

    <hr class="hr">

    <div class="block small">
        <div class="muted">Capturado por:</div>
        <div class="bold">{{ $recibo->capturadoPor?->name ?? '—' }}</div>
    </div>

    @if($logoFraccSrc || $fracc?->nombre)
    <hr class="hr">
    <div class="center">
        @if($logoFraccSrc)
        <img class="logo-fracc" src="{{ $logoFraccSrc }}" alt="Logo {{ $fracc?->nombre }}">
        @endif
        @if($fracc?->nombre)
        <div class="bold small">{{ mb_strtoupper($fracc->nombre) }}</div>
        @endif
    </div>
    @endif

    <div class="center bold small" style="margin-top:10px;">
        @if($esRecargo)
        Pagando a tiempo evita recargos.
        @else
        Gracias por su pago puntual.
        @endif
    </div>
</div>