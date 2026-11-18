<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Fraccionamiento;
use App\Models\Lote;
use App\Models\Propietario;
use App\Services\Contratos\ContratoWordService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use ZipArchive;

class AnexoCambioDiaPagoDocxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        Carbon::setTestNow('2026-06-30 10:00:00');

        $this->artisan('contratos:plantilla-anexo-dia-pago')->assertSuccessful();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    #[Test]
    public function en_un_contrato_semanal_pone_el_dia_de_la_semana(): void
    {
        $contrato = $this->contrato(['frecuencia' => 'semanal', 'dia_semana' => 5, 'dia_mes' => null]);

        $texto = $this->texto($contrato);

        $this->assertStringContainsString('ANEXO AL CONTRATO', $texto);
        $this->assertStringContainsString('MODIFICACIÓN DE DÍA DE PAGO Y RECARGOS POR ATRASO', $texto);
        $this->assertStringContainsString('Piedras Negras, Coahuila. A 30 de junio del 2026', $texto);
        $this->assertStringContainsString('nuevo día de pago el día VIERNES de cada semana', $texto);
        $this->assertStringContainsString(
            'un recargo de $50.00 (cincuenta pesos 00/100 M.N.) por cada día natural de atraso, '
            .'comenzando a contabilizarse a partir del día siguiente a la fecha de pago acordada',
            $texto
        );
        $this->assertStringContainsString('C. MARIA LOPEZ GARZA', $texto);
        $this->assertStringNotContainsString('${', $texto);
    }

    #[Test]
    public function en_un_contrato_mensual_pone_el_dia_del_mes(): void
    {
        $contrato = $this->contrato(['frecuencia' => 'mensual', 'dia_mes' => 15, 'dia_semana' => null]);

        $this->assertStringContainsString('nuevo día de pago el día 15 de cada mes', $this->texto($contrato));
    }

    #[Test]
    public function el_recargo_sale_del_contrato_con_sus_dias_de_gracia(): void
    {
        $contrato = $this->contrato([
            'valor_recargo' => 100,
            'dias_gracia' => 7,
            'frecuencia_recargo_dias' => 7,
        ]);

        $this->assertStringContainsString(
            'un recargo de $100.00 (cien pesos 00/100 M.N.) por cada 7 días naturales de atraso, '
            .'comenzando a contabilizarse a partir del día siguiente a que se cumplan 7 días naturales '
            .'de gracia posteriores a la fecha de pago acordada',
            $this->texto($contrato)
        );
    }

    #[Test]
    public function un_recargo_en_porcentaje_se_dice_como_porcentaje(): void
    {
        $contrato = $this->contrato(['tipo_recargo' => 'porcentaje', 'valor_recargo' => 10]);

        $this->assertStringContainsString(
            'un recargo de 10% sobre el monto del pago vencido por cada día natural de atraso',
            $this->texto($contrato)
        );
    }

    #[Test]
    public function sin_recargo_capturado_deja_la_raya_para_llenarla_a_mano(): void
    {
        $contrato = $this->contrato(['valor_recargo' => 0]);

        $this->assertStringContainsString('un recargo de $__________ por cada día', $this->texto($contrato));
    }

    #[Test]
    public function sin_nombre_legal_firma_con_el_nombre_del_cliente(): void
    {
        $contrato = $this->contrato(['comprador_nombre_legal' => null]);
        $contrato->cliente->update(['nombre_legal' => null]);

        $this->assertStringContainsString('C. MARIA LOPEZ GARZA', $this->texto($contrato->fresh()));
    }

    #[Test]
    public function no_se_ofrece_en_un_contrato_de_contado(): void
    {
        $this->assertArrayHasKey(
            'anexo_cambio_dia_pago',
            ContratoWordService::documentTypesFor($this->contrato())
        );

        $this->assertArrayNotHasKey(
            'anexo_cambio_dia_pago',
            ContratoWordService::documentTypesFor($this->contrato(['es_contado' => true, 'folio_contrato' => 'CT-002']))
        );
    }

    private function texto(Contrato $contrato): string
    {
        $path = Storage::disk('private')->path(
            (new ContratoWordService)->generarDocx($contrato, 'anexo_cambio_dia_pago')
        );

        $zip = new ZipArchive;
        $zip->open($path);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        return html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function contrato(array $datos = []): Contrato
    {
        $propietario = Propietario::firstOrCreate(['nombre' => 'El Pato'], ['nombre_legal' => 'INMOBILIARIA EL PATO']);

        $fraccionamiento = Fraccionamiento::firstOrCreate(
            ['nombre' => 'Rancho El Pato'],
            ['propietario_id' => $propietario->id]
        );

        $numero = (string) (Lote::count() + 1);

        $lote = Lote::create([
            'fraccionamiento_id' => $fraccionamiento->id,
            'manzana' => '5',
            'lote' => $numero,
            'clave' => 'M5-L'.$numero,
        ]);

        $cliente = Cliente::create([
            'nombres' => 'Maria',
            'apellidos' => 'Lopez Garza',
            'nombre_legal' => 'MARIA LOPEZ GARZA',
        ]);

        return Contrato::create(array_merge([
            'cliente_id' => $cliente->id,
            'lote_id' => $lote->id,
            'folio_contrato' => 'CT-001',
            'fecha_inicio' => '2026-01-10',
            'frecuencia' => 'semanal',
            'dia_semana' => 1,
            'precio_total' => 100000,
            'enganche' => 10000,
            'saldo_inicial' => 90000,
            'saldo_actual' => 90000,
            'monto_pago' => 500,
            'tipo_recargo' => 'fijo',
            'valor_recargo' => 50,
            'dias_gracia' => 0,
            'estatus' => 'activo',
            'tipo' => 'terreno',
            'comprador_nombre_legal' => 'MARIA LOPEZ GARZA',
        ], $datos));
    }
}
