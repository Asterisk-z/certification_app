<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Concerns\BelongsToOrganization;

class CertificateNumberService
{
    /**
     * Unambiguous alphabet for the random part — no 0/O, 1/I/L so the number
     * is easy to read and re-type from a printed credential.
     */
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    private const LENGTH = 8;

    /**
     * Generate a unique, non-sequential credential number for a template
     * (e.g. "FST-7K3MQ9P2"). Random so numbers can't be guessed or counted,
     * retried on the (astronomically rare) chance of a collision.
     */
    public function next(CertificateTemplate $template): string
    {
        return $this->nextForCode($template->code);
    }

    /**
     * Generate a unique credential number for an arbitrary prefix — used when
     * there is no template to take the code from (e.g. registering a standalone
     * uploaded certificate).
     */
    public function nextForCode(string $code): string
    {
        do {
            $candidate = $this->format($code, $this->randomPart());
        } while ($this->numberExists($candidate));

        return $candidate;
    }

    /**
     * Certificate numbers are the public verifier key and must be unique across
     * every organization, so the existence probe ignores the org global scope
     * (an org user would otherwise only see collisions within its own tenant).
     */
    private function numberExists(string $number): bool
    {
        return Certificate::withoutGlobalScope(BelongsToOrganization::class)
            ->withTrashed()
            ->where('certificate_number', $number)
            ->exists();
    }

    public function format(string $code, string $random): string
    {
        return strtoupper($code).'-'.$random;
    }

    private function randomPart(): string
    {
        $max = strlen(self::ALPHABET) - 1;
        $out = '';

        for ($i = 0; $i < self::LENGTH; $i++) {
            $out .= self::ALPHABET[random_int(0, $max)];
        }

        return $out;
    }
}
