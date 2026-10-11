<?php

use App\Models\Certificate;

it('confirms a valid certificate with only masked personal data', function () {
    $certificate = Certificate::factory()->create();

    $this->getJson("/api/v1/certificates/verify/{$certificate->verification_code}")
        ->assertOk()
        ->assertJsonPath('data.status', 'valid')
        ->assertJsonPath('data.serial_number', $certificate->serial_number)
        ->assertJsonPath('data.holder_national_id_masked', '*******4821')
        ->assertJsonPath('data.issuer.en', 'Ministry of Interior')
        ->assertJsonMissingPath('data.holder_national_id');
});

it('accepts a lowercase verification code', function () {
    $certificate = Certificate::factory()->create();

    $this->getJson('/api/v1/certificates/verify/'.strtolower($certificate->verification_code))->assertOk();
});

it('reports an expired certificate as expired', function () {
    $certificate = Certificate::factory()->expired()->create();

    $this->getJson("/api/v1/certificates/verify/{$certificate->verification_code}")
        ->assertOk()
        ->assertJsonPath('data.status', 'expired');
});

it('returns 404 for an unknown code', function () {
    $this->getJson('/api/v1/certificates/verify/NOTAREALCODE')->assertNotFound();
});

it('returns 429 after 30 verification attempts in a minute', function () {
    foreach (range(1, 30) as $attempt) {
        $this->getJson('/api/v1/certificates/verify/NOTAREALCODE');
    }

    $this->getJson('/api/v1/certificates/verify/NOTAREALCODE')->assertTooManyRequests();
});
