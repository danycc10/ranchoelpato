<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\SolicitudDatosCliente;
use App\Models\User;
use App\Services\Clientes\SolicitudDatosClienteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * Liga temporal para que el cliente capture sus propios datos.
 *
 * Lo que llena no toca su registro hasta que alguien lo revisa: una liga
 * reenviada o un dedazo no deben pisar el catálogo.
 */
class SolicitudDatosClienteTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function la_liga_solo_se_guarda_hasheada(): void
    {
        $resultado = $this->servicio()->crear($this->cliente(), User::factory()->create());

        $token = $resultado['token'];

        $this->assertSame(hash('sha256', $token), $resultado['solicitud']->token_hash);

        // El token en claro no queda en ninguna columna.
        $fila = (array) DB::table('solicitudes_datos_cliente')->first();
        $this->assertNotContains($token, $fila);
    }

    #[Test]
    public function una_liga_nueva_deja_sin_efecto_la_anterior(): void
    {
        $cliente = $this->cliente();
        $servicio = $this->servicio();

        $primera = $servicio->crear($cliente)['token'];
        $segunda = $servicio->crear($cliente)['token'];

        $this->assertNull($servicio->abiertaPorToken($primera));
        $this->assertNotNull($servicio->abiertaPorToken($segunda));
    }

    #[Test]
    public function una_liga_vencida_ya_no_sirve(): void
    {
        $servicio = $this->servicio();
        $resultado = $servicio->crear($this->cliente());

        $resultado['solicitud']->forceFill(['expira_en' => now()->subDay()])->save();

        $this->assertNull($servicio->abiertaPorToken($resultado['token']));
    }

    #[Test]
    public function un_token_inventado_no_abre_nada(): void
    {
        $this->servicio()->crear($this->cliente());

        $this->assertNull($this->servicio()->abiertaPorToken(str_repeat('a', 48)));
    }

    #[Test]
    public function lo_capturado_no_toca_al_cliente_hasta_que_se_revisa(): void
    {
        $cliente = $this->cliente();
        $servicio = $this->servicio();

        $solicitud = $servicio->crear($cliente)['solicitud'];

        $servicio->guardarCaptura($solicitud, [
            'curp' => 'PEJU800101HCLXXX01',
            'rfc' => 'PEJU800101RN9',
            'direccion' => 'Calle Falsa 123',
        ]);

        // Sigue igual: lo capturado espera revisión.
        $this->assertNull($cliente->fresh()->curp);
        $this->assertSame(SolicitudDatosCliente::CAPTURADA, $solicitud->fresh()->estatus);
    }

    #[Test]
    public function al_aplicarla_los_datos_pasan_al_cliente(): void
    {
        $cliente = $this->cliente();
        $servicio = $this->servicio();
        $revisor = User::factory()->create();

        $solicitud = $servicio->crear($cliente)['solicitud'];

        $servicio->guardarCaptura($solicitud, [
            'curp' => 'PEJU800101HCLXXX01',
            'direccion' => 'Calle Falsa 123',
        ]);

        $actualizado = $servicio->aplicar($solicitud->fresh(), $revisor);

        $this->assertSame('PEJU800101HCLXXX01', $actualizado->curp);
        $this->assertSame('Calle Falsa 123', $actualizado->direccion);
        $this->assertSame(SolicitudDatosCliente::APLICADA, $solicitud->fresh()->estatus);
        $this->assertSame($revisor->id, $solicitud->fresh()->aplicada_por_user_id);
    }

    #[Test]
    public function lo_que_el_cliente_deja_en_blanco_no_borra_lo_que_ya_se_tenia(): void
    {
        $cliente = $this->cliente();
        $cliente->update(['rfc' => 'RFC-QUE-YA-ESTABA']);

        $servicio = $this->servicio();
        $solicitud = $servicio->crear($cliente)['solicitud'];

        // Solo llenó la CURP.
        $servicio->guardarCaptura($solicitud, ['curp' => 'PEJU800101HCLXXX01', 'rfc' => '']);

        $actualizado = $servicio->aplicar($solicitud->fresh());

        $this->assertSame('PEJU800101HCLXXX01', $actualizado->curp);
        $this->assertSame('RFC-QUE-YA-ESTABA', $actualizado->rfc);
    }

    #[Test]
    public function se_puede_aplicar_solo_una_parte_de_lo_capturado(): void
    {
        $cliente = $this->cliente();
        $servicio = $this->servicio();

        $solicitud = $servicio->crear($cliente)['solicitud'];

        $servicio->guardarCaptura($solicitud, [
            'curp' => 'PEJU800101HCLXXX01',
            'ocupacion' => 'ocupacion mal escrita',
        ]);

        // El revisor acepta la CURP y descarta lo demás.
        $actualizado = $servicio->aplicar($solicitud->fresh(), null, ['curp']);

        $this->assertSame('PEJU800101HCLXXX01', $actualizado->curp);
        $this->assertNull($actualizado->ocupacion);
    }

    #[Test]
    public function una_liga_ya_usada_no_se_puede_volver_a_capturar(): void
    {
        $servicio = $this->servicio();
        $solicitud = $servicio->crear($this->cliente())['solicitud'];

        $servicio->guardarCaptura($solicitud, ['curp' => 'PEJU800101HCLXXX01']);
        $servicio->aplicar($solicitud->fresh());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ya no está disponible');

        $servicio->guardarCaptura($solicitud->fresh(), ['curp' => 'OTRA']);
    }

    #[Test]
    public function no_se_puede_aplicar_algo_que_nadie_ha_capturado(): void
    {
        $servicio = $this->servicio();
        $solicitud = $servicio->crear($this->cliente())['solicitud'];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no tiene datos por revisar');

        $servicio->aplicar($solicitud);
    }

    #[Test]
    public function el_cliente_no_puede_cambiarse_el_nombre_ni_el_estatus(): void
    {
        $cliente = $this->cliente();
        $servicio = $this->servicio();

        $solicitud = $servicio->crear($cliente)['solicitud'];

        // Aunque llegue de más en el formulario, solo se guarda lo permitido.
        $servicio->guardarCaptura($solicitud, [
            'curp' => 'PEJU800101HCLXXX01',
            'nombres' => 'HACKEADO',
            'estatus' => 'inactivo',
            'notas' => 'texto libre',
        ]);

        $guardado = $solicitud->fresh()->datos;

        $this->assertArrayNotHasKey('nombres', $guardado);
        $this->assertArrayNotHasKey('estatus', $guardado);
        $this->assertArrayNotHasKey('notas', $guardado);

        $servicio->aplicar($solicitud->fresh());

        $this->assertSame('Juan', $cliente->fresh()->nombres);
        $this->assertSame('activo', $cliente->fresh()->estatus);
    }

    #[Test]
    public function dice_que_datos_le_faltan_al_cliente(): void
    {
        $cliente = $this->cliente();
        $cliente->update(['curp' => 'PEJU800101HCLXXX01', 'telefono' => '8781234567']);

        $faltan = $this->servicio()->faltantes($cliente->fresh());

        $this->assertArrayNotHasKey('curp', $faltan);
        $this->assertArrayNotHasKey('telefono', $faltan);
        $this->assertArrayHasKey('rfc', $faltan);
        $this->assertArrayHasKey('direccion', $faltan);
    }

    private function servicio(): SolicitudDatosClienteService
    {
        return app(SolicitudDatosClienteService::class);
    }

    private function cliente(): Cliente
    {
        return Cliente::create([
            'nombres' => 'Juan',
            'apellidos' => 'Perez',
        ]);
    }
}
