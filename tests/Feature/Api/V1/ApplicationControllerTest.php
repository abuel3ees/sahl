<?php

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\Service;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->service = Service::factory()->goodConduct()->create();
    $this->citizen = User::factory()->create();
});

describe('store', function () {
    it('submits an application and records the first timeline entry', function () {
        Sanctum::actingAs($this->citizen);

        $response = $this->postJson('/api/v1/applications', [
            'service' => 'good-conduct-certificate',
            'purpose' => 'employment',
            'addressed_to' => 'Al Noor Hospital',
            'certificate_language' => 'en',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.purpose', 'employment')
            ->assertJsonPath('data.addressed_to', 'Al Noor Hospital')
            ->assertJsonPath('data.timeline.0.to_status', 'submitted')
            ->assertJsonPath('data.timeline.0.actor', 'applicant');

        expect($response->json('data.reference_number'))->toMatch('/^SHL-\d{4}-[A-Z2-9]{6}$/');

        $application = Application::sole();
        expect($application->applicant_id)->toBe($this->citizen->id)
            ->and($application->gov_entity_id)->toBe($this->service->gov_entity_id)
            ->and($application->status)->toBe(ApplicationStatus::Submitted);
    });

    it('rejects a second open application for the same service', function () {
        Application::factory()->for($this->service)->for($this->citizen, 'applicant')->create();
        Sanctum::actingAs($this->citizen);

        $this->postJson('/api/v1/applications', [
            'service' => 'good-conduct-certificate',
            'purpose' => 'travel',
            'certificate_language' => 'ar',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['service' => 'You already have an open application for this service.']);
    });

    it('allows a new application once the previous one is decided', function () {
        Application::factory()->approved()->for($this->service)->for($this->citizen, 'applicant')->create();
        Sanctum::actingAs($this->citizen);

        $this->postJson('/api/v1/applications', [
            'service' => 'good-conduct-certificate',
            'purpose' => 'travel',
            'certificate_language' => 'ar',
        ])->assertCreated();
    });

    it('validates the purpose and language against the allowed values', function () {
        Sanctum::actingAs($this->citizen);

        $this->postJson('/api/v1/applications', [
            'service' => 'good-conduct-certificate',
            'purpose' => 'fun',
            'certificate_language' => 'fr',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['purpose', 'certificate_language']);
    });

    it('rejects an inactive service', function () {
        $inactive = Service::factory()->inactive()->create();
        Sanctum::actingAs($this->citizen);

        $this->postJson('/api/v1/applications', [
            'service' => $inactive->slug,
            'purpose' => 'travel',
            'certificate_language' => 'ar',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['service']);
    });

    it('forbids employees from applying with 403', function () {
        Sanctum::actingAs(User::factory()->employee()->create());

        $this->postJson('/api/v1/applications', [
            'service' => 'good-conduct-certificate',
            'purpose' => 'travel',
            'certificate_language' => 'ar',
        ])->assertForbidden();

        expect(Application::count())->toBe(0);
    });

    it('returns 401 when no token is provided', function () {
        $this->postJson('/api/v1/applications', [])->assertUnauthorized();
    });
});

describe('index', function () {
    it('lists only the signed-in citizen\'s applications', function () {
        $mine = Application::factory()->for($this->service)->for($this->citizen, 'applicant')->create();
        Application::factory()->for($this->service)->create();
        Sanctum::actingAs($this->citizen);

        $this->getJson('/api/v1/applications')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->id)
            ->assertJsonPath('meta.total', 1);
    });
});

describe('show', function () {
    it('shows the citizen\'s own application with its timeline', function () {
        $application = Application::factory()->for($this->service)->for($this->citizen, 'applicant')->create();
        $application->statusChanges()->create([
            'to_status' => ApplicationStatus::Submitted,
            'actor_id' => $this->citizen->id,
            'created_at' => now(),
        ]);
        Sanctum::actingAs($this->citizen);

        $this->getJson("/api/v1/applications/{$application->id}")
            ->assertOk()
            ->assertJsonPath('data.reference_number', $application->reference_number)
            ->assertJsonCount(1, 'data.timeline')
            ->assertJsonPath('data.certificate', null);
    });

    it('returns 404 for another citizen\'s application', function () {
        $application = Application::factory()->for($this->service)->create();
        Sanctum::actingAs($this->citizen);

        $this->getJson("/api/v1/applications/{$application->id}")->assertNotFound();
    });
});
