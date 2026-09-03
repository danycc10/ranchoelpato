<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\FormaPago;
use App\Models\Fraccionamiento;
use App\Models\Lote;
use App\Models\Propietario;
use App\Models\Recibo;
use App\Models\TipoCobro;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RecibosImpresionTicketTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function el_ticket_es_identico_impreso_solo_que_en_lote(): void
    {
        $recibo = $this->recibo();

        $this->actingAs($this->usuarioConPermisos());

        $individual = $this->get(route('admin.recibos.imprimir', $recibo))
            ->assertOk()
            ->getContent();

        $lote = $this->get(route('admin.recibos.print-lote', ['token' => $this->tokenLote([$recibo->id])]))
            ->assertOk()
            ->getContent();

        $this->assertSame(
            $this->ticketDe($individual),
            $this->ticketDe($lote),
            'El contenido del ticket cambia entre la impresión individual y la de lote.'
        );

        // Y con los mismos estilos: la hoja y el ticket miden igual en los dos.
        $this->assertSame($this->cssDe($individual), $this->cssDe($lote));
        $this->assertStringContainsString('size: 80mm auto', $this->cssDe($lote));
    }

    #[Test]
    public function el_pdf_de_lote_usa_la_misma_hoja_que_el_individual(): void
    {
        $recibos = collect([$this->recibo(), $this->recibo('R-002'), $this->recibo('R-003')]);

        $this->actingAs($this->usuarioConPermisos());

        $individual = $this->get(route('admin.recibos.pdf', $recibos->first()))
            ->assertOk()
            ->getContent();

        $lote = $this->get(route('admin.recibos.pdf-lote', ['token' => $this->tokenLote($recibos->pluck('id')->all())]))
            ->assertOk()
            ->getContent();

        // Misma hoja de 80 mm x 247 mm en los dos.
        $this->assertSame($this->hojas($individual), $this->hojas($lote));

        // Y una hoja por recibo, sin páginas de más.
        $this->assertSame(1, $this->paginas($individual));
        $this->assertSame(3, $this->paginas($lote));
    }

    #[Test]
    public function el_lote_tambien_se_manda_a_la_impresora_local(): void
    {
        $recibos = collect([$this->recibo(), $this->recibo('R-002')]);

        $this->actingAs($this->usuarioConPermisos());

        $individual = $this->get(route('admin.recibos.imprimir', $recibos->first()))
            ->assertOk()
            ->getContent();

        $lote = $this->get(route('admin.recibos.print-lote', ['token' => $this->tokenLote($recibos->pluck('id')->all())]))
            ->assertOk()
            ->getContent();

        // Las dos vistas usan el mismo envío al puente de impresión.
        $this->assertStringContainsString('enviarTicketAImpresoraLocal', $individual);
        $this->assertStringContainsString('enviarTicketAImpresoraLocal', $lote);
        $this->assertStringContainsString('btnPrintLocal', $lote);

        // Y el lote lleva el token propio de cada recibo, no uno solo.
        foreach ($recibos as $recibo) {
            $this->assertStringContainsString(
                str_replace('/', '\/', route('admin.recibos.print-token', $recibo)),
                $lote
            );
            $this->assertStringContainsString('ticket-'.$recibo->uuid, $lote);
        }
    }

    private function ticketDe(string $html): string
    {
        $documento = new DOMDocument;

        libxml_use_internal_errors(true);
        $documento->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();

        $ticket = (new DOMXPath($documento))->query('//div[@class="ticket"]')->item(0);

        return $ticket
            ? trim(preg_replace('/\s+/', ' ', (string) $documento->saveHTML($ticket)))
            : '';
    }

    private function cssDe(string $html): string
    {
        preg_match('#<style id="ticket-css">(.*?)</style>#s', $html, $m);

        return trim($m[1] ?? '');
    }

    /** @return array<int, string> Medidas únicas de las hojas del PDF. */
    private function hojas(string $pdf): array
    {
        preg_match_all('#/MediaBox\s*\[([^\]]+)\]#', $pdf, $m);

        return array_values(array_unique($m[1] ?? []));
    }

    private function paginas(string $pdf): int
    {
        preg_match_all('#/Type\s*/Pages.*?/Count\s+(\d+)#s', $pdf, $m);

        return (int) ($m[1] ? max($m[1]) : 0);
    }

    /**
     * @param  array<int, int>  $ids
     */
    private function tokenLote(array $ids): string
    {
        $token = 'token-de-prueba-'.count($ids).'-'.$ids[0];

        Cache::put('print_batch:'.$token, [
            'user_id' => auth()->id(),
            'recibo_ids' => $ids,
        ], now()->addMinutes(15));

        return $token;
    }

    private function usuarioConPermisos(): User
    {
        $usuario = User::factory()->create();

        $rol = Role::findOrCreate('admin', 'web');

        foreach (['admin.ver', 'recibos.imprimir'] as $permiso) {
            $rol->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
        }

        $usuario->assignRole($rol);

        return $usuario;
    }

    private function recibo(string $folio = 'R-001'): Recibo
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
            'manzana' => '5',
            'lote' => (string) random_int(100, 999),
            'clave' => 'M5-L'.random_int(100, 999),
        ]);

        $cliente = Cliente::firstOrCreate(
            ['nombres' => 'Maria', 'apellidos' => 'Lopez Garza'],
            ['telefono' => '8781234567']
        );

        $contrato = Contrato::create([
            'cliente_id' => $cliente->id,
            'lote_id' => $lote->id,
            'folio_contrato' => 'CT-'.$folio,
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
        ]);

        $tipoCobro = TipoCobro::firstOrCreate(['nombre' => 'MENSUALIDAD']);
        $formaPago = FormaPago::firstOrCreate(['nombre' => 'EFECTIVO']);

        return Recibo::create([
            'folio' => $folio,
            'fecha' => '2026-03-10',
            'anio' => 2026,
            'mes_del_anio' => 3,
            'cliente_id' => $cliente->id,
            'contrato_id' => $contrato->id,
            'lote_id' => $lote->id,
            'tipos_cobro_id' => $tipoCobro->id,
            'forma_pago_id' => $formaPago->id,
            'monto' => 3000,
            'saldo_anterior' => 90000,
            'saldo_posterior' => 87000,
            'observaciones' => 'Pago recibido en efectivo en oficina.',
        ]);
    }
}
