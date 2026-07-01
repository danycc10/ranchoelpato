<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillNombresLegales extends Command
{
    protected $signature = 'app:backfill-nombres-legales {--dry-run : Muestra pendientes sin actualizar datos}';

    protected $description = 'Rellena nombres legales faltantes en clientes, propietarios y contratos.';

    public function handle(): int
    {
        $pendientes = $this->contarPendientes();

        $this->info('Pendientes antes del backfill:');
        $this->mostrarConteos($pendientes);

        if ($this->option('dry-run')) {
            $this->warn('Dry run: no se actualizaron datos.');

            return self::SUCCESS;
        }

        $actualizados = DB::transaction(fn () => [
            'Clientes nombre legal' => DB::update(
                "update clientes
                    set nombre_legal = trim(concat_ws(' ', nombres, apellidos)),
                        updated_at = now()
                  where (nombre_legal is null or trim(nombre_legal) = '')
                    and trim(concat_ws(' ', nombres, apellidos)) <> ''"
            ),
            'Propietarios nombre legal' => DB::update(
                "update propietarios
                    set nombre_legal = trim(nombre),
                        updated_at = now()
                  where (nombre_legal is null or trim(nombre_legal) = '')
                    and trim(coalesce(nombre, '')) <> ''"
            ),
            'Contratos comprador legal' => DB::update(
                "update contratos
                    join clientes on clientes.id = contratos.cliente_id
                    set contratos.comprador_nombre_legal = trim(coalesce(
                            nullif(trim(clientes.nombre_legal), ''),
                            nullif(trim(concat_ws(' ', clientes.nombres, clientes.apellidos)), '')
                        )),
                        contratos.updated_at = now()
                  where (contratos.comprador_nombre_legal is null or trim(contratos.comprador_nombre_legal) = '')
                    and coalesce(
                        nullif(trim(clientes.nombre_legal), ''),
                        nullif(trim(concat_ws(' ', clientes.nombres, clientes.apellidos)), '')
                    ) is not null"
            ),
            'Contratos vendedor legal' => DB::update(
                "update contratos
                    join lotes on lotes.id = contratos.lote_id
                    join fraccionamientos on fraccionamientos.id = lotes.fraccionamiento_id
                    join propietarios on propietarios.id = fraccionamientos.propietario_id
                    set contratos.vendedor_nombre_legal = trim(coalesce(
                            nullif(trim(propietarios.nombre_legal), ''),
                            nullif(trim(propietarios.nombre), '')
                        )),
                        contratos.updated_at = now()
                  where (contratos.vendedor_nombre_legal is null or trim(contratos.vendedor_nombre_legal) = '')
                    and coalesce(
                        nullif(trim(propietarios.nombre_legal), ''),
                        nullif(trim(propietarios.nombre), '')
                    ) is not null"
            ),
        ]);

        $this->info('Registros actualizados:');
        $this->mostrarConteos($actualizados);

        $restantes = $this->contarPendientes();

        $this->info('Pendientes despues del backfill:');
        $this->mostrarConteos($restantes);

        return self::SUCCESS;
    }

    private function contarPendientes(): array
    {
        return [
            'Clientes nombre legal' => $this->contar(
                "select count(*) as c
                   from clientes
                  where (nombre_legal is null or trim(nombre_legal) = '')
                    and trim(concat_ws(' ', nombres, apellidos)) <> ''"
            ),
            'Propietarios nombre legal' => $this->contar(
                "select count(*) as c
                   from propietarios
                  where (nombre_legal is null or trim(nombre_legal) = '')
                    and trim(coalesce(nombre, '')) <> ''"
            ),
            'Contratos comprador legal' => $this->contar(
                "select count(*) as c
                   from contratos
                   join clientes on clientes.id = contratos.cliente_id
                  where (contratos.comprador_nombre_legal is null or trim(contratos.comprador_nombre_legal) = '')
                    and trim(concat_ws(' ', clientes.nombres, clientes.apellidos)) <> ''"
            ),
            'Contratos vendedor legal' => $this->contar(
                "select count(*) as c
                   from contratos
                   join lotes on lotes.id = contratos.lote_id
                   join fraccionamientos on fraccionamientos.id = lotes.fraccionamiento_id
                   join propietarios on propietarios.id = fraccionamientos.propietario_id
                  where (contratos.vendedor_nombre_legal is null or trim(contratos.vendedor_nombre_legal) = '')
                    and trim(coalesce(propietarios.nombre, '')) <> ''"
            ),
        ];
    }

    private function contar(string $sql): int
    {
        return (int) DB::selectOne($sql)->c;
    }

    private function mostrarConteos(array $conteos): void
    {
        $this->table(
            ['Dato', 'Cantidad'],
            collect($conteos)
                ->map(fn (int $cantidad, string $dato) => [$dato, $cantidad])
                ->values()
                ->all()
        );
    }
}
