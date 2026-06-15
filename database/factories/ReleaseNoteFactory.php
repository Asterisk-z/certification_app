<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ReleaseNoteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'version' => fake()->numerify('#.#.#'),
            'title' => fake()->optional()->sentence(3),
            'body' => '- '.implode("\n- ", fake()->sentences(3)),
            'released_on' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
        ];
    }
}
