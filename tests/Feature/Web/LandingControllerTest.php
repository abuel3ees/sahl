<?php

use App\Models\GovEntity;
use App\Models\Service;
use Inertia\Testing\AssertableInertia;

it('renders the landing page with the featured service exactly as the API shapes it', function () {
    Service::factory()->goodConduct()->for(GovEntity::factory()->interior())->create();

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('landing')
            ->where('featuredService.slug', 'good-conduct-certificate')
            ->where('featuredService.fee.amount_minor', 5000)
            ->where('featuredService.gov_entity.code', 'MOI')
            ->where('featuredService.gov_entity.name.ar', 'وزارة الداخلية'));
});

it('renders without a featured service when it is inactive', function () {
    Service::factory()->goodConduct()->inactive()->create();

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('landing')->where('featuredService', null));
});
