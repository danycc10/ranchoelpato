<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TiposCobroSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['nombre' => 'MENSUALIDAD', 'categoria' => 'contrato', 'requiere_periodo' => true],
            ['nombre' => 'ENGANCHE', 'categoria' => 'contrato', 'requiere_periodo' => false],
            ['nombre' => 'ANUALIDAD', 'categoria' => 'contrato', 'requiere_periodo' => true],
            ['nombre' => 'RECARGO', 'categoria' => 'recargo', 'requiere_periodo' => false],
            ['nombre' => 'CAMBIO DE CONTRATO', 'categoria' => 'tramite', 'requiere_periodo' => false],
            ['nombre' => 'ENTREGA DEL TERRENO', 'categoria' => 'tramite', 'requiere_periodo' => false],
            ['nombre' => 'SEPARADO', 'categoria' => 'contrato', 'requiere_periodo' => false],
            ['nombre' => 'LIMPIEZA', 'categoria' => 'servicio', 'requiere_periodo' => false],
            ['nombre' => 'SERVICIO - AGUA', 'categoria' => 'servicio', 'requiere_periodo' => false],
            ['nombre' => 'SERVICIO - ELECTRICIDAD', 'categoria' => 'servicio', 'requiere_periodo' => false],
            ['nombre' => 'SERVICIO - FOSA', 'categoria' => 'servicio', 'requiere_periodo' => false],
            ['nombre' => 'PROTOCOLO DE ESCRITURACIÓN', 'categoria' => 'tramite', 'requiere_periodo' => false],
            ['nombre' => 'RETIRO', 'categoria' => 'tramite', 'requiere_periodo' => false],
        ];

        $now = now();

        $rows = [];
        foreach ($items as $item) {
            $rows[] = array_merge($item, [
                'activa' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('tipos_cobro')->insert($rows);
    }
}
