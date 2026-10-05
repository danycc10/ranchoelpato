<?php

namespace Tests\Feature;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Dar de alta en el servidor los permisos que se agregan en el código.
 *
 * El seeder también los crea, pero hace syncPermissions sobre jefa y
 * secretaria: en el servidor eso les borraría lo que se les haya ajustado a
 * mano. Este comando solo agrega.
 */
class SincronizarPermisosTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function da_de_alta_los_permisos_que_faltan(): void
    {
        $this->assertDatabaseMissing('permissions', ['name' => 'recibos.validar']);

        $this->artisan('app:permisos-sincronizar')->assertSuccessful();

        $this->assertDatabaseHas('permissions', [
            'name' => 'recibos.validar',
            'guard_name' => 'web',
        ]);
    }

    #[Test]
    public function se_los_da_a_los_roles_que_tienen_todo(): void
    {
        $dueno = Role::create(['name' => 'dueño', 'guard_name' => 'web']);
        $admin = Role::create(['name' => 'admin', 'guard_name' => 'web']);

        $this->artisan('app:permisos-sincronizar')->assertSuccessful();

        foreach ([$dueno, $admin] as $rol) {
            $this->assertTrue($rol->fresh()->hasPermissionTo('recibos.validar'));
        }
    }

    #[Test]
    public function no_le_quita_nada_a_los_demas_roles(): void
    {
        Role::create(['name' => 'dueño', 'guard_name' => 'web']);

        // Un rol con permisos puestos a mano en el servidor, como los de jefa.
        $jefa = Role::create(['name' => 'jefa', 'guard_name' => 'web']);
        $jefa->givePermissionTo(Permission::create(['name' => 'algo.a.mano', 'guard_name' => 'web']));

        $this->artisan('app:permisos-sincronizar')->assertSuccessful();

        $jefa->refresh();

        // Lo suyo sigue ahí...
        $this->assertTrue($jefa->hasPermissionTo('algo.a.mano'));
        // ...y no se le regaló nada que no le tocaba.
        $this->assertFalse($jefa->hasPermissionTo('recibos.validar'));
    }

    #[Test]
    public function con_rol_se_le_da_solo_el_permiso_nuevo_no_el_catalogo(): void
    {
        $this->comoElServidor();

        Role::create(['name' => 'dueño', 'guard_name' => 'web']);
        $jefa = Role::create(['name' => 'jefa', 'guard_name' => 'web']);

        $this->artisan('app:permisos-sincronizar', ['--rol' => ['jefa']])->assertSuccessful();

        $jefa->refresh();

        $this->assertTrue($jefa->hasPermissionTo('recibos.validar'));

        // Darle el catálogo entero la subiría a administradora sin que nadie
        // lo pidiera: --rol es para los permisos nuevos, no para promover.
        $this->assertFalse($jefa->hasPermissionTo('usuarios.eliminar'));
        $this->assertFalse($jefa->hasPermissionTo('roles.crear'));
    }

    #[Test]
    public function en_una_base_vacia_se_niega_a_usar_rol(): void
    {
        Role::create(['name' => 'dueño', 'guard_name' => 'web']);
        $jefa = Role::create(['name' => 'jefa', 'guard_name' => 'web']);

        // Sin permisos en la base todo es "nuevo": --rol le daría todo.
        $this->artisan('app:permisos-sincronizar', ['--rol' => ['jefa']])
            ->expectsOutputToContain('Corre primero el seeder de roles')
            ->assertFailed();

        $this->assertSame(0, $jefa->fresh()->permissions->count());
    }

    /** El estado real del servidor: todo dado de alta menos lo que se acaba de agregar. */
    private function comoElServidor(): void
    {
        foreach (RolesAndPermissionsSeeder::PERMISOS as $permiso) {
            if ($permiso !== 'recibos.validar') {
                Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
            }
        }
    }

    #[Test]
    public function el_dry_run_no_toca_nada(): void
    {
        Role::create(['name' => 'dueño', 'guard_name' => 'web']);

        $this->artisan('app:permisos-sincronizar', ['--dry-run' => true])
            ->expectsOutputToContain('no se tocó la base')
            ->assertSuccessful();

        $this->assertDatabaseMissing('permissions', ['name' => 'recibos.validar']);
    }

    #[Test]
    public function correrlo_dos_veces_no_cambia_nada(): void
    {
        Role::create(['name' => 'dueño', 'guard_name' => 'web']);

        $this->artisan('app:permisos-sincronizar')->assertSuccessful();

        $cuantos = Permission::count();

        $this->artisan('app:permisos-sincronizar')
            ->expectsOutputToContain('Todo al corriente')
            ->assertSuccessful();

        $this->assertSame($cuantos, Permission::count());
    }

    #[Test]
    public function el_catalogo_del_seeder_es_del_que_sale_todo(): void
    {
        // Si el seeder deja de exponer el catálogo, el comando queda mudo y
        // los permisos nuevos no llegarían al servidor.
        $this->assertContains('recibos.validar', RolesAndPermissionsSeeder::PERMISOS);
        $this->assertGreaterThan(20, count(RolesAndPermissionsSeeder::PERMISOS));
    }
}
