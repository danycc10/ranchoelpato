<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Reportes fuera del alcance de la secretaria.
 *
 * El enlace no estaba protegido: salía para todos y el 403 llegaba hasta que
 * le picaban. Ofrecer una puerta que no abre es peor que no ofrecerla.
 */
class ReportesOcultosSecretariaTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function sin_el_permiso_el_menu_no_ofrece_reportes(): void
    {
        $this->actingAs($this->usuario(['dashboard.ver']))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(route('reportes.index'));
    }

    #[Test]
    public function con_el_permiso_si_aparece(): void
    {
        $this->actingAs($this->usuario(['dashboard.ver', 'reportes.ver']))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('reportes.index'));
    }

    #[Test]
    public function la_ruta_sigue_cerrada_aunque_adivinen_la_direccion(): void
    {
        // Esconder el enlace es cortesía; lo que protege es el permiso.
        $this->actingAs($this->usuario(['dashboard.ver']))
            ->get(route('reportes.index'))
            ->assertForbidden();
    }

    #[Test]
    public function la_secretaria_queda_sin_reportes_y_los_demas_con_ellos(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $secretaria = Role::where('name', 'secretaria')->sole();

        foreach (['reportes.ver', 'reportes.diario_recibos', 'reportes.ingresos_mensuales'] as $permiso) {
            $this->assertFalse(
                $secretaria->hasPermissionTo($permiso),
                "La secretaria no debería tener {$permiso}."
            );
        }

        foreach (['dueño', 'admin', 'jefa'] as $nombre) {
            $this->assertTrue(
                Role::where('name', $nombre)->sole()->hasPermissionTo('reportes.ver'),
                "El rol {$nombre} sí debería ver reportes."
            );
        }
    }

    #[Test]
    public function lo_suyo_sigue_igual(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $secretaria = Role::where('name', 'secretaria')->sole();

        // Quitarle reportes no la deja sin su trabajo del día.
        foreach (['recibos.crear', 'recibos.imprimir', 'clientes.editar', 'cuotas.ver'] as $permiso) {
            $this->assertTrue(
                $secretaria->hasPermissionTo($permiso),
                "La secretaria perdió {$permiso}, que sí necesita."
            );
        }
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
}
