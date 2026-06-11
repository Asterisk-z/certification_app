<?php

namespace App\Http\Controllers\Recipient;

use App\Enums\CertificateStatus;
use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Services\CertificateRenderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PortalController extends Controller
{
    /**
     * Certificates that belong to the signed-in recipient. Only states a
     * holder should see — internal pipeline states stay hidden.
     */
    public function certificates(Request $request): JsonResponse
    {
        $recipient = $request->user()->recipient;

        if (! $recipient) {
            return response()->json(['data' => [], 'total' => 0]);
        }

        $query = Certificate::where('recipient_id', $recipient->id)
            ->whereIn('status', [CertificateStatus::Sent, CertificateStatus::Expired, CertificateStatus::Revoked, CertificateStatus::Renewed])
            ->with('template:id,uuid,name,code,background_image')
            ->latest('issue_date');

        if ($search = trim((string) $request->query('q'))) {
            $query->search($search);
        }

        return response()->json($query->paginate((int) $request->query('per_page', 12)));
    }

    public function show(Request $request, string $uuid): JsonResponse
    {
        $certificate = $this->ownCertificate($request, $uuid);

        return response()->json($certificate->load('template:id,uuid,name,code,background_image'));
    }

    public function download(Request $request, string $uuid): StreamedResponse|JsonResponse
    {
        $certificate = $this->ownCertificate($request, $uuid);
        $format = $request->query('format') === 'png' ? 'png' : 'pdf';

        try {
            $path = app(CertificateRenderService::class)->downloadPath($certificate, $format);
        } catch (\Throwable) {
            return response()->json(['message' => 'The file is not available yet.'], 422);
        }

        return Storage::disk('local')->download($path, $certificate->certificate_number.'.'.$format);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['nullable', 'confirmed', Password::min(8)],
            'current_password' => ['required_with:password', 'current_password'],
        ]);

        $user->name = $validated['name'];

        if (! empty($validated['password'])) {
            $user->password = $validated['password'];
        }

        $user->save();

        if ($recipient = $user->recipient) {
            $recipient->update(['full_name' => $validated['name']]);
        }

        return response()->json(['user' => $user->fresh()->load('recipient')]);
    }

    private function ownCertificate(Request $request, string $uuid): Certificate
    {
        $recipient = $request->user()->recipient;
        abort_unless($recipient, 404);

        return Certificate::where('uuid', $uuid)
            ->where('recipient_id', $recipient->id)
            ->whereIn('status', [CertificateStatus::Sent, CertificateStatus::Expired, CertificateStatus::Revoked, CertificateStatus::Renewed])
            ->firstOrFail();
    }
}
