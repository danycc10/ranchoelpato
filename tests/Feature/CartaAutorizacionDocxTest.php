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

class CartaAutorizacionDocxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        Carbon::setTestNow('2026-06-30 10:00:00');

        $this->artisan('contratos:plantilla-carta-autorizacion')->assertSuccessful();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    #[Test]
    public function con_un_lote_habla_del_lote_en_singular(): void
    {
        $texto = $this->texto($this->contrato($this->cliente(), '12'));

        $this->assertStringContainsString('CARTA DE AUTORIZACIÓN', $texto);
        $this->assertStringContainsString('Piedras Negras, Coahuila. A 30 de junio del 2026', $texto);
        $this->assertStringContainsString(
            'yo, MARIA LOPEZ GARZA, en mi carácter de titular del lote identificado con el número 12, '
            .'en Finca Rancho El Pato, manifiesto que autorizo a mi pareja',
            $texto
        );
        $this->assertStringContainsString('los pagos correspondientes a dicho lote ante la administración', $texto);
        $this->assertStringContainsString('cualquier otro trámite relacionado con el lote.', $texto);
        $this->assertStringContainsString('C. MARIA LOPEZ GARZA', $texto);
        $this->assertStringContainsString('NOMBRE DE LA PERSONA AUTORIZADA', $texto);
        $this->assertStringNotContainsString('${', $texto);
    }

    #[Test]
    public function con_varios_lotes_los_nombra_todos(): void
    {
        $cliente = $this->cliente();
        $contrato = $this->contrato($cliente, '12');
        $this->contrato($cliente, '13', 'CT-002');

        $texto = $this->texto($contrato);

        $this->assertStringContainsString('titular de los lotes identificados con los números 12 y 13', $texto);
        $this->assertStringContainsString('los pagos correspondientes a dichos lotes', $texto);
        $this->assertStringContainsString('relacionado con los lotes.', $texto);
    }

    #[Test]
    public function no_se_ofrece_en_un_contrato_de_contado(): void
    {
        $cliente = $this->cliente();

        $this->assertArrayHasKey(
            'carta_autorizacion',
            ContratoWordService::documentTypesFor($this->contrato($cliente, '12'))
        );

        $contado = $this->contrato($cliente, '13', 'CT-002');
        $contado->update(['es_contado' => true]);

        $this->assertArrayNotHasKey(
            'carta_autorizacion',
            ContratoWordService::documentTypesFor($contado->fresh())
        );
    }

    private function texto(Contrato $contrato): string
    {
        $path = Storage::disk('private')->path(
            (new ContratoWordService)->generarDocx($contrato, 'carta_autorizacion')
        );

        $zip = new ZipArchive;
        $zip->open($path);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        return html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function cliente(): Cliente
    {
        return Cliente::create([
            'nombres' => 'Maria',
            'apellidos' => 'Lopez Garza',
            'nombre_legal' => 'MARIA LOPEZ GARZA',
        ]);
    }

    private function contrato(Cliente $cliente, string $numeroLote, string $folio = 'CT-001'): Contrato
    {
        $propietario = Propietario::firstOrCreate(['nombre' => 'El Pato'], ['nombre_legal' => 'INMOBILIARIA EL PATO']);

        $fraccionamiento = Fraccionamiento::firstOrCreate(
            ['nombre' => 'Rancho El Pato'],
            ['propietario_id' => $propietario->id]
        );

        $lote = Lote::create([
            'fraccionamiento_id' => $fraccionamiento->id,
            'manzana' => '5',
            'lote' => $numeroLote,
            'clave' => 'M5-L'.$numeroLote,
        ]);

        return Contrato::create([
            'cliente_id' => $cliente->id,
            'lote_id' => $lote->id,
            'folio_contrato' => $folio,
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
        ]);
    }
}
