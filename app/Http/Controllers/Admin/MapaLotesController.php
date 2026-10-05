<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Fraccionamiento;
use App\Services\MapaLotesService;

class MapaLotesController extends Controller
{
    public function index(MapaLotesService $mapas)
    {
        return response()->view('admin.mapas.index', ['mapas' => $mapas->listado()])
            ->header('Cache-Control', 'private, no-store');
    }

    public function plano(string $mapa, MapaLotesService $mapas)
    {
        return response()->view('admin.mapas.show', ['mapa' => $mapas->datosPorClave($mapa)])
            ->header('Cache-Control', 'private, no-store');
    }

    public function datosPlano(string $mapa, MapaLotesService $mapas)
    {
        return response()->json($mapas->datosPorClave($mapa))
            ->header('Cache-Control', 'private, no-store');
    }

    public function show(Fraccionamiento $fraccionamiento, MapaLotesService $mapas)
    {
        return response()->view('admin.mapas.show', [
            'fraccionamiento' => $fraccionamiento,
            'mapa' => $mapas->datos($fraccionamiento),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function datos(Fraccionamiento $fraccionamiento, MapaLotesService $mapas)
    {
        return response()->json($mapas->datos($fraccionamiento))
            ->header('Cache-Control', 'private, no-store');
    }
}
