<?php

use App\Http\Controllers\Api\PrintAgentHeartbeatController;
use App\Http\Controllers\Api\PrintAgentJobController;
use App\Http\Controllers\Webhooks\MetaWhatsappWebhookController;
use App\Http\Controllers\Webhooks\OpenWaWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

/*
 * Webhook de OpenWA (comprobantes de pago por WhatsApp).
 *
 * Sin autenticación de sesión a propósito: quien llama es OpenWA, no un usuario.
 * El control de acceso es la firma HMAC que el controlador verifica contra el
 * cuerpo crudo. No agregar middleware que consuma o reescriba el body.
 */
Route::post('/whatsapp/webhook', OpenWaWebhookController::class)
    ->name('webhooks.openwa');

Route::get('/whatsapp/meta/webhook', [MetaWhatsappWebhookController::class, 'verificar'])
    ->name('webhooks.meta.verify');
Route::post('/whatsapp/meta/webhook', [MetaWhatsappWebhookController::class, 'recibir'])
    ->name('webhooks.meta.receive');

/*
 * Agente de impresión local. Quien llama es el agente instalado en la
 * computadora del usuario, no una sesión del navegador: se autentica con su
 * propia clave (bearer) contra el middleware print-agent.
 */
Route::prefix('print-agent')->middleware(['print-agent', 'throttle:print-agent'])->group(function (): void {
    Route::post('/heartbeat', PrintAgentHeartbeatController::class)->name('api.print-agent.heartbeat');
    Route::post('/jobs/claim', [PrintAgentJobController::class, 'claim'])->name('api.print-agent.jobs.claim');
    Route::get('/jobs/{printJob}/document', [PrintAgentJobController::class, 'document'])->name('api.print-agent.jobs.document');
    Route::post('/jobs/{printJob}/complete', [PrintAgentJobController::class, 'complete'])->name('api.print-agent.jobs.complete');
    Route::post('/jobs/{printJob}/fail', [PrintAgentJobController::class, 'fail'])->name('api.print-agent.jobs.fail');
});
