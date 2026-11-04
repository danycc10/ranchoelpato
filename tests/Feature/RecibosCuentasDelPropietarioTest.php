<?php

namespace Tests\Feature;

use App\Livewire\Admin\Recibos\Crear;
use App\Models\CuentaBancaria;
use App\Models\TipoCobroPropietarioConfig;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RecibosCuentasDelPropietarioTest extends TestCase
{
    use RefreshDatabase;

    private array $ids;

    protected function setUp(): void
    {
        parent::setUp();

        $ahora = now();
        $osvaldo = DB::table('propietarios')->insertGetId(['nombre' => 'Osvaldo', 'created_at' => $ahora, 'updated_at' => $ahora]);
        $alejandro = DB::table('propietarios')->insertGetId(['nombre' => 'Alejandro', 'created_at' => $ahora, 'updated_at' => $ahora]);
        $fraccionamiento = DB::table('fraccionamientos')->insertGetId(['propietario_id' => $osvaldo, 'nombre' => 'Finca', 'created_at' => $ahora, 'updated_at' => $ahora]);

        $this->ids = [
            'osvaldo' => $osvaldo,
            'alejandro' => $alejandro,
            'fraccionamiento' => $fraccionamiento,
            'lote' => DB::table('lotes')->insertGetId(['fraccionamiento_id' => $fraccionamiento, 'lote' => '1', 'clave' => 'L1', 'created_at' => $ahora, 'updated_at' => $ahora]),
            'tipo_cobro' => DB::table('tipos_cobro')->insertGetId(['nombre' => 'MENSUALIDAD', 'created_at' => $ahora, 'updated_at' => $ahora]),
            'transferencia' => DB::table('formas_pago')->insertGetId(['nombre' => 'TRANSFERENCIA', 'requiere_cuenta' => true, 'created_at' => $ahora, 'updated_at' => $ahora]),
            'cuenta_osvaldo' => CuentaBancaria::create(['propietario_id' => $osvaldo, 'alias' => 'BBVA-TARJETA-0298 OSVALDO', 'activa' => true])->id,
            'cuenta_alejandro' => CuentaBancaria::create(['propietario_id' => $alejandro, 'alias' => 'BBVA-TARJETA-1975 ALEJANDRO', 'activa' => true])->id,
        ];

        CuentaBancaria::create(['propietario_id' => $osvaldo, 'alias' => 'CUENTA CERRADA OSVALDO', 'activa' => false]);

        $this->actingAs(User::factory()->create());
    }

    #[Test]
    public function solo_ofrece_las_cuentas_del_propietario_del_terreno_y_se_pueden_ver_todas(): void
    {
        $componente = $this->formulario()
            ->assertSee('BBVA-TARJETA-0298 OSVALDO')
            ->assertDontSee('BBVA-TARJETA-1975 ALEJANDRO')
            ->assertDontSee('CUENTA CERRADA OSVALDO')
            ->assertSee('Cuentas de Osvaldo.')
            ->assertSee('Ver todas las activas');

        // Se equivocaron de tarjeta: el cliente depositó a la de Alejandro.
        $componente->set('verTodasLasCuentas', true)
            ->assertSee('BBVA-TARJETA-0298 OSVALDO')
            ->assertSee('BBVA-TARJETA-1975 ALEJANDRO')
            ->assertDontSee('CUENTA CERRADA OSVALDO')
            ->assertSee('Osvaldo (de este terreno)')
            ->set('pagos.0.cuentas_bancarias_id', $this->ids['cuenta_alejandro'])
            ->assertSee('Esta cuenta es de Alejandro, no de Osvaldo');

        // Al volver al filtro, la ya elegida no desaparece.
        $componente->set('verTodasLasCuentas', false)
            ->assertSee('BBVA-TARJETA-1975 ALEJANDRO')
            ->assertSee('Esta cuenta es de Alejandro, no de Osvaldo');
    }

    #[Test]
    public function sigue_al_propietario_contable_configurado_para_el_cobro(): void
    {
        // Este cobro, en esta finca, le entra a Alejandro aunque el terreno sea de Osvaldo.
        TipoCobroPropietarioConfig::create([
            'tipo_cobro_id' => $this->ids['tipo_cobro'],
            'fraccionamiento_id' => $this->ids['fraccionamiento'],
            'propietario_id' => $this->ids['alejandro'],
            'prioridad' => 1,
            'activo' => true,
        ]);

        $this->formulario()
            ->assertSee('BBVA-TARJETA-1975 ALEJANDRO')
            ->assertDontSee('BBVA-TARJETA-0298 OSVALDO')
            ->assertSee('Cuentas de Alejandro.');
    }

    #[Test]
    public function si_el_propietario_no_tiene_cuentas_muestra_todas(): void
    {
        CuentaBancaria::whereKey($this->ids['cuenta_osvaldo'])->update(['activa' => false]);

        $this->formulario()
            ->assertSee('BBVA-TARJETA-1975 ALEJANDRO')
            ->assertSee('Osvaldo no tiene cuentas activas');
    }

    private function formulario()
    {
        return Livewire::test(Crear::class)
            ->set('lote_id', $this->ids['lote'])
            ->set('tipos_cobro_id', $this->ids['tipo_cobro'])
            ->set('pagos', [[
                'forma_pago_id' => $this->ids['transferencia'],
                'cuentas_bancarias_id' => null,
                'monto' => 500,
                'referencia' => null,
                'evidencia' => null,
                'sin_evidencia' => true,
                'orden' => 1,
            ]]);
    }
}
