<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Cuota;
use App\Models\Fraccionamiento;
use App\Models\Lote;
use App\Models\Propietario;
use App\Services\Contratos\ContratoWordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use ZipArchive;

class ContratoFinanciamientoElectricoDocxTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function solo_los_contratos_de_luz_ofrecen_el_contrato_de_financiamiento_electrico(): void
    {
        $terreno = $this->contratoTerreno();

        $this->assertArrayNotHasKey(
            'contrato_financiamiento_electrico',
            ContratoWordService::documentTypesFor($terreno)
        );

        $agua = $this->contratoServicio($terreno, 'agua');

        $this->assertArrayNotHasKey(
            'contrato_financiamiento_electrico',
            ContratoWordService::documentTypesFor($agua)
        );

        $luz = $this->contratoServicio($terreno, 'electricidad');

        $this->assertArrayHasKey(
            'contrato_financiamiento_electrico',
            ContratoWordService::documentTypesFor($luz)
        );

        $this->assertNull(
            ContratoWordService::documentTypeFor('contrato_financiamiento_electrico', $agua)
        );
    }

    #[Test]
    public function el_contrato_de_compraventa_no_se_ofrece_en_contratos_de_servicio(): void
    {
        $terreno = $this->contratoTerreno();
        $luz = $this->contratoServicio($terreno, 'electricidad');

        $this->assertArrayHasKey('contrato', ContratoWordService::documentTypesFor($terreno));
        $this->assertArrayNotHasKey('contrato', ContratoWordService::documentTypesFor($luz));
        $this->assertNull(ContratoWordService::documentTypeFor('contrato', $luz));
    }

    #[Test]
    public function genera_el_word_con_los_datos_del_contrato_de_luz(): void
    {
        Storage::fake('private');

        $this->artisan('contratos:plantilla-electrica')->assertSuccessful();

        $terreno = $this->contratoTerreno();
        $luz = $this->contratoServicio($terreno, 'electricidad');

        $path = (new ContratoWordService)->generarDocx($luz, 'contrato_financiamiento_electrico');

        $this->assertTrue(Storage::disk('private')->exists($path));

        $texto = $this->textoDocx(Storage::disk('private')->path($path));

        // Datos del proveedor, del cliente y del lote.
        $this->assertStringContainsString('INMOBILIARIA EL PATO', $texto);
        $this->assertStringContainsString('MARIA LOPEZ GARZA', $texto);
        $this->assertStringContainsString('LOGM900101MCLPRR03', $texto);
        $this->assertStringContainsString('Calle Hidalgo 123', $texto);
        $this->assertStringContainsString('8781234567', $texto);
        $this->assertStringContainsString('Rancho El Pato', $texto);
        $this->assertStringContainsString('CT-001', $texto); // contrato de terreno relacionado

        // Tabla de financiamiento.
        $this->assertStringContainsString('$12,000.00', $texto);
        $this->assertStringContainsString('DOCE MIL PESOS 00/100 MONEDA NACIONAL', $texto);
        $this->assertStringContainsString('$2,000.00', $texto);
        $this->assertStringContainsString('$10,000.00', $texto);
        $this->assertStringContainsString('$1,000.00', $texto);
        $this->assertStringContainsString('Mensual', $texto);
        $this->assertStringContainsString('Día 10 de cada mes', $texto);

        // Sin placeholders sin resolver.
        $this->assertStringNotContainsString('${', $texto);
    }

    #[Test]
    public function el_word_incluye_todos_los_lotes_del_cliente(): void
    {
        Storage::fake('private');

        $this->artisan('contratos:plantilla-electrica')->assertSuccessful();

        $terreno = $this->contratoTerreno();
        $this->otroLoteDelCliente($terreno, '13', 'CT-002');
        $this->otroLoteDelCliente($terreno, '14', 'CT-003', 'cancelado');

        $luz = $this->contratoServicio($terreno, 'electricidad');

        $path = (new ContratoWordService)->generarDocx($luz, 'contrato_financiamiento_electrico');

        $texto = $this->textoDocx(Storage::disk('private')->path($path));

        // Los dos lotes vigentes se mencionan juntos, con sus contratos de terreno.
        $this->assertStringContainsString('Lotes 12 y 13, Manzana 5, Rancho El Pato', $texto);
        $this->assertStringContainsString('12 y 13', $texto);
        $this->assertStringContainsString('CT-001 y CT-002', $texto);

        // Las medidas son solo las del lote principal (el del contrato).
        $this->assertStringContainsString('Norte 10.00 m', $texto);
        $this->assertStringNotContainsString('Norte 15.00 m', $texto);

        // El lote de un contrato cancelado no se incluye.
        $this->assertStringNotContainsString('12, 13 y 14', $texto);
        $this->assertStringNotContainsString('CT-003', $texto);

        $this->assertStringNotContainsString('${', $texto);
    }

    #[Test]
    public function no_genera_el_documento_para_un_contrato_que_no_es_de_luz(): void
    {
        Storage::fake('private');

        $this->artisan('contratos:plantilla-electrica')->assertSuccessful();

        $terreno = $this->contratoTerreno();

        $this->expectException(\RuntimeException::class);

        (new ContratoWordService)->generarDocx($terreno, 'contrato_financiamiento_electrico');
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
            'medida_norte' => 10,
            'medida_sur' => 10,
            'medida_este' => 20,
            'medida_oeste' => 20,
        ]);

        $cliente = Cliente::create([
            'nombres' => 'Maria',
            'apellidos' => 'Lopez Garza',
            'nombre_legal' => 'MARIA LOPEZ GARZA',
            'curp' => 'LOGM900101MCLPRR03',
            'rfc' => 'LOGM900101AB1',
            'direccion' => 'Calle Hidalgo 123, Piedras Negras',
            'telefono' => '8781234567',
            'correo' => 'maria@example.com',
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
            'comprador_nombre_legal' => 'MARIA LOPEZ GARZA',
            'comprador_curp' => 'LOGM900101MCLPRR03',
            'area_m2_snapshot' => 200,
            'medida_norte_snapshot' => 10,
            'medida_sur_snapshot' => 10,
            'medida_este_snapshot' => 20,
            'medida_oeste_snapshot' => 20,
        ]);
    }

    /**
     * Otro lote del mismo cliente, con su propio contrato de terreno.
     */
    private function otroLoteDelCliente(
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
            'medida_norte' => 15,
            'medida_sur' => 15,
            'medida_este' => 20,
            'medida_oeste' => 20,
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

    private function contratoServicio(Contrato $base, string $servicioTipo): Contrato
    {
        $contrato = Contrato::create([
            'cliente_id' => $base->cliente_id,
            'lote_id' => $base->lote_id,
            'folio_contrato' => $base->folio_contrato.'-'.strtoupper(substr($servicioTipo, 0, 2)),
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
            'servicio_tipo' => $servicioTipo,
            'contrato_base_id' => $base->id,
            'comprador_nombre_legal' => $base->comprador_nombre_legal,
            'comprador_curp' => $base->comprador_curp,
            'area_m2_snapshot' => $base->area_m2_snapshot,
            'medida_norte_snapshot' => $base->medida_norte_snapshot,
            'medida_sur_snapshot' => $base->medida_sur_snapshot,
            'medida_este_snapshot' => $base->medida_este_snapshot,
            'medida_oeste_snapshot' => $base->medida_oeste_snapshot,
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

    private function textoDocx(string $absolutePath): string
    {
        $zip = new ZipArchive;
        $zip->open($absolutePath);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        return html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
