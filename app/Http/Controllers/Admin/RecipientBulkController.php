<?php

namespace App\Http\Controllers\Admin;

use App\Exports\RecipientsFormatExport;
use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\Recipient;
use App\Services\RecipientInviteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\HeadingRowImport;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RecipientBulkController extends Controller
{
    /**
     * Bulk-create recipients from pasted lines ("Name, email[, phone]") or an
     * uploaded Excel/CSV (columns: full_name, email, phone), optionally
     * attaching everyone to one or more groups.
     */
    public function store(Request $request, RecipientInviteService $invites): JsonResponse
    {
        $validated = $request->validate([
            'text' => ['nullable', 'string', 'max:100000', 'required_without:file'],
            'file' => ['nullable', 'file', 'mimes:xlsx,xls,csv', 'max:10240', 'required_without:text'],
            'group_uuids' => ['nullable', 'array'],
            'group_uuids.*' => ['uuid', 'exists:groups,uuid'],
        ]);

        $rows = $request->hasFile('file')
            ? $this->rowsFromFile($request->file('file'))
            : $this->rowsFromText($validated['text']);

        if ($rows instanceof JsonResponse) {
            return $rows;
        }

        $groupIds = empty($validated['group_uuids'])
            ? collect()
            : Group::whereIn('uuid', $validated['group_uuids'])->pluck('id');

        $created = 0;
        $updated = 0;
        $failures = [];

        foreach ($rows as $index => $row) {
            $line = $index + ($request->hasFile('file') ? 2 : 1); // heading row offset for files

            $validator = Validator::make($row, [
                'full_name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255'],
                'phone' => ['nullable', 'string', 'max:30'],
            ]);

            if ($validator->fails()) {
                $failures[] = ['row' => $line, 'errors' => $validator->errors()->all()];

                continue;
            }

            $recipient = Recipient::withTrashed()->firstOrNew(['email' => strtolower(trim($row['email']))]);
            $isNew = ! $recipient->exists;

            if ($recipient->trashed()) {
                $recipient->restore();
            }

            $recipient->full_name = trim($row['full_name']);
            if (filled($row['phone'] ?? null)) {
                $recipient->phone = trim((string) $row['phone']);
            }
            $recipient->save();

            if ($groupIds->isNotEmpty()) {
                $recipient->groups()->syncWithoutDetaching($groupIds);
            }

            // Newly added recipients are emailed a portal invite.
            if ($isNew) {
                $invites->send($recipient, $request->user());
                $created++;
            } else {
                $updated++;
            }
        }

        activity()->causedBy($request->user())
            ->withProperties(['created' => $created, 'updated' => $updated, 'failed' => count($failures)])
            ->log('recipients_bulk_added');

        return response()->json([
            'message' => trim("{$created} recipient(s) added & invited, {$updated} updated.".(count($failures) ? ' '.count($failures).' row(s) skipped.' : '')),
            'created' => $created,
            'updated' => $updated,
            'failures' => $failures,
        ]);
    }

    /**
     * Apply a bulk action (invite / delete) to selected recipients.
     */
    public function action(Request $request, RecipientInviteService $invites): JsonResponse
    {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['invite', 'delete'])],
            'uuids' => ['required', 'array', 'min:1', 'max:500'],
            'uuids.*' => ['uuid'],
        ]);

        $recipients = Recipient::whereIn('uuid', $validated['uuids'])->get();
        $affected = 0;

        foreach ($recipients as $recipient) {
            if ($validated['action'] === 'invite') {
                $invites->send($recipient, $request->user());
            } else {
                $recipient->delete();
            }
            $affected++;
        }

        activity()->causedBy($request->user())
            ->withProperties(['action' => $validated['action'], 'affected' => $affected])
            ->log('recipients_bulk_'.$validated['action']);

        $message = $validated['action'] === 'invite'
            ? "Invite sent to {$affected} recipient(s)."
            : "{$affected} recipient(s) removed.";

        return response()->json(['message' => $message, 'affected' => $affected]);
    }

    /**
     * Downloadable Excel format for the bulk add (full_name, email, phone).
     */
    public function format(): BinaryFileResponse
    {
        return Excel::download(new RecipientsFormatExport, 'recipients-bulk-format.xlsx');
    }

    private function rowsFromText(string $text): array
    {
        $rows = [];

        foreach (preg_split('/\r\n|\r|\n/', trim($text)) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $parts = array_map('trim', preg_split('/[,;\t]/', $line));

            // Accept "Name, email" and "email, Name" orderings.
            $email = collect($parts)->first(fn ($p) => filter_var($p, FILTER_VALIDATE_EMAIL));
            $rest = array_values(array_filter($parts, fn ($p) => $p !== $email && $p !== ''));

            $rows[] = [
                'full_name' => $rest[0] ?? '',
                'email' => $email ?? ($parts[1] ?? ''),
                'phone' => $rest[1] ?? null,
            ];
        }

        return $rows;
    }

    private function rowsFromFile($file): array|JsonResponse
    {
        $headings = collect((new HeadingRowImport)->toArray($file)[0][0] ?? [])
            ->filter()->map(fn ($h) => strtolower(trim((string) $h)))->all();

        $missing = array_values(array_diff(['full_name', 'email'], $headings));
        if ($missing) {
            return response()->json([
                'message' => 'The file is missing required columns: '.implode(', ', $missing),
                'missing_columns' => $missing,
            ], 422);
        }

        $sheets = Excel::toArray(new class implements WithHeadingRow {}, $file);

        return collect($sheets[0] ?? [])
            ->map(fn ($row) => [
                'full_name' => isset($row['full_name']) ? trim((string) $row['full_name']) : '',
                'email' => isset($row['email']) ? trim((string) $row['email']) : '',
                'phone' => isset($row['phone']) && $row['phone'] !== null ? (string) $row['phone'] : null,
            ])
            ->filter(fn ($row) => $row['full_name'] !== '' || $row['email'] !== '')
            ->values()
            ->all();
    }
}
