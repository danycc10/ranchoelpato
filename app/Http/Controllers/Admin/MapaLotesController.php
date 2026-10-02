<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Fraccionamiento;
use App\Services\MapaLotesService;

class MapaLotesController extends Controller
{
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
