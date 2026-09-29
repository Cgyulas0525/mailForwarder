<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DeliveryController;
use App\Http\Controllers\Api\ForwardingRuleController;
use App\Http\Controllers\Api\GmailAccountController;
use App\Http\Controllers\Api\PartyController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Middleware\EnsureAdmin;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum', EnsureAdmin::class])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/me', [AuthController::class, 'update']);

    Route::get('/accounts', [GmailAccountController::class, 'index']);
    Route::post('/accounts', [GmailAccountController::class, 'store']);
    Route::get('/accounts/{account}', [GmailAccountController::class, 'show']);
    Route::put('/accounts/{account}', [GmailAccountController::class, 'update']);
    Route::patch('/accounts/{account}/enabled', [GmailAccountController::class, 'enable']);
    Route::post('/accounts/{account}/test', [GmailAccountController::class, 'test']);
    Route::post('/accounts/{account}/sync', [GmailAccountController::class, 'sync']);

    Route::get('/senders', [PartyController::class, 'senders']);
    Route::post('/senders', [PartyController::class, 'storeSender']);
    Route::get('/senders/{sender}', [PartyController::class, 'showSender']);
    Route::put('/senders/{sender}', [PartyController::class, 'updateSender']);
    Route::delete('/senders/{sender}', [PartyController::class, 'destroySender']);

    Route::get('/recipients', [PartyController::class, 'recipients']);
    Route::post('/recipients', [PartyController::class, 'storeRecipient']);
    Route::get('/recipients/{recipient}', [PartyController::class, 'showRecipient']);
    Route::put('/recipients/{recipient}', [PartyController::class, 'updateRecipient']);
    Route::delete('/recipients/{recipient}', [PartyController::class, 'destroyRecipient']);

    Route::get('/rules', [ForwardingRuleController::class, 'index']);
    Route::post('/rules', [ForwardingRuleController::class, 'store']);
    Route::get('/rules/{rule}', [ForwardingRuleController::class, 'show']);
    Route::put('/rules/{rule}', [ForwardingRuleController::class, 'update']);
    Route::delete('/rules/{rule}', [ForwardingRuleController::class, 'destroy']);
    Route::post('/rules/preview', [ForwardingRuleController::class, 'preview']);

    Route::get('/deliveries', [DeliveryController::class, 'index']);
    Route::get('/deliveries/{delivery}', [DeliveryController::class, 'show']);
    Route::post('/deliveries/{delivery}/retry', [DeliveryController::class, 'retry']);
    Route::get('/attachments/{attachment}', [DeliveryController::class, 'attachment']);

    Route::get('/settings/window', [SettingsController::class, 'show']);
    Route::put('/settings/window', [SettingsController::class, 'update']);
});
