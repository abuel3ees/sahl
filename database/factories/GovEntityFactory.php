<?php

namespace Database\Factories;

use App\Models\GovEntity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GovEntity>
 */
class GovEntityFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->lexify('???'),
            'name_ar' => 'وزارة '.fake()->unique()->word(),
            'name_en' => 'Ministry of '.fake()->unique()->word(),
        ];
    }

    public function interior(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => 'MOI',
            'name_ar' => 'وزارة الداخلية',
            'name_en' => 'Ministry of Interior',
        ]);
    }
}
