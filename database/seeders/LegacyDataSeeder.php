<?php

namespace Database\Seeders;

use App\Models\Group;
use App\Models\Recipient;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Migrates reference data carried over from the previous environment so the
 * new platform starts with the same records: the certificate groups and the
 * recipients that belong to them. Idempotent — safe to re-run.
 *
 * Recipients are upserted by email and attached to their group; no invite
 * emails are sent (a seeder must never mail real people — invite them from the
 * recipients screen when ready).
 */
class LegacyDataSeeder extends Seeder
{
    public function run(): void
    {
        $groups = [
            'Interim AGT Certificate',
            'ISPON MPDC 2025',
            'Certified Safety Auditor',
            'Safety Compliance Certificate',
            'Industrial HSE Awareness',
            'Letter of Training Completion',
        ];

        foreach ($groups as $name) {
            Group::firstOrCreate(['name' => $name]);
        }

        $this->seedRecipients();
    }

    /**
     * Import the legacy recipients from the bundled JSON file. The source data
     * carries duplicate emails (same contact across several groups) and stray
     * whitespace, so emails are trimmed + lowercased and recipients are keyed
     * by email — the first name seen wins and later rows only add the group
     * membership.
     */
    private function seedRecipients(): void
    {
        $path = __DIR__.'/data/legacy_recipients.json';

        if (! is_file($path)) {
            return;
        }

        $rows = json_decode(file_get_contents($path), true) ?? [];

        // name => id, so we resolve each row's group without re-querying.
        $groupIds = Group::pluck('id', 'name');

        DB::transaction(function () use ($rows, &$groupIds) {
            foreach ($rows as $row) {
                $email = strtolower(trim($row['email'] ?? ''));
                $name = trim($row['full_name'] ?? '');
                $groupName = trim($row['group_name'] ?? '');

                if ($email === '' || $name === '') {
                    continue;
                }

                $recipient = Recipient::withTrashed()->firstOrNew(['email' => $email]);

                if ($recipient->trashed()) {
                    $recipient->restore();
                }

                // Only set the name on first insert so re-running (or a later
                // duplicate email) doesn't overwrite an existing record.
                if (! $recipient->exists) {
                    $recipient->full_name = $name;
                }

                $recipient->save();

                if ($groupName === '') {
                    continue;
                }

                $groupId = $groupIds[$groupName]
                    ??= Group::firstOrCreate(['name' => $groupName])->id;

                $recipient->groups()->syncWithoutDetaching([$groupId]);
            }
        });
    }
}
