<?php

namespace Database\Factories;

use App\Models\GovEntity;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'gov_entity_id' => GovEntity::factory(),
            'code' => fake()->unique()->bothify('SVC_####'),
            'slug' => fake()->unique()->slug(3),
            'name_ar' => 'خدمة '.fake()->word(),
            'name_en' => fake()->words(3, true),
            'description_ar' => 'وصف الخدمة',
            'description_en' => fake()->sentence(),
            'fee_minor' => 0,
            'currency' => 'QAR',
            'sla_working_days' => 3,
            'validity_days' => null,
            'is_active' => true,
        ];
    }

    public function goodConduct(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => Service::GOOD_CONDUCT,
            'slug' => 'good-conduct-certificate',
            'name_ar' => 'شهادة حسن السيرة والسلوك',
            'name_en' => 'Good Conduct Certificate',
            'description_ar' => 'شهادة رسمية تثبت خلو السجل الجنائي لمقدم الطلب، تُطلب عادةً لأغراض التوظيف أو السفر أو الإقامة.',
            'description_en' => 'An official certificate confirming the applicant has no criminal record, usually required for employment, travel or residency.',
            'fee_minor' => 5000,
            'sla_working_days' => 3,
            'validity_days' => 90,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
