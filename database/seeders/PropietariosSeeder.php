<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PropietariosSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('propietarios')->insert([
            [
                'nombre' => 'Osvaldo',
                'telefono' => null,
                'correo' => null,
                'notas' => 'Propietario principal',
                'created_at' => now(),
                'updated_at' => now(),
            ],
                       [
                'nombre' => 'Alejandro',
                'telefono' => null,
                'correo' => null,
                'notas' => 'Socio',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
