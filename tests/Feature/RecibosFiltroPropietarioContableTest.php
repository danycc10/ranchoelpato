<?php

namespace Tests\Feature;

use App\Livewire\Admin\Recibos\Index;
use App\Models\Cliente;
use App\Models\FormaPago;
use App\Models\Fraccionamiento;
use App\Models\Lote;
use App\Models\Propietario;
use App\Models\Recibo;
use App\Models\TipoCobro;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RecibosFiltroPropietarioContableTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function el_recibo_se_filtra_por_su_propietario_contable(): void
    {
        $duenoDelFraccionamiento = Propietario::create(['nombre' => 'Alejandro']);
        $contable = Propietario::create(['nombre' => 'Osvaldo']);

        // El recibo es de un lote de Alejandro, pero ese cobro le entra a Osvaldo.
        $recibo = $this->recibo('R-CONTABLE', $duenoDelFraccionamiento, $contable->id);

        $this->assertSame(['R-CONTABLE'], $this->foliosFiltrando($contable->id));
        $this->assertNotContains('R-CONTABLE', $this->foliosFiltrando($duenoDelFraccionamiento->id));

        $this->assertSame($duenoDelFraccionamiento->id, $recibo->lote->fraccionamiento->propietario_id);
    }

    #[Test]
    public function un_recibo_sin_contable_cuenta_por_su_fraccionamiento(): void
    {
        $dueno = Propietario::create(['nombre' => 'Alejandro']);
        $otro = Propietario::create(['nombre' => 'Osvaldo']);

        $this->recibo('R-VIEJO', $dueno, null);

        $this->assertSame(['R-VIEJO'], $this->foliosFiltrando($dueno->id));
        $this->assertSame([], $this->foliosFiltrando($otro->id));
    }

    /**
     * @return array<int, string>
     */
    private function foliosFiltrando(int $propietarioId): array
    {
        $componente = Livewire::actingAs($this->usuarioConPermisos())
            ->test(Index::class)
            ->set('desde', null)
            ->set('hasta', null)
            ->set('propietario_id', $propietarioId);

        return collect($componente->viewData('recibos')->items())
            ->pluck('folio')
            ->all();
    }

    private function usuarioConPermisos(): User
    {
        $usuario = User::factory()->create();

        $rol = Role::findOrCreate('admin', 'web');

        foreach (['admin.ver', 'recibos.ver'] as $permiso) {
            $rol->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
        }

        $usuario->assignRole($rol);

        return $usuario;
    }

    private function recibo(string $folio, Propietario $duenoFraccionamiento, ?int $contableId): Recibo
    {
        $fraccionamiento = Fraccionamiento::create([
            'propietario_id' => $duenoFraccionamiento->id,
            'nombre' => 'Fraccionamiento de '.$duenoFraccionamiento->nombre,
        ]);

        $lote = Lote::create([
            'fraccionamiento_id' => $fraccionamiento->id,
            'manzana' => '1',
            'lote' => (string) random_int(100, 999),
            'clave' => 'M1-L'.random_int(100, 999),
        ]);

        $cliente = Cliente::firstOrCreate(
            ['nombres' => 'Maria', 'apellidos' => 'Lopez Garza'],
            ['telefono' => '8781234567']
        );

        return Recibo::create([
            'folio' => $folio,
            'fecha' => now()->toDateString(),
            'anio' => (int) now()->format('Y'),
            'mes_del_anio' => (int) now()->format('n'),
            'cliente_id' => $cliente->id,
            'lote_id' => $lote->id,
            'tipos_cobro_id' => TipoCobro::firstOrCreate(['nombre' => 'MENSUALIDAD'])->id,
            'forma_pago_id' => FormaPago::firstOrCreate(['nombre' => 'EFECTIVO'])->id,
            'propietario_contable_id' => $contableId,
            'monto' => 1000,
        ]);
    }
}
