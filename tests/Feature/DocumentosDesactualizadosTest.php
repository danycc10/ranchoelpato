<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Fraccionamiento;
use App\Models\Lote;
use App\Models\Propietario;
use App\Models\User;
use App\Services\Contratos\ContratoWordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Los documentos se generan una vez y se reusan. Al cambiar una plantilla los
 * que ya existían tienen que volver a generarse, o seguirían saliendo con el
 * texto viejo.
 */
class DocumentosDesactualizadosTest extends TestCase
{
    use RefreshDatabase;

    private const TIPO = 'contrato_cesion_derechos';

    #[Test]
    public function cada_descarga_vuelve_a_generar_el_documento(): void
    {
        $contrato = $this->contratoConDocumento();

        // Se marca el archivo como viejo para ver si la descarga lo rehace.
        $this->envejecerDocumento($contrato);

        $ruta = Storage::disk('private')->path($contrato->archivo_contrato_cesion_docx);
        $antes = filemtime($ruta);

        $this->actingAs($this->usuarioConPermisos())
            ->get(route('admin.private.contratos.documentos.docx.download', [
                'uuid' => $contrato->uuid,
                'tipo' => self::TIPO,
            ]))
            ->assertOk();

        clearstatcache(true, $ruta);

        $this->assertGreaterThan($antes, filemtime($ruta));
    }

    #[Test]
    public function si_no_hay_plantilla_la_descarga_entrega_el_ultimo_generado(): void
    {
        $contrato = $this->contratoConDocumento();

        // Sin plantilla no se puede rehacer, pero el usuario no debe quedarse
        // sin su documento.
        Storage::disk('private')->delete('plantillas/CONTRATO_CESION_DERECHOS_POSESORIOS.docx');

        $this->actingAs($this->usuarioConPermisos())
            ->get(route('admin.private.contratos.documentos.docx.download', [
                'uuid' => $contrato->uuid,
                'tipo' => self::TIPO,
            ]))
            ->assertOk();
    }

    #[Test]
    public function un_contrato_sin_documento_se_considera_pendiente(): void
    {
        $contrato = $this->contratoConPlantilla();

        $this->assertTrue(
            app(ContratoWordService::class)->estaDesactualizado($contrato, self::TIPO)
        );
    }

    #[Test]
    public function recien_generado_esta_al_dia(): void
    {
        $contrato = $this->contratoConDocumento();

        $this->assertFalse(
            app(ContratoWordService::class)->estaDesactualizado($contrato, self::TIPO)
        );
    }

    #[Test]
    public function al_cambiar_la_plantilla_el_documento_queda_desactualizado(): void
    {
        $contrato = $this->contratoConDocumento();
        $servicio = app(ContratoWordService::class);

        $this->assertFalse($servicio->estaDesactualizado($contrato, self::TIPO));

        // El documento se generó hace meses y hoy se reemplazó la plantilla.
        $this->envejecerDocumento($contrato);

        $this->assertTrue($servicio->estaDesactualizado($contrato, self::TIPO));
    }

    #[Test]
    public function regenerar_lo_vuelve_a_poner_al_dia(): void
    {
        $contrato = $this->contratoConDocumento();
        $servicio = app(ContratoWordService::class);

        $this->envejecerDocumento($contrato);
        $this->assertTrue($servicio->estaDesactualizado($contrato, self::TIPO));

        $contrato->update([
            'archivo_contrato_cesion_docx' => $servicio->generarDocx($contrato, self::TIPO),
        ]);

        $this->assertFalse($servicio->estaDesactualizado($contrato->fresh(), self::TIPO));
    }

    #[Test]
    public function si_el_archivo_desaparecio_hay_que_generarlo(): void
    {
        $contrato = $this->contratoConDocumento();

        Storage::disk('private')->delete($contrato->archivo_contrato_cesion_docx);

        $this->assertTrue(
            app(ContratoWordService::class)->estaDesactualizado($contrato, self::TIPO)
        );
    }

    #[Test]
    public function sin_plantilla_instalada_no_se_marca_nada(): void
    {
        $contrato = $this->contratoConDocumento();

        // Un formato cuya plantilla no existe no puede regenerarse: dejarlo
        // como desactualizado haría fallar la descarga en vez de servir lo
        // que ya se tenía.
        Storage::disk('private')->delete('plantillas/CONTRATO_CESION_DERECHOS_POSESORIOS.docx');

        $this->assertFalse(
            app(ContratoWordService::class)->estaDesactualizado($contrato, self::TIPO)
        );
    }

    private function usuarioConPermisos(): User
    {
        $usuario = User::factory()->create();

        $rol = Role::findOrCreate('admin', 'web');

        foreach (['admin.ver', 'sistema.ver'] as $permiso) {
            $rol->givePermissionTo(
                Permission::findOrCreate($permiso, 'web')
            );
        }

        $usuario->assignRole($rol);

        return $usuario;
    }

    /** Lo pone como generado hace meses, antes de la plantilla de hoy. */
    private function envejecerDocumento(Contrato $contrato): void
    {
        touch(
            Storage::disk('private')->path($contrato->archivo_contrato_cesion_docx),
            now()->subMonths(3)->getTimestamp()
        );
    }

    private function contratoConDocumento(): Contrato
    {
        $contrato = $this->contratoConPlantilla();

        $contrato->update([
            'archivo_contrato_cesion_docx' => app(ContratoWordService::class)
                ->generarDocx($contrato, self::TIPO),
        ]);

        return $contrato->fresh();
    }

    private function contratoConPlantilla(): Contrato
    {
        Storage::fake('private');

        $this->artisan('contratos:plantilla-cesion')->assertSuccessful();

        $propietario = Propietario::create([
            'nombre' => 'El Pato',
            'nombre_legal' => 'INMOBILIARIA EL PATO',
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
            'telefono' => '8781234567',
        ]);

        return Contrato::create([
            'cliente_id' => $cliente->id,
            'lote_id' => $lote->id,
            'folio_contrato' => 'CT-DESACTUALIZADO',
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
            'archivo_contrato_disk' => 'private',
        ]);
    }
}
