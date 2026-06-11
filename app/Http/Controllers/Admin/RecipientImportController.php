<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ImportFormatExport;
use App\Http\Controllers\Controller;
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
     * Download the per-template Excel format with all dynamic-field columns.
     */
    public function format(CertificateTemplate $template): BinaryFileResponse
    {
        $filename = strtolower($template->code).'-import-format.xlsx';

        return Excel::download(new ImportFormatExport($template), $filename);
    }

    /**
     * Import recipients + create pending certificates from an Excel file.
     */
    public function store(Request $request, CertificateTemplate $template, CertificateNumberService $numbers): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
            'group_uuid' => ['nullable', 'uuid', 'exists:groups,uuid'],
        ]);

        // Validate headings before touching any data.
        $expected = ImportFormatExport::headingsFor($template);
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

        $import = new RecipientsImport($template, $group, $numbers);
        Excel::import($import, $validated['file']);

        activity()->performedOn($template)->causedBy($request->user())
            ->withProperties(['created' => $import->created, 'failed' => count($import->failures)])
            ->log('recipients_imported');

        $request->user()->notify(new AdminAlertNotification(
            'import_completed',
            'Import completed',
            "{$import->created} certificate(s) created for “{$template->name}”".
                (count($import->failures) ? ', '.count($import->failures).' row(s) skipped.' : '.'),
        ));

        return response()->json([
            'message' => "{$import->created} certificate(s) created as pending.",
            'created' => $import->created,
            'failures' => $import->failures,
        ]);
    }
}
