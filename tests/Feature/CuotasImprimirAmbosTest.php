<?php

namespace Tests\Feature;

use App\Livewire\Admin\Cuotas\Index as CuotasIndex;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Cuota;
use App\Models\FormaPago;
use App\Models\Fraccionamiento;
use App\Models\Lote;
use App\Models\Propietario;
use App\Models\Recibo;
use App\Models\TipoCobro;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * "Imprimir AMBOS" desde Cuotas.
 *
 * Abría dos pestañas y salían dos tiras de papel por el mismo pago, cuando al
 * cobrar por primera vez el recargo va dentro del ticket principal. Ahora usa
 * ese mismo lote: un solo ticket, con el total sumado.
 */
class CuotasImprimirAmbosTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function ambos_abre_un_solo_ticket_con_el_recargo_adentro(): void
    {
        ['cuota' => $cuota, 'principal' => $principal, 'recargo' => $recargo] = $this->cuotaConRecargoCobrado();

        $url = null;
        $cajero = $this->cajero();

        Livewire::actingAs($cajero)
            ->test(CuotasIndex::class)
            ->call('imprimirReciboDeCuota', $cuota->id)
            ->assertSet('modalImprimir', true)
            ->call('confirmarImpresion', 'ambos')
            ->assertDispatched('open-print-tab', function ($evento, $params) use (&$url) {
                $url = $params['url'] ?? null;

                return true;
            });

        // Una sola pestaña, y del lote: ahí es donde el recargo se pega al
        // ticket del principal en vez de salir en su propia hoja.
        $this->assertNotNull($url);
        $this->assertStringContainsString('/imprimir', $url);

        // El lote queda atado a quien lo genero: lo abre el mismo cajero.
        $html = $this->actingAs($cajero)->get($url)->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'class="ticket"'));
        $this->assertStringContainsString($principal->folio, $html);
        $this->assertStringContainsString($recargo->folio, $html);

        // El total suma la cuota y el recargo, como el ticket del cobro.
        $this->assertStringContainsString('$1,600.00', $html);
    }

    #[Test]
    public function sin_recargo_no_abre_el_modal_y_sale_el_ticket_normal(): void
    {
        ['cuota' => $cuota] = $this->cuotaConRecargoCobrado(conRecargo: false);

        Livewire::actingAs($this->cajero())
            ->test(CuotasIndex::class)
            ->call('imprimirReciboDeCuota', $cuota->id)
            ->assertSet('modalImprimir', false)
            ->assertDispatched('open-print-tab');
    }

    #[Test]
    public function imprimir_solo_uno_sigue_abriendo_su_propio_ticket(): void
    {
        ['cuota' => $cuota, 'recargo' => $recargo] = $this->cuotaConRecargoCobrado();

        Livewire::actingAs($this->cajero())
            ->test(CuotasIndex::class)
            ->call('imprimirReciboDeCuota', $cuota->id)
            ->call('confirmarImpresion', 'recargo')
            ->assertDispatched('open-print-tab', function ($evento, $params) use ($recargo) {
                return str_contains($params['url'] ?? '', $recargo->uuid);
            });
    }

    #[Test]
    public function cerrar_el_modal_olvida_los_recibos(): void
    {
        ['cuota' => $cuota] = $this->cuotaConRecargoCobrado();

        Livewire::actingAs($this->cajero())
            ->test(CuotasIndex::class)
            ->call('imprimirReciboDeCuota', $cuota->id)
            ->call('cerrarModalImprimir')
            ->assertSet('reciboPrincipalId', null)
            ->assertSet('reciboRecargoId', null);
    }

    /**
     * @return array{cuota: Cuota, principal: Recibo, recargo: ?Recibo}
     */
    private function cuotaConRecargoCobrado(bool $conRecargo = true): array
    {
        $propietario = Propietario::create(['nombre' => 'El Pato']);

        $fraccionamiento = Fraccionamiento::create([
            'propietario_id' => $propietario->id,
            'nombre' => 'Rancho El Pato',
        ]);

        $lote = Lote::create([
            'fraccionamiento_id' => $fraccionamiento->id,
            'manzana' => '1',
            'lote' => '7',
            'clave' => 'M1-L7',
        ]);

        $cliente = Cliente::create(['nombres' => 'Dora', 'apellidos' => 'Cardona']);

        $contrato = Contrato::create([
            'cliente_id' => $cliente->id,
            'lote_id' => $lote->id,
            'folio_contrato' => 'CT-AMBOS',
            'fecha_inicio' => '2026-01-10',
            'frecuencia' => 'mensual',
            'dia_mes' => 10,
            'precio_total' => 90000,
            'enganche' => 0,
            'saldo_inicial' => 90000,
            'saldo_actual' => 88500,
            'monto_pago' => 1500,
            'tipo_recargo' => 'fijo',
            'valor_recargo' => 100,
            'dias_gracia' => 0,
            'estatus' => 'activo',
            'tipo' => 'terreno',
        ]);

        $cuota = Cuota::create([
            'contrato_id' => $contrato->id,
            'numero' => 1,
            'fecha_vencimiento' => '2026-01-10',
            'monto' => 1500,
            'estatus' => 'pagada',
        ]);

        $formaPago = FormaPago::firstOrCreate(['nombre' => 'EFECTIVO']);

        $principal = Recibo::create([
            'folio' => 'R-PRINCIPAL',
            'fecha' => '2026-01-15',
            'anio' => 2026,
            'mes_del_anio' => 1,
            'cliente_id' => $cliente->id,
            'contrato_id' => $contrato->id,
            'lote_id' => $lote->id,
            'cuota_id' => $cuota->id,
            'tipos_cobro_id' => TipoCobro::firstOrCreate(['nombre' => 'MENSUALIDAD'])->id,
            'forma_pago_id' => $formaPago->id,
            'monto' => 1500,
        ]);

        $recargo = null;

        if ($conRecargo) {
            // Folio propio: el recargo puede ir a otro propietario contable.
            $recargo = Recibo::create([
                'folio' => 'R-RECARGO',
                'fecha' => '2026-01-15',
                'anio' => 2026,
                'mes_del_anio' => 1,
                'cliente_id' => $cliente->id,
                'contrato_id' => $contrato->id,
                'lote_id' => $lote->id,
                'cuota_id' => $cuota->id,
                'tipos_cobro_id' => TipoCobro::firstOrCreate(['nombre' => 'RECARGO'])->id,
                'forma_pago_id' => $formaPago->id,
                'monto' => 100,
            ]);
        }

        return ['cuota' => $cuota, 'principal' => $principal, 'recargo' => $recargo];
    }

    private function cajero(): User
    {
        $usuario = User::factory()->create();

        $rol = Role::findOrCreate('admin', 'web');

        foreach (['admin.ver', 'cuotas.ver', 'recibos.imprimir'] as $permiso) {
            $rol->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
        }

        return tap($usuario)->assignRole($rol);
    }
}
