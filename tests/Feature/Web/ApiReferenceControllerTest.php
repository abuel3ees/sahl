<?php

use Inertia\Testing\AssertableInertia;

it('renders the API reference from the exported OpenAPI document', function () {
    $this->get('/developers')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('developers/api-reference')
            ->where('spec.info.title', 'Sahl API')
            ->has('spec.paths./v1/applications')
            ->where('apiBaseUrl', url('/api')));
});

it('downloads the OpenAPI document as JSON', function () {
    $response = $this->get('/developers/openapi.json');

    $response->assertOk()->assertHeader('Content-Type', 'application/json');
    expect(json_decode($response->baseResponse->getFile()->getContent(), true))->toHaveKey('openapi');
});
