<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CertificateUploadController extends Controller
{
    /**
     * Attach a manually produced certificate file (PDF) to a certificate.
     * The uploaded file then takes precedence over the rendered PDF for
     * downloads and email attachments.
     */
    public function store(Request $request, string $uuid): JsonResponse
    {
        $certificate = Certificate::withTrashed()->where('uuid', $uuid)->firstOrFail();

        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf', 'max:20480'],
        ]);

        if ($certificate->uploaded_file_path) {
            Storage::disk('local')->delete($certificate->uploaded_file_path);
        }

        $path = $request->file('file')->storeAs(
            'certificates/uploads',
            $certificate->uuid.'-manual.pdf',
            'local'
        );

        $certificate->forceFill(['uploaded_file_path' => $path, 'is_manual' => true])->save();

        activity()->performedOn($certificate)->causedBy($request->user())->log('certificate_file_uploaded');

        return response()->json($certificate->fresh()->load('recipient', 'template'));
    }

    public function destroy(Request $request, string $uuid): JsonResponse
    {
        $certificate = Certificate::withTrashed()->where('uuid', $uuid)->firstOrFail();

        if ($certificate->uploaded_file_path) {
            Storage::disk('local')->delete($certificate->uploaded_file_path);
            $certificate->forceFill(['uploaded_file_path' => null])->save();
        }

        return response()->json($certificate->fresh());
    }
}
