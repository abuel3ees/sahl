<?php

use App\Models\GovEntity;
use App\Models\Service;

it('lists only active services with their ministry', function () {
    $service = Service::factory()->goodConduct()->for(GovEntity::factory()->interior())->create();
    Service::factory()->inactive()->create();

    $this->getJson('/api/v1/services')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', $service->slug)
        ->assertJsonPath('data.0.name.ar', 'شهادة حسن السيرة والسلوك')
        ->assertJsonPath('data.0.fee', ['amount_minor' => 5000, 'currency' => 'QAR'])
        ->assertJsonPath('data.0.gov_entity.code', 'MOI');
});

it('shows a service by slug', function () {
    Service::factory()->goodConduct()->create();

    $this->getJson('/api/v1/services/good-conduct-certificate')
        ->assertOk()
        ->assertJsonPath('data.code', Service::GOOD_CONDUCT);
});

it('returns 404 for an inactive service', function () {
    $service = Service::factory()->inactive()->create();

    $this->getJson("/api/v1/services/{$service->slug}")->assertNotFound();
});
