<?php

namespace Tests\Feature;

use App\Livewire\Admin\Contratos\Create;
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
use App\Services\Contratos\ContratoWordService;
use App\Services\Contratos\VentaContadoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * Venta de contado: el contrato se paga completo al firmarse, así que nace
 * liquidado y sin calendario, con su propio formato y un recibo por el total.
 */
class ContratoContadoTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function el_contrato_de_contado_nace_liquidado_y_sin_saldo(): void
    {
        $campos = app(VentaContadoService::class)->camposDelContrato(150000);

        $this->assertTrue($campos['es_contado']);
        $this->assertSame('liquidado', $campos['estatus']);
        $this->assertSame(150000.0, $campos['precio_total']);

        // Nada que cobrar despues: sin saldo, sin pago periodico, sin recargo.
        $this->assertSame(0, $campos['saldo_inicial']);
        $this->assertSame(0, $campos['saldo_actual']);
        $this->assertSame(0, $campos['monto_pago']);
        $this->assertSame(0, $campos['valor_recargo']);
        $this->assertNotNull($campos['liquidado_at']);
    }

    #[Test]
    public function lo_pagado_no_se_guarda_como_enganche(): void
    {
        // Un enganche implica un saldo financiado: aqui no hay credito.
        $campos = app(VentaContadoService::class)->camposDelContrato(150000);

        $this->assertSame(0, $campos['enganche']);
    }

    #[Test]
    public function genera_un_recibo_por_el_total_con_el_concepto_contado(): void
    {
        $contrato = $this->contratoDeContado(150000);
        $forma = FormaPago::create(['nombre' => 'EFECTIVO']);

        $recibo = app(VentaContadoService::class)->generarRecibo($contrato, [
            'forma_pago_id' => $forma->id,
            'fecha' => '2026-09-18',
        ]);

        $this->assertSame(150000.0, (float) $recibo->monto);
        $this->assertSame($contrato->id, $recibo->contrato_id);
        $this->assertSame($contrato->cliente_id, $recibo->cliente_id);

        // Sin calendario no hay cuota a la cual colgar el pago.
        $this->assertNull($recibo->cuota_id);

        $this->assertSame('CONTADO', TipoCobro::find($recibo->tipos_cobro_id)->nombre);

        // El saldo queda en cero: el contrato no debe nada.
        $this->assertSame(0.0, (float) $recibo->saldo_posterior);
    }

    #[Test]
    public function el_concepto_contado_se_da_de_alta_una_sola_vez(): void
    {
        $servicio = app(VentaContadoService::class);

        $primero = $servicio->tipoCobroId();
        $segundo = $servicio->tipoCobroId();

        $this->assertSame($primero, $segundo);
        $this->assertSame(1, TipoCobro::where('nombre', 'CONTADO')->count());
    }

    #[Test]
    public function un_contrato_a_plazos_no_puede_emitir_recibo_de_contado(): void
    {
        $contrato = $this->contratoDeContado(150000);
        $contrato->update(['es_contado' => false]);

        $forma = FormaPago::create(['nombre' => 'EFECTIVO']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no está marcado como pagado de contado');

        app(VentaContadoService::class)->generarRecibo($contrato->fresh(), [
            'forma_pago_id' => $forma->id,
        ]);
    }

    #[Test]
    public function el_contado_deja_fuera_los_formatos_de_credito(): void
    {
        $contado = $this->contratoDeContado(150000);

        $formatos = array_keys(ContratoWordService::documentTypesFor($contado));

        // Pagado al firmar: no hay contrato a plazos ni adeudo que convenir.
        $this->assertNotContains('contrato', $formatos);
        $this->assertNotContains('convenio_pago', $formatos);
        $this->assertNotContains('convenio_pago_reconocimiento_adeudo', $formatos);
    }

    #[Test]
    public function cada_contado_lleva_el_formato_que_le_toca(): void
    {
        // El terreno lleva la compraventa de contado...
        $terreno = $this->contratoDeContado(150000);
        $formatosTerreno = array_keys(ContratoWordService::documentTypesFor($terreno));

        $this->assertContains('contrato_contado_terreno', $formatosTerreno);
        $this->assertNotContains('contrato_contado', $formatosTerreno);

        // ...y la luz, la aportación eléctrica. Nunca se cruzan.
        $luz = $this->contratoDeContado(15000, 'CT-LUZ', 'L-LUZ');
        $luz->update(['tipo' => 'servicio', 'servicio_tipo' => 'electricidad']);

        $formatosLuz = array_keys(ContratoWordService::documentTypesFor($luz->fresh()));

        $this->assertContains('contrato_contado', $formatosLuz);
        $this->assertNotContains('contrato_contado_terreno', $formatosLuz);
    }

    #[Test]
    public function el_contrato_de_terreno_de_contado_no_habla_de_plazos(): void
    {
        $contrato = $this->contratoDeContado(150000);

        $efectivo = FormaPago::create(['nombre' => 'EFECTIVO']);

        app(VentaContadoService::class)->generarRecibo($contrato, [
            'forma_pago_id' => $efectivo->id,
        ]);

        $texto = $this->textoDelDocx(
            (new ContratoWordService)->generarDocx($contrato->fresh(), 'contrato_contado_terreno')
        );

        // El documento no puede quedar con marcadores a medio llenar.
        $this->assertStringNotContainsString('${', $texto);

        $this->assertStringContainsString('COMPRAVENTA DE CONTADO', $texto);
        $this->assertStringContainsString('cubre íntegramente de contado', $texto);
        $this->assertStringContainsString('EFECTIVO', $texto);

        // Y no quedan rastros del credito: ni enganche, ni recargos, ni plazos.
        $this->assertStringNotContainsString('A PLAZOS', $texto);
        $this->assertStringNotContainsString('Enganche', $texto);
        $this->assertStringNotContainsString('penalización', $texto);
    }

    private function textoDelDocx(string $rutaRelativa): string
    {
        $zip = new \ZipArchive;
        $zip->open(Storage::disk('private')->path($rutaRelativa));
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        return html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    #[Test]
    public function el_contrato_a_plazos_conserva_sus_formatos_de_siempre(): void
    {
        $plazos = $this->contratoDeContado(150000);
        $plazos->update(['es_contado' => false]);

        $formatos = array_keys(ContratoWordService::documentTypesFor($plazos->fresh()));

        $this->assertContains('contrato', $formatos);
        $this->assertContains('convenio_pago', $formatos);
        $this->assertNotContains('contrato_contado', $formatos);
    }

    #[Test]
    public function el_documento_dice_como_se_pago(): void
    {
        $luz = $this->contratoDeContado(15000, 'CT-LUZ', 'L-LUZ');
        $luz->update(['tipo' => 'servicio', 'servicio_tipo' => 'electricidad']);

        $transferencia = FormaPago::create(['nombre' => 'TRANSFERENCIA']);

        app(VentaContadoService::class)->generarRecibo($luz->fresh(), [
            'forma_pago_id' => $transferencia->id,
        ]);

        $reflexion = new \ReflectionMethod(ContratoWordService::class, 'formaPagoDeContado');
        $reflexion->setAccessible(true);

        // El contrato declara la forma de pago que quedó en su recibo.
        $this->assertSame(
            'TRANSFERENCIA',
            $reflexion->invoke(new ContratoWordService, $luz->fresh())
        );
    }

    #[Test]
    public function un_contrato_de_contado_no_deja_cuotas_pendientes(): void
    {
        $contrato = $this->contratoDeContado(150000);

        $this->assertSame(0, Cuota::where('contrato_id', $contrato->id)->count());

        // Y por lo tanto nunca puede caer en cobranza.
        $this->assertSame(0.0, (float) $contrato->saldo_actual);
    }

    #[Test]
    public function sin_precio_no_se_puede_emitir_el_recibo(): void
    {
        $contrato = $this->contratoDeContado(150000);
        $contrato->update(['precio_total' => 0]);

        $forma = FormaPago::create(['nombre' => 'EFECTIVO']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('precio mayor a cero');

        app(VentaContadoService::class)->generarRecibo($contrato->fresh(), [
            'forma_pago_id' => $forma->id,
        ]);
    }

    #[Test]
    public function dos_ventas_de_contado_no_chocan_de_folio(): void
    {
        $forma = FormaPago::create(['nombre' => 'EFECTIVO']);
        $servicio = app(VentaContadoService::class);

        $uno = $servicio->generarRecibo($this->contratoDeContado(1000, 'CT-A', 'L-A'), ['forma_pago_id' => $forma->id]);
        $dos = $servicio->generarRecibo($this->contratoDeContado(2000, 'CT-B', 'L-B'), ['forma_pago_id' => $forma->id]);

        $this->assertNotSame($uno->folio, $dos->folio);
        $this->assertSame(2, Recibo::count());
    }

    #[Test]
    public function marcar_contado_apaga_el_calendario_y_el_credito(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(Create::class)
            ->set('precio_total', 150000)
            ->set('monto_pago', 3000)
            ->set('es_contado', true)
            ->assertSet('monto_pago', 0.0)
            ->assertSet('saldo_inicial', 0.0)
            ->assertSet('valor_recargo', 0.0)
            ->assertSet('previewCuotas', []);
    }

    #[Test]
    public function el_contado_exige_saber_como_entro_el_dinero(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(Create::class)
            ->set('es_contado', true)
            ->set('precio_total', 150000)
            ->set('step', 2)
            ->call('next')
            ->assertHasErrors(['contado_forma_pago_id', 'contado_fecha']);
    }

    #[Test]
    public function con_transferencia_se_pide_la_cuenta_bancaria(): void
    {
        $this->actingAs(User::factory()->create());

        $efectivo = FormaPago::create(['nombre' => 'EFECTIVO']);
        $transferencia = FormaPago::create(['nombre' => 'TRANSFERENCIA']);

        $componente = Livewire::test(Create::class)
            ->set('es_contado', true)
            ->set('contado_forma_pago_id', $efectivo->id);

        // En efectivo no hay cuenta bancaria que capturar.
        $this->assertFalse($componente->instance()->contadoRequiereCuenta);

        $componente->set('contado_forma_pago_id', $transferencia->id);

        $this->assertTrue($componente->instance()->contadoRequiereCuenta);
    }

    private function contratoDeContado(
        float $precio,
        string $folio = 'CT-CONTADO',
        string $claveLote = 'M1-L1',
    ): Contrato {
        $propietario = Propietario::firstOrCreate(
            ['nombre' => 'El Pato'],
            ['nombre_legal' => 'INMOBILIARIA EL PATO']
        );

        $fraccionamiento = Fraccionamiento::firstOrCreate(
            ['nombre' => 'Rancho El Pato'],
            ['propietario_id' => $propietario->id]
        );

        $lote = Lote::create([
            'fraccionamiento_id' => $fraccionamiento->id,
            'manzana' => '1',
            'lote' => $claveLote,
            'clave' => $claveLote,
        ]);

        $cliente = Cliente::firstOrCreate(
            ['nombres' => 'Maria', 'apellidos' => 'Lopez Garza'],
            ['telefono' => '8781234567']
        );

        return Contrato::create(array_merge([
            'cliente_id' => $cliente->id,
            'lote_id' => $lote->id,
            'folio_contrato' => $folio,
            'fecha_inicio' => '2026-09-18',
            'frecuencia' => 'mensual',
            'dia_mes' => 1,
            'tipo' => 'terreno',
        ], app(VentaContadoService::class)->camposDelContrato($precio)));
    }
}
