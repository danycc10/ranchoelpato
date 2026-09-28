<?php

namespace Tests\Feature;

use App\Exports\Sheets\MonthlyIncomeDetailSheet;
use App\Livewire\Admin\Recibos\Crear;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Cuota;
use App\Models\FormaPago;
use App\Models\Fraccionamiento;
use App\Models\Lote;
use App\Models\Periodo;
use App\Models\Propietario;
use App\Models\Recibo;
use App\Models\TipoCobro;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\RegistraFuncionesSqlite;
use Tests\TestCase;

/**
 * Cobrar tres meses de un jalón sale en un solo recibo. Ese recibo no es de
 * ningún mes en particular, y el ticket y los reportes tienen que decirlo:
 * antes se quedaba con el periodo de la primera cuota y los $4,500 de julio,
 * agosto y septiembre se contaban enteros en julio.
 */
class ReciboAgrupadoVariosMesesTest extends TestCase
{
    use RefreshDatabase, RegistraFuncionesSqlite;

    #[Test]
    public function el_ticket_imprime_el_rango_de_meses_que_cubre(): void
    {
        $datos = $this->contratoConTresMeses();
        $usuario = $this->cajero();

        $this->cobrar($datos, $datos['cuota_ids'], $usuario);

        $recibo = Recibo::sole();

        $this->actingAs($usuario)
            ->get(route('admin.recibos.imprimir', $recibo))
            ->assertOk()
            ->assertSee('JULIO 2026 - SEPTIEMBRE 2026')
            ->assertDontSee('>JULIO 2026<', false);
    }

    #[Test]
    public function el_ticket_de_un_solo_mes_sigue_diciendo_ese_mes(): void
    {
        $datos = $this->contratoConTresMeses();
        $usuario = $this->cajero();

        $this->cobrar($datos, [$datos['cuota_ids'][0]], $usuario, monto: 1500);

        $this->actingAs($usuario)
            ->get(route('admin.recibos.imprimir', Recibo::sole()))
            ->assertOk()
            ->assertSee('JULIO 2026')
            ->assertDontSee('SEPTIEMBRE 2026');
    }

    #[Test]
    public function el_reporte_manda_cada_parte_al_mes_de_su_cuota(): void
    {
        $this->registrarFuncionesMysqlEnSqlite();

        $datos = $this->contratoConTresMeses();

        $this->cobrar($datos, $datos['cuota_ids'], $this->cajero());

        // Cada mes se lleva sus $1,500, no los $4,500 completos ni cero.
        foreach ([7 => 'JULIO 2026', 8 => 'AGOSTO 2026', 9 => 'SEPTIEMBRE 2026'] as $mes => $nombre) {
            $filas = $this->detalleDelMes($mes);

            $this->assertCount(1, $filas, "El mes {$mes} debería traer un renglón.");

            $fila = $filas->first();

            // Columnas PERIODO y MONTO de MonthlyIncomeDetailSheet::headings().
            $this->assertSame($nombre, $fila[12], "El renglón de {$nombre} salió con otro periodo.");
            $this->assertSame(1500.0, $fila[13]);
        }
    }

    #[Test]
    public function el_reporte_no_carga_el_cobro_completo_a_un_solo_mes(): void
    {
        $this->registrarFuncionesMysqlEnSqlite();

        $datos = $this->contratoConTresMeses();

        $this->cobrar($datos, $datos['cuota_ids'], $this->cajero());

        $total = collect([7, 8, 9])
            ->flatMap(fn ($mes) => $this->detalleDelMes($mes))
            ->sum(fn ($fila) => $fila[13]);

        $this->assertSame(4500.0, $total);
    }

    /** Los renglones del detalle de "cómo va el mes". */
    private function detalleDelMes(int $mes)
    {
        return (new MonthlyIncomeDetailSheet(
            anio: 2026,
            mes: $mes,
            propietarioId: null,
            modoVista: 'como_va_el_mes',
        ))->collection();
    }

