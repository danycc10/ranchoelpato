<?php

namespace Tests\Feature;

use App\Livewire\Admin\ContratosServicios\Create;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Fraccionamiento;
use App\Models\Lote;
use App\Models\Propietario;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ContratoServicioSobreLiquidadoTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function un_terreno_liquidado_puede_contratar_servicio(): void
    {
        $this->contratoTerreno('CT-ACTIVO', 'activo');
        $this->contratoTerreno('CT-LIQUIDADO', 'liquidado');

        $sugeridos = $this->buscar('CT-');

        $this->assertContains('CT-ACTIVO', $sugeridos->pluck('folio')->all());
        $this->assertContains('CT-LIQUIDADO', $sugeridos->pluck('folio')->all());

        // El liquidado se marca para no confundirlo al elegir.
        $this->assertNull($sugeridos->firstWhere('folio', 'CT-ACTIVO')['estatus']);
        $this->assertSame('LIQUIDADO', $sugeridos->firstWhere('folio', 'CT-LIQUIDADO')['estatus']);
    }

    #[Test]
    public function los_contratos_cancelados_siguen_fuera(): void
    {
        $this->contratoTerreno('CT-CANCELADO', 'cancelado');
        $this->contratoTerreno('CT-MOROSO', 'moroso');

        $folios = $this->buscar('CT-')->pluck('folio')->all();

        $this->assertNotContains('CT-CANCELADO', $folios);
        $this->assertNotContains('CT-MOROSO', $folios);
    }

    #[Test]
    public function el_contrato_de_luz_se_da_de_alta_sobre_uno_liquidado(): void
    {
        $base = $this->contratoTerreno('CT-LIQUIDADO', 'liquidado');

        Livewire::actingAs($this->usuarioConPermisos())
            ->test(Create::class)
            ->call('selectContratoBase', $base->id)
            ->assertSet('contrato_base_id', $base->id);

        $this->assertSame(
            $base->id,
            Contrato::query()->where('folio_contrato', 'CT-LIQUIDADO')->value('id')
        );
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function buscar(string $termino)
    {
        $componente = Livewire::actingAs($this->usuarioConPermisos())
            ->test(Create::class)
            ->set('contrato_q', $termino);

        return collect($componente->get('contratos_suggest'))
            ->map(fn (array $sugerido) => [
                'folio' => explode(' · ', $sugerido['label'])[0],
                'estatus' => $sugerido['estatus'],
            ]);
    }

    private function usuarioConPermisos(): User
    {
        $usuario = User::factory()->create();

        $rol = Role::findOrCreate('admin', 'web');

        foreach (['admin.ver', 'contratos.editar', 'contratos.ver_detalle'] as $permiso) {
            $rol->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
        }

        $usuario->assignRole($rol);

        return $usuario;
    }

    private function contratoTerreno(string $folio, string $estatus): Contrato
    {
        $propietario = Propietario::firstOrCreate(
            ['nombre' => 'El Pato'],
            ['nombre_legal' => 'INMOBILIARIA EL PATO']
        );

        $fraccionamiento = Fraccionamiento::firstOrCreate(
            ['nombre' => 'Rancho El Pato'],
            ['propietario_id' => $propietario->id]
        );

        $lote = Lote::create([
            'fraccionamiento_id' => $fraccionamiento->id,
            'manzana' => '5',
            'lote' => (string) random_int(100, 999),
            'clave' => 'M5-L'.random_int(100, 999),
        ]);

        $cliente = Cliente::firstOrCreate(
            ['nombres' => 'Maria', 'apellidos' => 'Lopez Garza'],
            ['telefono' => '8781234567']
        );

        return Contrato::create([
            'cliente_id' => $cliente->id,
            'lote_id' => $lote->id,
            'folio_contrato' => $folio,
            'fecha_inicio' => '2026-01-10',
            'frecuencia' => 'mensual',
            'dia_mes' => 10,
            'precio_total' => 100000,
            'enganche' => 10000,
            'saldo_inicial' => 90000,
            'saldo_actual' => $estatus === 'liquidado' ? 0 : 90000,
            'monto_pago' => 3000,
            'tipo_recargo' => 'fijo',
            'valor_recargo' => 100,
            'dias_gracia' => 3,
            'estatus' => $estatus,
            'liquidado_at' => $estatus === 'liquidado' ? now() : null,
            'tipo' => 'terreno',
        ]);
    }
}
