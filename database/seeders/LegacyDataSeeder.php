<?php

namespace Database\Seeders;

use App\Models\Group;
use Illuminate\Database\Seeder;

/**
 * Migrates reference data carried over from the previous environment so the
 * new platform starts with the same records. Idempotent — safe to re-run.
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
    }
}
