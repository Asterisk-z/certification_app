<?php

namespace App\Http\Controllers\Public;

use App\Enums\CertificateStatus;
use App\Http\Controllers\Controller;
use App\Models\Certificate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VerificationController extends Controller
{
    public function lookup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'number' => ['required', 'string', 'max:80'],
        ]);

        $certificate = Certificate::withTrashed()
            ->where('certificate_number', trim($validated['number']))
            ->with(['recipient:id,full_name', 'template:id,name,code'])
            ->first();

        activity()->withProperties([
            'number' => $validated['number'],
            'found' => (bool) $certificate,
            'ip' => $request->ip(),
        ])->log('certificate_verification_lookup');

        if (! $certificate || $certificate->trashed() || in_array($certificate->status, [CertificateStatus::Pending, CertificateStatus::Queued, CertificateStatus::Failed, CertificateStatus::Cancelled], true)) {
            return response()->json([
                'result' => 'not_found',
                'message' => 'No valid certificate was found for that number.',
            ], 404);
        }

        $result = match (true) {
            $certificate->status === CertificateStatus::Revoked => 'revoked',
            $certificate->status === CertificateStatus::Expired || $certificate->isExpired() => 'expired',
            $certificate->status === CertificateStatus::Renewed => 'renewed',
            default => 'valid',
        };

        return response()->json([
            'result' => $result,
            'certificate' => [
                'number' => $certificate->certificate_number,
                'holder' => $certificate->recipient->full_name,
                'template' => $certificate->displayName() ?? 'Certificate',
                'issue_date' => $certificate->issue_date->format('Y-m-d'),
                'expiry_date' => $certificate->expiry_date?->format('Y-m-d'),
                'revoked_at' => $certificate->revoked_at?->format('Y-m-d'),
                // Revoked certificates are not viewable publicly.
                'view_url' => $certificate->status === CertificateStatus::Revoked
                    ? null
                    : url('/c/'.$certificate->uuid),
            ],
        ]);
    }
}
