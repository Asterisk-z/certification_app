<?php

use App\Http\Controllers\Public\CertificateViewController;
use Illuminate\Support\Facades\Route;

// Public certificate view + download (QR / email targets).
Route::get('/c/{uuid}', [CertificateViewController::class, 'show'])->middleware('throttle:30,1');
Route::get('/c/{uuid}/download', [CertificateViewController::class, 'download'])->middleware('throttle:30,1');

// SPA catch-all: every non-API, non-storage route renders the Vue app.
Route::get('/{any?}', function () {
    return view('app');
})->where('any', '^(?!api|storage|sanctum|build|up$|c/).*$');
