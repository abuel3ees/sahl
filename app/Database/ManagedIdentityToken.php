<?php

namespace App\Database;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Gets Entra access tokens for the web app's managed identity.
 *
 * On App Service, a local endpoint inside the container hands out tokens for the app's identity. Only code running
 * in this app can reach it, and IDENTITY_HEADER proves the call comes from inside. Tokens last about an hour,
 * so they are cached until shortly before they expire.
 */
class ManagedIdentityToken
{
    /** Audience for Azure Database for PostgreSQL. */
    public const string DATABASE_RESOURCE = 'https://ossrdbms-aad.database.windows.net';

    public function forResource(string $resource): string
    {
        $cacheKey = 'managed-identity-token:'.$resource;

        $cached = Cache::store('file')->get($cacheKey);

        if (is_string($cached)) {
            return $cached;
        }

        // Set by App Service in the container. Read at runtime, never cached in config: the header is a secret.
        $endpoint = getenv('IDENTITY_ENDPOINT');
        $identityHeader = getenv('IDENTITY_HEADER');

        if (! $endpoint || ! $identityHeader) {
            throw new RuntimeException('No managed identity: IDENTITY_ENDPOINT and IDENTITY_HEADER are only set on Azure App Service.');
        }

        $response = Http::withHeaders(['X-IDENTITY-HEADER' => $identityHeader])
            ->timeout(5)
            ->retry(2, 200)
            ->get($endpoint, ['resource' => $resource, 'api-version' => '2019-08-01'])
            ->throw();

        $token = (string) $response->json('access_token');
        $expiresAt = now()->setTimestamp((int) $response->json('expires_on'));

        Cache::store('file')->put($cacheKey, $token, $expiresAt->subMinutes(5));

        return $token;
    }
}
