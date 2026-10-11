<?php

use App\Auth\EntraExternalIdProvider;
use Firebase\JWT\JWT;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Request;
use Laravel\Socialite\Two\InvalidStateException;
use SocialiteProviders\Manager\Config;

/*
 * The ID token is the only proof of who signed in, so its checks are tested with real signatures:
 * a throwaway RSA key plays Microsoft's signing key, and Guzzle's MockHandler plays the tenant's endpoints.
 */

const TENANT_ID = '004a7659-0000-4000-8000-000000000001';
const CLIENT_ID = '9bfd5683-0000-4000-8000-000000000002';

beforeEach(function () {
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($key, $this->privateKey);
    $rsa = openssl_pkey_get_details($key)['rsa'];
    $this->jwks = ['keys' => [[
        // Shaped like Entra's real keys, which have no "alg".
        'kty' => 'RSA', 'use' => 'sig', 'kid' => 'test-key',
        'n' => rtrim(strtr(base64_encode($rsa['n']), '+/', '-_'), '='),
        'e' => rtrim(strtr(base64_encode($rsa['e']), '+/', '-_'), '='),
    ]]];
    $this->issuer = 'https://'.TENANT_ID.'.ciamlogin.com/'.TENANT_ID.'/v2.0';
    $this->requests = new ArrayObject;
});

/**
 * @param  array<string, mixed>  $overrides
 */
function idToken(array $overrides = [], ?string $privateKey = null): string
{
    $claims = [
        'iss' => test()->issuer,
        'aud' => CLIENT_ID,
        'exp' => time() + 3600,
        'iat' => time(),
        'sub' => 'subject-1',
        'oid' => '6f1c2a3b-0000-4000-8000-0000000000aa',
        'name' => 'Ahmed Al-Mansouri',
        'email' => 'ahmed@example.com',
        'national_id' => '28763404821',
        ...$overrides,
    ];

    return JWT::encode($claims, $privateKey ?? test()->privateKey, 'RS256', 'test-key');
}

function providerReturning(string $idToken): EntraExternalIdProvider
{
    $handler = HandlerStack::create(new MockHandler([
        new Response(200, [], json_encode(['access_token' => 'access', 'id_token' => $idToken, 'token_type' => 'Bearer'])),
        new Response(200, [], json_encode(['issuer' => test()->issuer, 'jwks_uri' => 'https://sahlcitizens.ciamlogin.com/'.TENANT_ID.'/discovery/v2.0/keys'])),
        new Response(200, [], json_encode(test()->jwks)),
    ]));
    $history = test()->requests; // an ArrayObject, so the recorder and the test share it
    $handler->push(Middleware::history($history));

    $provider = new EntraExternalIdProvider(Request::create('/auth/citizen/callback', 'GET', ['code' => 'one-time-code']), CLIENT_ID, 'secret', 'http://localhost:8123/auth/citizen/callback');

    return $provider
        ->setConfig(new Config(CLIENT_ID, 'secret', 'http://localhost:8123/auth/citizen/callback', ['tenant' => TENANT_ID, 'domain' => 'sahlcitizens']))
        ->setHttpClient(new Client(['handler' => $handler]))
        ->stateless();
}

it('sends citizens to the tenant’s ciamlogin.com sign-in page', function () {
    $location = providerReturning(idToken())->redirect()->getTargetUrl();

    expect($location)
        ->toStartWith('https://sahlcitizens.ciamlogin.com/'.TENANT_ID.'/oauth2/v2.0/authorize?')
        ->toContain('client_id='.CLIENT_ID)
        ->toContain('scope=openid+profile+email')
        ->toContain('response_type=code');
});

it('accepts a correctly signed ID token and maps the citizen from its claims', function () {
    $user = providerReturning(idToken())->user();

    expect($user->getId())->toBe('6f1c2a3b-0000-4000-8000-0000000000aa')
        ->and($user->getName())->toBe('Ahmed Al-Mansouri')
        ->and($user->getEmail())->toBe('ahmed@example.com')
        ->and($user->getRaw()['national_id'])->toBe('28763404821');

    // Token from the tenant's token endpoint, then discovery and keys from the same tenant. No Graph call.
    expect(collect($this->requests->getArrayCopy())->map(fn (array $entry) => (string) $entry['request']->getUri())->all())->toBe([
        'https://sahlcitizens.ciamlogin.com/'.TENANT_ID.'/oauth2/v2.0/token',
        'https://sahlcitizens.ciamlogin.com/'.TENANT_ID.'/v2.0/.well-known/openid-configuration',
        'https://sahlcitizens.ciamlogin.com/'.TENANT_ID.'/discovery/v2.0/keys',
    ]);
});

it('rejects a token that fails a check', function (Closure $token) {
    providerReturning($token())->user();
})->with([
    'signed with another key' => [function () {
        openssl_pkey_export(openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]), $forger);

        return idToken([], $forger);
    }],
    'issued for another app' => [fn () => idToken(['aud' => 'someone-elses-client-id'])],
    'issued by another tenant' => [fn () => idToken(['iss' => 'https://evil.ciamlogin.com/other/v2.0'])],
    'expired' => [fn () => idToken(['exp' => time() - 600])],
])->throws(InvalidStateException::class);
