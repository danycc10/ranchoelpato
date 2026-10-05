"""Convert LibreDWG JSON to reviewed map assets. Requires shapely >= 2.

Usage: python tools/mapas/convertir_planos.py SOURCE_DIRECTORY OUTPUT_DIRECTORY
Each source subdirectory must contain plano.json from dwgread -O JSON.
No DWG is modified. Small endpoint extensions preserve the original line direction.
"""
import argparse
import collections
import copy
import hashlib
import json
import math
import re
from pathlib import Path

from shapely.geometry import LineString, Point, Polygon, box
from shapely.ops import polygonize, unary_union

CONFIG = {
    'ballesteros': ('Ballesteros', 'Ballesteros.dwg', None, .0001),
    'finca-del-rio': ('Finca del Río', 'Finca Del Rio.dwg', None, 0),
    'finca-espinoza': ('Finca Espinoza', 'Finca Espinoza.dwg', 'plano', 0),
    'finca-del-norte': ('Finca del Norte', 'Finca del norte.dwg', 'lotes norte', .05),
    'finca-miranda': ('Finca Miranda', 'FINCA MIRANDA 2026.dwg', None, .05),
}


def clean(value):
    return re.sub(r'\\[A-Za-z][^;{}]*;', '', value).replace('\\P', ' ').replace('{', '').replace('}', '').strip()


