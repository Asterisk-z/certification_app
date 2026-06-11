<?php

namespace Database\Factories;

use App\Models\CertificateTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TemplateBlockFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'certificate_template_id' => CertificateTemplate::factory(),
            'name' => $name,
            'slug' => Str::slug($name, '_'),
            'type' => 'text',
            'value' => fake()->sentence(3),
            'is_dynamic' => false,
            'is_visible' => true,
            'is_default' => false,
            'pos_x' => fake()->numberBetween(0, 800),
            'pos_y' => fake()->numberBetween(0, 600),
            'width' => 300,
            'font_size' => 60,
        ];
    }

    public function dynamic(string $slug): static
    {
        return $this->state([
            'slug' => $slug,
            'is_dynamic' => true,
            'value' => '{{'.$slug.'}}',
        ]);
    }
}
