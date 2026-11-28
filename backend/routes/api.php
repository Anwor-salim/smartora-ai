<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CompanyChannelsController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

// Public Authentication
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});

// Inbound WhatsApp Webhooks (n8n / API & Direct WAHA Auto-Reply)
Route::post('/webhook/whatsapp', [WebhookController::class, 'whatsapp']);
Route::post('/webhook/waha', [WebhookController::class, 'waha']);

// Official Meta WhatsApp Cloud API Webhooks
Route::get('/webhook/meta', [WebhookController::class, 'verifyMetaWebhook']);
Route::post('/webhook/meta', [WebhookController::class, 'handleMetaWebhook']);
Route::get('/webhook/meta/{company}', [WebhookController::class, 'verifyMetaWebhook']);
Route::post('/webhook/meta/{company}', [WebhookController::class, 'handleMetaWebhook']);

// Official Meta Instagram Messaging & Comments Webhooks
Route::get('/webhook/instagram', [WebhookController::class, 'verifyInstagramWebhook']);
Route::post('/webhook/instagram', [WebhookController::class, 'handleInstagramWebhook']);
Route::get('/webhook/instagram/{company}', [WebhookController::class, 'verifyInstagramWebhook']);
Route::post('/webhook/instagram/{company}', [WebhookController::class, 'handleInstagramWebhook']);

// Official Meta Facebook Messenger & Page Comments Webhooks
Route::get('/webhook/messenger', [WebhookController::class, 'verifyMessengerWebhook']);
Route::post('/webhook/messenger', [WebhookController::class, 'handleMessengerWebhook']);
Route::get('/webhook/messenger/{company}', [WebhookController::class, 'verifyMessengerWebhook']);
Route::post('/webhook/messenger/{company}', [WebhookController::class, 'handleMessengerWebhook']);

// Public read of active companies list for simulator / dropdown
Route::get('/companies/public', function () {
    return response()->json([
        'companies' => \App\Models\Company::select('id', 'name', 'status', 'whatsapp_number', 'subscription_expires_at')
            ->get()
            ->map(function ($c) {
                return [
                    'id' => $c->id,
                    'name' => $c->name,
                    'is_active' => $c->isSubscriptionActive(),
                    'whatsapp_number' => $c->whatsapp_number,
                ];
            })
    ]);
});

// Protected Routes
Route::middleware('auth:sanctum')->group(function () {
    // Current User
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Live AI Test Chat from Dashboard
    Route::post('/ai/test-chat', [WebhookController::class, 'testChat']);

    // Direct alias for companies list
    Route::get('/companies', [AdminController::class, 'companies']);

    // Super Admin Routes
    Route::prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboard']);
        Route::get('/companies', [AdminController::class, 'companies']);
        Route::post('/companies', [AdminController::class, 'createCompany']);
        Route::post('/companies/{id}/toggle-status', [AdminController::class, 'toggleStatus']);
        Route::post('/companies/{id}/extend-subscription', [AdminController::class, 'extendSubscription']);
        Route::delete('/companies/{id}', [AdminController::class, 'deleteCompany']);
        Route::get('/notifications', [AdminController::class, 'notifications']);
        Route::post('/notifications/{id}/read', [AdminController::class, 'markNotificationRead']);
    });

    // Company Portal Routes
    Route::prefix('company')->group(function () {
        Route::get('/dashboard', [CompanyController::class, 'dashboard']);
        Route::post('/switch', [CompanyController::class, 'switchCompany']);

        Route::get('/profile', [CompanyController::class, 'profile']);
        Route::put('/profile', [CompanyController::class, 'updateProfile']);

        Route::get('/documents', [CompanyController::class, 'documents']);
        Route::post('/documents/upload', [CompanyController::class, 'uploadDocument']);
        Route::post('/documents/{id}/reparse', [CompanyController::class, 'reparseDocument']);
        Route::delete('/documents/{id}', [CompanyController::class, 'deleteDocument']);

        Route::get('/appointments', [CompanyController::class, 'appointments']);
        Route::get('/appointments/{id}', [CompanyController::class, 'appointmentDetail']);
        Route::post('/appointments/{id}/approve', [CompanyController::class, 'approveAppointment']);
        Route::post('/appointments/{id}/reject', [CompanyController::class, 'rejectAppointment']);
        Route::post('/appointments/{id}/cancel', [CompanyController::class, 'cancelAppointment']);
        Route::post('/appointments/sync-sheets', [CompanyController::class, 'syncAppointmentSheets']);

        Route::get('/booking-settings', [CompanyController::class, 'bookingSettings']);
        Route::put('/booking-settings', [CompanyController::class, 'updateBookingSettings']);
        Route::post('/booking-settings/sync-headers', [CompanyController::class, 'syncBookingHeaders']);

        Route::get('/conversations', [CompanyController::class, 'conversations']);
        Route::get('/conversations/{id}', [CompanyController::class, 'conversationDetail']);
        Route::post('/conversations/{id}/toggle-pause', [CompanyController::class, 'toggleConversationPause']);
        Route::post('/conversations/{id}/reset-context', [CompanyController::class, 'resetConversationContext']);

        Route::post('/simulator/send', [CompanyController::class, 'simulatorSend']);
        Route::post('/simulator/reset', [CompanyController::class, 'simulatorReset']);

        Route::prefix('channels')->group(function () {
            Route::get('/whatsapp', [CompanyChannelsController::class, 'whatsappStatus']);
            Route::post('/whatsapp/provider', [CompanyChannelsController::class, 'switchWhatsappProvider']);
            Route::post('/whatsapp/qr', [CompanyChannelsController::class, 'generateWhatsappQr']);
            Route::post('/whatsapp/check', [CompanyChannelsController::class, 'checkWhatsappConnection']);
            Route::post('/whatsapp/disconnect', [CompanyChannelsController::class, 'disconnectWhatsapp']);
            Route::put('/whatsapp/numbers', [CompanyChannelsController::class, 'saveWhatsappNumbers']);
            Route::post('/whatsapp/meta', [CompanyChannelsController::class, 'saveMetaCredentials']);
            Route::post('/whatsapp/test', [CompanyChannelsController::class, 'sendWhatsappTestMessage']);

            Route::get('/instagram', [CompanyChannelsController::class, 'instagramStatus']);
            Route::post('/instagram/credentials', [CompanyChannelsController::class, 'saveInstagramCredentials']);
            Route::post('/instagram/disconnect', [CompanyChannelsController::class, 'disconnectInstagram']);
            Route::put('/instagram/comments', [CompanyChannelsController::class, 'saveInstagramComments']);
            Route::post('/instagram/simulate', [CompanyChannelsController::class, 'simulateInstagramReply']);

            Route::get('/messenger', [CompanyChannelsController::class, 'messengerStatus']);
            Route::post('/messenger/credentials', [CompanyChannelsController::class, 'saveMessengerCredentials']);
            Route::post('/messenger/disconnect', [CompanyChannelsController::class, 'disconnectMessenger']);
            Route::put('/messenger/comments', [CompanyChannelsController::class, 'saveMessengerComments']);
            Route::post('/messenger/simulate', [CompanyChannelsController::class, 'simulateMessengerReply']);
        });
    });
});