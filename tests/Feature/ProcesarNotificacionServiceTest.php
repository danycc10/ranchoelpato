<?php

namespace Tests\Feature;

use App\Models\Notificacion;
use App\Services\Notificaciones\ProcesarNotificacionService;
use App\Services\Recibos\ReciboPdfService;
use App\Services\Whatsapp\WhatsappService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProcesarNotificacionServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function el_envio_inmediato_exitoso_marca_la_notificacion_como_enviada(): void
    {
        $notificacion = $this->notificacion();
        $whatsapp = Mockery::mock(WhatsappService::class);
        $whatsapp->shouldReceive('enviarTexto')
            ->once()
            ->with('8781234567', 'Pago recibido')
            ->andReturn(['id' => 'wa-message-id']);

        $procesador = new ProcesarNotificacionService(
            $whatsapp,
            Mockery::mock(ReciboPdfService::class),
        );

        $this->assertTrue($procesador->procesar($notificacion, mantenerEnColaSiFalla: true));
        $this->assertDatabaseHas('notificaciones_salida', [
            'id' => $notificacion->id,
            'estatus' => 'enviado',
            'error' => null,
        ]);
    }

    #[Test]
    public function una_falla_inmediata_conserva_la_notificacion_en_cola(): void
    {
        $notificacion = $this->notificacion();
        $whatsapp = Mockery::mock(WhatsappService::class);
        $whatsapp->shouldReceive('enviarTexto')
            ->once()
            ->andThrow(new \RuntimeException('OpenWA no disponible'));

        $procesador = new ProcesarNotificacionService(
            $whatsapp,
            Mockery::mock(ReciboPdfService::class),
        );

        $this->assertFalse($procesador->procesar($notificacion, mantenerEnColaSiFalla: true));
        $this->assertDatabaseHas('notificaciones_salida', [
            'id' => $notificacion->id,
            'estatus' => 'en_cola',
            'error' => 'OpenWA no disponible',
        ]);
    }

    protected function notificacion(): Notificacion
    {
        return Notificacion::create([
            'canal' => 'whatsapp',
            'tipo' => 'prueba',
            'destino' => '8781234567',
            'payload' => ['mensaje' => 'Pago recibido'],
            'estatus' => 'en_cola',
        ]);
    }
}
