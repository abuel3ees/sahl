<?php

use App\Models\Application;
use App\Models\GovEntity;
use App\Models\User;
use App\Policies\ApplicationPolicy;

beforeEach(function () {
    $this->ministry = GovEntity::factory()->create();
    $this->application = Application::factory()->create(['gov_entity_id' => $this->ministry->id]);
    $this->policy = new ApplicationPolicy;
});

test('the applicant can view their application', function () {
    expect($this->policy->view($this->application->applicant, $this->application)->allowed())->toBeTrue();
});

test('another citizen gets a 404 when viewing', function () {
    $response = $this->policy->view(User::factory()->create(), $this->application);

    expect($response->denied())->toBeTrue()->and($response->status())->toBe(404);
});

test('an employee of the owning ministry can view and decide', function () {
    $reviewer = User::factory()->employee($this->ministry)->create();

    expect($this->policy->view($reviewer, $this->application)->allowed())->toBeTrue()
        ->and($this->policy->decide($reviewer, $this->application)->allowed())->toBeTrue();
});

test('an employee of another ministry gets a 404 when viewing or deciding', function () {
    $outsider = User::factory()->employee()->create();

    expect($this->policy->view($outsider, $this->application)->status())->toBe(404)
        ->and($this->policy->decide($outsider, $this->application)->status())->toBe(404);
});

test('an employee cannot decide their own application', function () {
    $reviewer = User::factory()->employee($this->ministry)->create();
    $ownApplication = Application::factory()->create([
        'gov_entity_id' => $this->ministry->id,
        'applicant_id' => $reviewer->id,
    ]);

    expect($this->policy->decide($reviewer, $ownApplication)->denied())->toBeTrue();
});

test('only citizens can create applications', function () {
    expect($this->policy->create(User::factory()->create()))->toBeTrue()
        ->and($this->policy->create(User::factory()->employee()->create()))->toBeFalse();
});
