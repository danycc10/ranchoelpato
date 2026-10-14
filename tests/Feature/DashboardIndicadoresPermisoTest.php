<?php

namespace Tests\Feature;

use App\Livewire\Dashboard\CobranzaDashboard;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Cuota;
use App\Models\Fraccionamiento;
use App\Models\Lote;
use App\Models\Propietario;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Los totales de cartera del tablero.
 *
 * "Por cobrar hoy", "Atrasadas" y "Total pendiente" dicen de un vistazo
 * cuánto dinero hay en juego. No es dato para todos los puestos.
 */
class DashboardIndicadoresPermisoTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function con_el_permiso_se_ven_los_totales(): void
    {
        $this->cuotaAtrasada();

        Livewire::actingAs($this->usuario(['dashboard.ver', 'dashboard.indicadores']))
            ->test(CobranzaDashboard::class)
            ->assertSee('Por cobrar hoy')
            ->assertSee('Atrasadas')
            ->assertSee('Total pendiente');
    }

    #[Test]
    public function sin_el_permiso_no_aparecen(): void
    {
        $this->cuotaAtrasada();

        Livewire::actingAs($this->usuario(['dashboard.ver']))
            ->test(CobranzaDashboard::class)
            ->assertOk()
            ->assertDontSee('Por cobrar hoy')
            ->assertDontSee('Total pendiente');
    }

    #[Test]
    public function sin_el_permiso_el_monto_ni_se_calcula(): void
    {
        $this->cuotaAtrasada(monto: 9999);

        $componente = Livewire::actingAs($this->usuario(['dashboard.ver']))
            ->test(CobranzaDashboard::class);

        // Ni siquiera viaja en la respuesta: no se consulta.
        $this->assertSame(0.0, $componente->instance()->kpis->atr_monto);
        $componente->assertDontSee('9,999');
    }

    #[Test]
    public function el_tablero_sigue_sirviendo_sin_los_totales(): void
    {
        $this->cuotaAtrasada();

        // La secretaria entra igual: lo que se le quita son los totales.
        Livewire::actingAs($this->usuario(['dashboard.ver']))
            ->test(CobranzaDashboard::class)
            ->assertOk()
            ->assertSee('Cuotas a cobrar hoy');
    }

    #[Test]
    public function la_secretaria_no_lo_trae_y_los_demas_si(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        foreach (['dueño', 'admin', 'jefa'] as $nombre) {
            $this->assertTrue(
                Role::where('name', $nombre)->sole()->hasPermissionTo('dashboard.indicadores'),
                "El rol {$nombre} debería ver los totales."
            );
        }

        $this->assertFalse(
            Role::where('name', 'secretaria')->sole()->hasPermissionTo('dashboard.indicadores')
        );
    }

    /**
     * @param  array<int, string>  $permisos
     */
    private function usuario(array $permisos): User
    {
        $usuario = User::factory()->create();

        $rol = Role::findOrCreate('rol-'.implode('-', $permisos), 'web');

        foreach ($permisos as $permiso) {
            $rol->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
        }

        return tap($usuario)->assignRole($rol);
    }

    private function cuotaAtrasada(float $monto = 1500): void
    {
        $propietario = Propietario::create(['nombre' => 'El Pato']);

        $fraccionamiento = Fraccionamiento::create([
            'propietario_id' => $propietario->id,
            'nombre' => 'Rancho El Pato',
        ]);

        $lote = Lote::create([
            'fraccionamiento_id' => $fraccionamiento->id,
            'manzana' => '1',
            'lote' => '7',
            'clave' => 'M1-L7',
        ]);

        $cliente = Cliente::create(['nombres' => 'Dora', 'apellidos' => 'Cardona']);

        $contrato = Contrato::create([
            'cliente_id' => $cliente->id,
            'lote_id' => $lote->id,
            'folio_contrato' => 'CT-KPI',
            'fecha_inicio' => '2026-01-10',
            'frecuencia' => 'mensual',
            'dia_mes' => 10,
            'precio_total' => 90000,
            'enganche' => 0,
            'saldo_inicial' => 90000,
            'saldo_actual' => 90000,
            'monto_pago' => $monto,
            'tipo_recargo' => 'fijo',
            'valor_recargo' => 0,
            'dias_gracia' => 0,
            'estatus' => 'activo',
            'tipo' => 'terreno',
        ]);

        Cuota::create([
            'contrato_id' => $contrato->id,
            'numero' => 1,
            'fecha_vencimiento' => now()->subMonth()->toDateString(),
            'monto' => $monto,
            'estatus' => 'vencida',
        ]);
    }
}
