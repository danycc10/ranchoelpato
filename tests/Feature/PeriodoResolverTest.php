<?php

namespace Tests\Feature;

use App\Models\Periodo;
use App\Services\Recibos\PeriodoResolver;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PeriodoResolverTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function reutiliza_el_periodo_del_catalogo_cuando_ya_existe(): void
    {
        $periodo = Periodo::create([
            'tipo' => 'mensual',
            'anio' => 2026,
            'mes' => 8,
            'nombre' => 'AGOSTO 2026',
        ]);

        $id = app(PeriodoResolver::class)->idParaFecha('2026-08-31');

        $this->assertSame($periodo->id, $id);
        $this->assertSame(1, Periodo::query()->count());
    }

    #[Test]
    public function da_de_alta_el_mes_que_falta_en_el_catalogo(): void
    {
        $id = app(PeriodoResolver::class)->idParaFecha('2027-01-15');

        $this->assertNotNull($id);
        $this->assertDatabaseHas('periodos', [
            'id' => $id,
            'tipo' => 'mensual',
            'anio' => 2027,
            'mes' => 1,
            'nombre' => 'ENERO 2027',
        ]);
    }

    #[Test]
    public function no_duplica_el_periodo_al_resolver_la_misma_fecha_dos_veces(): void
    {
        $resolver = app(PeriodoResolver::class);

        $primero = $resolver->idParaFecha(Carbon::parse('2027-03-01'));
        $segundo = $resolver->idParaFecha('2027-03-28');

        $this->assertSame($primero, $segundo);
        $this->assertSame(1, Periodo::query()->count());
    }

    #[Test]
    public function devuelve_null_cuando_no_hay_fecha_utilizable(): void
    {
        $resolver = app(PeriodoResolver::class);

        $this->assertNull($resolver->idParaFecha(null));
        $this->assertNull($resolver->idParaFecha(''));
        $this->assertNull($resolver->idParaFecha('no es una fecha'));
        $this->assertSame(0, Periodo::query()->count());
    }
}
