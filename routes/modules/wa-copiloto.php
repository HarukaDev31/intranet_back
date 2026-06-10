<?php

use App\Http\Controllers\WaCopiloto\MetaCopilotoWebhookController;
use App\Http\Controllers\WaCopiloto\WaCopilotoController;
use Illuminate\Support\Facades\Route;

Route::match(['get', 'post'], '/webhooks/meta/whatsapp-copiloto', function () {
    $request = request();
    if ($request->isMethod('get')) {
        return app(MetaCopilotoWebhookController::class)->verify($request);
    }

    return app(MetaCopilotoWebhookController::class)->receive($request);
});

Route::group([
    'prefix' => 'wa-copiloto',
    'middleware' => ['jwt.auth', 'role.copiloto_wa'],
], function () {
    Route::get('/sessions', [WaCopilotoController::class, 'sessions']);
    Route::get('/session', [WaCopilotoController::class, 'session']);
    Route::post('/contacts/sync', [WaCopilotoController::class, 'syncContacts']);
    Route::post('/contacts/{contactId}/open', [WaCopilotoController::class, 'openContactConversation']);
    Route::get('/conversations', [WaCopilotoController::class, 'conversations']);
    Route::post('/conversations', [WaCopilotoController::class, 'storeConversation']);
    Route::get('/conversations/{id}/messages', [WaCopilotoController::class, 'messages']);
    Route::post('/conversations/{id}/messages', [WaCopilotoController::class, 'sendMessage']);
    Route::post('/conversations/{id}/scheduled-messages', [WaCopilotoController::class, 'scheduleMessage']);
    Route::post('/conversations/{id}/templates', [WaCopilotoController::class, 'sendTemplate']);
    Route::patch('/conversations/{id}/assign', [WaCopilotoController::class, 'assign']);
    Route::patch('/conversations/{id}/contact-name', [WaCopilotoController::class, 'renameContact']);
    Route::patch('/conversations/{id}/read', [WaCopilotoController::class, 'markRead']);
    Route::get('/conversations/{id}/suggestion-usages', [WaCopilotoController::class, 'suggestionUsages']);
    Route::post('/conversations/{id}/suggestion-usages', [WaCopilotoController::class, 'recordSuggestionUsage']);
    Route::get('/templates', [WaCopilotoController::class, 'templates']);
    Route::get('/users/assignable', [WaCopilotoController::class, 'assignableUsers']);
    Route::get('/pipeline/stages', [WaCopilotoController::class, 'pipelineStages']);
    Route::post('/pipeline/stages', [WaCopilotoController::class, 'pipelineCreateStage']);
    Route::patch('/pipeline/stages/reorder', [WaCopilotoController::class, 'pipelineReorderStages']);
    Route::get('/pipeline/kanban', [WaCopilotoController::class, 'pipelineKanban']);
    Route::get('/pipeline/kpis', [WaCopilotoController::class, 'pipelineKpis']);
    Route::patch('/conversations/{id}/pipeline-stage', [WaCopilotoController::class, 'pipelineTransition']);
    Route::get('/conversations/{id}/assignment-history', [WaCopilotoController::class, 'pipelineAssignmentHistory']);
    Route::get('/conversations/{id}/pipeline-history', [WaCopilotoController::class, 'pipelineTransitionHistory']);
});
