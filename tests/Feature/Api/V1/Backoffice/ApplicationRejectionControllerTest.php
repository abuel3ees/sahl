<?php

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\GovEntity;
use App\Models\Service;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->ministry = GovEntity::factory()->interior()->create();
    $this->service = Service::factory()->goodConduct()->for($this->ministry)->create();
    $this->reviewer = User::factory()->employee($this->ministry)->create();
    $this->application = Application::factory()->for($this->service)->create();
});

it('rejects the application with a reason and issues no certificate', function () {
    Sanctum::actingAs($this->reviewer);

    $this->postJson("/api/v1/backoffice/applications/{$this->application->id}/rejection", [
        'reason' => 'The uploaded passport copy is unreadable.',
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected')
        ->assertJsonPath('data.decision_note', 'The uploaded passport copy is unreadable.');

    expect($this->application->fresh()->status)->toBe(ApplicationStatus::Rejected)
        ->and($this->application->certificate()->exists())->toBeFalse();
});

it('requires a reason of at least 10 characters', function () {
    Sanctum::actingAs($this->reviewer);

    $this->postJson("/api/v1/backoffice/applications/{$this->application->id}/rejection", ['reason' => 'No'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['reason' => 'The reason field must be at least 10 characters.']);
});

it('returns 409 when the application was already decided', function () {
    $this->application->update(['status' => ApplicationStatus::Approved]);
    Sanctum::actingAs($this->reviewer);

    $this->postJson("/api/v1/backoffice/applications/{$this->application->id}/rejection", [
        'reason' => 'Changed my mind about this.',
    ])->assertConflict();
});
