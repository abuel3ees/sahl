<?php

use App\Models\Application;
use App\Models\Certificate;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('returns the certificate of an approved application', function () {
    $certificate = Certificate::factory()->create();
    Sanctum::actingAs($certificate->application->applicant);

    $this->getJson("/api/v1/applications/{$certificate->application_id}/certificate")
        ->assertOk()
        ->assertJsonPath('data.serial_number', $certificate->serial_number)
        ->assertJsonPath('data.verification_url', route('verify.show', $certificate->verification_code))
        ->assertJsonPath('data.is_expired', false);
});

it('returns 404 while the application has no certificate', function () {
    $application = Application::factory()->create();
    Sanctum::actingAs($application->applicant);

    $this->getJson("/api/v1/applications/{$application->id}/certificate")->assertNotFound();
});

it('returns 404 for another citizen\'s certificate', function () {
    $certificate = Certificate::factory()->create();
    Sanctum::actingAs(User::factory()->create());

    $this->getJson("/api/v1/applications/{$certificate->application_id}/certificate")->assertNotFound();
});
