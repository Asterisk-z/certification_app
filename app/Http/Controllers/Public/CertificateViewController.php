<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Services\CertificateRenderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CertificateViewController extends Controller
{
    public function __construct(private readonly CertificateRenderService $renderer) {}

    /**
     * Public HTML view of a certificate (the uuid acts as an unguessable key).
     */
    public function show(string $uuid): Response
    {
        $certificate = Certificate::where('uuid', $uuid)->with('template.blocks', 'recipient')->firstOrFail();

        if (! $certificate->first_seen_at) {
            $certificate->forceFill(['first_seen_at' => now()])->save();
        }

        return response($this->renderer->html($certificate));
    }

    public function download(string $uuid): StreamedResponse|JsonResponse
    {
        $certificate = Certificate::where('uuid', $uuid)->firstOrFail();
        $path = $certificate->uploaded_file_path ?: $certificate->pdf_path;

        if (! $path || ! Storage::disk('local')->exists($path)) {
            try {
                $path = $this->renderer->pdf($certificate);
            } catch (\Throwable $e) {
                return response()->json(['message' => 'The PDF is not available yet.'], 422);
            }
        }

        return Storage::disk('local')->download($path, $certificate->certificate_number.'.pdf');
    }
}
