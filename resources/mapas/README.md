# Planos de fraccionamientos

La galería **Administración → Mapas** (`/admin/mapas`) muestra todos los planos registrados, con una miniatura y los fraccionamientos vinculados. También se accede desde **Fraccionamientos → Todos los mapas**. Los planos sin vínculos se pueden explorar sin crear registros: sus lotes permanecen sin disponibilidad confirmada. La galería, el visor por clave y su endpoint de actualización requieren los mismos permisos de catálogo que el visor por fraccionamiento.

La ficha muestra las medidas y colindancias capturadas en el catálogo por norte, sur, este y oeste, conservando su contenido. No calcula longitudes desde el DWG ni completa campos vacíos con la geometría: muestra «Sin capturar». Un lote sin vinculación única no recibe datos de otro registro. La actualización vuelve a consultar estos campos del catálogo. Las referencias geométricas L1, L2… ya no se muestran, porque no establecen correspondencia con los puntos cardinales capturados.

`finca-escondida.json` contiene 150 polígonos numerados y las etiquetas Calle 1 a Calle 5 y Acceso, extraídos del bloque `lotes` de `Finca Escondida Levantamiento Fisico.dwg`.

La conversión se hizo localmente con LibreDWG 0.14. Los polígonos se reconstruyeron con la unión e intersección de los segmentos originales, sin prolongaciones ni tolerancias añadidas. Se comprobó que cada polígono contiene una sola etiqueta y que existen exactamente los números del 1 al 150. Las coordenadas de visualización se trasladan y se invierte el eje vertical para SVG. Las superficies calculadas son de referencia; no reemplazan las registradas.

Para vincular el plano, aplicar las migraciones y elegir **Fraccionamientos → Editar → Plano interactivo**. También se puede ejecutar:

```sh
php artisan mapas:vincular UUID_DEL_FRACCIONAMIENTO finca-escondida
```

El vínculo se establece explícitamente por UUID; nunca se adivina por nombre. Los accesos «Ver mapa» aparecen en Fraccionamientos y Lotes. La vista y la actualización de datos requieren sesión y los permisos `admin.ver` y `catalogos.ver`.

Los registros de fraccionamiento vinculados a la misma clave de plano se muestran juntos. Cada lote conserva su propietario, estado y registro de origen; su ficha y enlace al catálogo muestran esa procedencia. Entrar desde cualquiera de los registros vinculados abre la misma vista conjunta. Para un predio dividido entre tres propietarios, seleccionar el mismo plano en los tres registros.

Los colores proceden del estado del lote en el catálogo. Solo se asocia una figura si hay una coincidencia única del número entre todos los registros vinculados; los ceros iniciales se normalizan. Un número ausente o repetido entre propietarios o manzanas se presenta sin vincular, con un aviso que identifica los registros en conflicto. No se elige un propietario automáticamente ni se suman los registros repetidos como lotes distintos. El mapa no modifica datos ni crea los lotes faltantes. «Actualizar disponibilidad» consulta de nuevo el catálogo y mantiene la selección.

Para incorporar otro plano, preparar su JSON con la misma estructura, registrarlo en `MapaLotesService::MAPAS` y vincularlo con el comando. El archivo DWG original y las herramientas de conversión no se necesitan en el servidor.

## Planos incorporados en octubre de 2026

| Clave | Figuras numeradas | Tratamiento |
| --- | ---: | --- |
| `ballesteros` | 48 | Cierre del lote 24 con extensión máxima de 0.0001 unidades por extremo. |
| `finca-del-rio` | 255 | Contornos originales; ubicación de MTEXT por su centro real, alineación y orientación. |
| `finca-espinoza` | 49 | Bloque `plano`; 22 figuras en M1, 8 en M2 y 19 en M3. |
| `finca-del-norte` | 340 | Bloque `lotes norte`; cierre de pequeños huecos con extensiones de hasta 0.05 unidades por extremo. |
| `finca-miranda` | 200 | Modelo y bloques insertados `fisico` y `lotees`, respetando traslación y rotación; extensiones máximas de 0.05 unidades. |
| `reyes` | 68 | Bloque `lotificacion` de `Reyes Geo.dwg`; cierre de diferencias de precisión con extensiones máximas de 0.000001 unidades. |

Las extensiones siguen la dirección de los segmentos originales. No se trazan divisiones a partir de la posición de las etiquetas. La conversión exige una sola etiqueta por polígono, que todas las etiquetas candidatas tengan figura, geometrías válidas y ausencia de solapamientos después de serializar las coordenadas. Las cifras anteriores son figuras numeradas, no un conteo catastral de toda superficie sin etiqueta. Los DWG originales permanecen intactos. Las unidades declaradas por algunos archivos son inconsistentes con sus cotas; las superficies del dibujo siguen siendo orientativas y no sustituyen al catálogo.

Cada figura nueva tiene un `id` estable basado en el identificador CAD. Espinoza incorpora `manzana_plano`, asignada por los rótulos M1/M2/M3 dentro de cada conjunto conectado de parcelas. El servicio relaciona manzana y número en ese plano; normaliza espacios, mayúsculas y ceros iniciales (`17 A` equivale a `17A`). La búsqueda ofrece todas las coincidencias y la selección/actualización conserva la figura individual.

En Miranda hay números repetidos en posiciones distintas. No basta que exista un registro único en el catálogo: si el plano contiene varias figuras con la misma clave, todas quedan sin vincular hasta identificar su procedencia. Tampoco se elige automáticamente entre registros duplicados del catálogo. Compartir un mapa combina los registros; no fusiona ni elimina lotes.

Para Del Río y Miranda, asignar respectivamente `finca-del-rio` y `finca-miranda` a **cada** registro que comparta el dibujo mediante el selector del catálogo o `mapas:vincular UUID CLAVE`. La asignación se guarda en la base de datos del entorno; desplegar el código no la replica en producción. Revisar los registros incluidos y los avisos de duplicados después de vincular.

### Reproducir la conversión

Se necesita Python con Shapely 2 y LibreDWG 0.14. Convertir cada DWG con `dwgread -O JSON -o DIRECTORIO/CLAVE/plano.json ARCHIVO.dwg` y ejecutar:

```sh
python tools/mapas/convertir_planos.py DIRECTORIO resources/mapas
php artisan test --filter=MapaLotesTest
```

`revision_geometria` registra los conteos, la tolerancia de extensión y el SHA-256 del JSON CAD de entrada. El script contiene la selección explícita de bloques/capas de estos seis archivos; debe revisarse al recibir otra versión del plano.

En Reyes, los números 1–68 están en la capa `CVL_PUNTO_NUM` del bloque `lotificacion`, que se muestra en sus coordenadas locales. Esa capa se acepta únicamente en este bloque; no se incorporan los puntos de levantamiento ni la tabla de coordenadas del espacio modelo. Se conservan los rótulos `CANAL` y `LINEAS ELECTRICAS`. Para habilitarlo, seleccionar **Reyes** en el catálogo o ejecutar `php artisan mapas:vincular UUID_DEL_FRACCIONAMIENTO reyes`. No requiere migración adicional ni crea registros de fraccionamiento, propietarios o lotes.
