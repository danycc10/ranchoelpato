<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CuentasBancariasSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('cuentas_bancarias')->insert([

            [
                'propietario_id' => 1,
                'alias' => 'BBVA-CLABE-9844 OSVALDO',
                'banco' => 'BBVA',
                'tipo' => 'CLABE',
                'numero' => '9844',
                'activa' => 1,
            ],
            [
                'propietario_id' => 1,
                'alias' => 'BBVA-TARJETA-0298 OSVALDO',
                'banco' => 'BBVA',
                'tipo' => 'TARJETA',
                'numero' => '0298',
                'activa' => 1,
            ],
            [
                'propietario_id' => 1,
                'alias' => 'BBVA-TARJETA-3876 OSVALDO',
                'banco' => 'BBVA',
                'tipo' => 'TARJETA',
                'numero' => '3876',
                'activa' => 1,
            ],
            [
                'propietario_id' => 1,
                'alias' => 'MP-CLABE-7617 OSVALDO',
                'banco' => 'MP',
                'tipo' => 'CLABE',
                'numero' => '7617',
                'activa' => 1,
            ],
            [
                'propietario_id' => 1,
                'alias' => 'BANAMEX-TARJETA-5287 OSVALDO',
                'banco' => 'BANAMEX',
                'tipo' => 'TARJETA',
                'numero' => '5287',
                'activa' => 1,
            ],
            [
                'propietario_id' => 1,
                'alias' => 'SPIN-OXXO-7063 OSVALDO',
                'banco' => 'SPIN',
                'tipo' => 'OXXO',
                'numero' => '7063',
                'activa' => 1,
            ],

            [
                'propietario_id' => 2,
                'alias' => 'BBVA-CLABE-6358 ALEJANDRO',
                'banco' => 'BBVA',
                'tipo' => 'CLABE',
                'numero' => '6358',
                'activa' => 1,
            ],
            [
                'propietario_id' => 2,
                'alias' => 'SPIN-CLABE-6942 ALEJANDRO',
                'banco' => 'SPIN',
                'tipo' => 'CLABE',
                'numero' => '6942',
                'activa' => 1,
            ],
            [
                'propietario_id' => 2,
                'alias' => 'MP-CLABE-9365 ALEJANDRO',
                'banco' => 'MP',
                'tipo' => 'CLABE',
                'numero' => '9365',
                'activa' => 1,
            ],
            [
                'propietario_id' => 2,
                'alias' => 'BBVA-TARJETA-1975 ALEJANDRO',
                'banco' => 'BBVA',
                'tipo' => 'TARJETA',
                'numero' => '1975',
                'activa' => 1,
            ],
            [
                'propietario_id' => 2,
                'alias' => 'SPIN-OXXO-4761 ALEJANDRO',
                'banco' => 'SPIN',
                'tipo' => 'OXXO',
                'numero' => '4761',
                'activa' => 1,
            ],
            [
                'propietario_id' => 2,
                'alias' => 'PAGO EN OXXO ALEJANDRO',
                'banco' => 'OXXO',
                'tipo' => 'OXXO',
                'numero' => null,
                'activa' => 1,
            ],

            [
                'propietario_id' => 1,
                'alias' => 'PAGO EN OXXO OSVALDO',
                'banco' => 'OXXO',
                'tipo' => 'OXXO',
                'numero' => null,
                'activa' => 1,
            ],
            [
                'propietario_id' => 1,
                'alias' => 'SPIN-CLABE-2835 VANESSA',
                'banco' => 'SPIN',
                'tipo' => 'CLABE',
                'numero' => '2835',
                'activa' => 1,
            ],
            [
                'propietario_id' => 1,
                'alias' => 'SPIN-TARJETA-6890 VANESSA',
                'banco' => 'SPIN',
                'tipo' => 'TARJETA',
                'numero' => '6890',
                'activa' => 1,
            ],
            [
                'propietario_id' => 1,
                'alias' => 'SPIN-TARJETA-0303 DIANA',
                'banco' => 'SPIN',
                'tipo' => 'TARJETA',
                'numero' => '0303',
                'activa' => 1,
            ],
            [
                'propietario_id' => 1,
                'alias' => 'SPIN-CLABE-3034 DIANA',
                'banco' => 'SPIN',
                'tipo' => 'CLABE',
                'numero' => '3034',
                'activa' => 1,
            ],

        ]);
    }
}
