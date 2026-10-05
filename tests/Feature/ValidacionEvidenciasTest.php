<?php

namespace Tests\Feature;

use App\Livewire\Admin\Recibos\ValidacionEvidencias;
use App\Models\Cliente;
use App\Models\FormaPago;
use App\Models\Fraccionamiento;
use App\Models\Lote;
use App\Models\Propietario;
use App\Models\Recibo;
use App\Models\ReciboPago;
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
 * La bandeja para validar comprobantes.
 *
 * Validar se podía desde el índice de recibos, pero de uno en uno y abriendo
 * un modal por pago. Aquí llegan juntos y se marcan de varios de un jalón.
 */
class ValidacionEvidenciasTest extends TestCase
{
    use RefreshDatabase, RegistraFuncionesSqlite;

    #[Test]
    public function solo_lista_los_pagos_con_comprobante_que_esperan_revision(): void
    {
        $conEvidencia = $this->pago('R-001', 'TRANSFERENCIA', evidencia: true);
        $sinEvidencia = $this->pago('R-002', 'TRANSFERENCIA', evidencia: false);
        $efectivo = $this->pago('R-003', 'EFECTIVO', evidencia: true);
        $yaValidado = $this->pago('R-004', 'TRANSFERENCIA', evidencia: true, validado: true);

        Livewire::actingAs($this->validador())
            ->test(ValidacionEvidencias::class)
            ->assertSee('R-001')
            // En efectivo no hay nada que validar, y sin comprobante tampoco.
            ->assertDontSee('R-002')
            ->assertDontSee('R-003')
            ->assertDontSee('R-004');

        $this->assertNotNull($conEvidencia->id);
        $this->assertNotNull($sinEvidencia->id);
        $this->assertNotNull($efectivo->id);
        $this->assertNotNull($yaValidado->id);
    }

    #[Test]
    public function marca_de_un_jalon_los_pagos_seleccionados(): void
    {
        $uno = $this->pago('R-001', 'TRANSFERENCIA', evidencia: true);
        $dos = $this->pago('R-002', 'PAGO EN OXXO', evidencia: true);
        $fuera = $this->pago('R-003', 'TRANSFERENCIA', evidencia: true);

        $validador = $this->validador();

        Livewire::actingAs($validador)
            ->test(ValidacionEvidencias::class)
            ->set('seleccion', [$uno->id, $dos->id])
            ->call('validarSeleccionados')
            ->assertDispatched('toast')
            // Palomeado consumido: no se revalida sin querer.
            ->assertSet('seleccion', []);

        foreach ([$uno, $dos] as $pago) {
            $pago->refresh();
            $this->assertNotNull($pago->validado_at);
            $this->assertSame($validador->id, (int) $pago->validado_por_user_id);
        }

        $this->assertNull($fuera->refresh()->validado_at);
    }

    #[Test]
    public function quitar_la_validacion_tambien_va_de_varios(): void
    {
        $uno = $this->pago('R-001', 'TRANSFERENCIA', evidencia: true, validado: true);
        $dos = $this->pago('R-002', 'TRANSFERENCIA', evidencia: true, validado: true);

        Livewire::actingAs($this->validador())
            ->test(ValidacionEvidencias::class)
            ->set('estado', 'validados')
            ->set('seleccion', [$uno->id, $dos->id])
            ->call('quitarValidacionSeleccionados');

        foreach ([$uno, $dos] as $pago) {
            $pago->refresh();
            $this->assertNull($pago->validado_at);
            $this->assertNull($pago->validado_por_user_id);
        }
    }

    #[Test]
    public function no_valida_un_pago_que_ya_se_anulo(): void
    {
        $pago = $this->pago('R-001', 'TRANSFERENCIA', evidencia: true);

        // Alguien lo anula mientras la pantalla seguía abierta.
        $pago->update(['anulado_at' => now()]);

        Livewire::actingAs($this->validador())
            ->test(ValidacionEvidencias::class)
            ->set('seleccion', [$pago->id])
            ->call('validarSeleccionados')
            ->assertDispatched('toast');

        $this->assertNull($pago->refresh()->validado_at);
    }

    #[Test]
    public function sin_seleccion_no_hace_nada(): void
    {
        $pago = $this->pago('R-001', 'TRANSFERENCIA', evidencia: true);

        Livewire::actingAs($this->validador())
            ->test(ValidacionEvidencias::class)
            ->call('validarSeleccionados')
            ->assertDispatched('toast');

        $this->assertNull($pago->refresh()->validado_at);
    }

    #[Test]
    public function seleccionar_toda_la_pagina_y_soltarla(): void
    {
        $pagos = collect(['R-001', 'R-002', 'R-003'])
            ->map(fn (string $folio) => $this->pago($folio, 'TRANSFERENCIA', evidencia: true));

        $componente = Livewire::actingAs($this->validador())
            ->test(ValidacionEvidencias::class)
            ->call('alternarPagina');

        $this->assertEqualsCanonicalizing(
            $pagos->pluck('id')->all(),
            $componente->get('seleccion')
        );

        // El mismo botón la suelta.
        $componente->call('alternarPagina')->assertSet('seleccion', []);
    }

