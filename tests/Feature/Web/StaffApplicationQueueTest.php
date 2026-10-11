<?php

use App\Actions\Applications\ApproveApplication;
use App\Actions\Applications\SubmitApplication;
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
});

it('lists the ministry’s waiting requests, oldest first, with counts per status', function () {
    $newer = Application::factory()->for($this->goodConduct)->create(['submitted_at' => now()->subDay()]);
    $older = Application::factory()->for($this->goodConduct)->create(['submitted_at' => now()->subDays(3)]);
    Application::factory()->for($this->goodConduct)->approved()->create();

    $this->actingAs($this->reviewer)
        ->get('/staff')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('staff/applications/index')
            ->where('status', 'submitted')
            ->where('counts', ['submitted' => 2, 'approved' => 1, 'rejected' => 0])
            ->has('applications.data', 2)
            ->where('applications.data.0.id', $older->id)
            ->where('applications.data.1.id', $newer->id)
            ->where('applications.data.0.applicant.national_id', $older->applicant->national_id)
            ->has('applications.meta.total'));
});

it('filters by status', function () {
    Application::factory()->for($this->goodConduct)->create();
    $approved = Application::factory()->for($this->goodConduct)->approved()->create();

    $this->actingAs($this->reviewer)
        ->get('/staff?status=approved')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('status', 'approved')
            ->has('applications.data', 1)
            ->where('applications.data.0.id', $approved->id));
});

it('rejects an unknown status filter', function () {
    $this->actingAs($this->reviewer)->get('/staff?status=lost')->assertSessionHasErrors('status');
});

it('never shows another ministry’s requests', function () {
    $otherService = Service::factory()->for(GovEntity::factory()->create(['code' => 'MUN']))->create();
    Application::factory()->for($otherService)->create();

    $this->actingAs($this->reviewer)
        ->get('/staff')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('applications.data', 0)
            ->where('counts.submitted', 0));
});

it('flags requests waiting longer than the SLA, counting only Sunday to Thursday', function () {
    $this->travelTo(now()->parse('2026-10-11 10:00')); // a Sunday; the SLA is 3 working days

    $onTime = Application::factory()->for($this->goodConduct)->create(['submitted_at' => '2026-10-07 09:00']); // Wed: Thu, Sun = 2
    $late = Application::factory()->for($this->goodConduct)->create(['submitted_at' => '2026-10-04 09:00']); // Sun: Mon–Thu, Sun = 5

    $this->actingAs($this->reviewer)
        ->get('/staff')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('applications.data.0.id', $late->id)
            ->where('applications.data.0.sla', ['working_days' => 3, 'working_days_waiting' => 5, 'is_overdue' => true])
            ->where('applications.data.1.id', $onTime->id)
            ->where('applications.data.1.sla.working_days_waiting', 2)
            ->where('applications.data.1.sla.is_overdue', false));
});

it('never marks a decided request as overdue', function () {
    $application = Application::factory()->for($this->goodConduct)->create([
        'status' => ApplicationStatus::Rejected,
        'submitted_at' => now()->subDays(20),
        'decided_at' => now(),
    ]);

    expect($application->isOverdue())->toBeFalse();
});

it('opens a request with its timeline and certificate', function () {
    $application = app(SubmitApplication::class)->handle(User::factory()->create(), $this->goodConduct, [
        'purpose' => 'employment',
        'addressed_to' => 'Oasis Energy Company',
        'certificate_language' => 'ar',
    ]);
    app(ApproveApplication::class)->handle($application, $this->reviewer, 'All checks clear.');

    $this->actingAs($this->reviewer)
        ->get("/staff/applications/{$application->id}")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('staff/applications/show')
            ->where('application.id', $application->id)
            ->where('application.status', 'approved')
            ->where('application.addressed_to', 'Oasis Energy Company')
            ->has('application.timeline', 2)
            ->where('application.timeline.1.to_status', 'approved')
            ->where('application.timeline.1.note', 'All checks clear.')
            ->where('application.certificate.serial_number', fn (string $serial) => str_starts_with($serial, 'MOI-GC-'))
            ->where('decidedBy', $this->reviewer->name));
});

it('hides another ministry’s request behind a 404', function () {
    $otherService = Service::factory()->for(GovEntity::factory()->create(['code' => 'MUN']))->create();
    $application = Application::factory()->for($otherService)->create();

    $this->actingAs($this->reviewer)->get("/staff/applications/{$application->id}")->assertNotFound();
});

it('keeps citizens out of request details, even their own', function () {
    $application = Application::factory()->for($this->goodConduct)->create();

    $this->actingAs($application->applicant)->get("/staff/applications/{$application->id}")->assertForbidden();
});
