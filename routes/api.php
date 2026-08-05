<?php

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
