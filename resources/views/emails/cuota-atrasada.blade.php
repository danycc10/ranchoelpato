<x-mail::message>
# Pago atrasado

Hola {{ $p['cliente'] ?? 'cliente' }},

Te recordamos que tienes una cuota pendiente.

<x-mail::table>
| Concepto | Informacion |
|:---------|:------------|
| Contrato | {{ $p['contrato'] ?? 'N/A' }} |
| Lote | {{ $p['lote'] ?? 'N/A' }} |
| Fraccionamiento | {{ $p['fraccionamiento'] ?? 'N/A' }} |
| Vencimiento | {{ isset($p['vencimiento']) ? \Carbon\Carbon::parse($p['vencimiento'])->format('d/m/Y') : 'N/A' }} |
| Monto | ${{ number_format((float)($p['monto'] ?? 0), 2) }} |
| Recargo | ${{ number_format((float)($p['recargo'] ?? 0), 2) }} |
| Total a pagar | ${{ number_format((float)($p['total'] ?? 0), 2) }} |
</x-mail::table>

Te recomendamos realizar tu pago lo antes posible para evitar cargos adicionales.

Gracias,  
{{ config('app.name') }}
</x-mail::message>