def text_center(entity):
    p = entity.get('alignment_pt') if entity.get('horiz_alignment', 0) else entity.get('ins_pt')
    p = list((p or entity['ins_pt'])[:2])
    angle = entity.get('rotation', 0)
    if entity['entity'] == 'MTEXT':
        direction = entity.get('x_axis_dir', [1, 0])
        angle = math.atan2(direction[1], direction[0])
        attachment = entity.get('attachment', 1) - 1
        width = entity.get('extents_width', 0)
        height = entity.get('extents_height', entity.get('text_height', 0))
        dx = (.5 - (attachment % 3) / 2) * width
        dy = ((attachment // 3) / 2 - .5) * height
        p[0] += dx * math.cos(angle) - dy * math.sin(angle)
        p[1] += dx * math.sin(angle) + dy * math.cos(angle)
    return p, -math.degrees(angle)


def convert(source, output, key, config):
    name, filename, block, extension = config
    raw = (source / key / 'plano.json').read_bytes()
    objects = json.loads(raw)['OBJECTS']
    index = {o['handle'][-1]: o for o in objects}
    layers = {o['handle'][-1]: o['name'] for o in objects if o.get('object') == 'LAYER'}
    if block:
        header = next(o for o in objects if o.get('object') == 'BLOCK_HEADER' and o.get('name') == block)
        entities = [index[r[-1]] for r in header['entities']]
    else:
        entities = [o for o in objects if o.get('entity') and o.get('entmode') == 2]
    if key == 'finca-miranda':
        # The central parcels live inside an INSERT, not ModelSpace directly.
        # Apply its placement; displaying the block at local coordinates loses them.
        for insert in list(entities):
            if insert.get('entity') != 'INSERT':
                continue
            header = index[insert['block_header'][-1]]
            if header.get('name') not in ('fisico', 'lotees'):
                continue
            assert insert.get('scale', [1, 1, 1]) == [1, 1, 1]
            theta = insert.get('rotation', 0)
            origin = header.get('base_pt', [0, 0, 0])
            offset = insert['ins_pt']
            def placed(p):
                x, y = p[0] - origin[0], p[1] - origin[1]
                return [x * math.cos(theta) - y * math.sin(theta) + offset[0],
                        x * math.sin(theta) + y * math.cos(theta) + offset[1], 0]
            for reference in header.get('entities', []):
                child = copy.deepcopy(index[reference[-1]])
                for field in ('ins_pt', 'alignment_pt', 'start', 'end'):
                    if field in child:
                        child[field] = placed(child[field])
                if 'points' in child:
                    child['points'] = [placed(p)[:2] for p in child['points']]
                child['rotation'] = child.get('rotation', 0) + theta
                if 'x_axis_dir' in child:
                    x, y = child['x_axis_dir'][:2]
                    child['x_axis_dir'] = [x * math.cos(theta) - y * math.sin(theta),
                                           x * math.sin(theta) + y * math.cos(theta), 0]
                entities.append(child)
    lines, texts = [], []
    for o in entities:
        kind = o.get('entity')
        layer = layers.get(o.get('layer', [None])[-1], '')
        if kind in ('TEXT', 'MTEXT'):
            point, angle = text_center(o)
            texts.append({'text': clean(o.get('text_value', o.get('text', ''))), 'point': point,
                          'angle': angle, 'layer': layer, 'id': str(o['handle'][-1])})
        if any(s in layer.upper() for s in ('CUADCON', 'MARCO')):
            continue
        points = []
        if kind == 'LINE':
            points = [o['start'][:2], o['end'][:2]]
        elif kind == 'LWPOLYLINE':
            if any(o.get('bulges', [])):
                raise ValueError(f'{key}: curved polyline needs explicit handling')
            points = [p[:2] for p in o['points']]
            if o.get('flag', 0) & 512 and points[0] != points[-1]:
                points.append(points[0])
        if len(points) > 1:
            lines.append(points)
    labels = [t for t in texts if re.fullmatch(r'\d+[A-Za-z]?', t['text']) and not
              any(s in t['layer'].upper() for s in ('PUNTO', 'CUADCON_TIT', 'PT_PROY'))]
    # Miranda contains one distant dimension annotation, outside the site.
    if key == 'finca-miranda':
        labels = [t for t in labels if t['point'][0] < 500000]
    segments = []
    for line in lines:
        for a, b in zip(line, line[1:]):
            length = math.dist(a, b)
            if length < 1e-8:
                continue
            delta = [(b[i] - a[i]) * extension / length for i in (0, 1)]
            segments.append(LineString([[a[i] - delta[i] for i in (0, 1)],
                                       [b[i] + delta[i] for i in (0, 1)]]))
    polygons = [p for p in polygonize(unary_union(segments)) if p.area > 25]
    # Coordinate-table numerals sit far to the right of the Ballesteros site.
    if key == 'ballesteros':
        bounds = unary_union(polygons).bounds
        labels = [t for t in labels if box(*bounds).covers(Point(t['point']))]
    matches = [(p, [t for t in labels if p.covers(Point(t['point']))]) for p in polygons]
    accepted = [(p, ts[0]) for p, ts in matches if len(ts) == 1]
    matched_ids = [t['id'] for _, t in accepted]
    if len(matched_ids) != len(set(matched_ids)):
        raise ValueError(f'{key}: an annotation belongs to more than one polygon')
    pending = [t['text'] for t in labels if t['id'] not in matched_ids]
    if pending:
        raise ValueError(f'{key}: unresolved labels: {pending}')
    if any(p.interiors for p, _ in accepted):
        raise ValueError(f'{key}: a parcel contains interior rings requiring review')
    expected = {'ballesteros': 48, 'finca-del-rio': 255, 'finca-del-norte': 340}
    if key in expected and collections.Counter(t['text'] for _, t in accepted) != collections.Counter(map(str, range(1, expected[key] + 1))):
        raise ValueError(f'{key}: missing or repeated lot numbers')
    # The explicitly labelled M1/M2/M3 markers identify connected parcel blocks.
    regions = []
    if key == 'finca-espinoza':
        connected = unary_union([p.buffer(.00001, join_style=2) for p, _ in accepted])
        for region in connected.geoms:
            markers = [t['text'][1:] for t in texts if re.fullmatch('M[123]', t['text'])
                       and region.covers(Point(t['point']))]
            if len(markers) != 1:
                raise ValueError('Espinoza: block marker is missing or ambiguous')
            regions.append((region, markers[0]))
        assert len(regions) == 3
    x0, y0, x1, y1 = unary_union([p for p, _ in accepted]).bounds
    x0 -= 12; y0 -= 12; x1 += 12; y1 += 12
    def point(p):
        return [round(p[0] - x0, 6), round(y1 - p[1], 6)]
    lots = []
    for p, t in accepted:
        item = {'id': 'cad-' + t['id'], 'numero': t['text'], 'area_plano': round(p.area, 2),
                'puntos': [point(v) for v in p.exterior.coords], 'centro': point(p.representative_point().coords[0])}
        if regions:
            item['manzana_plano'] = next(n for r, n in regions if r.covers(p.representative_point()))
        lots.append(item)
    lots.sort(key=lambda l: (l.get('manzana_plano', ''), int(re.match(r'\d+', l['numero'])[0]), l['numero'], l['id']))
    # Validation after coordinate serialization, including all cross-parcel overlaps.
    shapes = [Polygon(l['puntos']) for l in lots]
    assert all(p.is_valid and p.area > 25 for p in shapes)
    assert sum(p.area for p in shapes) - unary_union(shapes).area < .001
    viewport = box(x0, y0, x1, y1)
    base_lines = []
    for line in lines:
        clipped = LineString(line).intersection(viewport)
        parts = [clipped] if clipped.geom_type == 'LineString' else getattr(clipped, 'geoms', [])
        base_lines.extend([[point(v) for v in part.coords] for part in parts if part.geom_type == 'LineString' and not part.is_empty])
    streets = [{'texto': t['text'], 'punto': point(t['point']), 'angulo': t['angle']} for t in texts
               if viewport.covers(Point(t['point'])) and re.search(r'CALLE|AVENIDA|ACCESO|PRIVADA|^M[123]$', t['text'], re.I)]
    report = {'poligonos': len(lots), 'etiquetas': len(labels), 'extension_maxima': extension,
              'numeros_repetidos': {n: c for n, c in collections.Counter(l['numero'] for l in lots).items() if c > 1},
              'manzanas': dict(collections.Counter(l.get('manzana_plano', '') for l in lots)),
              'sha256_json_origen': hashlib.sha256(raw).hexdigest()}
    result = {'nombre': name, 'fuente': filename, 'bloque': block or 'ModelSpace',
              'viewBox': [0, 0, round(x1 - x0, 6), round(y1 - y0, 6)],
              'lotes': lots, 'lineas': base_lines, 'calles': streets, 'revision_geometria': report}
    output.mkdir(parents=True, exist_ok=True)
    (output / (key + '.json')).write_text(json.dumps(result, ensure_ascii=False, separators=(',', ':')), encoding='utf-8')
    print(key, json.dumps(report, ensure_ascii=False))


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('source', type=Path)
    parser.add_argument('output', type=Path)
    args = parser.parse_args()
    for key, config in CONFIG.items():
        convert(args.source, args.output, key, config)
