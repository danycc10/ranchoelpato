<?php

namespace Tests\Feature;

use App\Livewire\Admin\Fraccionamientos\Index;
use App\Models\Fraccionamiento;
use App\Models\Lote;
use App\Models\Propietario;
use App\Models\User;
use App\Services\MapaLotesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MapaLotesTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_plano_conserva_los_150_numeros_y_las_cinco_calles(): void
    {
        $datos = app(MapaLotesService::class)->datos($this->fraccionamiento());
        $this->assertSame(array_map('strval', range(1, 150)), array_column($datos['lotes'], 'numero'));
        foreach (range(1, 5) as $numero) {
            $this->assertContains('CALLE '.$numero, array_column($datos['calles'], 'texto'));
        }
        $this->assertSame(0, $datos['vinculados']);
        $this->assertSame(['sin_vincular'], array_values(array_unique(array_column($datos['lotes'], 'estatus'))));
    }

    public function test_solo_vincula_lotes_del_fraccionamiento_elegido_y_refleja_cambios_del_catalogo(): void
    {
        $finca = $this->fraccionamiento();
        $lote = $this->lote($finca, '069', 'vendido');
        $this->lote($this->fraccionamiento(), '70', 'disponible');
        $servicio = app(MapaLotesService::class);
        $datos = $servicio->datos($finca);
        $this->assertSame('vendido', $datos['lotes'][68]['estatus']);
        $this->assertSame('sin_vincular', $datos['lotes'][69]['estatus']);
        $this->assertSame('20.00', $datos['lotes'][68]['area_registrada']);
        $this->assertNotEquals(20, $datos['lotes'][68]['area_plano']);
        $this->assertSame(1, $datos['vinculados']);
        $lote->update(['estatus' => 'apartado']);
        $this->assertSame('apartado', $servicio->datos($finca)['lotes'][68]['estatus']);
        $this->assertSame('20.00', $lote->fresh()->area_m2);
    }

    public function test_no_asigna_disponibilidad_si_un_numero_se_repite_en_varias_manzanas(): void
    {
        $finca = $this->fraccionamiento();
        $this->lote($finca, '3', 'disponible');
        $this->lote($finca, '03', 'vendido', '2');
        $datos = app(MapaLotesService::class)->datos($finca);
        $this->assertFalse($datos['lotes'][2]['vinculado']);
        $this->assertSame('sin_vincular', $datos['lotes'][2]['estatus']);
        $this->assertStringContainsString('manzana', $datos['lotes'][2]['aviso']);
        $this->assertSame(2, $datos['registros_sin_figura']);
    }

    public function test_mapa_y_datos_requieren_sesion_y_permiso_de_catalogos(): void
    {
        $finca = $this->fraccionamiento();
        foreach (['admin.ver', 'catalogos.ver'] as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }
        $usuario = User::factory()->create();
        foreach (['admin.fraccionamientos.mapa', 'admin.fraccionamientos.mapa.datos'] as $ruta) {
            $this->get(route($ruta, $finca))->assertRedirect(route('login'));
        }
        $this->actingAs($usuario);
        foreach (['admin.fraccionamientos.mapa', 'admin.fraccionamientos.mapa.datos'] as $ruta) {
            $this->get(route($ruta, $finca))->assertForbidden();
        }
        $usuario->givePermissionTo(['admin.ver', 'catalogos.ver']);
        $this->get(route('admin.fraccionamientos.mapa', $finca))->assertOk()->assertSee('Buscar lote');
        $this->getJson(route('admin.fraccionamientos.mapa.datos', $finca))
            ->assertOk()->assertJsonCount(150, 'lotes')->assertJsonPath('vinculados', 0);
        $finca->forceFill(['mapa_clave' => '../privado'])->save();
        $this->getJson(route('admin.fraccionamientos.mapa.datos', $finca))->assertNotFound();
    }

    public function test_vincular_el_plano_no_crea_ni_modifica_lotes(): void
    {
        $finca = $this->fraccionamiento();
        $finca->forceFill(['mapa_clave' => null])->save();
        $lote = $this->lote($finca, '3', 'donacion');
        $antes = $lote->fresh()->getAttributes();
        $this->artisan('mapas:vincular', ['fraccionamiento' => $finca->uuid])->assertSuccessful();
        $this->assertSame('finca-escondida', $finca->fresh()->mapa_clave);
        $this->assertSame($antes, $lote->fresh()->getAttributes());
        $this->assertDatabaseCount('lotes', 1);
    }

    public function test_se_puede_elegir_el_plano_desde_el_catalogo_y_aparece_su_acceso(): void
    {
        $finca = $this->fraccionamiento();
        $finca->update(['mapa_clave' => null]);
        $usuario = User::factory()->create();
        foreach (['admin.ver', 'catalogos.ver'] as $permiso) {
            $usuario->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
        }
        Livewire::actingAs($usuario)->test(Index::class)
            ->call('editar', $finca->id)
            ->set('mapa_clave', 'finca-escondida')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSee('Ver mapa');
        $this->assertSame('finca-escondida', $finca->fresh()->mapa_clave);
        Livewire::actingAs($usuario)->test(\App\Livewire\Admin\Lotes\Index::class)
            ->assertSee(route('admin.fraccionamientos.mapa', $finca), false);
    }

    private function fraccionamiento(): Fraccionamiento
    {
        $finca = Fraccionamiento::create(['nombre' => 'Finca de prueba', 'propietario_id' => Propietario::create(['nombre' => 'Propietario'])->id]);
        $finca->forceFill(['mapa_clave' => 'finca-escondida'])->save();

        return $finca;
    }

    private function lote(Fraccionamiento $finca, string $numero, string $estatus, string $manzana = '1'): Lote
    {
        return Lote::create(['fraccionamiento_id' => $finca->id, 'lote' => $numero, 'manzana' => $manzana, 'clave' => $finca->id.'-'.$manzana.'-'.$numero, 'estatus' => $estatus, 'area_m2' => 20]);
    }
}
