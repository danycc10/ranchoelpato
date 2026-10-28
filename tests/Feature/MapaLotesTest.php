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

    public function test_la_galeria_muestra_todos_los_planos_incluso_sin_registros_y_protege_sus_rutas(): void
    {
        $rutas = [route('admin.mapas.index'), route('admin.mapas.show', 'reyes'), route('admin.mapas.datos', 'reyes')];
        foreach ($rutas as $ruta) {
            $this->get($ruta)->assertRedirect(route('login'));
        }
        $usuario = User::factory()->create();
        $this->actingAs($usuario);
        foreach ($rutas as $ruta) {
            $this->get($ruta)->assertForbidden();
        }
        foreach (['admin.ver', 'catalogos.ver'] as $permiso) {
            $usuario->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
        }
        $this->fraccionamiento();
        $this->fraccionamiento();
        $lista = app(MapaLotesService::class)->listado();
        $this->assertCount(count(MapaLotesService::MAPAS), $lista);
        $this->assertCount(2, $lista[0]['fincas']);
        $respuesta = $this->get($rutas[0])->assertOk();
        foreach (MapaLotesService::MAPAS as $clave => $nombre) {
            $respuesta->assertSee($nombre)->assertSee(route('admin.mapas.show', $clave), false);
        }
        $this->get($rutas[1])->assertOk()->assertSee('Medidas y colindancias capturadas')->assertSee('aún no tiene fraccionamientos');
        $this->getJson($rutas[2])->assertOk()->assertJsonCount(68, 'lotes')->assertJsonCount(0, 'fincas')
            ->assertJsonPath('vinculados', 0)->assertJsonStructure(['lotes' => [['medidas_capturadas' => [['orientacion', 'medida', 'colindancia']]]]]);
        $this->get(route('admin.mapas.show', 'inexistente'))->assertNotFound();
        $this->getJson(route('admin.mapas.datos', 'inexistente'))->assertNotFound();
    }

    public function test_la_ficha_usa_medidas_capturadas_sin_completar_vacios_desde_el_plano(): void
    {
        $finca = $this->fraccionamiento();
        $lote = $this->lote($finca, '1', 'disponible');
        $lote->update(['medida_norte' => '12.50 m', 'colindancia_norte' => 'Calle principal', 'medida_sur' => '10 + 2 m', 'medida_este' => '0', 'medida_oeste' => null]);
        $servicio = app(MapaLotesService::class);
        $datos = $servicio->datos($finca);
        $medidas = $datos['lotes'][0]['medidas_capturadas'];
        $this->assertSame(['Norte', 'Sur', 'Este', 'Oeste'], array_column($medidas, 'orientacion'));
        $this->assertSame('12.50 m', $medidas[0]['medida']);
        $this->assertSame('Calle principal', $medidas[0]['colindancia']);
        $this->assertSame('10 + 2 m', $medidas[1]['medida']);
        $this->assertSame('0', (string) $medidas[2]['medida']);
        $this->assertNull($medidas[3]['medida']);
        $this->assertArrayNotHasKey('lados', $datos['lotes'][0]);
        $this->assertSame([null, null, null, null], array_column($datos['lotes'][1]['medidas_capturadas'], 'medida'));
        $lote->update(['medida_norte' => '14 m']);
        $this->assertSame('14 m', $servicio->datos($finca)['lotes'][0]['medidas_capturadas'][0]['medida']);
        $this->lote($finca, '01', 'vendido', '2');
        $ambiguo = $servicio->datos($finca)['lotes'][0];
        $this->assertFalse($ambiguo['vinculado']);
        $this->assertSame([null, null, null, null], array_column($ambiguo['medidas_capturadas'], 'medida'));
    }

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
        $otra = $this->fraccionamiento();
        $otra->update(['mapa_clave' => null]);
        $this->lote($otra, '70', 'disponible');
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

    public function test_combina_tres_propietarios_y_el_enlace_abre_el_catalogo_correcto(): void
    {
        $fincas = [$this->fraccionamiento(), $this->fraccionamiento(), $this->fraccionamiento()];
        foreach ($fincas as $index => $finca) {
            $finca->propietario->update(['nombre' => 'Propietario '.($index + 1)]);
            $this->lote($finca, (string) ($index + 1), ['disponible', 'apartado', 'vendido'][$index]);
        }
        $servicio = app(MapaLotesService::class);
        $datos = $servicio->datos($fincas[0]);
        $this->assertCount(3, $datos['fincas']);
        $this->assertSame(3, $datos['vinculados']);
        $this->assertSame('Propietario 3', $datos['lotes'][2]['propietario']);
        $this->assertSame('vendido', $datos['lotes'][2]['estatus']);
        $this->assertStringContainsString('fraccionamientoFilter='.$fincas[2]->id, $datos['lotes'][2]['catalogo_url']);
        $this->assertSame($datos['lotes'], $servicio->datos($fincas[2])['lotes']);

        $this->lote($fincas[1], '03', 'disponible');
        $duplicados = $servicio->datos($fincas[0]);
        $this->assertSame('sin_vincular', $duplicados['lotes'][2]['estatus']);
        $this->assertNull($duplicados['lotes'][2]['propietario']);
        $this->assertNull($duplicados['lotes'][2]['catalogo_url']);
        $this->assertStringContainsString('Número repetido', $duplicados['lotes'][2]['aviso']);
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

    public function test_los_seis_planos_nuevos_tienen_contornos_cerrados_e_identificadores_unicos(): void
    {
        // Finca del Norte: 340 figuras del DWG más el 116-B, que en el plano no tiene rótulo.
        foreach (['ballesteros' => 48, 'finca-del-rio' => 255, 'finca-espinoza' => 49, 'finca-del-norte' => 341, 'finca-miranda' => 200, 'reyes' => 68] as $clave => $cantidad) {
            $plano = json_decode(file_get_contents(resource_path('mapas/'.$clave.'.json')), true, 512, JSON_THROW_ON_ERROR);
            $this->assertArrayHasKey($clave, MapaLotesService::MAPAS);
            $this->assertCount($cantidad, $plano['lotes']);
            $this->assertCount($cantidad, array_unique(array_column($plano['lotes'], 'id')));
            foreach ($plano['lotes'] as $figura) {
                $this->assertEquals($figura['puntos'][0], end($figura['puntos']));
                $this->assertGreaterThan(25, $figura['area_plano']);
            }
            if (in_array($clave, ['ballesteros', 'finca-del-rio', 'finca-del-norte', 'reyes'])) {
                $numeros = array_map('strval', range(1, $clave === 'finca-del-norte' ? 340 : $cantidad));
                if ($clave === 'finca-del-norte') {
                    array_splice($numeros, 116, 0, ['116-B']);
                }
                $this->assertSame($numeros, array_column($plano['lotes'], 'numero'));
            }
        }
    }

    public function test_finca_del_norte_vincula_el_116_b_aunque_el_catalogo_lo_escriba_distinto(): void
    {
        foreach (['116-B', '116 B', '116b'] as $captura) {
            $finca = $this->fraccionamiento();
            $finca->update(['mapa_clave' => 'finca-del-norte']);
            $this->lote($finca, '116', 'vendido');
            $this->lote($finca, $captura, 'apartado');
            $figuras = collect(app(MapaLotesService::class)->datos($finca)['lotes']);
            $this->assertSame('apartado', $figuras->firstWhere('numero', '116-B')['estatus'], $captura);
            $this->assertSame('vendido', $figuras->firstWhere('numero', '116')['estatus'], $captura);
            $finca->update(['mapa_clave' => null]);
        }
    }

    public function test_reyes_se_puede_asignar_y_conserva_referencias_y_disponibilidad(): void
    {
        $finca = $this->fraccionamiento();
        $finca->update(['mapa_clave' => null]);
        $this->lote($finca, '068', 'apartado');
        $this->artisan('mapas:vincular', ['fraccionamiento' => $finca->uuid, 'mapa' => 'reyes'])->assertSuccessful();
        $datos = app(MapaLotesService::class)->datos($finca->fresh());
        $this->assertCount(68, $datos['lotes']);
        $this->assertSame(1, $datos['vinculados']);
        $this->assertSame('apartado', $datos['lotes'][67]['estatus']);
        $this->assertContains('CANAL', array_column($datos['calles'], 'texto'));
        $this->assertContains('LINEAS ELECTRICAS', array_column($datos['calles'], 'texto'));
        $this->assertDatabaseCount('lotes', 1);
    }

    public function test_espinoza_distingue_manzanas_y_normaliza_sufijos_del_catalogo(): void
    {
        $finca = $this->fraccionamiento();
        $finca->update(['mapa_clave' => 'finca-espinoza']);
        $this->lote($finca, '001', 'disponible', '01');
        $this->lote($finca, '1', 'vendido', '2');
        $this->lote($finca, '17 A', 'apartado', '3');
        $datos = app(MapaLotesService::class)->datos($finca);
        $figuras = collect($datos['lotes']);
        $this->assertSame(3, $datos['vinculados']);
        $this->assertSame('disponible', $figuras->first(fn ($l) => $l['manzana_plano'] === '1' && $l['numero'] === '1')['estatus']);
        $this->assertSame('vendido', $figuras->first(fn ($l) => $l['manzana_plano'] === '2' && $l['numero'] === '1')['estatus']);
        $this->assertSame('apartado', $figuras->first(fn ($l) => $l['manzana_plano'] === '3' && $l['numero'] === '17A')['estatus']);
        $this->assertFalse($figuras->first(fn ($l) => $l['manzana_plano'] === '1' && $l['numero'] === '17A')['vinculado']);
    }

    public function test_miranda_no_colorea_dos_figuras_con_un_solo_registro(): void
    {
        $finca = $this->fraccionamiento();
        $finca->update(['mapa_clave' => 'finca-miranda']);
        $this->lote($finca, '106', 'vendido');
        $datos = app(MapaLotesService::class)->datos($finca);
        $repetidas = collect($datos['lotes'])->where('numero', '106');
        $this->assertCount(2, $repetidas);
        foreach ($repetidas as $figura) {
            $this->assertFalse($figura['vinculado']);
            $this->assertNull($figura['catalogo_url']);
            $this->assertStringContainsString('varias figuras', $figura['aviso']);
        }
        $this->assertSame(0, $datos['vinculados']);
        $this->assertSame(1, $datos['registros_sin_figura']);
    }

    public function test_del_rio_combina_registros_y_mantiene_los_duplicados_sin_vincular(): void
    {
        $primera = $this->fraccionamiento();
        $segunda = $this->fraccionamiento();
        $primera->update(['mapa_clave' => 'finca-del-rio']);
        $segunda->update(['mapa_clave' => 'finca-del-rio']);
        $this->lote($primera, '101', 'apartado');
        $this->lote($segunda, '128', 'vendido');
        $datos = app(MapaLotesService::class)->datos($segunda);
        $this->assertCount(2, $datos['fincas']);
        $this->assertSame(2, $datos['vinculados']);
        $this->assertSame('apartado', $datos['lotes'][100]['estatus']);
        $this->assertSame('vendido', $datos['lotes'][127]['estatus']);
        $this->lote($segunda, '101', 'disponible');
        $datos = app(MapaLotesService::class)->datos($primera);
        $this->assertFalse($datos['lotes'][100]['vinculado']);
        $this->assertSame(1, $datos['vinculados']);
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
    }

    public function test_el_catalogo_de_lotes_ya_no_lleva_un_boton_por_cada_plano(): void
    {
        $finca = $this->fraccionamiento();
        $usuario = User::factory()->create();
        foreach (['admin.ver', 'catalogos.ver'] as $permiso) {
            $usuario->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
        }

        // Era un boton por fraccionamiento con plano: con una docena de fincas
        // el encabezado se desbordaba. El acceso vive en Fraccionamientos.
        Livewire::actingAs($usuario)->test(\App\Livewire\Admin\Lotes\Index::class)
            ->assertDontSee(route('admin.fraccionamientos.mapa', $finca), false)
            ->assertDontSee('Mapa · '.$finca->nombre);
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
