<?php

use App\Http\Controllers\WhatsappInbox\MetaInboxWebhookController;
use App\Http\Controllers\WhatsappInbox\WhatsappInboxController;
use Illuminate\Support\Facades\Route;

Route::match(['get', 'post'], '/webhooks/meta/whatsapp-inbox', function () {
    $request = request();
    if ($request->isMethod('get')) {
        return app(MetaInboxWebhookController::class)->verify($request);
    }

    return app(MetaInboxWebhookController::class)->receive($request);
});

Route::group([
    'prefix' => 'whatsapp-inbox',
    'middleware' => ['jwt.auth', 'role.coordinacion'],
], function () {
    Route::get('/session', [WhatsappInboxController::class, 'session']);
    Route::get('/conversations', [WhatsappInboxController::class, 'conversations']);
    Route::get('/conversations/{id}/messages', [WhatsappInboxController::class, 'messages']);
    Route::post('/conversations/{id}/messages', [WhatsappInboxController::class, 'sendMessage']);
    Route::post('/conversations/{id}/templates', [WhatsappInboxController::class, 'sendTemplate']);
    Route::patch('/conversations/{id}/assign', [WhatsappInboxController::class, 'assign']);
    Route::patch('/conversations/{id}/read', [WhatsappInboxController::class, 'markRead']);
    Route::get('/templates', [WhatsappInboxController::class, 'templates']);
    Route::get('/users/assignable', [WhatsappInboxController::class, 'assignableUsers']);
});
