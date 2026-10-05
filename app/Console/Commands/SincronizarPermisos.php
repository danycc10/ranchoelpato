<?php

namespace App\Console\Commands;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Da de alta en la base los permisos que se agregaron en el código.
 *
 * El seeder de roles también los crea, pero de paso hace `syncPermissions`
 * sobre jefa y secretaria: correrlo en el servidor les borraría cualquier
 * ajuste hecho a mano desde la pantalla de roles. Este comando solo agrega
 * —nunca quita—, así que se puede correr en producción sin pensarlo.
 *
 * Los permisos nuevos se le dan a los roles que mandan (dueño y admin), que
 * por definición tienen todo. A los demás se les asignan desde la pantalla
 * de roles, que es donde se decide quién hace qué.
 */
class SincronizarPermisos extends Command
{
    protected $signature = 'app:permisos-sincronizar
        {--dry-run : Muestra lo que haría sin tocar la base}
        {--rol=* : Roles extra a los que darles los permisos nuevos}';

    protected $description = 'Crea los permisos que faltan y se los da a los roles que tienen todo.';

    /** Los roles que por definición tienen el catálogo completo. */
    private const ROLES_CON_TODO = ['dueño', 'admin'];

    public function handle(): int
    {
        $faltantes = $this->permisosFaltantes();
        $rolesSinPermiso = $this->rolesQueDebenTenerlos($faltantes);

        if ($faltantes->isEmpty() && $rolesSinPermiso->isEmpty()) {
            $this->info('Todo al corriente: no falta ningún permiso.');

            return self::SUCCESS;
        }

        if ($faltantes->isNotEmpty()) {
            $this->info($faltantes->count().' permiso(s) por dar de alta:');
            $faltantes->each(fn (string $permiso) => $this->line('  · '.$permiso));
        }

        foreach ($rolesSinPermiso as $rol => $permisos) {
            $this->info("El rol «{$rol}» no tiene: ".implode(', ', $permisos));
        }

        if ($this->option('dry-run')) {
            $this->warn('Dry run: no se tocó la base.');

            return self::SUCCESS;
        }

        // En una base recién levantada TODO es nuevo, y --rol acabaría dandole
        // el catálogo entero a un rol que no manda. Para eso está el seeder.
        if ($this->extras() !== [] && $faltantes->count() === count(RolesAndPermissionsSeeder::PERMISOS)) {
            $this->error(
                'La base no tiene ningún permiso todavía: --rol le daría el catálogo completo a '
                .implode(', ', $this->extras()).'. Corre primero el seeder de roles.'
            );

            return self::FAILURE;
        }

        $this->aplicar($faltantes);

        $this->info('Listo. Los permisos quedaron dados de alta y asignados.');
        $this->line('Para los demás roles, asígnalos desde la pantalla de Roles.');

        return self::SUCCESS;
    }

    /** @return Collection<int, string> */
    private function permisosFaltantes(): Collection
    {
        $existentes = Permission::where('guard_name', 'web')->pluck('name');

        return collect(RolesAndPermissionsSeeder::PERMISOS)
            ->diff($existentes)
            ->values();
    }

    /**
     * Qué le falta a cada rol destino. Se revisa aparte de los permisos
     * faltantes: uno puede existir en la base y aun así no habérsele dado a
     * nadie, que es como queda un permiso nuevo tras un despliegue.
     *
     * @param  Collection<int, string>  $faltantes
     * @return Collection<string, array<int, string>>
     */
    private function rolesQueDebenTenerlos(Collection $faltantes): Collection
    {
        return collect($this->rolesDestino())
            ->mapWithKeys(function (string $nombre) use ($faltantes) {
                $rol = Role::where('name', $nombre)->where('guard_name', 'web')->first();

                if (! $rol) {
                    return [$nombre => []];
                }

                $leTocan = collect($this->permisosParaElRol($nombre, $faltantes));

                return [$nombre => $leTocan->diff($rol->permissions->pluck('name'))->values()->all()];
            })
            ->filter(fn (array $permisos) => $permisos !== []);
    }

    /**
     * Qué permisos le corresponden a cada rol destino.
     *
     * Dueño y admin tienen el catálogo completo por definición. A un rol que
     * llega por --rol solo se le dan los permisos NUEVOS: darle el catálogo
     * entero lo subiría a administrador sin que nadie lo pidiera.
     *
     * @param  Collection<int, string>  $faltantes
     * @return array<int, string>
     */
    private function permisosParaElRol(string $nombre, Collection $faltantes): array
    {
        return in_array($nombre, self::ROLES_CON_TODO, true)
            ? RolesAndPermissionsSeeder::PERMISOS
            : $faltantes->values()->all();
    }

    /** @return array<int, string> */
    private function rolesDestino(): array
    {
        return array_values(array_unique(array_merge(self::ROLES_CON_TODO, $this->extras())));
    }

    /**
     * Los roles que llegan por --rol, sin contar los que ya tienen todo.
     *
     * @return array<int, string>
     */
    private function extras(): array
    {
        return array_values(array_diff(
            array_filter((array) $this->option('rol')),
            self::ROLES_CON_TODO
        ));
    }

    /** @param  Collection<int, string>  $faltantes */
    private function aplicar(Collection $faltantes): void
    {
        foreach ($faltantes as $permiso) {
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->rolesDestino() as $nombre) {
            $rol = Role::where('name', $nombre)->where('guard_name', 'web')->first();

            if (! $rol) {
                $this->warn("El rol «{$nombre}» no existe: se omite.");

                continue;
            }

            $permisos = $this->permisosParaElRol($nombre, $faltantes);

            if ($permisos === []) {
                continue;
            }

            // givePermissionTo solo agrega. syncPermissions borraría lo que el
            // rol tenga de más, y en el servidor eso puede estar puesto a mano.
            $rol->givePermissionTo($permisos);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
