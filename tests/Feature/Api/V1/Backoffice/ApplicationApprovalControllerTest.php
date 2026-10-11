<?php

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\GovEntity;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->ministry = GovEntity::factory()->interior()->create();
    $this->service = Service::factory()->goodConduct()->for($this->ministry)->create();
    $this->reviewer = User::factory()->employee($this->ministry)->create();
    $this->application = Application::factory()->for($this->service)->create([
        'form_data' => ['purpose' => 'travel', 'addressed_to' => null, 'certificate_language' => 'en'],
    ]);
});

it('approves the application and issues a 90-day certificate', function () {
    Date::setTestNow('2026-10-11 09:00:00');
    Sanctum::actingAs($this->reviewer);

    $this->postJson("/api/v1/backoffice/applications/{$this->application->id}/approval", ['note' => 'Record is clear.'])
        ->assertOk()
        ->assertJsonPath('data.status', 'approved')
        ->assertJsonPath('data.decision_note', 'Record is clear.')
        ->assertJsonPath('data.timeline.0.to_status', 'approved')
        ->assertJsonPath('data.timeline.0.actor', 'ministry');

    $application = $this->application->fresh();
    $certificate = $application->certificate;

    expect($application->status)->toBe(ApplicationStatus::Approved)
        ->and($application->decided_by)->toBe($this->reviewer->id)
        ->and($certificate->serial_number)->toMatch('/^MOI-GC-2026-[A-Z2-9]{6}$/')
        ->and($certificate->verification_code)->toMatch('/^[A-Z2-9]{12}$/')
        ->and($certificate->expires_at->toDateTimeString())->toBe('2027-01-09 09:00:00')
        ->and($certificate->content['holder_national_id'])->toBe($application->applicant->national_id)
        ->and($certificate->content['purpose'])->toBe('travel');
});

it('returns 409 when the application was already decided', function () {
    $this->application->update(['status' => ApplicationStatus::Rejected]);
    Sanctum::actingAs($this->reviewer);

    $this->postJson("/api/v1/backoffice/applications/{$this->application->id}/approval")
        ->assertConflict()
        ->assertJsonPath('message', 'Application is already rejected.');

    expect($this->application->certificate()->exists())->toBeFalse();
});

it('returns 404 for another ministry\'s reviewer', function () {
    Sanctum::actingAs(User::factory()->employee()->create());

    $this->postJson("/api/v1/backoffice/applications/{$this->application->id}/approval")->assertNotFound();

    expect($this->application->fresh()->status)->toBe(ApplicationStatus::Submitted);
});

it('forbids citizens with 403', function () {
    Sanctum::actingAs($this->application->applicant);

    $this->postJson("/api/v1/backoffice/applications/{$this->application->id}/approval")->assertForbidden();
});
