<?php

use App\Database\ManagedIdentityToken;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/*
 * On Azure the app signs in to Postgres with its managed identity: App Service's local identity endpoint
 * hands out an Entra token, and the connector uses it as the password. Here Http::fake plays that endpoint,
 * and the local test database checks which password was really sent.
 */

beforeEach(function () {
    Cache::store('file')->forget('managed-identity-token:'.ManagedIdentityToken::DATABASE_RESOURCE);
});

afterEach(function () {
    putenv('IDENTITY_ENDPOINT');
    putenv('IDENTITY_HEADER');
});

/**
 * A connection to the local test database that signs in with a token.
 *
 * @return array<string, mixed>
 */
function entraConnection(string $password): array
{
    return array_merge(config('database.connections.pgsql'), ['entra_auth' => true, 'password' => $password]);
}

it('gets a database token from the App Service identity endpoint and caches it', function () {
    putenv('IDENTITY_ENDPOINT=http://localhost:8081/msi/token');
    putenv('IDENTITY_HEADER=header-secret');

    Http::fake(['localhost:8081/*' => Http::response([
        'access_token' => 'entra-token',
        'expires_on' => (string) now()->addHour()->timestamp,
    ])]);

    $tokens = app(ManagedIdentityToken::class);

    expect($tokens->forResource(ManagedIdentityToken::DATABASE_RESOURCE))->toBe('entra-token')
        ->and($tokens->forResource(ManagedIdentityToken::DATABASE_RESOURCE))->toBe('entra-token');

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request->hasHeader('X-IDENTITY-HEADER', 'header-secret')
        && $request['resource'] === 'https://ossrdbms-aad.database.windows.net'
        && $request['api-version'] === '2019-08-01');
});

it('fails clearly outside Azure, where there is no identity endpoint', function () {
    putenv('IDENTITY_ENDPOINT');
    putenv('IDENTITY_HEADER');

    app(ManagedIdentityToken::class)->forResource(ManagedIdentityToken::DATABASE_RESOURCE);
})->throws(RuntimeException::class, 'only set on Azure App Service');

it('signs in to Postgres with the token instead of the configured password', function () {
    // The token is the real password of the local test database; the configured one is wrong.
    $this->mock(ManagedIdentityToken::class)
        ->shouldReceive('forResource')->once()->with(ManagedIdentityToken::DATABASE_RESOURCE)->andReturn('secret');

    config(['database.connections.entra_test' => entraConnection(password: 'not-the-password')]);

    expect(DB::connection('entra_test')->scalar('select 1'))->toBe(1);
});

it('does not fall back to the configured password when the token is rejected', function () {
    $this->mock(ManagedIdentityToken::class)
        ->shouldReceive('forResource')->andReturn('expired-token');

    config(['database.connections.entra_test' => entraConnection(password: 'secret')]);

    DB::connection('entra_test')->scalar('select 1');
})->throws(QueryException::class, 'password authentication failed');

it('uses the configured password when Entra sign-in is off', function () {
    $this->mock(ManagedIdentityToken::class)->shouldNotReceive('forResource');

    config(['database.connections.entra_test' => array_merge(entraConnection(password: 'secret'), ['entra_auth' => false])]);

    expect(DB::connection('entra_test')->scalar('select 1'))->toBe(1);
});
