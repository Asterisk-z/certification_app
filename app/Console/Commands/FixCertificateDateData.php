<?php

namespace App\Console\Commands;

use App\Models\Certificate;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Repairs certificates whose `data` JSON captured a canonical field — most
 * commonly a raw Excel date serial for `expiry_date` (e.g. "46191") leaked in
 * by an older import that treated expiry_date as a custom column. Such values
 * used to override the properly formatted date at render time, so the
 * certificate showed a bare number instead of the date.
 *
 * For each affected certificate this:
 *   1. back-fills the real `expiry_date` column from the leaked value when the
 *      column is empty (parsing an Excel serial or a date string),
 *   2. strips every canonical slug out of `data` (they belong to columns, not
 *      `data`), and
 *   3. clears the cached PDF so it re-renders with the corrected value.
 */
class FixCertificateDateData extends Command
{
    protected $signature = 'certificates:fix-date-data {--dry-run : Report what would change without writing}';

    protected $description = 'Strip leaked canonical date fields (e.g. raw Excel expiry serials) out of certificate data';

    /** Canonical slugs that live in columns and must never sit in `data`. */
    private const CANONICAL = ['full_name', 'email', 'completion_date', 'issue_date', 'expiry_date', 'certificate_number', 'qr_code'];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $fixed = 0;
        $expiriesRestored = 0;
        $pdfsCleared = 0;

        Certificate::withTrashed()
            ->whereNotNull('data')
            ->chunkById(200, function ($certificates) use ($dryRun, &$fixed, &$expiriesRestored, &$pdfsCleared) {
                foreach ($certificates as $certificate) {
                    $data = $certificate->data ?? [];

                    $leaked = array_intersect(array_keys($data), self::CANONICAL);
                    if ($leaked === []) {
                        continue;
                    }

                    // Recover a real expiry date from the leaked value when the
                    // column has none, so the certificate shows a date instead
                    // of going blank.
                    $restored = false;
                    if (! $certificate->expiry_date && filled($data['expiry_date'] ?? null)) {
                        if ($parsed = $this->parseDate($data['expiry_date'])) {
                            $certificate->expiry_date = $parsed;
                            $restored = true;
                        }
                    }

                    foreach (self::CANONICAL as $slug) {
                        unset($data[$slug]);
                    }
                    $certificate->data = $data;

                    $hadPdf = (bool) $certificate->pdf_path;

                    $this->line(sprintf(
                        '  #%d %s — dropped [%s]%s%s',
                        $certificate->id,
                        $certificate->certificate_number,
                        implode(', ', $leaked),
                        $restored ? ' — expiry set to '.$certificate->expiry_date->format('d M Y') : '',
                        $hadPdf ? ' — cached PDF cleared' : '',
                    ));

                    if (! $dryRun) {
                        if ($hadPdf) {
                            Storage::disk('local')->delete($certificate->pdf_path);
                            $certificate->pdf_path = null;
                        }
                        // Repairing stored data — don't stamp an activity-log entry.
                        $certificate->saveQuietly();
                    }

                    $fixed++;
                    $expiriesRestored += $restored ? 1 : 0;
                    $pdfsCleared += $hadPdf ? 1 : 0;
                }
            });

        $this->newLine();
        $this->info(sprintf(
            '%s%d certificate(s) repaired, %d expiry date(s) restored, %d cached PDF(s) cleared.',
            $dryRun ? '[dry run] ' : '',
            $fixed,
            $expiriesRestored,
            $pdfsCleared,
        ));

        return self::SUCCESS;
    }

    private function parseDate(mixed $value): ?Carbon
    {
        try {
            if (is_numeric($value)) {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value));
            }

            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }
}
