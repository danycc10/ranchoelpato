<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PeriodosSeeder extends Seeder
{
    public function run(): void
    {
        $anio = now()->year;

        foreach (range(1, 12) as $mes) {
            DB::table('periodos')->insert([
                'tipo' => 'mensual',
                'anio' => $anio,
                'mes' => $mes,
                'nombre' => strtoupper(now()->setMonth($mes)->translatedFormat('F')) . " $anio",
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
