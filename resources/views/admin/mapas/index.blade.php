<x-app-layout>
    <div class="lotes-map map-gallery">
        <header class="map-heading">
            <div><a href="{{ route('admin.fraccionamientos') }}">← Fraccionamientos</a><h1>Mapas de fraccionamientos</h1><p>Elige un plano para consultar sus lotes, disponibilidad y medidas.</p></div>
            <span class="map-gallery-count">{{ count($mapas) }} planos</span>
        </header>
        <div class="map-gallery-grid">
            @foreach($mapas as $plano)
                <article class="map-card">
                    <a href="{{ route('admin.mapas.show', $plano['clave']) }}" class="map-card-link" aria-label="Abrir mapa de {{ $plano['nombre'] }}">
                        <div class="map-preview"><svg viewBox="{{ implode(' ', $plano['viewBox']) }}" aria-hidden="true" focusable="false">
                            @foreach($plano['lotes'] as $lote)
                                <polygon points="{{ implode(' ', array_map(fn($p) => implode(',', $p), $lote['puntos'])) }}" />
                            @endforeach
                        </svg></div>
                        <div class="map-card-heading"><h2>{{ $plano['nombre'] }}</h2><span aria-hidden="true">↗</span></div>
                    </a>
                    <div class="map-card-info"><p><strong>{{ count($plano['lotes']) }}</strong> lotes en el plano</p>
                        @if(count($plano['fincas']))
                            <p class="map-small">{{ count($plano['fincas']) }} registro(s) vinculados</p><ul class="map-card-fincas">@foreach($plano['fincas'] as $nombre)<li>{{ $nombre }}</li>@endforeach</ul>
                        @else
                            <p class="map-warning">Sin fraccionamientos vinculados</p><p class="map-small">Disponible para explorar y consultar medidas.</p>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</x-app-layout>
