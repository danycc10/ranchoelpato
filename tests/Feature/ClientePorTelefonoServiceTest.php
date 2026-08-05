<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Services\Clientes\ClientePorTelefonoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClientePorTelefonoServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function encuentra_clientes_de_mexico_y_estados_unidos_sin_confundir_ladas(): void
    {
        $mexico = Cliente::create([
            'nombres' => 'Cliente',
            'apellidos' => 'México',
            'telefono' => '8781234567',
        ]);
        $estadosUnidos = Cliente::create([
            'nombres' => 'Cliente',
            'apellidos' => 'Estados Unidos',
            'telefono' => '19561234567',
        ]);

        $servicio = app(ClientePorTelefonoService::class);

        $this->assertSame($mexico->id, $servicio->buscarUnico('+52 878 123 4567')?->id);
        $this->assertSame($mexico->id, $servicio->buscarUnico('5218781234567')?->id);
        $this->assertSame($estadosUnidos->id, $servicio->buscarUnico('+1 956 123 4567')?->id);
    }

    #[Test]
    public function no_adivina_cuando_el_mismo_numero_esta_duplicado(): void
    {
        Cliente::create(['nombres' => 'Uno', 'apellidos' => 'Prueba', 'telefono' => '8781234567']);
        Cliente::create(['nombres' => 'Dos', 'apellidos' => 'Prueba', 'telefono' => '528781234567']);

        $this->assertNull(app(ClientePorTelefonoService::class)->buscarUnico('8781234567'));
    }
}
