<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ExistingCertificatesFormatExport;
use App\Exports\ImportFormatExport;
use App\Http\Controllers\Controller;
use App\Imports\ExistingCertificatesImport;
use App\Imports\RecipientsImport;
use App\Models\CertificateTemplate;
use App\Models\Group;
use App\Notifications\AdminAlertNotification;
use App\Services\CertificateNumberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\HeadingRowImport;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RecipientImportController extends Controller
{
    /**
     * Download the per-template Excel format. mode=existing returns the
     * format for registering certificates already issued offline (keeps the
     * original number, explicit expiry).
     */
    public function format(Request $request, CertificateTemplate $template): BinaryFileResponse
    {
        if ($request->query('mode') === 'existing') {
            return Excel::download(
                new ExistingCertificatesFormatExport($template),
                strtolower($template->code).'-existing-certificates-format.xlsx'
            );
        }

        return Excel::download(
            new ImportFormatExport($template),
            strtolower($template->code).'-import-format.xlsx'
        );
    }

    /**
     * Import an Excel file. mode=new (default) creates pending certificates
     * with generated numbers for sending; mode=existing registers offline
     * certificates as already issued — original numbers, no emails.
     */
    public function store(Request $request, CertificateTemplate $template, CertificateNumberService $numbers): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
            'group_uuid' => ['nullable', 'uuid', 'exists:groups,uuid'],
            'mode' => ['nullable', 'in:new,existing'],
        ]);

        $existing = ($validated['mode'] ?? 'new') === 'existing';

        // Validate headings before touching any data. Optional date columns
        // of the existing-mode format are not required to be present.
        $expected = $existing
            ? array_diff(ExistingCertificatesFormatExport::headingsFor($template), ['completion_date', 'expiry_date'])
            : ImportFormatExport::headingsFor($template);
        $found = collect((new HeadingRowImport)->toArray($validated['file'])[0][0] ?? [])
            ->filter()->map(fn ($h) => strtolower(trim((string) $h)))->all();
        $missing = array_values(array_diff($expected, $found));

        if ($missing) {
            return response()->json([
                'message' => 'The file is missing required columns: '.implode(', ', $missing),
                'missing_columns' => $missing,
            ], 422);
        }

        $group = isset($validated['group_uuid'])
            ? Group::where('uuid', $validated['group_uuid'])->firstOrFail()
            : null;

        $import = $existing
            ? new ExistingCertificatesImport($template, $group)
            : new RecipientsImport($template, $group, $numbers);
        Excel::import($import, $validated['file']);

        activity()->performedOn($template)->causedBy($request->user())
            ->withProperties(['mode' => $existing ? 'existing' : 'new', 'created' => $import->created, 'failed' => count($import->failures)])
            ->log($existing ? 'existing_certificates_imported' : 'recipients_imported');

        $request->user()->notify(new AdminAlertNotification(
            'import_completed',
            'Import completed',
            "{$import->created} certificate(s) ".($existing ? 'registered' : 'created')." for “{$template->name}”".
                (count($import->failures) ? ', '.count($import->failures).' row(s) skipped.' : '.'),
        ));

        return response()->json([
            'message' => $existing
                ? "{$import->created} existing certificate(s) registered — verifiable immediately, no emails sent."
                : "{$import->created} certificate(s) created as pending.",
            'created' => $import->created,
            'failures' => $import->failures,
        ]);
    }
}
