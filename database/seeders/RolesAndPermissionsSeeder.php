<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        /*
        |--------------------------------------------------------------------------
        | PERMISOS
        |--------------------------------------------------------------------------
        */

        $perms = [

            // Dashboard
            'dashboard.ver',
            'admin.ver',

            // Reportes
            'reportes.ver',
            'reportes.diario_recibos',
            'reportes.cliente_pagos',
            'reportes.ingresos_mensuales',
            'reportes.movimientos_bancarios',

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


        foreach ($perms as $perm) {
            Permission::firstOrCreate([
                'name' => $perm,
                'guard_name' => 'web'
            ]);
        }


        /*
        |--------------------------------------------------------------------------
        | ROLES
        |--------------------------------------------------------------------------
        */

        $dueno = Role::firstOrCreate([
            'name' => 'dueño',
            'guard_name' => 'web'
        ]);

        $admin = Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'web'
        ]);

        $jefa = Role::firstOrCreate([
            'name' => 'jefa',
            'guard_name' => 'web'
        ]);

        $secretaria = Role::firstOrCreate([
            'name' => 'secretaria',
            'guard_name' => 'web'
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

            'reportes.ver',
            'reportes.diario_recibos',
            'reportes.ingresos_mensuales',

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
