<?php

namespace Database\Factories;

use App\Enums\ApplicationStatus;
use App\Enums\CertificateLanguage;
use App\Enums\CertificatePurpose;
use App\Models\Application;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Application>
 */
class ApplicationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference_number' => 'SHL-'.now()->format('Y').'-'.fake()->unique()->bothify('??####'),
            'service_id' => Service::factory(),
            'gov_entity_id' => fn (array $attributes) => Service::find($attributes['service_id'])->gov_entity_id,
            'applicant_id' => User::factory(),
            'status' => ApplicationStatus::Submitted,
            'form_data' => [
                'purpose' => fake()->randomElement(CertificatePurpose::cases())->value,
                'addressed_to' => null,
                'certificate_language' => CertificateLanguage::Arabic->value,
            ],
            'submitted_at' => now(),
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ApplicationStatus::Approved,
            'decided_at' => now(),
        ]);
    }
}
