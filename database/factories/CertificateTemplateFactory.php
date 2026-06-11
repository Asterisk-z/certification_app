<?php

namespace Database\Factories;

use App\Enums\TemplateStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CertificateTemplateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(3, true).' Certificate',
            'code' => strtoupper(fake()->unique()->lexify('???')).fake()->numberBetween(10, 99),
            'bg_width' => 1123,
            'bg_height' => 794,
            'duration' => 1,
            'duration_type' => 'year',
            'status' => TemplateStatus::Draft,
        ];
    }

    public function ready(): static
    {
        return $this->state(['status' => TemplateStatus::Ready]);
    }
}
