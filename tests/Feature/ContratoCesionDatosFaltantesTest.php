<?php

namespace Tests\Feature;

use App\Livewire\Admin\Contratos\Show;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Fraccionamiento;
use App\Models\Lote;
use App\Models\Propietario;
use App\Models\User;
use App\Services\Contratos\ContratoWordService;
use App\Services\Contratos\DatosContratoCesionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use ZipArchive;

class ContratoCesionDatosFaltantesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function detecta_los_datos_que_le_faltan_al_contrato(): void
    {
        $contrato = $this->contratoSinDatos();

        $faltantes = collect(DatosContratoCesionService::faltantes($contrato));
        $claves = $faltantes->pluck('clave');

        $this->assertContains('fraccionamiento.ejido', $claves);
        $this->assertContains('fraccionamiento.municipio', $claves);
        $this->assertContains('propietario.ocupacion', $claves);
        $this->assertContains('cliente.direccion', $claves);
        $this->assertContains('contrato.area_m2_snapshot', $claves);

        // Lo que sí está capturado no se pide.
        $this->assertNotContains('cliente.telefono', $claves);
        $this->assertNotContains('propietario.curp', $claves);

        // Cada dato sabe a qué bloque del contrato pertenece.
        $this->assertSame(
            'Fraccionamiento: Rancho El Pato',
            $faltantes->firstWhere('clave', 'fraccionamiento.ejido')['grupo']
        );

        // El antecedente se elige de la lista del formato, no se escribe libre.
        $antecedente = $faltantes->firstWhere('clave', 'fraccionamiento.antecedente_tipo');

        $this->assertSame('antecedente', $antecedente['tipo']);
        $this->assertSame(
            ['Certificado parcelario', 'Contrato', 'Cesión', 'Constancia de posesión', 'Resolución', 'Otro'],
            array_values(DatosContratoCesionService::opciones($antecedente['tipo']))
        );
    }

    #[Test]
    public function la_superficie_del_catalogo_del_lote_no_se_vuelve_a_pedir(): void
    {
        $contrato = $this->contratoSinDatos();
        $contrato->lote->update(['area_m2' => 250]);

        $claves = collect(DatosContratoCesionService::faltantes($contrato->fresh()))->pluck('clave');

        $this->assertNotContains('contrato.area_m2_snapshot', $claves);
    }

    #[Test]
    public function la_superficie_sale_del_lote_cuando_el_contrato_no_la_congelo(): void
    {
        Storage::fake('private');

        $this->artisan('contratos:plantilla-cesion')->assertSuccessful();

        $contrato = $this->contratoSinDatos();
        $contrato->lote->update(['area_m2' => 250]);

        $texto = $this->textoDocx(Storage::disk('private')->path(
            (new ContratoWordService)->generarDocx($contrato->fresh(), 'contrato_cesion_derechos')
        ));

        $this->assertStringContainsString('250.00 m²', $texto);
    }

    #[Test]
    public function sin_superficie_capturada_el_documento_deja_la_raya(): void
    {
        Storage::fake('private');

        $this->artisan('contratos:plantilla-cesion')->assertSuccessful();

        // Ni el contrato ni el lote la traen: un 0.00 seria un dato falso en
        // un documento legal, asi que se deja la raya para llenarla a mano.
        $contrato = $this->contratoSinDatos();

        $texto = $this->textoDocx(Storage::disk('private')->path(
            (new ContratoWordService)->generarDocx($contrato->fresh(), 'contrato_cesion_derechos')
        ));

        $this->assertStringNotContainsString('0.00 m²', $texto);
        $this->assertStringContainsString('____________________', $texto);
    }

    #[Test]
    public function el_modal_guarda_los_datos_y_regenera_el_word(): void
    {
        Storage::fake('private');

        $this->artisan('contratos:plantilla-cesion')->assertSuccessful();

        $contrato = $this->contratoSinDatos();

        Livewire::actingAs($this->usuarioConPermisos())
            ->test(Show::class, ['uuid' => $contrato->uuid])
            ->call('abrirModalContrato')
            ->call('completarDatosCesion')
            ->assertSet('completandoDatosCesion', true)
            ->assertSet('pasoDatosCesion', 0)
            ->set('datosCesion.fraccionamiento.ejido', 'EJIDO LA MOTA')
            ->set('datosCesion.fraccionamiento.municipio', 'Piedras Negras')
            // Los datos capturados sobreviven al cambiar de bloque.
            ->call('irAPasoDatosCesion', 2)
            ->assertSet('pasoDatosCesion', 2)
            ->set('datosCesion.cliente.ocupacion', 'Enfermera')
            ->call('irAPasoDatosCesion', 3)
            ->set('datosCesion.contrato.area_m2_snapshot', '250')
            ->call('guardarDatosCesion')
            ->assertSet('completandoDatosCesion', false)
            ->assertSet('pasoDatosCesion', 0);

        $contrato->refresh();

        $this->assertSame('EJIDO LA MOTA', $contrato->lote->fraccionamiento->ejido);
        $this->assertSame('Piedras Negras', $contrato->lote->fraccionamiento->municipio);
        $this->assertSame('Enfermera', $contrato->cliente->ocupacion);
        $this->assertSame(250.0, (float) $contrato->area_m2_snapshot);

        // El Word quedó regenerado con lo capturado.
        $this->assertNotNull($contrato->archivo_contrato_cesion_docx);

        $texto = $this->textoDocx(
            Storage::disk('private')->path($contrato->archivo_contrato_cesion_docx)
        );

        $this->assertStringContainsString('EJIDO LA MOTA', $texto);
        $this->assertStringContainsString('Enfermera', $texto);
        $this->assertStringContainsString('250.00 m²', $texto);
    }

    #[Test]
    public function lo_capturado_queda_guardado_para_los_siguientes_contratos(): void
    {
        $contrato = $this->contratoSinDatos();

        DatosContratoCesionService::guardar($contrato, [
            'fraccionamiento' => ['ejido' => 'EJIDO LA MOTA', 'municipio' => 'Piedras Negras'],
            'propietario' => ['ocupacion' => 'Comerciante'],
            'cliente' => ['direccion' => 'Calle Hidalgo 123'],
            'contrato' => ['area_m2_snapshot' => '250', 'colindancia_norte_snapshot' => 'Calle Juárez'],
        ]);

        // El lote también queda con sus datos en el catálogo.
        $lote = $contrato->lote->fresh();
        $this->assertSame(250.0, (float) $lote->area_m2);
        $this->assertSame('Calle Juárez', $lote->colindancia_norte);

        // Otro contrato del mismo fraccionamiento y cliente ya no los pide.
        $otro = $this->otroContrato($contrato);

        $claves = collect(DatosContratoCesionService::faltantes($otro))->pluck('clave');

        $this->assertNotContains('fraccionamiento.ejido', $claves);
        $this->assertNotContains('fraccionamiento.municipio', $claves);
        $this->assertNotContains('propietario.ocupacion', $claves);
        $this->assertNotContains('cliente.direccion', $claves);
    }

    #[Test]
    public function el_catalogo_del_lote_no_se_pisa_con_lo_del_contrato(): void
    {
        $contrato = $this->contratoSinDatos();
        $contrato->lote->update(['colindancia_norte' => 'Dato original']);

        DatosContratoCesionService::guardar($contrato, [
            'contrato' => ['colindancia_norte_snapshot' => 'Dato del contrato'],
        ]);

        $this->assertSame('Dato original', $contrato->lote->fresh()->colindancia_norte);
        $this->assertSame('Dato del contrato', $contrato->fresh()->colindancia_norte_snapshot);
    }

    #[Test]
    public function lo_que_se_deja_vacio_no_se_guarda(): void
    {
        $contrato = $this->contratoSinDatos();

        $guardados = DatosContratoCesionService::guardar($contrato, [
            'fraccionamiento' => ['ejido' => '  ', 'municipio' => 'Nava'],
            'contrato' => ['area_m2_snapshot' => '0'],
            'cliente' => ['campo_inventado' => 'x'],
        ]);

        $this->assertSame(1, $guardados);
        $this->assertNull($contrato->lote->fraccionamiento->fresh()->ejido);
        $this->assertSame('Nava', $contrato->lote->fraccionamiento->fresh()->municipio);
    }

    private function usuarioConPermisos(): User
    {
        $usuario = User::factory()->create();

        $rol = Role::findOrCreate('admin', 'web');

        foreach (['sistema.ver', 'contratos.ver_detalle'] as $permiso) {
            $rol->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
        }

        $usuario->assignRole($rol);

        return $usuario;
    }

    private function contratoSinDatos(): Contrato
    {
        $propietario = Propietario::create([
            'nombre' => 'El Pato',
            'nombre_legal' => 'INMOBILIARIA EL PATO',
            'curp' => 'MIRA800101HCLXRR01',
        ]);

        $fraccionamiento = Fraccionamiento::create([
            'propietario_id' => $propietario->id,
            'nombre' => 'Rancho El Pato',
        ]);

        $lote = Lote::create([
            'fraccionamiento_id' => $fraccionamiento->id,
            'manzana' => '5',
            'lote' => '12',
            'clave' => 'M5-L12',
        ]);

        $cliente = Cliente::create([
            'nombres' => 'Maria',
            'apellidos' => 'Lopez Garza',
            'nombre_legal' => 'MARIA LOPEZ GARZA',
            'telefono' => '8781234567',
        ]);

        return Contrato::create([
            'cliente_id' => $cliente->id,
            'lote_id' => $lote->id,
            'folio_contrato' => 'CT-001',
            'fecha_inicio' => '2026-01-10',
            'frecuencia' => 'mensual',
            'dia_mes' => 10,
            'precio_total' => 100000,
            'enganche' => 10000,
            'saldo_inicial' => 90000,
            'saldo_actual' => 90000,
            'monto_pago' => 3000,
            'tipo_recargo' => 'fijo',
            'valor_recargo' => 100,
            'dias_gracia' => 3,
            'estatus' => 'activo',
            'tipo' => 'terreno',
        ]);
    }

    /**
     * Otro contrato del mismo cliente, en otro lote del mismo fraccionamiento.
     */
    private function otroContrato(Contrato $base): Contrato
    {
        $lote = Lote::create([
            'fraccionamiento_id' => $base->lote->fraccionamiento_id,
            'manzana' => '5',
            'lote' => '13',
            'clave' => 'M5-L13',
        ]);

        return Contrato::create([
            'cliente_id' => $base->cliente_id,
            'lote_id' => $lote->id,
            'folio_contrato' => 'CT-002',
            'fecha_inicio' => '2026-02-10',
            'frecuencia' => 'mensual',
            'dia_mes' => 10,
            'precio_total' => 120000,
            'enganche' => 12000,
            'saldo_inicial' => 108000,
            'saldo_actual' => 108000,
            'monto_pago' => 3500,
            'tipo_recargo' => 'fijo',
            'valor_recargo' => 100,
            'dias_gracia' => 3,
            'estatus' => 'activo',
            'tipo' => 'terreno',
        ]);
    }

    private function textoDocx(string $absolutePath): string
    {
        $zip = new ZipArchive;
        $zip->open($absolutePath);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        return html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
