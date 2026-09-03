<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Fraccionamiento;
use App\Models\Lote;
use App\Models\Propietario;
use App\Services\Contratos\ContratoWordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use ZipArchive;

class CaratulaExpedienteDocxTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function imprime_la_caratula_de_la_operacion(): void
    {
        Storage::fake('private');

        $this->artisan('contratos:plantilla-caratula')->assertSuccessful();

        $contrato = $this->contrato();

        $texto = $this->textoDocx(Storage::disk('private')->path(
            (new ContratoWordService)->generarDocx($contrato, 'caratula_expediente')
        ));

        $this->assertStringContainsString('CARÁTULA DE DATOS DE LA OPERACIÓN', $texto);
        $this->assertStringContainsString('No forma parte del contrato', $texto);

        $this->assertStringContainsString('CT-001', $texto);
        $this->assertStringContainsString('Rancho El Pato', $texto);
        $this->assertStringContainsString('EJIDO LA MOTA', $texto);
        $this->assertStringContainsString('Piedras Negras, Coahuila de Zaragoza', $texto);
        $this->assertStringContainsString('12 / 5', $texto);
        $this->assertStringContainsString('200.00 m²', $texto);
        $this->assertStringContainsString('INMOBILIARIA EL PATO', $texto);
        $this->assertStringContainsString('MARIA LOPEZ GARZA', $texto);
        $this->assertStringContainsString('$100,000.00 (CIEN MIL PESOS 00/100 MONEDA NACIONAL)', $texto);

        $this->assertStringNotContainsString('${', $texto);
    }

    #[Test]
    public function el_control_interno_responde_lo_que_el_sistema_sabe(): void
    {
        Storage::fake('private');

        $this->artisan('contratos:plantilla-caratula')->assertSuccessful();

        $contrato = $this->contrato();

        $texto = $this->textoDocx(Storage::disk('private')->path(
            (new ContratoWordService)->generarDocx($contrato, 'caratula_expediente')
        ));

        // Sin scans ni credenciales cargadas.
        $this->assertMatchesRegularExpression('/EXPEDIENTE COMPLETO\s*NO/u', $texto);
        $this->assertMatchesRegularExpression('/IDENTIFICACIONES\s*NO/u', $texto);
        // Hay ejido registrado y antecedente capturado.
        $this->assertMatchesRegularExpression('/ANTECEDENTE AGRARIO\s*SÍ/u', $texto);
        $this->assertStringContainsString('FECHA DE ALTA', $texto);

        // Con las credenciales de ambas partes, las identificaciones ya están.
        $contrato->update([
            'comprador_ine_frente' => 'contratos/ine-comprador.jpg',
            'vendedor_ine_frente' => 'contratos/ine-vendedor.jpg',
        ]);

        $texto = $this->textoDocx(Storage::disk('private')->path(
            (new ContratoWordService)->generarDocx($contrato->fresh(), 'caratula_expediente')
        ));

        $this->assertMatchesRegularExpression('/IDENTIFICACIONES\s*SÍ/u', $texto);
    }

    #[Test]
    public function sin_ejido_el_antecedente_agrario_no_aplica(): void
    {
        Storage::fake('private');

        $this->artisan('contratos:plantilla-caratula')->assertSuccessful();

        $contrato = $this->contrato();
        $contrato->lote->fraccionamiento->update(['ejido' => null]);

        $texto = $this->textoDocx(Storage::disk('private')->path(
            (new ContratoWordService)->generarDocx($contrato->fresh(), 'caratula_expediente')
        ));

        $this->assertMatchesRegularExpression('/ANTECEDENTE AGRARIO\s*NO APLICA/u', $texto);
    }

    #[Test]
    public function la_caratula_no_se_ofrece_en_contratos_de_servicio(): void
    {
        $contrato = $this->contrato();

        $this->assertArrayHasKey(
            'caratula_expediente',
            ContratoWordService::documentTypesFor($contrato)
        );

        $luz = Contrato::create([
            'cliente_id' => $contrato->cliente_id,
            'lote_id' => $contrato->lote_id,
            'folio_contrato' => 'CT-001-EL',
            'fecha_inicio' => '2026-03-10',
            'frecuencia' => 'mensual',
            'dia_mes' => 10,
            'precio_total' => 12000,
            'enganche' => 2000,
            'saldo_inicial' => 10000,
            'saldo_actual' => 10000,
            'monto_pago' => 1000,
            'tipo_recargo' => 'fijo',
            'valor_recargo' => 100,
            'dias_gracia' => 3,
            'estatus' => 'activo',
            'tipo' => 'servicio',
            'servicio_tipo' => 'electricidad',
            'contrato_base_id' => $contrato->id,
        ]);

        $this->assertArrayNotHasKey(
            'caratula_expediente',
            ContratoWordService::documentTypesFor($luz)
        );
    }

    private function contrato(): Contrato
    {
        $propietario = Propietario::create([
            'nombre' => 'El Pato',
            'nombre_legal' => 'INMOBILIARIA EL PATO',
        ]);

        $fraccionamiento = Fraccionamiento::create([
            'propietario_id' => $propietario->id,
            'nombre' => 'Rancho El Pato',
            'ejido' => 'EJIDO LA MOTA',
            'municipio' => 'Piedras Negras',
            'antecedente_tipo' => 'Certificado parcelario',
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
            'vendedor_nombre_legal' => 'INMOBILIARIA EL PATO',
            'comprador_nombre_legal' => 'MARIA LOPEZ GARZA',
            'area_m2_snapshot' => 200,
        ]);
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
