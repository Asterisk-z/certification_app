<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use Illuminate\Http\JsonResponse;

/**
 * Lightweight change-poll for the admin credential list. Returns a cheap
 * signature of the credentials table instantly; the frontend polls it every
 * few seconds and refetches only when the signature changes.
 *
 * This replaces an earlier SSE stream that held a PHP worker open per tab —
 * with the production server's small worker pool, a few open tabs could
 * exhaust the workers and hang the page. A fast, stateless poll never holds
 * a connection open.
 */
class CertificateStreamController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $row = Certificate::withTrashed()
            ->selectRaw('count(*) as total, max(updated_at) as latest')
            ->first();

        return response()->json([
            'signature' => $row->total.'|'.$row->latest,
        ]);
    }
}
