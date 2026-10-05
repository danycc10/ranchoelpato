import '../css/mapa-lotes.css';

function initialize(root) {
    if (root.dataset.initialized) return;
    root.dataset.initialized = 'true';
    let data = JSON.parse(root.querySelector('[data-map-data]').textContent);
    const find = key => root.querySelector(`[data-${key}]`);
    const svg = find('svg'), ns = 'http://www.w3.org/2000/svg';
    const names = { disponible: 'Disponible', apartado: 'Apartado', vendido: 'Vendido', donacion: 'Donación', cancelado: 'Cancelado', sin_vincular: 'Sin vincular' };
    const nodes = new Map();
    let selected = null, view = [...data.viewBox], drag = null, moved = false;
    const number = value => Number(value).toLocaleString('es-MX', { maximumFractionDigits: 2 });
    const element = (tag, attrs) => {
        const el = document.createElementNS(ns, tag);
        for (const [key, value] of Object.entries(attrs)) el.setAttribute(key, value);
        return el;
    };
    const drawView = () => svg.setAttribute('viewBox', view.join(' '));
    function paint() {
        const counts = Object.fromEntries(Object.keys(names).map(key => [key, 0]));
        for (const lot of data.lotes) {
            const state = names[lot.estatus] ? lot.estatus : 'sin_vincular';
            counts[state]++;
            const node = nodes.get(lot.id);
            node.setAttribute('class', `map-parcel ${state}${selected === lot.id ? ' selected' : ''}`);
            node.setAttribute('aria-label', `Lote ${lot.numero}${lot.manzana_plano ? ', manzana ' + lot.manzana_plano : ''}, ${names[state]}`);
            node.setAttribute('aria-pressed', String(selected === lot.id));
        }
        for (const [key, count] of Object.entries(counts)) root.querySelector(`[data-count="${key}"]`).textContent = count;
        find('linked').textContent = data.vinculados;
        find('finca-count').textContent = data.fincas.length;
        find('fincas').replaceChildren(...data.fincas.map(finca => {
            const row = document.createElement('li');
            row.textContent = `${finca.nombre} · ${finca.propietario || 'Propietario sin registrar'}`;
            return row;
        }));
        find('updated').textContent = 'Consulta: ' + new Date(data.actualizado).toLocaleTimeString('es-MX');
        find('unmapped').textContent = data.registros_sin_figura ? `${data.registros_sin_figura} registro(s) del catálogo no tienen una figura identificada en el plano.` : '';
    }
    function showDetails(lot, follow) {
        find('detail').hidden = false;
        find('empty').hidden = true;
        find('number').textContent = `Lote ${lot.numero}`;
        find('status').textContent = names[lot.estatus] || 'Estado sin confirmar';
        find('owner').textContent = lot.propietario || 'Sin identificar';
        find('finca').textContent = lot.fraccionamiento || 'Sin vincular';
        find('block').textContent = lot.manzana || 'Sin registrar';
        find('area').textContent = lot.area_registrada !== null ? `${number(lot.area_registrada)} m²` : 'Sin registrar';
        find('price').textContent = lot.precio_lista !== null ? Number(lot.precio_lista).toLocaleString('es-MX', { style: 'currency', currency: 'MXN' }) : 'Sin registrar';
        find('plan-area').textContent = `Superficie del dibujo: ${number(lot.area_plano)} m² aprox.`;
        const mismatch = lot.area_registrada !== null && Math.abs(Number(lot.area_registrada) - lot.area_plano) > 0.5;
        find('warning').textContent = lot.aviso || (mismatch ? 'La superficie del catálogo difiere del plano. Revisar antes de usarla en documentos.' : '');
        find('catalog').hidden = !lot.catalogo_url;
        if (lot.catalogo_url) find('catalog').href = lot.catalogo_url;
        if (follow) {
            find('announcement').textContent = `Lote ${lot.numero}. ${names[lot.estatus] || 'Sin confirmar'}.`;
            // En móvil la ficha es fija; en escritorio mantenemos visible el panel seleccionado.
            if (!matchMedia('(max-width: 800px)').matches) {
                const rect = find('detail').getBoundingClientRect();
                if (rect.top < 0 || rect.bottom > innerHeight) find('detail').scrollIntoView({ block: 'nearest', behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth' });
                find('detail').parentElement.scrollTop = 0;
            }
        }
    }
    function select(value, focusMap = false) {
        const lot = data.lotes.find(lot => lot.id === value);
        if (!lot) { find('message').textContent = 'Ese número no aparece en el plano.'; return; }
        selected = lot.id;
        find('message').textContent = '';
        showDetails(lot, true);
        paint();
        if (focusMap) {
            view = [lot.centro[0] - data.viewBox[2] / 5, lot.centro[1] - data.viewBox[3] / 5, data.viewBox[2] / 2.5, data.viewBox[3] / 2.5];
            drawView();
        }
    }
    for (const lot of data.lotes) {
        const parcel = element('polygon', { points: lot.puntos.map(p => p.join(',')).join(' '), tabindex: 0, role: 'button', 'data-lote': lot.numero, 'data-figura': lot.id });
        const title = element('title', {}); title.textContent = `Lote ${lot.numero}${lot.manzana_plano ? ' · Manzana ' + lot.manzana_plano : ''}`; parcel.appendChild(title);
        parcel.addEventListener('click', () => { if (!moved) select(lot.id); });
        parcel.addEventListener('keydown', event => { if (['Enter', ' '].includes(event.key)) { event.preventDefault(); select(lot.id); } });
        svg.appendChild(parcel); nodes.set(lot.id, parcel);
    }
    for (const line of data.lineas) svg.appendChild(element('polyline', { points: line.map(p => p.join(',')).join(' '), class: 'map-base' }));
    for (const lot of data.lotes) {
        const label = element('text', { x: lot.centro[0], y: lot.centro[1], class: 'map-number' });
        label.textContent = lot.numero; svg.appendChild(label);
    }
    for (const street of data.calles) {
        const label = element('text', { x: street.punto[0], y: street.punto[1], transform: `rotate(${street.angulo} ${street.punto.join(' ')})`, class: 'map-street' });
        label.textContent = street.texto; svg.appendChild(label);
    }
    function zoom(factor) {
        const w = Math.min(data.viewBox[2] * 1.5, Math.max(data.viewBox[2] / 12, view[2] * factor)), h = w * data.viewBox[3] / data.viewBox[2];
        view = [view[0] + (view[2] - w) / 2, view[1] + (view[3] - h) / 2, w, h]; drawView();
    }
    find('zoom-in').onclick = () => zoom(.75);
    find('zoom-out').onclick = () => zoom(1.3);
    find('fit').onclick = () => { view = [...data.viewBox]; drawView(); };
    find('close').onclick = () => { const previous = selected; selected = null; find('detail').hidden = true; find('empty').hidden = false; paint(); nodes.get(previous)?.focus({ preventScroll: true }); };
    root.addEventListener('keydown', event => { if (event.key === 'Escape' && selected) find('close').click(); });
    find('search').onsubmit = event => {
        event.preventDefault();
        const normalize = value => value.toUpperCase().replace(/\s+/g, '').replace(/^0+(?=\d)/, '');
        const query = normalize(new FormData(event.target).get('numero'));
        const matches = data.lotes.filter(lot => normalize(lot.numero) === query);
        find('results').replaceChildren();
        if (matches.length === 1) { select(matches[0].id, true); return; }
        find('message').textContent = matches.length ? 'Ese número aparece varias veces. Selecciona la figura que deseas revisar.' : 'Ese número no aparece en el plano.';
        matches.forEach((lot, index) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = `Lote ${lot.numero} · ${lot.manzana_plano ? 'Manzana ' + lot.manzana_plano : 'Figura ' + (index + 1)}`;
            button.onclick = () => select(lot.id, true);
            find('results').appendChild(button);
        });
    };
    find('refresh').onclick = async () => {
        find('refresh').disabled = true;
        try {
            const response = await fetch(root.dataset.refreshUrl, { headers: { Accept: 'application/json' }, cache: 'no-store' });
            if (!response.ok) throw new Error('No se pudo actualizar. Revisa tu sesión e inténtalo de nuevo.');
            data = await response.json(); paint();
            if (selected) showDetails(data.lotes.find(lot => lot.id === selected), false);
            find('message').textContent = '';
        } catch (error) { find('message').textContent = error.message; }
        finally { find('refresh').disabled = false; }
    };
    svg.addEventListener('wheel', event => { event.preventDefault(); zoom(event.deltaY < 0 ? .9 : 1.1); }, { passive: false });
    svg.addEventListener('pointerdown', event => { moved = false; drag = { x: event.clientX, y: event.clientY, view: [...view] }; });
    svg.addEventListener('pointermove', event => {
        if (!drag) return;
        const dx = event.clientX - drag.x, dy = event.clientY - drag.y;
        if (Math.hypot(dx, dy) > 4) {
            moved = true; svg.setPointerCapture(event.pointerId);
            const rect = svg.getBoundingClientRect(), scale = Math.min(rect.width / drag.view[2], rect.height / drag.view[3]);
            view = [drag.view[0] - dx / scale, drag.view[1] - dy / scale, drag.view[2], drag.view[3]]; drawView();
        }
    });
    for (const name of ['pointerup', 'pointercancel', 'lostpointercapture']) svg.addEventListener(name, () => drag = null);
    paint(); drawView();
}

const boot = () => document.querySelectorAll('[data-lotes-map]').forEach(initialize);
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
document.addEventListener('livewire:navigated', boot);
