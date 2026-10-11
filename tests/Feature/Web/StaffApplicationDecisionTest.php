<?php

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\GovEntity;
use App\Models\Service;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->interior = GovEntity::factory()->interior()->create();
    $this->goodConduct = Service::factory()->goodConduct()->for($this->interior)->create();
    $this->reviewer = User::factory()->employee($this->interior)->create();
    $this->application = Application::factory()->for($this->goodConduct)->create();
});

it('offers the decision panel only while the request is waiting', function () {
    $decided = Application::factory()->for($this->goodConduct)->approved()->create();

    $this->actingAs($this->reviewer)
        ->get("/staff/applications/{$this->application->id}")
        ->assertInertia(fn (AssertableInertia $page) => $page->where('canDecide', true));

    $this->actingAs($this->reviewer)
        ->get("/staff/applications/{$decided->id}")
        ->assertInertia(fn (AssertableInertia $page) => $page->where('canDecide', false));
});

it('approves a request and issues its certificate', function () {
    $this->actingAs($this->reviewer)
        ->post("/staff/applications/{$this->application->id}/approval", ['note' => 'All checks clear.'])
        ->assertRedirect(route('staff.applications.show', $this->application));

    $this->application->refresh();
    expect($this->application)
        ->status->toBe(ApplicationStatus::Approved)
        ->decided_by->toBe($this->reviewer->id)
        ->decision_note->toBe('All checks clear.')
        ->and($this->application->certificate)->not->toBeNull();

    $this->get("/staff/applications/{$this->application->id}")
        ->assertInertia(fn (AssertableInertia $page) => $page->hasFlash('decision', 'approved')->where('canDecide', false));
});

it('rejects a request with a reason', function () {
    $reason = 'The employer letter is missing. Please apply again with it attached.';

    $this->actingAs($this->reviewer)
        ->post("/staff/applications/{$this->application->id}/rejection", ['reason' => $reason])
        ->assertRedirect(route('staff.applications.show', $this->application));

    expect($this->application->refresh())
        ->status->toBe(ApplicationStatus::Rejected)
        ->decision_note->toBe($reason)
        ->and($this->application->certificate)->toBeNull();
});

it('requires a meaningful reason to reject', function (?string $reason) {
    $this->actingAs($this->reviewer)
        ->from("/staff/applications/{$this->application->id}")
        ->post("/staff/applications/{$this->application->id}/rejection", ['reason' => $reason])
        ->assertRedirect("/staff/applications/{$this->application->id}")
        ->assertSessionHasErrors('reason');

    expect($this->application->refresh()->status)->toBe(ApplicationStatus::Submitted);
})->with([
    'missing' => [null],
    'too short' => ['No.'],
]);

it('tells the reviewer when a colleague already decided', function () {
    $this->application->update(['status' => ApplicationStatus::Rejected, 'decided_at' => now()]);

    $this->actingAs($this->reviewer)
        ->post("/staff/applications/{$this->application->id}/approval")
        ->assertRedirect(route('staff.applications.show', $this->application));

    expect($this->application->refresh()->status)->toBe(ApplicationStatus::Rejected);
    $this->get("/staff/applications/{$this->application->id}")
        ->assertInertia(fn (AssertableInertia $page) => $page->hasFlash('decision', 'already_decided'));
});

it('hides another ministry’s request from the decision routes', function () {
    $other = Application::factory()->for(Service::factory()->for(GovEntity::factory()->create(['code' => 'MUN'])))->create();

    $this->actingAs($this->reviewer)->post("/staff/applications/{$other->id}/approval")->assertNotFound();
    expect($other->refresh()->status)->toBe(ApplicationStatus::Submitted);
});

it('keeps citizens away from decisions', function () {
    $this->actingAs($this->application->applicant)
        ->post("/staff/applications/{$this->application->id}/approval")
        ->assertForbidden();
});
