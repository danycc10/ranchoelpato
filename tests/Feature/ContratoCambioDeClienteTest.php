<?php

namespace Tests\Feature;

use App\Livewire\Admin\Contratos\Edit;
use App\Livewire\Admin\ContratosServicios\Create;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Fraccionamiento;
use App\Models\Lote;
use App\Models\Propietario;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Los contratos congelan los datos del comprador aparte del catálogo, para que
 * un documento firmado no cambie si después se corrige un cliente. Eso obliga
 * a volver a tomarlos cuando el contrato cambia de cliente: si no, los
 * documentos siguen nombrando al comprador anterior.
 */
class ContratoCambioDeClienteTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function cambiar_de_cliente_actualiza_los_datos_del_comprador(): void
    {
        $contrato = $this->contratoDe($this->clienteAnterior());
        $nuevo = $this->clienteNuevo();

        $this->guardarConCliente($contrato, $nuevo);

        $contrato->refresh();

        $this->assertSame($nuevo->id, $contrato->cliente_id);
        $this->assertSame('ANA SOFIA PEREZ RUIZ', $contrato->comprador_nombre_legal);
        $this->assertSame('PERA900101MCLXXX05', $contrato->comprador_curp);
    }

    #[Test]
    public function la_credencial_del_comprador_anterior_no_se_queda_pegada(): void
    {
        $contrato = $this->contratoDe($this->clienteAnterior());

        $this->assertSame('ine/juan-frente.jpg', $contrato->comprador_ine_frente);

        // El cliente nuevo no trae credencial: mejor sin ella que con la del
        // anterior, que es de otra persona.
        $this->guardarConCliente($contrato, $this->clienteNuevo());

        $contrato->refresh();

        $this->assertNull($contrato->comprador_ine_frente);
        $this->assertNull($contrato->comprador_ine_reverso);
    }

    #[Test]
    public function sin_cambiar_de_cliente_los_datos_congelados_se_respetan(): void
    {
        // El nombre guardado puede diferir a propósito del catálogo: es el que
        // quedó firmado. Guardar el contrato no debe pisarlo.
        $contrato = $this->contratoDe($this->clienteAnterior());

        $contrato->update(['comprador_nombre_legal' => 'JUAN PEREZ COMO FIRMO']);

        $this->guardarConCliente($contrato, $contrato->cliente);

        $this->assertSame('JUAN PEREZ COMO FIRMO', $contrato->fresh()->comprador_nombre_legal);
    }

    #[Test]
    public function el_contrato_de_servicio_nace_con_el_cliente_de_hoy(): void
    {
        $contrato = $this->contratoDe($this->clienteAnterior());
        $nuevo = $this->clienteNuevo();

        // Se cambia el cliente directo en la base, como quedaría un contrato
        // viejo al que le cambiaron el comprador antes de este arreglo.
        $contrato->forceFill(['cliente_id' => $nuevo->id])->save();

        $servicio = $this->contratoDeServicioDesde($contrato->fresh());

        $this->assertSame('ANA SOFIA PEREZ RUIZ', $servicio['comprador_nombre_legal']);
        $this->assertSame('PERA900101MCLXXX05', $servicio['comprador_curp']);
    }

    #[Test]
    public function si_el_cliente_no_trae_nombre_legal_se_usa_el_del_contrato(): void
    {
        $contrato = $this->contratoDe($this->clienteAnterior());

        $sinDatos = Cliente::create([
            'nombres' => 'Pedro',
            'apellidos' => 'Sin Datos',
        ]);

        $contrato->forceFill(['cliente_id' => $sinDatos->id])->save();

        // Sin nombre legal capturado se usa el nombre del catálogo.
        $servicio = $this->contratoDeServicioDesde($contrato->fresh());

        $this->assertSame('Pedro Sin Datos', $servicio['comprador_nombre_legal']);

        // Y lo que el cliente no tenga se cae a lo que traía el contrato.
        $this->assertSame('PEJU800101HCLXXX01', $servicio['comprador_curp']);
    }

    /** @return array<string, string|null> */
    private function contratoDeServicioDesde(Contrato $base): array
    {
        $componente = new Create;

        $metodo = new \ReflectionMethod($componente, 'datosDelComprador');
        $metodo->setAccessible(true);

        return $metodo->invoke($componente, $base);
    }

    private function guardarConCliente(Contrato $contrato, Cliente $cliente): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(Edit::class, ['uuid' => $contrato->uuid])
            ->set('cliente_id', $cliente->id)
            ->set('confirmar_reestructura', true)
            ->call('guardarCambios');
    }

    private function clienteAnterior(): Cliente
    {
        return Cliente::create([
            'nombres' => 'Juan',
            'apellidos' => 'Perez',
            'nombre_legal' => 'JUAN PEREZ LOPEZ',
            'curp' => 'PEJU800101HCLXXX01',
            'ine_frente' => 'ine/juan-frente.jpg',
            'ine_reverso' => 'ine/juan-reverso.jpg',
        ]);
    }

    private function clienteNuevo(): Cliente
    {
        return Cliente::create([
            'nombres' => 'Ana Sofia',
            'apellidos' => 'Perez Ruiz',
            'nombre_legal' => 'ANA SOFIA PEREZ RUIZ',
            'curp' => 'PERA900101MCLXXX05',
        ]);
    }

    private function contratoDe(Cliente $cliente): Contrato
    {
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
            'lote' => '10',
            'clave' => 'M1-L10',
        ]);

        return Contrato::create(array_merge([
            'cliente_id' => $cliente->id,
            'lote_id' => $lote->id,
            'folio_contrato' => 'CT-CAMBIO-CLIENTE',
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
        ], $cliente->datosComoComprador()));
    }
}
