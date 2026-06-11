<?php

namespace Database\Factories;

use App\Enums\CertificateStatus;
use App\Models\CertificateTemplate;
use App\Models\Recipient;
use Illuminate\Database\Eloquent\Factories\Factory;

class CertificateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'certificate_template_id' => CertificateTemplate::factory(),
            'recipient_id' => Recipient::factory(),
            'certificate_number' => 'TST-'.str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'data' => [],
            'completion_date' => now()->subWeek(),
            'issue_date' => now(),
            'expiry_date' => now()->addYear(),
            'status' => CertificateStatus::Pending,
        ];
    }

    public function sent(): static
    {
        return $this->state(['status' => CertificateStatus::Sent, 'sent_at' => now()]);
    }

    public function expired(): static
    {
        return $this->state([
            'status' => CertificateStatus::Expired,
            'issue_date' => now()->subYears(2),
            'expiry_date' => now()->subYear(),
        ]);
    }
}
