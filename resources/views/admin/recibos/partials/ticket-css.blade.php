{{-- Estilos del ticket. Los comparten la impresión individual, la de lote y
     el documento que se manda a la impresora local, para que el recibo salga
     idéntico se imprima como se imprima. --}}
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

/* Cada recibo de un lote ocupa su propia hoja, del alto de su contenido. */
.ticket-page{
    width: 80mm;
    margin: 0 auto;
    page-break-after: always;
    break-after: page;
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
/* Solo 400 y 700 existen en Arial/Helvetica: con 600 u 800 el PDF del
   agente cae a una serif y el ticket sale con otra tipografia. */
.bold{ font-weight: 700; }
.small{ font-size: var(--small); }

.muted{
    font-size: var(--small);
    color:#000;
    font-weight: 700;
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

    .ticket{ margin:0 auto; }
}
