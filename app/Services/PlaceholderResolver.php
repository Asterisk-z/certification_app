<?php

namespace App\Services;

use App\Models\Certificate;

class PlaceholderResolver
{
    /**
     * Resolve every dynamic slug for a certificate to its concrete value.
     * Default fields come from the certificate itself; custom fields come
     * from the certificate's data JSON.
     *
     * @return array<string, string>
     */
    public function resolve(Certificate $certificate): array
    {
        $certificate->loadMissing('recipient', 'template');

        // Custom fields first, then the canonical fields overlay them. The
        // canonical fields (dates, name, number) are the source of truth and
        // must always win — a stray `data` key with the same slug (e.g. a raw
        // Excel date serial captured by an older import) must never override
        // the properly formatted column value.
        $values = [];

        foreach ($certificate->data ?? [] as $slug => $value) {
            $values[$slug] = (string) $value;
        }

        $values = array_merge($values, [
            'full_name' => $certificate->recipient->full_name,
            'email' => $certificate->recipient->email,
            'completion_date' => $certificate->completion_date?->format('d M Y') ?? '',
            'issue_date' => $certificate->issue_date->format('d M Y'),
            'expiry_date' => $certificate->expiry_date?->format('d M Y') ?? '',
            'certificate_number' => $certificate->certificate_number,
        ]);

        return $values;
    }

    /**
     * Replace {{slug}} placeholders inside a block value.
     */
    public function interpolate(?string $text, array $values): string
    {
        if ($text === null) {
            return '';
        }

        return preg_replace_callback(
            '/\{\{\s*([a-z0-9_]+)\s*\}\}/i',
            fn ($m) => $values[strtolower($m[1])] ?? '',
            $text
        );
    }
}
