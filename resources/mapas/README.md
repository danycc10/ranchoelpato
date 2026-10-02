# Planos de fraccionamientos

`finca-escondida.json` contiene 150 polígonos numerados y las etiquetas Calle 1 a Calle 5 y Acceso, extraídos del bloque `lotes` de `Finca Escondida Levantamiento Fisico.dwg`.

La conversión se hizo localmente con LibreDWG 0.14. Los polígonos se reconstruyeron con la unión e intersección de los segmentos originales, sin prolongaciones ni tolerancias añadidas. Se comprobó que cada polígono contiene una sola etiqueta y que existen exactamente los números del 1 al 150. Las coordenadas de visualización se trasladan y se invierte el eje vertical para SVG. Las superficies calculadas son de referencia; no reemplazan las registradas.

Para vincular el plano, aplicar las migraciones y elegir **Fraccionamientos → Editar → Plano interactivo**. También se puede ejecutar:

```sh
php artisan mapas:vincular UUID_DEL_FRACCIONAMIENTO finca-escondida
```

El vínculo se establece explícitamente por UUID; nunca se adivina por nombre. Los accesos «Ver mapa» aparecen en Fraccionamientos y Lotes. La vista y la actualización de datos requieren sesión y los permisos `admin.ver` y `catalogos.ver`.

Los colores proceden del estado del lote en el catálogo. Solo se asocia una figura si hay una coincidencia única del número en el fraccionamiento seleccionado; los ceros iniciales se normalizan. Un número ausente o repetido entre manzanas se presenta sin vincular. El mapa no modifica datos ni crea los lotes faltantes. «Actualizar disponibilidad» consulta de nuevo el catálogo y mantiene la selección.

Para incorporar otro plano, preparar su JSON con la misma estructura, registrarlo en `MapaLotesService::MAPAS` y vincularlo con el comando. El archivo DWG original y las herramientas de conversión no se necesitan en el servidor.
