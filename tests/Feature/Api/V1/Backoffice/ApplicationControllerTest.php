<?php

use App\Models\Application;
use App\Models\GovEntity;
use App\Models\Service;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->ministry = GovEntity::factory()->interior()->create();
    $this->service = Service::factory()->goodConduct()->for($this->ministry)->create();
    $this->reviewer = User::factory()->employee($this->ministry)->create();
});

describe('index', function () {
    it('lists the ministry\'s applications oldest first with applicant details', function () {
        $newer = Application::factory()->for($this->service)->create(['submitted_at' => now()]);
        $older = Application::factory()->for($this->service)->create(['submitted_at' => now()->subDay()]);
        Sanctum::actingAs($this->reviewer);

        $this->getJson('/api/v1/backoffice/applications')
            ->assertOk()
            ->assertJsonPath('data.0.id', $older->id)
            ->assertJsonPath('data.1.id', $newer->id)
            ->assertJsonPath('data.0.applicant.national_id', $older->applicant->national_id);
    });

    it('filters by status', function () {
        Application::factory()->for($this->service)->create();
        $approved = Application::factory()->approved()->for($this->service)->create();
        Sanctum::actingAs($this->reviewer);

        $this->getJson('/api/v1/backoffice/applications?status=approved')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $approved->id);
    });

    it('rejects an unknown status filter', function () {
        Sanctum::actingAs($this->reviewer);

        $this->getJson('/api/v1/backoffice/applications?status=lost')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    });

    it('excludes applications of other ministries', function () {
        Application::factory()->create();
        Sanctum::actingAs($this->reviewer);

        $this->getJson('/api/v1/backoffice/applications')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    });

    it('forbids citizens with 403', function () {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/backoffice/applications')->assertForbidden();
    });
});

describe('show', function () {
    it('shows an application of the reviewer\'s ministry', function () {
        $application = Application::factory()->for($this->service)->create();
        Sanctum::actingAs($this->reviewer);

        $this->getJson("/api/v1/backoffice/applications/{$application->id}")
            ->assertOk()
            ->assertJsonPath('data.applicant.id', $application->applicant_id);
    });

    it('returns 404 for another ministry\'s application', function () {
        $application = Application::factory()->create();
        Sanctum::actingAs($this->reviewer);

        $this->getJson("/api/v1/backoffice/applications/{$application->id}")->assertNotFound();
    });
});
