<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FormasPagoSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('formas_pago')->insert([
            [
                'nombre' => 'EFECTIVO',
                'requiere_cuenta' => false,
                'activa' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nombre' => 'TRANSFERENCIA',
                'requiere_cuenta' => true,
                'activa' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nombre' => 'DEPÓSITO OXXO',
                'requiere_cuenta' => true,
                'activa' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nombre' => 'TARJETA',
                'requiere_cuenta' => true,
                'activa' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
