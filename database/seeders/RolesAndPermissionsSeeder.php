<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * El catalogo completo de permisos.
     *
     * Vive aqui para que `app:permisos-sincronizar` pueda dar de alta en el
     * servidor los que se agregan en el codigo, sin volver a correr el seeder
     * (que con syncPermissions le borraria a jefa y secretaria lo que se les
     * haya ajustado a mano desde la pantalla de roles).
     *
     * @var array<int, string>
     */
    public const PERMISOS = [

        // Dashboard
        'dashboard.ver',
        'dashboard.indicadores',
        'admin.ver',

        // Reportes
        'reportes.ver',
        'reportes.diario_recibos',
        'reportes.cliente_pagos',
        'reportes.ingresos_mensuales',
        'reportes.movimientos_bancarios',
        'reportes.carga_trabajo',

        // Catálogos
        'catalogos.ver',
        'catalogos.editar',

        // Clientes
        'clientes.ver',
        'clientes.editar',

        // Contratos terreno
        'contratos.ver',
        'contratos.editar',
        'contratos.ver_detalle',

        // Contratos servicios
        'contratos_servicios.ver',
        'contratos_servicios.editar',
        'contratos_servicios.ver_detalle',

        // Cobranza
        'cuotas.ver',
        'recibos.crear',
        'recibos.imprimir',
        'recibos.eliminar',
        'recibos.validar',

        // Clientes excelentes
        'clientes_excelentes.ver',
        'clientes_excelentes.condonar',

        // SISTEMA
        'sistema.ver',

        // Usuarios
        'usuarios.ver',
        'usuarios.crear',
        'usuarios.editar',
        'usuarios.eliminar',

        // Roles
        'roles.ver',
        'roles.crear',
        'roles.editar',
        'roles.eliminar',

        // Logs
        'logs.ver',
        'logs.detalle',
        'logs.eliminar',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        /*
        |--------------------------------------------------------------------------
        | PERMISOS
        |--------------------------------------------------------------------------
        */

        $perms = self::PERMISOS;

        foreach ($perms as $perm) {
            Permission::firstOrCreate([
                'name' => $perm,
                'guard_name' => 'web',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | ROLES
        |--------------------------------------------------------------------------
        */

        $dueno = Role::firstOrCreate([
            'name' => 'dueño',
            'guard_name' => 'web',
        ]);

        $admin = Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'web',
        ]);

        $jefa = Role::firstOrCreate([
            'name' => 'jefa',
            'guard_name' => 'web',
        ]);

        $secretaria = Role::firstOrCreate([
            'name' => 'secretaria',
            'guard_name' => 'web',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Dueño → TODO
        |--------------------------------------------------------------------------
        */

        $dueno->syncPermissions(Permission::all());

        /*
        |--------------------------------------------------------------------------
        | Admin → TODO excepto eliminar logs y roles críticos
        |--------------------------------------------------------------------------
        */

        $admin->syncPermissions(Permission::all());

        /*
        |--------------------------------------------------------------------------
        | Jefa
        |--------------------------------------------------------------------------
        */

        $jefa->syncPermissions([
            'dashboard.ver',
            'dashboard.indicadores',
            'admin.ver',

            'reportes.ver',
            'reportes.diario_recibos',
            'reportes.cliente_pagos',
            'reportes.ingresos_mensuales',

            'catalogos.ver',
            'catalogos.editar',

            'clientes.ver',
            'clientes.editar',

            'contratos.ver',
            'contratos.editar',
            'contratos.ver_detalle',

            'contratos_servicios.ver',
            'contratos_servicios.editar',
            'contratos_servicios.ver_detalle',

            'cuotas.ver',
            'recibos.crear',
            'recibos.imprimir',
            'recibos.eliminar',
            'recibos.validar',

            'clientes_excelentes.ver',
            'clientes_excelentes.condonar',

            'sistema.ver',
            'usuarios.ver',
            'logs.ver',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Secretaria
        |--------------------------------------------------------------------------
        */

        $secretaria->syncPermissions([
            'dashboard.ver',
            'admin.ver',

            // Sin reportes: son los números del negocio, no su herramienta
            // de captura.

            'catalogos.ver',

            'clientes.ver',
            'clientes.editar',

            'contratos.ver',
            'contratos.editar',

            'contratos_servicios.ver',
            'contratos_servicios.editar',

            'cuotas.ver',
            'recibos.crear',
            'recibos.imprimir',

            'clientes_excelentes.ver',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