    /**
     * @param  array<int, int>  $cuotaIds
     */
    private function cobrar(array $datos, array $cuotaIds, User $usuario, float $monto = 4500): void
    {
        $this->actingAs($usuario);

        Livewire::test(Crear::class)
            ->set('cliente_id', $datos['cliente_id'])
            ->set('contrato_id', $datos['contrato_id'])
            ->set('lote_id', $datos['lote_id'])
            ->set('tipos_cobro_id', $datos['tipos_cobro_id'])
            ->set('asociarACuota', true)
            ->set('cuotaIds', $cuotaIds)
            ->set('fecha', '2026-09-18')
            ->set('metodo', 'efectivo')
            ->set('pagos', [[
                'forma_pago_id' => $datos['forma_pago_id'],
                'cuentas_bancarias_id' => null,
                'monto' => $monto,
                'referencia' => null,
                'evidencia' => null,
                'sin_evidencia' => true,
                'orden' => 1,
            ]])
            ->call('guardar')
            ->assertHasNoErrors();
    }

    private function cajero(): User
    {
        $usuario = User::factory()->create();

        $rol = Role::findOrCreate('admin', 'web');

        foreach (['admin.ver', 'recibos.imprimir'] as $permiso) {
            $rol->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
        }

        return tap($usuario)->assignRole($rol);
    }

    /** Un contrato con julio, agosto y septiembre de 2026 pendientes. */
    private function contratoConTresMeses(): array
    {
        foreach ([[7, 'JULIO'], [8, 'AGOSTO'], [9, 'SEPTIEMBRE']] as [$mes, $nombre]) {
            Periodo::create([
                'tipo' => 'mensual',
                'anio' => 2026,
                'mes' => $mes,
                'nombre' => $nombre.' 2026',
            ]);
        }

        $cliente = Cliente::create([
            'nombres' => 'Dora',
            'apellidos' => 'Cardona',
            'telefono' => '8781112233',
        ]);

        $propietario = Propietario::create(['nombre' => 'El Pato']);

        $fraccionamiento = Fraccionamiento::create([
            'propietario_id' => $propietario->id,
            'nombre' => 'Finca Tres Meses',
        ]);

        $lote = Lote::create([
            'fraccionamiento_id' => $fraccionamiento->id,
            'manzana' => '1',
            'lote' => '43',
            'clave' => 'F3-M1-L43',
        ]);

        $contrato = Contrato::create([
            'cliente_id' => $cliente->id,
            'lote_id' => $lote->id,
            'folio_contrato' => 'CT-TRES-MESES',
            'fecha_inicio' => '2026-07-18',
            'frecuencia' => 'mensual',
            'dia_mes' => 18,
            'precio_total' => 4500,
            'enganche' => 0,
            'saldo_inicial' => 4500,
            'saldo_actual' => 4500,
            'monto_pago' => 1500,
            'tipo_recargo' => 'fijo',
            'valor_recargo' => 0,
            'dias_gracia' => 0,
            'estatus' => 'activo',
            'tipo' => 'terreno',
        ]);

        $cuotaIds = [];

        foreach ([[1, '2026-07-18'], [2, '2026-08-18'], [3, '2026-09-18']] as [$numero, $vence]) {
            $cuotaIds[] = Cuota::create([
                'contrato_id' => $contrato->id,
                'numero' => $numero,
                'fecha_vencimiento' => $vence,
                'monto' => 1500,
                'estatus' => 'pendiente',
            ])->id;
        }

        return [
            'cliente_id' => $cliente->id,
            'contrato_id' => $contrato->id,
            'lote_id' => $lote->id,
            'cuota_ids' => $cuotaIds,
            'tipos_cobro_id' => TipoCobro::firstOrCreate(['nombre' => 'MENSUALIDAD'])->id,
            'forma_pago_id' => FormaPago::firstOrCreate(['nombre' => 'EFECTIVO'])->id,
        ];
    }
}
