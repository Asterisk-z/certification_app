<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Services\CertificateRenderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CertificateViewController extends Controller
{
    public function __construct(private readonly CertificateRenderService $renderer) {}

    /**
     * Public HTML view of a certificate (the uuid acts as an unguessable key).
     */
    public function show(string $uuid): mixed
    {
        $certificate = Certificate::where('uuid', $uuid)->with('template.blocks', 'recipient')->firstOrFail();

        if (! $certificate->first_seen_at) {
            $certificate->forceFill(['first_seen_at' => now()])->save();
        }

        // A manually uploaded file is the credential's real document — it is
        // what gets emailed and downloaded — so show it rather than a render
        // of the template it was filed under.
        if ($certificate->uploaded_file_path && Storage::disk('local')->exists($certificate->uploaded_file_path)) {
            return Storage::disk('local')->response(
                $certificate->uploaded_file_path,
                $certificate->certificate_number.'.pdf'
            );
        }

        // Offline certificates without a template have nothing else to show.
        abort_unless($certificate->certificate_template_id, 404, 'No viewable document for this credential.');

        return response($this->renderer->html($certificate));
    }

    public function download(string $uuid): StreamedResponse|JsonResponse
    {
        $certificate = Certificate::where('uuid', $uuid)->firstOrFail();
        $format = request()->query('format') === 'png' ? 'png' : 'pdf';

        try {
            $path = $this->renderer->downloadPath($certificate, $format);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'The file is not available yet.'], 422);
        }

        return Storage::disk('local')->download($path, $certificate->certificate_number.'.'.$format);
    }
}