    #[Test]
    public function cambiar_un_filtro_suelta_lo_palomeado(): void
    {
        $pago = $this->pago('R-001', 'TRANSFERENCIA', evidencia: true);

        // Si no, se validaría algo que ya no se está viendo.
        Livewire::actingAs($this->validador())
            ->test(ValidacionEvidencias::class)
            ->set('seleccion', [$pago->id])
            ->set('estado', 'validados')
            ->assertSet('seleccion', []);
    }

    #[Test]
    public function el_buscador_encuentra_por_folio_cliente_y_referencia(): void
    {
        // El buscador arma el nombre completo con CONCAT_WS, que es de MySQL.
        $this->registrarFuncionesMysqlEnSqlite();

        $this->pago('R-BUSCADO', 'TRANSFERENCIA', evidencia: true, referencia: 'SPEI-9988');
        $this->pago('R-OTRO', 'TRANSFERENCIA', evidencia: true);

        $componente = Livewire::actingAs($this->validador())->test(ValidacionEvidencias::class);

        $componente->set('q', 'R-BUSCADO')->assertSee('R-BUSCADO')->assertDontSee('R-OTRO');
        $componente->set('q', 'SPEI-9988')->assertSee('R-BUSCADO')->assertDontSee('R-OTRO');
    }

    #[Test]
    public function quien_no_tiene_el_permiso_no_entra(): void
    {
        $this->actingAs($this->usuario(['admin.ver']))
            ->get(route('admin.recibos.validacion'))
            ->assertForbidden();

        $this->actingAs($this->validador())
            ->get(route('admin.recibos.validacion'))
            ->assertOk();
    }

    #[Test]
    public function el_conteo_de_pendientes_alimenta_el_badge(): void
    {
        $this->pago('R-001', 'TRANSFERENCIA', evidencia: true);
        $this->pago('R-002', 'TRANSFERENCIA', evidencia: true);
        $this->pago('R-003', 'TRANSFERENCIA', evidencia: true, validado: true);
        $this->pago('R-004', 'EFECTIVO', evidencia: true);

        $this->assertSame(2, ValidacionEvidencias::conteoPendientes());
    }

    private function validador(): User
    {
        return $this->usuario(['admin.ver', 'recibos.validar']);
    }

    /**
     * @param  array<int, string>  $permisos
     */
    private function usuario(array $permisos): User
    {
        $usuario = User::factory()->create();

        $rol = Role::findOrCreate('rol-'.implode('-', $permisos), 'web');

        foreach ($permisos as $permiso) {
            $rol->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
        }

        return tap($usuario)->assignRole($rol);
    }

    private function pago(
        string $folio,
        string $formaPago,
        bool $evidencia,
        bool $validado = false,
        ?string $referencia = null,
    ): ReciboPago {
        $propietario = Propietario::firstOrCreate(['nombre' => 'El Pato']);

        $fraccionamiento = Fraccionamiento::firstOrCreate(
            ['nombre' => 'Rancho El Pato'],
            ['propietario_id' => $propietario->id]
        );

        $lote = Lote::create([
            'fraccionamiento_id' => $fraccionamiento->id,
            'manzana' => '1',
            'lote' => $folio,
            'clave' => 'M1-L'.$folio,
        ]);

        $cliente = Cliente::firstOrCreate(['nombres' => 'Dora', 'apellidos' => 'Cardona']);

        // EFECTIVO no pide comprobante; las demás sí.
        $forma = FormaPago::firstOrCreate(
            ['nombre' => $formaPago],
            ['requiere_cuenta' => $formaPago !== 'EFECTIVO']
        );

        $recibo = Recibo::create([
            'folio' => $folio,
            'fecha' => '2026-09-20',
            'anio' => 2026,
            'mes_del_anio' => 9,
            'cliente_id' => $cliente->id,
            'lote_id' => $lote->id,
            'tipos_cobro_id' => TipoCobro::firstOrCreate(['nombre' => 'MENSUALIDAD'])->id,
            'forma_pago_id' => $forma->id,
            'monto' => 1500,
        ]);

        return ReciboPago::create([
            'recibo_id' => $recibo->id,
            'forma_pago_id' => $forma->id,
            'monto' => 1500,
            'fecha_efectiva' => '2026-09-20',
            'referencia' => $referencia,
            'evidencia_path' => $evidencia ? 'evidencias/'.$folio.'.jpg' : null,
            'evidencia_mime' => $evidencia ? 'image/jpeg' : null,
            'orden' => 1,
            'validado_at' => $validado ? now() : null,
            'validado_por_user_id' => $validado ? User::factory()->create()->id : null,
        ]);
    }
}
