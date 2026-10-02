<?php

namespace App\Console\Commands;

use App\Models\Fraccionamiento;
use App\Services\MapaLotesService;
use Illuminate\Console\Command;

class VincularMapaFraccionamiento extends Command
{
    protected $signature = 'mapas:vincular {fraccionamiento : UUID del fraccionamiento} {mapa=finca-escondida}';

    protected $description = 'Vincula un plano preparado al fraccionamiento indicado, sin cambiar sus lotes.';

    public function handle(MapaLotesService $mapas): int
    {
        $clave = (string) $this->argument('mapa');
        $fraccionamiento = Fraccionamiento::where('uuid', $this->argument('fraccionamiento'))->first();
        if (! $fraccionamiento || ! isset(MapaLotesService::MAPAS[$clave])) {
            $this->error('No se encontró el fraccionamiento o el mapa solicitado.');

            return self::FAILURE;
        }
        $fraccionamiento->forceFill(['mapa_clave' => $clave])->save();
        $datos = $mapas->datos($fraccionamiento);
        $this->info($fraccionamiento->nombre.': '.$datos['vinculados'].' de '.count($datos['lotes']).' lotes vinculados.');
        $this->line(route('admin.fraccionamientos.mapa', $fraccionamiento));

        return self::SUCCESS;
    }
}
