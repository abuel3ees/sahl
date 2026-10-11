<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('issues a token when a citizen signs in with their national id', function () {
    $citizen = User::factory()->create(['national_id' => '28763404821']);

    $this->postJson('/api/v1/auth/tokens', [
        'login' => '28763404821',
        'password' => 'password',
        'device_name' => 'postman',
    ])
        ->assertCreated()
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('user.id', $citizen->id)
        ->assertJsonStructure(['token', 'expires_at']);

    expect($citizen->tokens()->count())->toBe(1);
});

it('issues a token when an employee signs in with their email', function () {
    User::factory()->employee()->create(['email' => 'reviewer@moi.sahl.test']);

    $this->postJson('/api/v1/auth/tokens', [
        'login' => 'reviewer@moi.sahl.test',
        'password' => 'password',
        'device_name' => 'postman',
    ])
        ->assertCreated()
        ->assertJsonPath('user.type', 'employee');
});

it('rejects a wrong password with 422', function () {
    User::factory()->create(['national_id' => '28763404821']);

    $this->postJson('/api/v1/auth/tokens', [
        'login' => '28763404821',
        'password' => 'wrong-password',
        'device_name' => 'postman',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['login' => 'These credentials do not match our records.']);
});

it('requires login, password and device name', function () {
    $this->postJson('/api/v1/auth/tokens', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['login', 'password', 'device_name']);
});

it('revokes the current token on sign out', function () {
    $citizen = User::factory()->create();
    $token = $citizen->createToken('postman')->plainTextToken;

    $this->withToken($token)->deleteJson('/api/v1/auth/tokens/current')->assertNoContent();

    expect($citizen->tokens()->count())->toBe(0);
});

it('returns the signed-in user', function () {
    Sanctum::actingAs($citizen = User::factory()->create());

    $this->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.id', $citizen->id)
        ->assertJsonPath('data.type', 'citizen');
});

it('returns 401 when no token is provided', function () {
    $this->getJson('/api/v1/me')->assertUnauthorized();
});
