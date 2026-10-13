<?php

namespace App\Database;

use Illuminate\Database\Connectors\PostgresConnector;
use PDO;

/**
 * Postgres connector that can sign in with the web app's managed identity.
 *
 * With 'entra_auth' on (DB_ENTRA_AUTH=true), the password is a fresh Entra access token instead of DB_PASSWORD.
 * It is fetched on every new connection, so a reconnect never uses an expired token. Otherwise it is Laravel's own.
 */
class EntraPostgresConnector extends PostgresConnector
{
    public function __construct(private ManagedIdentityToken $tokens) {}

    /**
     * @param  string  $dsn
     * @param  array<string, mixed>  $config
     * @param  array<int, mixed>  $options
     */
    public function createConnection($dsn, array $config, array $options): PDO
    {
        if ($config['entra_auth'] ?? false) {
            $config['password'] = $this->tokens->forResource(ManagedIdentityToken::DATABASE_RESOURCE);
        }

        return parent::createConnection($dsn, $config, $options);
    }
}
