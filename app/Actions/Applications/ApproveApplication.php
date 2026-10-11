<?php

namespace App\Actions\Applications;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\Certificate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ApproveApplication
{
    /**
     * Approve a submitted application and issue its certificate in one transaction.
     */
    public function handle(Application $application, User $reviewer, ?string $note = null): Application
    {
        return DB::transaction(function () use ($application, $reviewer, $note): Application {
            $application = Application::whereKey($application->getKey())->lockForUpdate()->firstOrFail();

            if (! $application->status->isAwaitingDecision()) {
                throw new ConflictHttpException("Application is already {$application->status->value}.");
            }

            $application->update([
                'status' => ApplicationStatus::Approved,
                'decided_at' => now(),
                'decided_by' => $reviewer->id,
                'decision_note' => $note,
            ]);

            $application->statusChanges()->create([
                'from_status' => ApplicationStatus::Submitted,
                'to_status' => ApplicationStatus::Approved,
                'actor_id' => $reviewer->id,
                'note' => $note,
                'created_at' => now(),
            ]);

            $this->issueCertificate($application);

            return $application;
        });
    }

    private function issueCertificate(Application $application): Certificate
    {
        $application->loadMissing(['applicant', 'service.govEntity']);
        $service = $application->service;
        $issuedAt = now();

        return $application->certificate()->create([
            'serial_number' => $this->newSerialNumber($application),
            'verification_code' => $this->newVerificationCode(),
            'issued_at' => $issuedAt,
            'expires_at' => $service->validity_days ? $issuedAt->copy()->addDays($service->validity_days) : null,
            'content' => [
                'holder_name_ar' => $application->applicant->name_ar,
                'holder_name_en' => $application->applicant->name,
                'holder_national_id' => $application->applicant->national_id,
                'purpose' => $application->form_data['purpose'],
                'addressed_to' => $application->form_data['addressed_to'] ?? null,
                'language' => $application->form_data['certificate_language'],
                'service_name_ar' => $service->name_ar,
                'service_name_en' => $service->name_en,
                'issuer_name_ar' => $service->govEntity->name_ar,
                'issuer_name_en' => $service->govEntity->name_en,
            ],
        ]);
    }

    /**
     * Build a serial like "MOI-GC-2026-4F7KQ2".
     */
    private function newSerialNumber(Application $application): string
    {
        $prefix = $application->service->govEntity->code.'-GC-'.now()->format('Y').'-';

        do {
            $serial = $prefix.SubmitApplication::randomCode(6);
        } while (Certificate::where('serial_number', $serial)->exists());

        return $serial;
    }

    private function newVerificationCode(): string
    {
        do {
            $code = SubmitApplication::randomCode(12);
        } while (Certificate::where('verification_code', $code)->exists());

        return $code;
    }
}
