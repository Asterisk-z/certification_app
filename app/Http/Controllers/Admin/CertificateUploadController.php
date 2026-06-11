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

    /**
     * Bulk-attach scanned certificate files from a ZIP: each PDF inside is
     * matched to a certificate by filename vs certificate number (case and
     * separator insensitive, so HSE-2023-0042.pdf matches HSE/2023/0042).
     */
    public function storeZip(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:zip', 'max:102400'],
        ]);

        $normalize = fn (string $value): string => trim(preg_replace('/[^A-Z0-9]+/', '-', strtoupper($value)), '-');

        $certificates = Certificate::withTrashed()->pluck('uuid', 'certificate_number')
            ->mapWithKeys(fn ($uuid, $number) => [$normalize($number) => $number]);

        $zip = new \ZipArchive;
        if ($zip->open($request->file('file')->getRealPath()) !== true) {
            return response()->json(['message' => 'Could not read the ZIP file.'], 422);
        }

        $attached = [];
        $unmatched = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);

            if (str_ends_with($name, '/') || strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'pdf') {
                continue;
            }

            $key = $normalize(pathinfo(basename($name), PATHINFO_FILENAME));
            $number = $certificates[$key] ?? null;

            if (! $number) {
                $unmatched[] = basename($name);

                continue;
            }

            $certificate = Certificate::withTrashed()->firstWhere('certificate_number', $number);

            if ($certificate->uploaded_file_path) {
                Storage::disk('local')->delete($certificate->uploaded_file_path);
            }

            $path = "certificates/uploads/{$certificate->uuid}-manual.pdf";
            Storage::disk('local')->put($path, $zip->getFromIndex($i));
            $certificate->forceFill(['uploaded_file_path' => $path, 'is_manual' => true])->save();

            $attached[] = $number;
        }

        $zip->close();

        activity()->causedBy($request->user())
            ->withProperties(['attached' => count($attached), 'unmatched' => count($unmatched)])
            ->log('certificate_files_zip_attached');

        return response()->json([
            'message' => count($attached).' file(s) attached.'.(count($unmatched) ? ' '.count($unmatched).' file(s) had no matching credential.' : ''),
            'attached' => $attached,
            'unmatched' => $unmatched,
        ]);
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
