<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class OrganizationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'description' => fake()->sentence(),
            'email' => fake()->unique()->companyEmail(),
            'code' => strtoupper(fake()->unique()->lexify('????')),
            'status' => 'active',
            'features' => null,
            'limits' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['status' => 'inactive']);
    }
}
