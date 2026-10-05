<x-app-layout>
    <div class="lotes-map" data-lotes-map data-refresh-url="{{ route('admin.mapas.datos', $mapa['clave_mapa']) }}">
        <script type="application/json" data-map-data>{!! \Illuminate\Support\Js::encode($mapa) !!}</script>
        <header class="map-heading">
            <div>
                <a href="{{ route('admin.mapas.index') }}">← Todos los mapas</a>
                <h1>{{ $mapa['nombre'] }}</h1>
                <p><span data-linked>{{ $mapa['vinculados'] }}</span> de {{ count($mapa['lotes']) }} lotes del plano relacionados con el catálogo.</p>
                <p class="map-small">Vista conjunta de <span data-finca-count>{{ count($mapa['fincas']) }}</span> registro(s) de fraccionamiento.</p>
            </div>
            <div><button type="button" data-refresh>Actualizar disponibilidad</button><p class="map-small" data-updated role="status"></p></div>
        </header>
        <div class="map-layout">
            <aside class="map-sidebar">
                <section data-detail class="map-detail" hidden aria-label="Información del lote seleccionado" tabindex="-1">
                    <button type="button" data-close class="map-close" aria-label="Cerrar ficha">×</button>
                    <p class="map-eyebrow">Lote seleccionado</p><h2 data-number></h2>
                    <p data-status class="map-status"></p>
                    <p class="map-small">Propietario: <strong data-owner></strong><br>Registro: <span data-finca></span></p>
                    <dl class="map-facts"><div><dt>Manzana</dt><dd data-block></dd></div><div><dt>Superficie registrada</dt><dd data-area></dd></div><div><dt>Precio de lista</dt><dd data-price></dd></div></dl>
                    <p data-warning class="map-warning"></p>
                    <div class="map-measures"><h3>Medidas y colindancias capturadas</h3><dl data-sides class="map-facts"></dl><p class="map-small">Información registrada en el catálogo del lote.</p></div>
                    <a data-catalog class="map-link">Ver en catálogo</a>
                </section>
                <p data-empty class="map-help">Selecciona un lote para consultar su disponibilidad y datos.</p>
                @if(count($mapa['fincas']) === 0)
                    <p class="map-warning">Este mapa aún no tiene fraccionamientos vinculados. Puedes explorar los lotes; sus datos capturados estarán disponibles al vincularlos.</p>
                    <a class="map-link" href="{{ route('admin.fraccionamientos') }}">Vincular desde fraccionamientos</a>
                @endif
                <form data-search class="map-search"><label for="map-lot-search">Buscar lote</label><div><input id="map-lot-search" name="numero" required placeholder="Ej. 24 o 17A"><button>Buscar</button></div></form>
                <p data-message role="status" class="map-warning"></p>
                <div data-results class="map-results" aria-label="Coincidencias de búsqueda"></div>
                <div class="map-legend" aria-label="Estados de los lotes">
                    @foreach(['disponible' => 'Disponible', 'apartado' => 'Apartado', 'vendido' => 'Vendido', 'donacion' => 'Donación', 'cancelado' => 'Cancelado', 'sin_vincular' => 'Sin vincular'] as $estado => $etiqueta)
                    <div><span class="map-dot {{ $estado }}"></span><span>{{ $etiqueta }}</span><b data-count="{{ $estado }}">0</b></div>
                    @endforeach
                </div>
                <p class="map-small">Los colores corresponden al catálogo. Gris significa que falta relacionar el lote; no indica disponibilidad.</p>
                <p data-unmapped class="map-warning"></p>
                <p class="map-small">Plano: {{ $mapa['nombre'] }}. La ficha muestra los datos capturados en el catálogo.</p>
                <p class="map-small">Registros incluidos:</p><ul data-fincas class="map-small"></ul>
            </aside>
            <section class="map-surface" aria-label="Plano interactivo del fraccionamiento">
                <div class="map-tools"><button type="button" data-zoom-in aria-label="Acercar mapa">+</button><button type="button" data-zoom-out aria-label="Alejar mapa">−</button><button type="button" data-fit>Ver todo</button></div>
                <svg data-svg xmlns="http://www.w3.org/2000/svg" aria-label="Plano de lotes y calles"></svg>
                <p class="map-caption">Selecciona un lote · Acerca y arrastra para explorar</p>
            </section>
        </div>
        <div class="map-sr-only" data-announcement aria-live="polite"></div>
    </div>
</x-app-layout>
