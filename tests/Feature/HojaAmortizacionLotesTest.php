<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\ContratosPdfController;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Cuota;
use App\Models\Fraccionamiento;
use App\Models\Lote;
use App\Models\Propietario;
use App\Models\User;
use App\Services\Contratos\ContratoPlanService;
use App\Services\Contratos\LotesClienteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HojaAmortizacionLotesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function la_hoja_de_un_contrato_de_luz_lista_todos_los_lotes_del_cliente(): void
    {
        $terreno = $this->contratoTerreno();
        $this->otroLote($terreno, '13', 'CT-002');
        $this->otroLote($terreno, '14', 'CT-003', 'cancelado');

        $luz = $this->contratoLuz($terreno);

        $html = $this->hoja($luz, LotesClienteService::resumen($luz));

        // Los números de los lotes, sin el del contrato cancelado (el 14).
        $this->assertSame(['Lotes:', '12 y 13'], $this->campoLote($html));
    }

    #[Test]
    public function la_hoja_de_un_contrato_de_terreno_sigue_mostrando_solo_su_lote(): void
    {
        $terreno = $this->contratoTerreno();
        $this->otroLote($terreno, '13', 'CT-002');

        $html = $this->hoja($terreno, null);

        $this->assertSame(['Lote:', 'M5-L12'], $this->campoLote($html));
    }

    #[Test]
    public function el_pdf_del_contrato_de_luz_se_genera(): void
    {
        $terreno = $this->contratoTerreno();
        $this->otroLote($terreno, '13', 'CT-002');

        $luz = $this->contratoLuz($terreno);

        $this->actingAs($this->usuarioConPermisos())
            ->get(route('admin.contratos.pdf', $luz))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    #[Test]
    public function las_hojas_siguientes_se_identifican_con_el_nombre_y_el_lote(): void
    {
        $terreno = $this->contratoTerreno();
        $this->otroLote($terreno, '13', 'CT-002');

        $this->assertSame('MARIA LOPEZ GARZA  ·  Lote 12', ContratosPdfController::identificadorDeHoja($terreno));

        $luz = $this->contratoLuz($terreno);

        $this->assertSame(
            'MARIA LOPEZ GARZA  ·  Lotes 12 y 13',
            ContratosPdfController::identificadorDeHoja($luz, LotesClienteService::resumen($luz))
        );
    }

    #[Test]
    public function el_pdf_de_varias_hojas_se_genera(): void
    {
        $terreno = $this->contratoTerreno();

        foreach (range(1, 80) as $numero) {
            Cuota::create([
                'contrato_id' => $terreno->id,
                'numero' => $numero,
                'fecha_vencimiento' => now()->addMonths($numero)->toDateString(),
                'monto' => 3000,
                'estatus' => 'pendiente',
            ]);
        }

        $pdf = $this->actingAs($this->usuarioConPermisos())
            ->get(route('admin.contratos.pdf', $terreno))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->getContent();

        $this->assertGreaterThan(1, preg_match_all('/\/Type\s*\/Page\b/', $pdf));
    }

    /**
     * Etiqueta y valor del campo del lote en la hoja.
     *
     * @return array{0: string, 1: string}
     */
    private function campoLote(string $html): array
    {
        preg_match('/<td class="label">(Lotes?:)<\/td>\s*<td>(.*?)<\/td>/s', $html, $campo);

        return [trim($campo[1] ?? ''), trim($campo[2] ?? '')];
    }

    private function hoja(Contrato $contrato, ?array $lotesCliente): string
    {
        $contrato->load([
            'cliente',
            'lote.fraccionamiento',
            'cuotas' => fn ($q) => $q->orderBy('numero'),
        ]);

        return view('admin.contratos.pdf', [
            'contrato' => $contrato,
            'diasSemana' => ContratoPlanService::diasSemana(),
            'lotesCliente' => $lotesCliente,
        ])->render();
    }

    private function usuarioConPermisos(): User
    {
        $usuario = User::factory()->create();

        $rol = Role::findOrCreate('admin', 'web');

        foreach (['admin.ver', 'contratos.ver_detalle', 'sistema.ver'] as $permiso) {
            $rol->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
        }

        $usuario->assignRole($rol);

        return $usuario;
    }

    private function contratoTerreno(): Contrato
    {
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
            'area_m2' => 200,
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

    private function otroLote(
        Contrato $base,
        string $numeroLote,
        string $folio,
        string $estatus = 'activo'
    ): Contrato {
        $lote = Lote::create([
            'fraccionamiento_id' => $base->lote->fraccionamiento_id,
            'manzana' => '5',
            'lote' => $numeroLote,
            'clave' => 'M5-L'.$numeroLote,
            'area_m2' => 300,
        ]);

        return Contrato::create([
            'cliente_id' => $base->cliente_id,
            'lote_id' => $lote->id,
            'folio_contrato' => $folio,
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
            'estatus' => $estatus,
            'tipo' => 'terreno',
        ]);
    }

    private function contratoLuz(Contrato $base): Contrato
    {
        $contrato = Contrato::create([
            'cliente_id' => $base->cliente_id,
            'lote_id' => $base->lote_id,
            'folio_contrato' => $base->folio_contrato.'-EL',
            'fecha_inicio' => '2026-03-10',
            'frecuencia' => 'mensual',
            'dia_mes' => 10,
            'precio_total' => 12000,
            'enganche' => 2000,
            'saldo_inicial' => 10000,
            'saldo_actual' => 10000,
            'monto_pago' => 1000,
            'tipo_recargo' => 'porcentaje',
            'valor_recargo' => 3,
            'dias_gracia' => 5,
            'estatus' => 'activo',
            'tipo' => 'servicio',
            'servicio_tipo' => 'electricidad',
            'contrato_base_id' => $base->id,
        ]);

        foreach (range(1, 10) as $numero) {
            Cuota::create([
                'contrato_id' => $contrato->id,
                'numero' => $numero,
                'fecha_vencimiento' => '2026-'.str_pad((string) ($numero + 2), 2, '0', STR_PAD_LEFT).'-10',
                'monto' => 1000,
                'pagado_total' => 0,
                'condonado_total' => 0,
                'recargo_aplicado' => 0,
                'estatus' => 'pendiente',
            ]);
        }

        return $contrato;
    }
}
