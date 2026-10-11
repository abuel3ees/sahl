<?php

use App\Http\Resources\Api\V1\CertificateResource;
use App\Models\Certificate;
use Inertia\Testing\AssertableInertia;

it('shows the public verification form without signing in', function () {
    $this->get('/verify')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('verify/index'));
});

it('confirms a valid certificate with the same masked data as the API', function () {
    $certificate = Certificate::factory()->create();

    $this->get("/verify/{$certificate->verification_code}")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('verify/show')
            ->where('code', $certificate->verification_code)
            ->where('certificate.status', 'valid')
            ->where('certificate.serial_number', $certificate->serial_number)
            ->where('certificate.holder_national_id_masked', '*******4821')
            ->missing('certificate.holder_national_id'));
});

it('accepts a lowercase code', function () {
    $certificate = Certificate::factory()->create();

    $this->get('/verify/'.strtolower($certificate->verification_code))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('code', $certificate->verification_code));
});

it('reports an expired certificate', function () {
    $certificate = Certificate::factory()->expired()->create();

    $this->get("/verify/{$certificate->verification_code}")
        ->assertInertia(fn (AssertableInertia $page) => $page->where('certificate.status', 'expired'));
});

it('answers 404 with a helpful page for an unknown code', function () {
    $this->get('/verify/NOTAREALCODE')
        ->assertNotFound()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('verify/show')
            ->where('code', 'NOTAREALCODE')
            ->where('certificate', null));
});

it('rate limits checks to 30 a minute per visitor', function () {
    foreach (range(1, 30) as $attempt) {
        $this->get('/verify/NOTAREALCODE');
    }

    $this->get('/verify/NOTAREALCODE')->assertTooManyRequests();
});

it('puts the web page, not the JSON endpoint, behind the QR code', function () {
    $certificate = Certificate::factory()->create();

    expect(CertificateResource::make($certificate)->resolve()['verification_url'])
        ->toBe(url("/verify/{$certificate->verification_code}"));
});
