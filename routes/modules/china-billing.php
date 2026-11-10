<?php

use App\Http\Controllers\ChinaBilling\ChinaBankAccountController;
use App\Http\Controllers\ChinaBilling\ChinaBillingController;
use App\Http\Controllers\ChinaBilling\ChinaBillingDocumentController;
use App\Http\Controllers\ChinaBilling\ChinaBillingPartnerController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'china-billing', 'middleware' => 'jwt.auth'], function () {
    // Datos bancarios (los ven China y el socio; edita China)
    Route::get('bank-account', [ChinaBankAccountController::class, 'show']);
    Route::put('bank-account', [ChinaBankAccountController::class, 'update']);

    // Almacén China
    Route::get('operations', [ChinaBillingController::class, 'operations']);
    Route::post('operations', [ChinaBillingController::class, 'storeOperation']);
    Route::post('operations/{id}', [ChinaBillingController::class, 'updateOperation'])->whereNumber('id');
    Route::delete('operations/{id}', [ChinaBillingController::class, 'destroyOperation'])->whereNumber('id');
    Route::delete('drafts/{containerId}', [ChinaBillingController::class, 'dismissDraft'])->whereNumber('containerId');
    Route::post('operations/{id}/transfer-difference', [ChinaBillingController::class, 'transferDifference'])->whereNumber('id');
    Route::post('operations/{id}/undo-transfer-difference', [ChinaBillingController::class, 'undoTransferDifference'])->whereNumber('id');
    Route::post('operations/{id}/apply-credit', [ChinaBillingController::class, 'applyCredit'])->whereNumber('id');
    Route::delete('credit-applications/{id}', [ChinaBillingController::class, 'undoCredit'])->whereNumber('id');

    Route::get('payments', [ChinaBillingController::class, 'payments']);
    Route::post('payments', [ChinaBillingController::class, 'storePayment']);
    Route::post('payments/{id}', [ChinaBillingController::class, 'updatePayment'])->whereNumber('id');
    Route::post('payments/{id}/confirm', [ChinaBillingController::class, 'confirmPayment'])->whereNumber('id');
    Route::post('payments/{id}/unconfirm', [ChinaBillingController::class, 'unconfirmPayment'])->whereNumber('id');
    Route::post('payments/{id}/cash', [ChinaBillingController::class, 'cashPayment'])->whereNumber('id');
    Route::post('payments/{id}/uncash', [ChinaBillingController::class, 'uncashPayment'])->whereNumber('id');
    Route::delete('payments/{id}', [ChinaBillingController::class, 'destroyPayment'])->whereNumber('id');

    Route::get('organizations/{organizationId}/pending-operations', [ChinaBillingController::class, 'pendingOperations'])->whereNumber('organizationId');
    // Documentos PDF (siempre del backend) con historial de emisiones
    Route::get('operations/{operationId}/documents', [ChinaBillingDocumentController::class, 'invoiceHistory'])->whereNumber('operationId');
    Route::post('operations/{operationId}/documents', [ChinaBillingDocumentController::class, 'issueInvoice'])->whereNumber('operationId');
    Route::get('organizations/{organizationId}/balance-documents', [ChinaBillingDocumentController::class, 'balanceHistory'])->whereNumber('organizationId');
    Route::post('organizations/{organizationId}/balance-documents', [ChinaBillingDocumentController::class, 'issueBalance'])->whereNumber('organizationId');

    // Socio
    Route::prefix('partner')->group(function () {
        Route::get('invoices', [ChinaBillingPartnerController::class, 'invoices']);
        Route::get('payable-invoices', [ChinaBillingPartnerController::class, 'payableInvoices']);
        Route::get('payments', [ChinaBillingPartnerController::class, 'myPayments']);
        Route::post('payments', [ChinaBillingPartnerController::class, 'store']);
        Route::post('payments/{id}', [ChinaBillingPartnerController::class, 'update'])->whereNumber('id');
        Route::delete('payments/{id}', [ChinaBillingPartnerController::class, 'destroy'])->whereNumber('id');
        Route::get('operations/{operationId}/documents', [ChinaBillingDocumentController::class, 'invoiceHistory'])->whereNumber('operationId')->defaults('scope', 'partner');
        Route::post('operations/{operationId}/documents', [ChinaBillingDocumentController::class, 'issueInvoice'])->whereNumber('operationId')->defaults('scope', 'partner');
        Route::get('balance-documents', [ChinaBillingDocumentController::class, 'balanceHistory'])->defaults('scope', 'partner');
        Route::post('balance-documents', [ChinaBillingDocumentController::class, 'issueBalance'])->defaults('scope', 'partner');
    });
});
