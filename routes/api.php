<?php

use App\Http\Controllers\Admin\CertificateActionController;
use App\Http\Controllers\Admin\CertificateController;
use App\Http\Controllers\Admin\CertificateStreamController;
use App\Http\Controllers\Admin\CertificateUploadController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\GroupController;
use App\Http\Controllers\Admin\LogController;
use App\Http\Controllers\Admin\NewsletterController;
use App\Http\Controllers\Admin\OrganizationController;
use App\Http\Controllers\Admin\RecipientBulkController;
use App\Http\Controllers\Admin\RecipientController;
use App\Http\Controllers\Admin\RecipientImportController;
use App\Http\Controllers\Admin\RecipientInviteController;
use App\Http\Controllers\Admin\ReleaseNoteController;
use App\Http\Controllers\Admin\TemplateBlockController;
use App\Http\Controllers\Admin\TemplateController;
use App\Http\Controllers\Admin\TemplateDesignerController;
use App\Http\Controllers\Auth\InviteController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Public\ChangelogController;
use App\Http\Controllers\Public\VerificationController;
use App\Http\Controllers\Recipient\PortalController;
use Illuminate\Support\Facades\Route;

// ---------------------------------------------------------------------------
// Public verification (landing page feature)
// ---------------------------------------------------------------------------
Route::get('verify', [VerificationController::class, 'lookup'])->middleware('throttle:20,1');

// Public changelog / current version (shown via the version badge everywhere).
Route::get('changelog', ChangelogController::class);

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
// Shared tenant management — used by both the admin area (/admin, sees all)
// and the organization portal (/org, scoped to the org by the
// BelongsToOrganization global scope). Defined once so the two portals stay
// in lock-step.
// ---------------------------------------------------------------------------
$tenantRoutes = function () {
    // Feature middleware gates org access (admins always pass). Grouped by the
    // feature an organization may be limited to.
    Route::middleware('feature:templates')->group(function () {
        Route::apiResource('templates', TemplateController::class)->scoped(['template' => 'uuid']);
        Route::post('templates/{template:uuid}/duplicate', [TemplateController::class, 'duplicate']);
        Route::put('templates/{template:uuid}/layout', [TemplateDesignerController::class, 'saveLayout']);

        Route::post('templates/{template:uuid}/blocks', [TemplateBlockController::class, 'store']);
        Route::match(['put', 'post'], 'blocks/{block:uuid}', [TemplateBlockController::class, 'update']);
        Route::delete('blocks/{block:uuid}', [TemplateBlockController::class, 'destroy']);
    });

    Route::middleware('feature:recipients')->group(function () {
        Route::post('recipients/bulk', [RecipientBulkController::class, 'store']);
        Route::post('recipients/bulk-action', [RecipientBulkController::class, 'action']);
        Route::get('recipients/bulk-format', [RecipientBulkController::class, 'format']);
        Route::apiResource('recipients', RecipientController::class);
        Route::post('recipients/{recipient:uuid}/invite', [RecipientInviteController::class, 'store']);
    });

    Route::middleware('feature:groups')->group(function () {
        Route::apiResource('groups', GroupController::class);
        Route::post('groups/{group:uuid}/recipients', [GroupController::class, 'addRecipients']);
        Route::delete('groups/{group:uuid}/recipients/{recipient:uuid}', [GroupController::class, 'removeRecipient']);
    });

    Route::middleware('feature:certificates')->group(function () {
        Route::get('templates/{template:uuid}/import-format', [RecipientImportController::class, 'format']);
        Route::post('templates/{template:uuid}/import', [RecipientImportController::class, 'store']);

        Route::get('certificates', [CertificateController::class, 'index']);
        Route::get('certificates/changes', CertificateStreamController::class);
        Route::get('certificates/import-existing-format', [RecipientImportController::class, 'existingFormat']);
        Route::post('certificates/import-existing', [RecipientImportController::class, 'storeExisting']);
        Route::post('certificates/attach-zip', [CertificateUploadController::class, 'storeZip']);
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
    });

    Route::get('dashboard/stats', [DashboardController::class, 'stats']);
};

// ---------------------------------------------------------------------------
// Admin — shared tenant routes (global, sees every org) plus admin-only areas.
// ---------------------------------------------------------------------------
Route::prefix('admin')->middleware(['auth:sanctum', 'role:admin'])->group(function () use ($tenantRoutes) {
    $tenantRoutes();

    Route::get('newsletters', [NewsletterController::class, 'index']);
    Route::post('newsletters', [NewsletterController::class, 'store']);
    Route::get('newsletters/{newsletter:uuid}', [NewsletterController::class, 'show']);

    Route::post('organizations/{organization:uuid}/resend-setup', [OrganizationController::class, 'resendSetup']);
    Route::patch('organizations/{organization:uuid}/settings', [OrganizationController::class, 'updateSettings']);
    Route::get('organizations/{organization:uuid}/stats', [OrganizationController::class, 'stats']);
    Route::apiResource('organizations', OrganizationController::class)->scoped(['organization' => 'uuid']);

    Route::get('logs/mail', [LogController::class, 'mail']);
    Route::get('logs/activity', [LogController::class, 'activity']);

    Route::get('release-notes', [ReleaseNoteController::class, 'index']);
    Route::post('release-notes', [ReleaseNoteController::class, 'store']);
    Route::put('release-notes/{releaseNote:uuid}', [ReleaseNoteController::class, 'update']);
    Route::delete('release-notes/{releaseNote:uuid}', [ReleaseNoteController::class, 'destroy']);
});

// ---------------------------------------------------------------------------
// Organization portal — same management surface, scoped to the org. Must be an
// active organization (EnsureActiveOrganization).
// ---------------------------------------------------------------------------
Route::prefix('org')->middleware(['auth:sanctum', 'role:organization', 'org.active'])->group($tenantRoutes);

// ---------------------------------------------------------------------------
// Recipient portal
// ---------------------------------------------------------------------------
Route::prefix('me')->middleware(['auth:sanctum', 'role:recipient'])->group(function () {
    Route::get('certificates', [PortalController::class, 'certificates']);
    Route::get('certificates/{uuid}', [PortalController::class, 'show']);
    Route::get('certificates/{uuid}/download', [PortalController::class, 'download']);
    Route::put('profile', [PortalController::class, 'updateProfile']);
});
