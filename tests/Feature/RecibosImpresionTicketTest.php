<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\FormaPago;
use App\Models\Fraccionamiento;
use App\Models\Lote;
use App\Models\PrintJob;
use App\Models\Propietario;
use App\Models\Recibo;
use App\Models\TipoCobro;
use App\Models\User;
use App\Services\Impresion\PrintAgentPairingService;
use App\Services\Impresion\ReciboTicketPdfService;
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
    public function el_pdf_del_agente_usa_la_misma_tipografia_que_el_navegador(): void
    {
        $pdf = app(ReciboTicketPdfService::class)->generar($this->recibo());

        preg_match_all('#/BaseFont\s*/([A-Za-z0-9+,\-]+)#', $pdf, $coincidencias);

        $fuentes = array_values(array_unique($coincidencias[1]));

        // El ticket pide Arial. Dompdf solo resuelve los pesos 400 y 700 de esa
        // familia: con 600 u 800 cae a Times y el ticket sale con otra letra.
        $this->assertNotEmpty($fuentes);

        foreach ($fuentes as $fuente) {
            $this->assertStringStartsWith(
                'Helvetica',
                $fuente,
                "El ticket del agente salió con la fuente {$fuente} en vez de Helvetica/Arial."
            );
        }
    }

    #[Test]
    public function la_hoja_del_agente_termina_donde_termina_el_ticket(): void
    {
        $servicio = app(ReciboTicketPdfService::class);

        $corto = $servicio->generar($this->recibo('R-CORTO'));

        $largo = $servicio->generar($this->recibo(
            'R-LARGO',
            str_repeat('Observacion larga que empuja el ticket hacia abajo. ', 12)
        ));

        // Un solo ticket es una sola hoja: partirlo seria peor que la cola.
        $this->assertSame(1, $this->paginasDe($corto));
        $this->assertSame(1, $this->paginasDe($largo));

        $altoCorto = $this->altoDeLaHoja($corto);
        $altoLargo = $this->altoDeLaHoja($largo);

        // La hoja se ajusta al contenido, como el `size: 80mm auto` del
        // navegador, en vez de medir siempre lo mismo.
        $this->assertGreaterThan($altoCorto, $altoLargo);

        // Y no se queda con la cola en blanco de la hoja fija de 700 pt.
        $this->assertLessThan(700, $altoCorto);
    }

    #[Test]
    public function sin_equipo_emparejado_el_ticket_solo_ofrece_el_navegador(): void
    {
        $recibo = $this->recibo();

        $this->actingAs($this->usuarioConPermisos());

        $this->get(route('admin.recibos.imprimir', $recibo))
            ->assertOk()
            ->assertSee('Imprimir navegador')
            ->assertDontSee('Imprimir por agente');
    }

    #[Test]
    public function con_el_equipo_emparejado_aparece_el_boton_del_agente(): void
    {
        $recibo = $this->recibo();

        $this->actingAs($this->usuarioConAgente());

        $this->get(route('admin.recibos.imprimir', $recibo))
            ->assertOk()
            ->assertSee('Imprimir por agente')
            ->assertSee(route('admin.recibos.imprimir-agente', $recibo));
    }

    #[Test]
    public function el_boton_del_agente_encola_el_ticket_del_cajero(): void
    {
        $recibo = $this->recibo();
        $usuario = $this->usuarioConAgente();

        $this->actingAs($usuario);

        $this->post(route('admin.recibos.imprimir-agente', $recibo))
            ->assertOk()
            ->assertJsonPath('message', 'Ticket enviado a tu impresora.');

        $job = PrintJob::sole();

        $this->assertSame($recibo->id, $job->document_id);
        $this->assertSame($usuario->id, $job->requested_by_id);
    }

    #[Test]
    public function quien_no_tiene_equipo_no_puede_encolar_aunque_llame_a_la_ruta(): void
    {
        $recibo = $this->recibo();

        $this->actingAs($this->usuarioConPermisos());

        $this->post(route('admin.recibos.imprimir-agente', $recibo))
            ->assertStatus(422)
            ->assertJsonPath('message', 'No tienes un equipo de impresión emparejado.');

        $this->assertSame(0, PrintJob::count());
    }

    #[Test]
    public function el_lote_se_encola_completo_por_el_agente(): void
    {
        $recibos = collect([$this->recibo(), $this->recibo('R-002')]);

        $this->actingAs($this->usuarioConAgente());

        $this->post(route('admin.recibos.print-lote-agente', [
            'token' => $this->tokenLote($recibos->pluck('id')->all()),
        ]))
            ->assertOk()
            ->assertJsonPath('message', '2 tickets enviados a tu impresora.');

        $this->assertSame(2, PrintJob::count());
    }

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
    public function el_lote_arma_una_hoja_por_recibo(): void
    {
        $recibos = collect([$this->recibo(), $this->recibo('R-002')]);

        $this->actingAs($this->usuarioConPermisos());

        $lote = $this->get(route('admin.recibos.print-lote', ['token' => $this->tokenLote($recibos->pluck('id')->all())]))
            ->assertOk()
            ->getContent();

        foreach ($recibos as $recibo) {
            $this->assertStringContainsString('ticket-'.$recibo->uuid, $lote);
        }

        // El puente Node se retiró: la impresión directa la hace el agente
        // local contra la API, no el navegador contra una IP de la red.
        $this->assertStringNotContainsString('enviarTicketAImpresoraLocal', $lote);
        $this->assertStringNotContainsString('btnPrintLocal', $lote);
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

    private function paginasDe(string $pdf): int
    {
        return preg_match_all('#/Type\s*/Page[^s]#', $pdf);
    }

    /** Alto de la hoja en puntos, leido del MediaBox del PDF. */
    private function altoDeLaHoja(string $pdf): float
    {
        $this->assertSame(
            1,
            preg_match('#/MediaBox\s*\[\s*[\d.]+\s+[\d.]+\s+([\d.]+)\s+([\d.]+)#', $pdf, $caja),
            'El PDF del ticket no trae MediaBox.'
        );

        return (float) $caja[2];
    }

    /** Un cajero con su computadora emparejada y el agente encendido. */
    private function usuarioConAgente(): User
    {
        $usuario = $this->usuarioConPermisos();

        $agente = app(PrintAgentPairingService::class)->pair($usuario, $usuario)['agent'];
        $agente->forceFill(['last_seen_at' => now()])->save();

        return $usuario;
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

    private function recibo(string $folio = 'R-001', ?string $observaciones = null): Recibo
    {
        $propietario = Propietario::firstOrCreate(
            ['nombre' => 'El Pato'],
            ['nombre_legal' => 'INMOBILIARIA EL PATO']
        );

        $fraccionamiento = Fraccionamiento::firstOrCreate(
            ['nombre' => 'Rancho El Pato'],
            ['propietario_id' => $propietario->id]
        );

        // La clave sale del folio, no de un azar que puede chocar entre recibos.
        $lote = Lote::create([
            'fraccionamiento_id' => $fraccionamiento->id,
            'manzana' => '5',
            'lote' => $folio,
            'clave' => 'M5-L'.$folio,
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
            'observaciones' => $observaciones ?? 'Pago recibido en efectivo en oficina.',
        ]);
    }
}
