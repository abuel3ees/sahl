<?php

namespace App\Actions\Applications;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SubmitApplication
{
    /**
     * Unambiguous characters for human-readable codes (no 0/O, 1/I/L).
     */
    public const CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    /**
     * @param  array{purpose: string, addressed_to?: string|null, certificate_language: string}  $formData
     */
    public function handle(User $applicant, Service $service, array $formData): Application
    {
        return DB::transaction(function () use ($applicant, $service, $formData): Application {
            $application = Application::create([
                'reference_number' => $this->newReferenceNumber(),
                'service_id' => $service->id,
                'gov_entity_id' => $service->gov_entity_id,
                'applicant_id' => $applicant->id,
                'status' => ApplicationStatus::Submitted,
                'form_data' => $formData,
                'submitted_at' => now(),
            ]);

            $application->statusChanges()->create([
                'from_status' => null,
                'to_status' => ApplicationStatus::Submitted,
                'actor_id' => $applicant->id,
                'created_at' => now(),
            ]);

            return $application;
        });
    }

    /**
     * Build a reference like "SHL-2026-7K3M9Q". The unique index is the final guard.
     */
    private function newReferenceNumber(): string
    {
        do {
            $reference = 'SHL-'.now()->format('Y').'-'.self::randomCode(6);
        } while (Application::where('reference_number', $reference)->exists());

        return $reference;
    }

    public static function randomCode(int $length): string
    {
        $alphabet = self::CODE_ALPHABET;
        $code = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= $alphabet[random_int(0, Str::length($alphabet) - 1)];
        }

        return $code;
    }
}
