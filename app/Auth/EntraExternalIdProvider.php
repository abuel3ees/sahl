<?php

namespace App\Auth;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Laravel\Socialite\Two\InvalidStateException;
use SocialiteProviders\Manager\OAuth2\AbstractProvider;
use SocialiteProviders\Manager\OAuth2\User;
use Throwable;

/**
 * Socialite driver for Microsoft Entra External ID (citizen sign-in).
 *
 * External tenants sign people in at `{domain}.ciamlogin.com`, not `login.microsoftonline.com`, so the community
 * Microsoft driver doesn't fit. Everything Sahl needs (object ID, name, email, `national_id`) is in the ID token,
 * so this driver validates that token and never calls Microsoft Graph.
 */
class EntraExternalIdProvider extends AbstractProvider
{
    public const IDENTIFIER = 'ENTRA_EXTERNAL_ID';

    /**
     * Entra signs ID tokens with RS256. Its published keys (JWKS) omit "alg", so it is supplied here.
     */
    private const SIGNING_ALGORITHM = 'RS256';

    /**
     * @var array<int, string>
     */
    protected $scopes = ['openid', 'profile', 'email'];

    protected $scopeSeparator = ' ';

    /**
     * @return array<int, string>
     */
    public static function additionalConfigKeys(): array
    {
        return ['tenant', 'domain'];
    }

    protected function getAuthUrl($state): string
    {
        return $this->buildAuthUrlFromBase($this->authority().'/oauth2/v2.0/authorize', $state);
    }

    protected function getTokenUrl(): string
    {
        return $this->authority().'/oauth2/v2.0/token';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getTokenFields($code): array
    {
        return [...parent::getTokenFields($code), 'scope' => $this->formatScopes($this->getScopes(), $this->scopeSeparator)];
    }

    /**
     * Build the user from the validated ID token instead of calling Graph.
     *
     * @return array<string, mixed>
     */
    protected function getUserByToken($token): array
    {
        $idToken = Arr::get($this->credentialsResponseBody, 'id_token');

        if (! is_string($idToken)) {
            throw new InvalidStateException('Entra External ID returned no ID token.');
        }

        return (array) $this->validate($idToken);
    }

    /**
     * @param  array<string, mixed>  $user
     */
    protected function mapUserToObject(array $user): User
    {
        return (new User)->setRaw($user)->map([
            'id' => $user['oid'] ?? $user['sub'],
            'name' => $user['name'] ?? null,
            'email' => $user['email'] ?? $user['preferred_username'] ?? null,
            'nickname' => null,
            'avatar' => null,
        ]);
    }

    /**
     * e.g. https://sahlcitizens.ciamlogin.com/{tenant-id}
     */
    private function authority(): string
    {
        return sprintf('https://%s.ciamlogin.com/%s', $this->getConfig('domain'), $this->getConfig('tenant'));
    }

    /**
     * Check the signature against the tenant's published keys, then issuer, audience and expiry.
     */
    private function validate(string $idToken): object
    {
        $configuration = $this->openIdConfiguration();

        try {
            $claims = JWT::decode($idToken, JWK::parseKeySet($this->signingKeys($configuration['jwks_uri']), self::SIGNING_ALGORITHM));
        } catch (Throwable $exception) {
            // Keys rotate: refetch once before giving up.
            Cache::forget($this->cacheKey('jwks'));
            try {
                $claims = JWT::decode($idToken, JWK::parseKeySet($this->signingKeys($configuration['jwks_uri']), self::SIGNING_ALGORITHM));
            } catch (Throwable) {
                throw new InvalidStateException('ID token signature is invalid: '.$exception->getMessage());
            }
        }

        if (($claims->iss ?? null) !== $configuration['issuer']) {
            throw new InvalidStateException('ID token issuer does not match the tenant.');
        }

        if (! in_array($this->clientId, (array) ($claims->aud ?? []), true)) {
            throw new InvalidStateException('ID token was not issued for this application.');
        }

        // JWT::decode already rejects expired tokens; this guards against a missing exp claim.
        if (! isset($claims->exp)) {
            throw new InvalidStateException('ID token has no expiry.');
        }

        return $claims;
    }

    /**
     * @return array{issuer: string, jwks_uri: string}
     */
    private function openIdConfiguration(): array
    {
        return Cache::remember($this->cacheKey('openid'), now()->addHour(), function (): array {
            $response = $this->getHttpClient()->get($this->authority().'/v2.0/.well-known/openid-configuration');
            $configuration = json_decode((string) $response->getBody(), true);

            if (! isset($configuration['issuer'], $configuration['jwks_uri'])) {
                throw new InvalidStateException('Entra External ID discovery document is incomplete.');
            }

            return $configuration;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function signingKeys(string $jwksUri): array
    {
        return Cache::remember($this->cacheKey('jwks'), now()->addMinutes(30), fn (): array => json_decode(
            (string) $this->getHttpClient()->get($jwksUri, [RequestOptions::HEADERS => ['Accept' => 'application/json']])->getBody(),
            true,
        ));
    }

    private function cacheKey(string $what): string
    {
        return "entra-external-id:{$what}:".$this->getConfig('tenant');
    }
}
