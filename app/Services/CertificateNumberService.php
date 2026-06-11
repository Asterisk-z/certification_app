<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use Illuminate\Support\Facades\DB;

class CertificateNumberService
{
    /**
     * Atomically reserve the next certificate number for a template
     * (e.g. "FST-000123"). Skips over numbers that were taken manually.
     */
    public function next(CertificateTemplate $template): string
    {
        return DB::transaction(function () use ($template) {
            $locked = CertificateTemplate::whereKey($template->id)->lockForUpdate()->first();

            do {
                $locked->counter++;
                $candidate = $this->format($locked->code, $locked->counter);
            } while (Certificate::withTrashed()->where('certificate_number', $candidate)->exists());

            $locked->save();
            $template->counter = $locked->counter;

            return $candidate;
        });
    }

    public function format(string $code, int $n): string
    {
        return strtoupper($code).'-'.str_pad((string) $n, 6, '0', STR_PAD_LEFT);
    }
}
