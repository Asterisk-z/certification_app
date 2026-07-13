<?php

namespace App\Console\Commands;

use App\Models\Certificate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Repairs certificate expiry rendering. Two things went wrong for certificates
 * created by older imports:
 *
 *   1. A raw Excel date serial (e.g. "46191") leaked into the `data` JSON under
 *      `expiry_date` and used to override the properly formatted date, so the
 *      certificate showed a bare number.
 *   2. The real `expiry_date` column was left empty, so once the leaked value
 *      is removed the certificate shows a blank expiry instead of a date.
 *
 * For every affected certificate this:
 *   - strips all canonical slugs out of `data` (they belong to columns),
 *   - back-fills an empty `expiry_date` column from the template's validity
 *     period (issue date + duration) — the authoritative rule, rather than the
 *     unreliable leaked serial, and
 *   - clears the cached PDF so it re-renders with the corrected value.
 */
class FixCertificateDateData extends Command
{
    protected $signature = 'certificates:fix-date-data {--dry-run : Report what would change without writing}';

    protected $description = 'Repair leaked/blank certificate expiry dates and clear stale cached PDFs';

    /** Canonical slugs that live in columns and must never sit in `data`. */
    private const CANONICAL = ['full_name', 'email', 'completion_date', 'issue_date', 'expiry_date', 'certificate_number', 'qr_code'];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $fixed = 0;
        $expiriesBackfilled = 0;
        $pdfsCleared = 0;

        Certificate::withTrashed()
            ->with('template')
            ->chunkById(200, function ($certificates) use ($dryRun, &$fixed, &$expiriesBackfilled, &$pdfsCleared) {
                foreach ($certificates as $certificate) {
                    $changes = [];
                    $data = $certificate->data ?? [];

                    // 1. Strip any canonical slug that leaked into `data`.
                    $leaked = array_intersect(array_keys($data), self::CANONICAL);
                    if ($leaked !== []) {
                        foreach (self::CANONICAL as $slug) {
                            unset($data[$slug]);
                        }
                        $certificate->data = $data;
                        $changes[] = 'dropped ['.implode(', ', $leaked).'] from data';
                    }

                    // 2. Back-fill an empty expiry from the template's validity period.
                    if (! $certificate->expiry_date && $certificate->template && $certificate->issue_date) {
                        $expiry = $certificate->template->duration && $certificate->template->duration_type
                            ? $certificate->template->duration_type->addTo($certificate->issue_date->copy(), $certificate->template->duration)
                            : null;

                        if ($expiry) {
                            $certificate->expiry_date = $expiry;
                            $changes[] = 'expiry set to '.$expiry->format('d M Y');
                            $expiriesBackfilled++;
                        }
                    }

                    if ($changes === []) {
                        continue;
                    }

                    // 3. Any change invalidates the cached PDF — clear it so the
                    //    document re-renders on the next view/download.
                    $hadPdf = (bool) $certificate->pdf_path;
                    if ($hadPdf) {
                        $changes[] = 'cached PDF cleared';
                        $pdfsCleared++;
                    }

                    $this->line(sprintf('  #%d %s — %s', $certificate->id, $certificate->certificate_number, implode('; ', $changes)));

                    if (! $dryRun) {
                        if ($hadPdf) {
                            Storage::disk('local')->delete($certificate->pdf_path);
                            $certificate->pdf_path = null;
                        }
                        // Repairing stored data — don't stamp an activity-log entry.
                        $certificate->saveQuietly();
                    }

                    $fixed++;
                }
            });

        $this->newLine();
        $this->info(sprintf(
            '%s%d certificate(s) repaired, %d expiry date(s) back-filled, %d cached PDF(s) cleared.',
            $dryRun ? '[dry run] ' : '',
            $fixed,
            $expiriesBackfilled,
            $pdfsCleared,
        ));

        return self::SUCCESS;
    }
}
