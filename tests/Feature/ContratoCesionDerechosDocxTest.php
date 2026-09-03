<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Cuota;
use App\Models\Fraccionamiento;
use App\Models\Lote;
use App\Models\Propietario;
use App\Services\Contratos\CondicionesCesionService;
use App\Services\Contratos\ContratoWordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use ZipArchive;

class ContratoCesionDerechosDocxTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function solo_los_contratos_de_terreno_ofrecen_el_contrato_de_cesion(): void
    {
        $terreno = $this->contratoTerreno();

        $this->assertArrayHasKey(
            'contrato_cesion_derechos',
            ContratoWordService::documentTypesFor($terreno)
        );

        $luz = $this->contratoServicio($terreno);

        $this->assertArrayNotHasKey(
            'contrato_cesion_derechos',
            ContratoWordService::documentTypesFor($luz)
        );

        $this->assertNull(
            ContratoWordService::documentTypeFor('contrato_cesion_derechos', $luz)
        );
    }

    #[Test]
    public function genera_el_word_con_los_datos_del_contrato(): void
    {
        Storage::fake('private');

        $this->artisan('contratos:plantilla-cesion')->assertSuccessful();

        $contrato = $this->contratoTerreno();

        $path = (new ContratoWordService)->generarDocx($contrato, 'contrato_cesion_derechos');

        $this->assertTrue(Storage::disk('private')->exists($path));

        $texto = $this->textoDocx(Storage::disk('private')->path($path));

        // Las partes, con los datos que declara cada una.
        $this->assertStringContainsString('INMOBILIARIA EL PATO', $texto);
        $this->assertStringContainsString('MARIA LOPEZ GARZA', $texto);
        $this->assertStringContainsString('persona física', $texto);
        $this->assertStringContainsString('Casado(a)', $texto);
        $this->assertStringContainsString('Comerciante', $texto);
        $this->assertStringContainsString('LOGM900101MCLPRR03', $texto);

        // El predio y su antecedente agrario.
        $this->assertStringContainsString('EJIDO LA MOTA', $texto);
        $this->assertStringContainsString('Piedras Negras', $texto);
        $this->assertStringContainsString('Certificado parcelario', $texto);
        $this->assertStringContainsString('Lote 12, Manzana 5, Rancho El Pato', $texto);
        $this->assertStringContainsString('200.00 m²', $texto);
        $this->assertStringContainsString('10.00 m, colinda con Calle Juárez', $texto);

        // Precio y calendario.
        $this->assertStringContainsString('$100,000.00', $texto);
        $this->assertStringContainsString('CIEN MIL PESOS 00/100 MONEDA NACIONAL', $texto);
        $this->assertStringContainsString('$10,000.00', $texto);
        $this->assertStringContainsString('$90,000.00', $texto);
        $this->assertStringContainsString('los días 10 de cada mes', $texto);
        $this->assertStringContainsString('BBVA CLABE 012345678901234567', $texto);

        // Condiciones por defecto del sistema.
        $this->assertStringContainsString('A la firma', $texto);
        $this->assertStringContainsString('En gestión', $texto);

        // Sin placeholders sin resolver.
        $this->assertStringNotContainsString('${', $texto);
    }

    #[Test]
    public function no_incluye_la_caratula_ni_el_control_interno(): void
    {
        Storage::fake('private');

        $this->artisan('contratos:plantilla-cesion')->assertSuccessful();

        $contrato = $this->contratoTerreno();

        $texto = $this->textoDocx(Storage::disk('private')->path(
            (new ContratoWordService)->generarDocx($contrato, 'contrato_cesion_derechos')
        ));

        $this->assertStringNotContainsString('CARÁTULA', $texto);
        $this->assertStringNotContainsString('CONTROL INTERNO', $texto);
        $this->assertStringNotContainsString('EXPEDIENTE COMPLETO', $texto);
        $this->assertStringNotContainsString('ASESOR', $texto);
    }

    #[Test]
    public function el_contrato_manda_sobre_las_condiciones_por_defecto(): void
    {
        Storage::fake('private');

        $this->artisan('contratos:plantilla-cesion')->assertSuccessful();

        $contrato = $this->contratoTerreno();
        $contrato->update([
            'posesion_entrega' => 'A la liquidación',
            'servicios_estado' => 'Existente',
            'parcialidades_rescision' => 4,
        ]);

        $condiciones = CondicionesCesionService::paraContrato($contrato->fresh());

        $this->assertSame('A la liquidación', $condiciones['posesion_entrega']);
        $this->assertSame('Existente', $condiciones['servicios_estado']);
        $this->assertSame('4', $condiciones['parcialidades_rescision']);
        // Lo que el contrato no fija sigue viniendo de los ajustes.
        $this->assertSame('5', $condiciones['tolerancia_medidas']);

        $texto = $this->textoDocx(Storage::disk('private')->path(
            (new ContratoWordService)->generarDocx($contrato->fresh(), 'contrato_cesion_derechos')
        ));

        $this->assertStringContainsString('A la liquidación', $texto);
        $this->assertStringNotContainsString('A la firma', $texto);
    }

    #[Test]
    public function un_formato_sin_plantilla_no_impide_generar_los_demas(): void
    {
        Storage::fake('private');

        // Solo se instala la plantilla del contrato de cesión.
        $this->artisan('contratos:plantilla-cesion')->assertSuccessful();

        $contrato = $this->contratoTerreno();

        $paths = (new ContratoWordService)->generarTodosDocx($contrato);

        $this->assertArrayHasKey('archivo_contrato_cesion_docx', $paths);
        $this->assertArrayNotHasKey('archivo_contrato_docx', $paths);
    }

    private function contratoTerreno(): Contrato
    {
        $propietario = Propietario::create([
            'nombre' => 'El Pato',
            'nombre_legal' => 'INMOBILIARIA EL PATO',
            'curp' => 'MIRA800101HCLXRR01',
            'rfc' => 'MIRA800101AB2',
            'direccion' => 'Boulevard Centenario 500, Piedras Negras',
            'telefono' => '8789876543',
            'correo' => 'contacto@elpato.mx',
            'persona_tipo' => 'fisica',
            'nacionalidad' => 'Mexicana',
            'estado_civil' => 'Casado(a)',
            'ocupacion' => 'Comerciante',
            'identificacion_tipo' => 'INE',
            'identificacion_numero' => 'IDMEX1234567890',
        ]);

        $propietario->cuentasBancarias()->create([
            'alias' => 'Principal',
            'banco' => 'BBVA',
            'tipo' => 'CLABE',
            'numero' => '012345678901234567',
            'activa' => true,
        ]);

        $fraccionamiento = Fraccionamiento::create([
            'propietario_id' => $propietario->id,
            'nombre' => 'Rancho El Pato',
            'ubicacion' => 'Carretera 57 kilómetro 12',
            'ejido' => 'EJIDO LA MOTA',
            'municipio' => 'Piedras Negras',
            'antecedente_tipo' => 'Certificado parcelario',
            'antecedente_datos' => 'Número 000123456789 del Registro Agrario Nacional',
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
            'curp' => 'LOGM900101MCLPRR03',
            'rfc' => 'LOGM900101AB1',
            'direccion' => 'Calle Hidalgo 123, Piedras Negras',
            'telefono' => '8781234567',
            'correo' => 'maria@example.com',
            'persona_tipo' => 'fisica',
            'nacionalidad' => 'Mexicana',
            'estado_civil' => 'Casado(a)',
            'ocupacion' => 'Comerciante',
            'identificacion_tipo' => 'INE',
            'identificacion_numero' => 'IDMEX0987654321',
        ]);

        $contrato = Contrato::create([
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
            'comprador_curp' => 'LOGM900101MCLPRR03',
            'area_m2_snapshot' => 200,
            'medida_norte_snapshot' => 10,
            'medida_sur_snapshot' => 10,
            'medida_este_snapshot' => 20,
            'medida_oeste_snapshot' => 20,
            'colindancia_norte_snapshot' => 'Calle Juárez',
            'colindancia_sur_snapshot' => 'Lote 13',
        ]);

        foreach (range(1, 30) as $numero) {
            Cuota::create([
                'contrato_id' => $contrato->id,
                'numero' => $numero,
                'fecha_vencimiento' => now()->addMonths($numero)->toDateString(),
                'monto' => 3000,
                'pagado_total' => 0,
                'condonado_total' => 0,
                'recargo_aplicado' => 0,
                'estatus' => 'pendiente',
            ]);
        }

        return $contrato;
    }

    private function contratoServicio(Contrato $base): Contrato
    {
        return Contrato::create([
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
