<?php

use App\Http\Controllers\Admin\CertificateActionController;
use App\Http\Controllers\Admin\CertificateController;
use App\Http\Controllers\Admin\CertificateUploadController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\GroupController;
use App\Http\Controllers\Admin\LogController;
use App\Http\Controllers\Admin\NewsletterController;
use App\Http\Controllers\Admin\RecipientBulkController;
use App\Http\Controllers\Admin\RecipientController;
use App\Http\Controllers\Admin\RecipientImportController;
use App\Http\Controllers\Admin\RecipientInviteController;
use App\Http\Controllers\Admin\TemplateBlockController;
use App\Http\Controllers\Admin\TemplateController;
use App\Http\Controllers\Admin\TemplateDesignerController;
use App\Http\Controllers\Auth\InviteController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Public\VerificationController;
use App\Http\Controllers\Recipient\PortalController;
use Illuminate\Support\Facades\Route;

// ---------------------------------------------------------------------------
// Public verification (landing page feature)
// ---------------------------------------------------------------------------
Route::get('verify', [VerificationController::class, 'lookup'])->middleware('throttle:20,1');

// ---------------------------------------------------------------------------
// Public auth
// ---------------------------------------------------------------------------
Route::prefix('auth')->group(function () {
    Route::post('login', [LoginController::class, 'login'])->middleware('throttle:10,1');
    Route::post('forgot-password', [PasswordResetController::class, 'forgot'])->middleware('throttle:5,1');
    Route::post('reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:5,1');

    Route::get('invite/{recipient:uuid}', [InviteController::class, 'show'])
        ->middleware('signed')->name('invite.show');
    Route::post('invite/{recipient:uuid}', [InviteController::class, 'store'])
        ->middleware(['signed', 'throttle:5,1'])->name('invite.store');
});

// ---------------------------------------------------------------------------
// Authenticated (shared)
// ---------------------------------------------------------------------------
Route::middleware('auth:sanctum')->group(function () {
    Route::get('auth/me', [LoginController::class, 'me']);
    Route::post('auth/logout', [LoginController::class, 'logout']);

    Route::get('notifications', [NotificationController::class, 'index']);
    Route::post('notifications/read-all', [NotificationController::class, 'readAll']);
    Route::post('notifications/{id}/read', [NotificationController::class, 'read']);
});

// ---------------------------------------------------------------------------
// Admin
// ---------------------------------------------------------------------------
Route::prefix('admin')->middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::apiResource('templates', TemplateController::class)->scoped(['template' => 'uuid']);
    Route::post('templates/{template:uuid}/duplicate', [TemplateController::class, 'duplicate']);
    Route::put('templates/{template:uuid}/layout', [TemplateDesignerController::class, 'saveLayout']);

    Route::post('templates/{template:uuid}/blocks', [TemplateBlockController::class, 'store']);
    Route::match(['put', 'post'], 'blocks/{block:uuid}', [TemplateBlockController::class, 'update']);
    Route::delete('blocks/{block:uuid}', [TemplateBlockController::class, 'destroy']);

    Route::post('recipients/bulk', [RecipientBulkController::class, 'store']);
    Route::get('recipients/bulk-format', [RecipientBulkController::class, 'format']);
    Route::apiResource('recipients', RecipientController::class);
    Route::apiResource('groups', GroupController::class);
    Route::post('groups/{group:uuid}/recipients', [GroupController::class, 'addRecipients']);
    Route::delete('groups/{group:uuid}/recipients/{recipient:uuid}', [GroupController::class, 'removeRecipient']);

    Route::get('templates/{template:uuid}/import-format', [RecipientImportController::class, 'format']);
    Route::post('templates/{template:uuid}/import', [RecipientImportController::class, 'store']);

    Route::get('certificates', [CertificateController::class, 'index']);
    Route::post('certificates/manual', [CertificateController::class, 'storeManual']);
    Route::post('certificates/bulk', [CertificateActionController::class, 'bulk']);
    Route::post('templates/{template:uuid}/send', [CertificateController::class, 'send']);
    Route::get('certificates/{uuid}', [CertificateController::class, 'show']);
    Route::get('certificates/{uuid}/download', [CertificateController::class, 'download']);
    Route::delete('certificates/{uuid}', [CertificateController::class, 'destroy']);
    Route::post('certificates/{uuid}/revoke', [CertificateActionController::class, 'revoke']);
    Route::post('certificates/{uuid}/unrevoke', [CertificateActionController::class, 'unrevoke']);
    Route::post('certificates/{uuid}/resend', [CertificateActionController::class, 'resend']);
    Route::post('certificates/{uuid}/renew', [CertificateActionController::class, 'renew']);
    Route::post('certificates/{uuid}/restore', [CertificateActionController::class, 'restore']);
    Route::post('certificates/{uuid}/upload', [CertificateUploadController::class, 'store']);
    Route::delete('certificates/{uuid}/upload', [CertificateUploadController::class, 'destroy']);

    Route::post('recipients/{recipient:uuid}/invite', [RecipientInviteController::class, 'store']);

    Route::get('newsletters', [NewsletterController::class, 'index']);
    Route::post('newsletters', [NewsletterController::class, 'store']);
    Route::get('newsletters/{newsletter:uuid}', [NewsletterController::class, 'show']);

    Route::get('logs/mail', [LogController::class, 'mail']);
    Route::get('logs/activity', [LogController::class, 'activity']);
    Route::get('dashboard/stats', [DashboardController::class, 'stats']);
});

// ---------------------------------------------------------------------------
// Recipient portal
// ---------------------------------------------------------------------------
Route::prefix('me')->middleware(['auth:sanctum', 'role:recipient'])->group(function () {
    Route::get('certificates', [PortalController::class, 'certificates']);
    Route::get('certificates/{uuid}', [PortalController::class, 'show']);
    Route::get('certificates/{uuid}/download', [PortalController::class, 'download']);
    Route::put('profile', [PortalController::class, 'updateProfile']);
});
