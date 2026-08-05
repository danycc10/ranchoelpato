<?php

namespace Tests\Unit;

use App\Services\Whatsapp\WhatsappService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class WhatsappServiceTest extends TestCase
{
    #[Test]
    public function prueba_mexico_y_estados_unidos_para_un_numero_local_de_diez_digitos(): void
    {
        $service = new class(['codigo_pais' => '52']) extends WhatsappService
        {
            public function candidatosPara(string $telefono): array
            {
                return $this->candidatos($telefono);
            }
        };

        $this->assertSame([
            '528781234567',
            '5218781234567',
            '18781234567',
        ], $service->candidatosPara('8781234567'));
    }

    #[Test]
    public function conserva_un_numero_estadounidense_con_prefijo_uno(): void
    {
        $service = new class(['codigo_pais' => '52']) extends WhatsappService
        {
            public function candidatosPara(string $telefono): array
            {
                return $this->candidatos($telefono);
            }
        };

        $this->assertSame(['19561234567'], $service->candidatosPara('19561234567'));
    }
}
