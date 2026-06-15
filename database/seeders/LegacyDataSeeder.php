<?php

namespace Database\Seeders;

use App\Enums\TemplateStatus;
use App\Enums\UserRole;
use App\Models\CertificateTemplate;
use App\Models\Group;
use App\Models\Recipient;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Migrates reference data carried over from the previous environment so the
 * new platform starts with the same records: the certificate templates, the
 * groups, and the recipients that belong to them. Idempotent — safe to re-run.
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

        $this->seedTemplates();
        $this->seedRecipients();
    }

    /**
     * Recreate the certificate templates from the previous environment, along
     * with their block layouts. The old background artwork lived on that server
     * and isn't part of this repo, so templates are seeded without a background
     * and left in `draft`: the block positions/sizes are carried over using the
     * old canvas dimensions, so once you re-upload artwork at that size in the
     * designer everything lines up. Save the layout there to mark it ready.
     *
     * Codes must be unique here, so the duplicate legacy "MGS" (shared by the
     * Interim AGT and Letter of Training templates) is disambiguated to "LTC".
     */
    private function seedTemplates(): void
    {
        $admin = User::where('role', UserRole::Admin)->orderBy('id')->first();

        if (! $admin) {
            return;
        }

        // legacy certification_id => the template it maps to in this app.
        $templates = [
            1 => ['name' => 'Interim AGT Certificate', 'code' => 'MGS', 'duration' => 15, 'duration_type' => 'day'],
            4 => ['name' => 'ISPON MPDC 2025', 'code' => 'MPDC', 'duration' => 10, 'duration_type' => 'year'],
            6 => ['name' => 'Safety Compliance Certificate', 'code' => 'SCC', 'duration' => 1, 'duration_type' => 'year'],
            7 => ['name' => 'Certified Safety Auditor', 'code' => 'CSA', 'duration' => 2, 'duration_type' => 'year'],
            8 => ['name' => 'Industrial HSE Awareness', 'code' => 'SAL', 'duration' => 10, 'duration_type' => 'year'],
            10 => ['name' => 'Letter of Training Completion', 'code' => 'LTC', 'duration' => 3, 'duration_type' => 'month'],
        ];

        $blocksByTemplate = $this->legacyBlocks();

        foreach ($templates as $legacyId => $data) {
            $blocks = $blocksByTemplate[$legacyId] ?? [];

            $template = CertificateTemplate::withTrashed()->firstOrNew(['code' => $data['code']]);

            if (! $template->exists) {
                $template->fill($data + [
                    'user_id' => $admin->id,
                    'status' => TemplateStatus::Draft,
                ]);
            }

            // The legacy "template" image block carries the background's intrinsic
            // size — adopt it as the canvas only while no real artwork is set, so
            // the carried-over block positions stay accurate.
            $bg = collect($blocks)->firstWhere('slug', 'template');
            if ($bg && ! $template->background_image) {
                $template->bg_width = (int) round((float) $bg['width']);
                $template->bg_height = (int) round((float) $bg['height']);
            }

            $template->save();

            $this->seedBlocks($template, $blocks);
        }
    }

    /**
     * Replace a template's blocks with the carried-over legacy layout. The old
     * `template` image block is the background (handled on the template itself),
     * so it's dropped here. Skipped when the blocks already match, so re-running
     * neither churns the table nor clobbers later designer edits.
     */
    private function seedBlocks(CertificateTemplate $template, array $blocks): void
    {
        $rows = collect($blocks)
            ->reject(fn ($b) => $b['slug'] === 'template')
            ->map(fn ($b) => $this->mapBlock($b))
            ->values();

        $desired = $rows->pluck('slug')->sort()->values()->all();
        $current = $template->blocks()->pluck('slug')->sort()->values()->all();

        if ($current === $desired) {
            return;
        }

        $template->blocks()->delete();

        foreach ($rows as $row) {
            $template->blocks()->create($row);
        }
    }

    /**
     * Translate one legacy block into this app's schema: drop the `$` from
     * placeholders, point the old "certificate_code" field at the credential
     * number, normalise colours/weights, and clear image sources (the files
     * aren't in this repo — re-upload them in the designer).
     */
    private function mapBlock(array $b): array
    {
        $slug = $b['slug'] === 'certificate_code' ? 'certificate_number' : $b['slug'];
        $type = $b['type'];
        $isDynamic = ($b['isDynamic'] ?? 'no') === 'yes';

        $value = match (true) {
            $type === 'image' => null,
            $isDynamic => '{{'.$slug.'}}',
            default => $b['value'],
        };

        $size = (int) ($b['font_size'] ?? 0);

        return [
            'name' => $b['name'],
            'slug' => $slug,
            'type' => $type,
            'value' => $value,
            'is_dynamic' => $isDynamic,
            'is_visible' => ($b['isVisible'] ?? 'yes') === 'yes',
            'is_default' => ($b['isDefault'] ?? 'no') === 'yes',
            'pos_x' => (float) $b['xPosition'],
            'pos_y' => (float) $b['yPosition'],
            'width' => isset($b['width']) ? (float) $b['width'] : null,
            'height' => isset($b['height']) ? (float) $b['height'] : null,
            'font_family' => $b['font_family'] ?: 'Arial',
            'font_size' => $size > 0 ? $size : 60,
            'font_color' => $this->normalizeColor($b['font_color'] ?? null),
            'font_weight' => $b['font_weight'] ?: 'normal',
            'text_align' => 'left',
        ];
    }

    private function normalizeColor(?string $color): string
    {
        $color = strtolower(trim((string) $color));

        return match ($color) {
            'black', '' => '#000000',
            'red' => '#FF0000',
            'white' => '#FFFFFF',
            default => str_starts_with($color, '#') ? strtoupper($color) : '#000000',
        };
    }

    /**
     * Legacy blocks grouped by their certification_id.
     *
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function legacyBlocks(): array
    {
        $path = __DIR__.'/data/legacy_template_blocks.json';

        if (! is_file($path)) {
            return [];
        }

        $grouped = [];
        foreach (json_decode(file_get_contents($path), true) ?? [] as $block) {
            $grouped[(int) $block['certification_id']][] = $block;
        }

        return $grouped;
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
