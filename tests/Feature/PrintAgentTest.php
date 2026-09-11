<?php

namespace Tests\Feature;

use App\Enums\PrintingMode;
use App\Enums\PrintJobStatus;
use App\Models\AjusteSistema;
use App\Models\Cliente;
use App\Models\PrintAgent;
use App\Models\Recibo;
use App\Models\User;
use App\Services\Impresion\ModoImpresionSelector;
use App\Services\Impresion\PrintAgentPairingService;
use App\Services\Impresion\PrintJobService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * El agente local vive en la computadora del usuario y jala los tickets por
 * HTTPS. El contrato de esta API es el mismo del POS a propósito: el agente ya
 * compilado funciona aquí sin recompilarse.
 */
class PrintAgentTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function la_clave_solo_se_guarda_hasheada(): void
    {
        $usuario = User::factory()->create();
        $admin = User::factory()->create();

        $resultado = app(PrintAgentPairingService::class)->pair($usuario, $admin, 'Caja principal');

        $token = $resultado['token'];
        $agente = $resultado['agent'];

        $this->assertSame(64, strlen($token));
        $this->assertSame(hash('sha256', $token), $agente->token_hash);
        $this->assertSame(substr($token, -4), $agente->token_last_four);
        $this->assertSame('Caja principal', $agente->name);

        // La clave en claro no queda en ninguna columna.
        $fila = (array) DB::table('print_agents')->where('id', $agente->id)->first();
        $this->assertNotContains($token, $fila);
    }

    #[Test]
    public function generar_una_clave_nueva_invalida_la_anterior(): void
    {
        $usuario = User::factory()->create();
        $admin = User::factory()->create();
        $pairing = app(PrintAgentPairingService::class);

        $primera = $pairing->pair($usuario, $admin)['token'];
        $segunda = $pairing->pair($usuario, $admin)['token'];

        $this->assertSame(1, PrintAgent::query()->count());

        $this->latido($primera)->assertUnauthorized();
        $this->latido($segunda)->assertOk();
    }

    #[Test]
    public function sin_clave_valida_la_api_no_responde(): void
    {
        $this->postJson('/api/print-agent/jobs/claim')->assertUnauthorized();

        $this->postJson('/api/print-agent/jobs/claim', [], [
            'Authorization' => 'Bearer '.str_repeat('a', 64),
        ])->assertUnauthorized();
    }

    #[Test]
    public function el_latido_registra_las_impresoras_del_equipo(): void
    {
        [$agente, $token] = $this->agenteEmparejado();

        $this->latido($token)->assertOk();

        $agente->refresh();

        $this->assertSame('TICKETERA 80MM', $agente->ticket_printer);
        $this->assertContains('TICKETERA 80MM', $agente->available_printers);
        $this->assertTrue($agente->isOnline());
    }

    #[Test]
    public function un_agente_sin_latido_reciente_se_considera_desconectado(): void
    {
        [$agente] = $this->agenteEmparejado();

        $agente->forceFill(['last_seen_at' => now()->subMinutes(5)])->save();

        $this->assertFalse($agente->fresh()->isOnline());
    }

    #[Test]
    public function el_ticket_se_encola_y_el_agente_lo_reclama_e_imprime(): void
    {
        [$agente, $token] = $this->agenteEmparejado();

        $recibo = $this->recibo();

        $job = app(PrintJobService::class)->queueRecibo($agente->user, $recibo);

        $this->assertSame(PrintJobStatus::Pending, $job->status);

        // El agente reclama el trabajo.
        $claim = $this->postJson('/api/print-agent/jobs/claim', [], $this->auth($token))
            ->assertOk()
            ->json('data');

        $this->assertSame($job->id, $claim['id']);
        // El agente compilado usa este valor para elegir la impresora térmica.
        $this->assertSame('sale_ticket', $claim['document_type']);
        $this->assertSame($recibo->folio, $claim['document_label']);

        $this->assertSame(PrintJobStatus::Processing, $job->fresh()->status);

        // Descarga el PDF del ticket.
        $pdf = $this->get($claim['document_path'], $this->auth($token))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->getContent();

        $this->assertStringStartsWith('%PDF', $pdf);

        // Y confirma la impresión.
        $this->postJson("/api/print-agent/jobs/{$job->id}/complete", [], $this->auth($token))
            ->assertOk();

        $this->assertSame(PrintJobStatus::Completed, $job->fresh()->status);
    }

    #[Test]
    public function sin_trabajos_el_claim_responde_vacio(): void
    {
        [, $token] = $this->agenteEmparejado();

        $this->postJson('/api/print-agent/jobs/claim', [], $this->auth($token))
            ->assertOk()
            ->assertJsonPath('data', null)
            ->assertJsonPath('poll_after_seconds', 2);
    }

    #[Test]
    public function un_agente_no_puede_tocar_los_trabajos_de_otro(): void
    {
        [, $tokenA] = $this->agenteEmparejado();
        [$agenteB] = $this->agenteEmparejado('otro@example.test');

        $job = app(PrintJobService::class)->queueRecibo($agenteB->user, $this->recibo());

        $this->get(route('api.print-agent.jobs.document', $job), $this->auth($tokenA))
            ->assertNotFound();

        $this->postJson("/api/print-agent/jobs/{$job->id}/complete", [], $this->auth($tokenA))
            ->assertNotFound();
    }

    #[Test]
    public function un_fallo_devuelve_el_ticket_a_la_cola(): void
    {
        [$agente, $token] = $this->agenteEmparejado();

        $job = app(PrintJobService::class)->queueRecibo($agente->user, $this->recibo());

        $this->postJson('/api/print-agent/jobs/claim', [], $this->auth($token))->assertOk();

        $this->postJson("/api/print-agent/jobs/{$job->id}/fail", [
            'error' => 'La impresora no está disponible.',
        ], $this->auth($token))->assertOk();

        $job->refresh();

        $this->assertSame(PrintJobStatus::Pending, $job->status);
        $this->assertSame(1, $job->attempts);
        $this->assertStringContainsString('no está disponible', $job->last_error);
    }

    #[Test]
    public function no_se_encola_si_el_modo_es_navegador(): void
    {
        [$agente] = $this->agenteEmparejado();

        AjusteSistema::query()->updateOrCreate(
            ['clave' => ModoImpresionSelector::CLAVE],
            ['valor' => PrintingMode::Browser->value],
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no está activada');

        app(PrintJobService::class)->queueRecibo($agente->user, $this->recibo());
    }

    #[Test]
    public function no_se_encola_si_el_agente_esta_desconectado(): void
    {
        [$agente] = $this->agenteEmparejado();

        $agente->forceFill(['last_seen_at' => now()->subHour()])->save();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('desconectado');

        app(PrintJobService::class)->queueRecibo($agente->user->fresh(), $this->recibo());
    }

    #[Test]
    public function un_usuario_sin_equipo_emparejado_no_encola(): void
    {
        $this->modoAgenteLocal();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no tiene un equipo');

        app(PrintJobService::class)->queueRecibo(User::factory()->create(), $this->recibo());
    }

    #[Test]
    public function un_equipo_desactivado_deja_de_imprimir(): void
    {
        [$agente, $token] = $this->agenteEmparejado();

        app(PrintAgentPairingService::class)->revoke($agente);

        $this->latido($token)->assertUnauthorized();
    }

    /** @return array{0: PrintAgent, 1: string} */
    private function agenteEmparejado(?string $correo = null): array
    {
        $this->modoAgenteLocal();

        $usuario = User::factory()->create(
            $correo ? ['email' => $correo] : []
        );

        $resultado = app(PrintAgentPairingService::class)->pair($usuario, $usuario);

        $agente = $resultado['agent'];
        $agente->forceFill(['last_seen_at' => now()])->save();

        return [$agente->fresh()->load('user'), $resultado['token']];
    }

    private function modoAgenteLocal(): void
    {
        AjusteSistema::query()->updateOrCreate(
            ['clave' => ModoImpresionSelector::CLAVE],
            ['valor' => PrintingMode::LocalAgent->value],
        );
    }

    private function latido(string $token)
    {
        return $this->postJson('/api/print-agent/heartbeat', [
            'version' => '1.0.0',
            'ticket_printer' => 'TICKETERA 80MM',
            'quote_printer' => null,
            'available_printers' => ['TICKETERA 80MM', 'Microsoft Print to PDF'],
        ], $this->auth($token));
    }

    /** @return array<string, string> */
    private function auth(string $token): array
    {
        return ['Authorization' => 'Bearer '.$token];
    }

    private function recibo(string $folio = 'R-001'): Recibo
    {
        $ahora = now();

        $cliente = Cliente::create([
            'nombres' => 'Cliente',
            'apellidos' => 'Impresion',
        ]);

        $fraccionamientoId = DB::table('fraccionamientos')->insertGetId([
            'propietario_id' => DB::table('propietarios')->insertGetId([
                'nombre' => 'Propietario prueba', 'created_at' => $ahora, 'updated_at' => $ahora,
            ]),
            'nombre' => 'Fraccionamiento prueba',
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);

        $loteId = DB::table('lotes')->insertGetId([
            'fraccionamiento_id' => $fraccionamientoId,
            'lote' => '1',
            'clave' => 'LOTE-'.$folio,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);

        return Recibo::create([
            'folio' => $folio,
            'fecha' => '2026-11-05',
            'anio' => 2026,
            'cliente_id' => $cliente->id,
            'lote_id' => $loteId,
            'tipos_cobro_id' => DB::table('tipos_cobro')->insertGetId([
                'nombre' => 'MENSUALIDAD', 'created_at' => $ahora, 'updated_at' => $ahora,
            ]),
            'forma_pago_id' => DB::table('formas_pago')->insertGetId([
                'nombre' => 'EFECTIVO', 'created_at' => $ahora, 'updated_at' => $ahora,
            ]),
            'monto' => 200,
        ]);
    }
}
