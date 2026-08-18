<?php

namespace Tests\Feature;

use App\Exports\Sheets\MonthlyIncomeActiveContractsSheet;
use App\Livewire\Admin\Contratos\CrearHistorico;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\User;
use App\Services\Contratos\ContratoFolioService;
use App\Services\Contratos\ContratoWordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\RegistraFuncionesSqlite;
use Tests\TestCase;

class ContratoHistoricoAltaTest extends TestCase
{
    use RefreshDatabase, RegistraFuncionesSqlite;

    #[Test]
    public function da_de_alta_el_contrato_terminado_sin_cuotas_ni_saldos(): void
    {
        $datos = $this->catalogos();

        $this->actingAs($this->usuarioConPermisos());

        Livewire::test(CrearHistorico::class)
            ->set('fraccionamiento_id', $datos['fraccionamiento_id'])
            ->call('selectCliente', $datos['cliente_id'])
            ->set('lote_id', $datos['lote_id'])
            ->set('fecha_inicio', '2019-03-01')
            ->set('fecha_terminacion', '2023-11-15')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertRedirect();

        $contrato = Contrato::query()->firstOrFail();

        // Mismo folio automático que el alta normal: CT-AAAAMMDDHHMMSS-XXXXXX.
        $this->assertMatchesRegularExpression(
            '/^CT-\d{14}-[0-9A-F]{6}$/',
            (string) $contrato->folio_contrato
        );

        $this->assertTrue($contrato->solo_documentos);
        $this->assertSame('liquidado', $contrato->estatus);
        $this->assertSame('2023-11-15', $contrato->liquidado_at->toDateString());
        $this->assertSame(0.0, (float) $contrato->precio_total);
        $this->assertSame(0.0, (float) $contrato->saldo_actual);
        $this->assertSame(0, $contrato->cuotas()->count());

        // El lote deja de estar disponible.
        $this->assertDatabaseHas('lotes', [
            'id' => $datos['lote_id'],
            'estatus' => 'vendido',
        ]);

        // Datos del lote copiados para que el Word salga completo.
        $this->assertSame('200.00', (string) $contrato->area_m2_snapshot);
        $this->assertSame('MARIA LOPEZ', $contrato->comprador_nombre_legal);
    }

    #[Test]
    public function solo_ofrece_lotes_disponibles_y_rechaza_uno_ya_vendido(): void
    {
        $datos = $this->catalogos();

        $this->actingAs($this->usuarioConPermisos());

        $componente = Livewire::test(CrearHistorico::class)
            ->set('fraccionamiento_id', $datos['fraccionamiento_id']);

        $this->assertCount(1, $componente->get('lotesDisponibles'));

        DB::table('lotes')->where('id', $datos['lote_id'])->update(['estatus' => 'vendido']);

        $componente
            ->set('fraccionamiento_id', $datos['fraccionamiento_id'])
            ->call('selectCliente', $datos['cliente_id'])
            ->set('lote_id', $datos['lote_id'])
            ->set('fecha_terminacion', '2023-11-15')
            ->call('guardar')
            ->assertHasErrors('lote_id');

        $this->assertCount(0, $componente->get('lotesDisponibles'));
        $this->assertSame(0, Contrato::query()->count());
    }

    #[Test]
    public function el_contrato_historico_no_aparece_en_el_reporte_mensual(): void
    {
        $this->registrarFuncionesMysqlEnSqlite();

        $datos = $this->catalogos();

        Contrato::create([
            'cliente_id' => $datos['cliente_id'],
            'lote_id' => $datos['lote_id'],
            'folio_contrato' => 'CT-HISTORICO',
            'fecha_inicio' => '2023-11-15',
            'frecuencia' => 'mensual',
            'precio_total' => 0,
            'enganche' => 0,
            'saldo_inicial' => 0,
            'saldo_actual' => 0,
            'monto_pago' => 0,
            'tipo_recargo' => 'fijo',
            'valor_recargo' => 0,
            'dias_gracia' => 0,
            'estatus' => 'liquidado',
            'liquidado_at' => '2023-11-15',
            'tipo' => 'terreno',
            'solo_documentos' => true,
        ]);

        $filas = (new MonthlyIncomeActiveContractsSheet(anio: 2023, mes: 11, propietarioId: null))
            ->collection();

        $this->assertCount(0, $filas);
    }

    #[Test]
    public function el_contrato_historico_conserva_sus_documentos_legales(): void
    {
        $contrato = new Contrato(['tipo' => 'terreno', 'solo_documentos' => true]);

        $documentos = ContratoWordService::documentTypesFor($contrato);

        $this->assertArrayHasKey('contrato', $documentos);
        $this->assertArrayHasKey('constancia_terminacion_pago', $documentos);
    }

    #[Test]
    public function el_folio_generado_no_choca_con_uno_existente(): void
    {
        $datos = $this->catalogos();
        $servicio = app(ContratoFolioService::class);

        $folio = $servicio->generar();

        DB::table('contratos')->insert([
            'cliente_id' => $datos['cliente_id'],
            'lote_id' => $datos['lote_id'],
            'folio_contrato' => $folio,
            'fecha_inicio' => '2020-01-01',
            'frecuencia' => 'mensual',
            'precio_total' => 0,
            'saldo_inicial' => 0,
            'saldo_actual' => 0,
            'monto_pago' => 0,
            'tipo_recargo' => 'fijo',
            'estatus' => 'liquidado',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertNotSame($folio, $servicio->generar());
    }

    private function usuarioConPermisos(): User
    {
        $usuario = User::factory()->create();

        $rol = Role::findOrCreate('admin', 'web');

        foreach (['contratos.editar', 'contratos.ver_detalle', 'sistema.ver'] as $permiso) {
            $rol->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
        }

        $usuario->assignRole($rol);

        return $usuario;
    }

    private function catalogos(): array
    {
        $ahora = now();

        $cliente = Cliente::create([
            'nombres' => 'Maria',
            'apellidos' => 'Lopez',
            'nombre_legal' => 'MARIA LOPEZ',
            'telefono' => '8781234567',
        ]);

        $propietarioId = DB::table('propietarios')->insertGetId([
            'nombre' => 'Propietario prueba',
            'nombre_legal' => 'PROPIETARIO PRUEBA',
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);
        $fraccionamientoId = DB::table('fraccionamientos')->insertGetId([
            'propietario_id' => $propietarioId,
            'nombre' => 'Fraccionamiento prueba',
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);
        $loteId = DB::table('lotes')->insertGetId([
            'fraccionamiento_id' => $fraccionamientoId,
            'manzana' => '1',
            'lote' => '7',
            'clave' => 'M1-L7',
            'area_m2' => 200,
            'estatus' => 'disponible',
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);

        return [
            'cliente_id' => $cliente->id,
            'fraccionamiento_id' => $fraccionamientoId,
            'lote_id' => $loteId,
        ];
    }
}
