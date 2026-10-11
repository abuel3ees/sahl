<?php

namespace Database\Factories;

use App\Models\Application;
use App\Models\Certificate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Certificate>
 */
class CertificateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'application_id' => Application::factory()->approved(),
            'serial_number' => 'MOI-GC-'.now()->format('Y').'-'.fake()->unique()->bothify('??####'),
            'verification_code' => strtoupper(fake()->unique()->bothify('????########')),
            'content' => [
                'holder_name_ar' => 'أحمد المنصوري',
                'holder_name_en' => 'Ahmed Al-Mansouri',
                'holder_national_id' => '28763404821',
                'purpose' => 'employment',
                'addressed_to' => null,
                'language' => 'ar',
                'service_name_ar' => 'شهادة حسن السيرة والسلوك',
                'service_name_en' => 'Good Conduct Certificate',
                'issuer_name_ar' => 'وزارة الداخلية',
                'issuer_name_en' => 'Ministry of Interior',
            ],
            'issued_at' => now(),
            'expires_at' => now()->addDays(90),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'issued_at' => now()->subDays(100),
            'expires_at' => now()->subDays(10),
        ]);
    }
}
