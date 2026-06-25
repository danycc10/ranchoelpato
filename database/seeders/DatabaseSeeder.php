<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();
        $this->call([
            PropietariosSeeder::class,
            FormasPagoSeeder::class,
            TiposCobroSeeder::class,
            PeriodosSeeder::class,
            RolesAndPermissionsSeeder::class,
            CuentasBancariasSeeder::class,
        ]);
        $user = User::firstOrCreate(
            ['email' => 'danycc10@gmail.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make('12345678'),
            ]
        );

        $user->syncRoles(['admin']);

    }
}
