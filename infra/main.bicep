param location string = resourceGroup().location

param administratorLogin string = 'sahladmin'
@secure()
param administratorPassword string

@minLength(7) // the shortest IPv4 address, e.g. 1.1.1.1
param allowedClientIp string

// Your own Entra object ID (`az ad signed-in-user show --query id -o tsv`): gets write access to the vault's secrets
// and becomes the Entra admin of the Postgres server.
param adminPrincipalId string
// Your sign-in name (`az ad signed-in-user show --query userPrincipalName -o tsv`): your Postgres role is named after it.
param adminPrincipalName string

// Entra app registrations (IDs, not secrets). The client secrets live in Key Vault.
param entraTenantId string
param entraClientId string
param entraCitizenTenantId string
param entraCitizenClientId string
param entraCitizenDomain string

// Server, site and vault names are public DNS names, so they must be unique worldwide.
// uniqueString() hashes the resource group ID: unique, but the same on every redeploy.
var suffix = take(uniqueString(resourceGroup().id), 6)
var serverName = 'psql-sahl-dev-sdc-${suffix}'
var siteName = 'app-sahl-dev-sdc-${suffix}'
var vaultName = 'kv-sahl-dev-sdc-${suffix}'
var appUrl = 'https://${siteName}.azurewebsites.net'

// The GitHub repo whose `main` branch may deploy the app (GitHub Actions, .github/workflows/deploy.yml).
// GitHub's OIDC tokens name it as owner@ownerId/repo@repoId: the numeric IDs stop a re-created account or repo
// with the same name from inheriting the trust. Look them up: gh api repos/abuel3ees/sahl --jq '.owner.id, .id'
var githubRepo = 'abuel3ees@170485164/sahl@1414746207'

// Built-in role IDs (the same in every tenant).
var websiteContributor = 'de139f84-1756-47ae-9be6-808fbbe84772'
var keyVaultSecretsUser = '4633458b-17de-408a-b874-0445c86b69e6'
var keyVaultSecretsOfficer = 'b86a8fe4-44ce-4948-aee5-eccb2c155cd7'

// App Settings value that App Service resolves from Key Vault with the web app's managed identity.
func vaultRef(vault string, secret string) string => '@Microsoft.KeyVault(VaultName=${vault};SecretName=${secret})'

resource postgres 'Microsoft.DBforPostgreSQL/flexibleServers@2025-08-01' = {
  name: serverName
  location: location
  sku: {
    name: 'Standard_B1ms'
    tier: 'Burstable'
  }
  properties: {
    administratorLogin: administratorLogin
    administratorLoginPassword: administratorPassword
    version: '17'
    storage: {
      storageSizeGB: 32
    }
    backup: {
      backupRetentionDays: 7
      geoRedundantBackup: 'Disabled'
    }
    highAvailability: {
      mode: 'Disabled'
    }
    network: {
      publicNetworkAccess: 'Enabled'
    }
    // Two ways to log in: Entra tokens (people and managed identities) and, for now, the sahladmin password.
    authConfig: {
      activeDirectoryAuth: 'Enabled'
      passwordAuth: 'Enabled'
      tenantId: tenant().tenantId
    }
  }

  // You, as the server's Entra admin: you can create Postgres roles for other Entra identities (the web app's).
  resource entraAdmin 'administrators' = {
    name: adminPrincipalId
    properties: {
      principalName: adminPrincipalName
      principalType: 'User'
      tenantId: tenant().tenantId
    }
  }

  resource database 'databases' = {
    name: 'sahl'
  }

  resource allowMyMac 'firewallRules' = {
    name: 'AllowMyMac'
    properties: {
      startIpAddress: allowedClientIp
      endIpAddress: allowedClientIp
    }
  }

  // 0.0.0.0 is a special value: "allow other Azure services", so App Service can connect
  resource allowAzureServices 'firewallRules' = {
    name: 'AllowAzureServices'
    properties: {
      startIpAddress: '0.0.0.0'
      endIpAddress: '0.0.0.0'
    }
  }
}

// The App Service plan is the server we rent (size and price). reserved: true is what makes it Linux.
resource plan 'Microsoft.Web/serverfarms@2025-03-01' = {
  name: 'asp-sahl-dev-sdc'
  location: location
  kind: 'linux'
  sku: {
    name: 'B1'
  }
  properties: {
    reserved: true
  }
}

// The web app runs on the plan. It is not a child of the plan: serverFarmId links to it, like a foreign key.
resource webapp 'Microsoft.Web/sites@2025-03-01' = {
  name: siteName
  location: location
  kind: 'app,linux'
  // A managed identity: the app gets its own identity in Entra and uses it to read Key Vault. No password needed.
  identity: {
    type: 'SystemAssigned'
  }
  properties: {
    serverFarmId: plan.id
    httpsOnly: true
    siteConfig: {
      linuxFxVersion: 'PHP|8.4'
      alwaysOn: true
      minTlsVersion: '1.2'
      ftpsState: 'Disabled'
      // Points nginx at public/ and caches Laravel's config on every start (infra/app-service/startup.sh).
      appCommandLine: '/home/site/wwwroot/infra/app-service/startup.sh'
      // Injected into PHP as environment variables: this is the app's .env on Azure. Values must be strings.
      // Secrets are Key Vault references: App Service fetches the real values; PHP sees plain env vars.
      appSettings: [
        { name: 'APP_NAME', value: 'Sahl' }
        { name: 'APP_ENV', value: 'production' }
        { name: 'APP_DEBUG', value: 'false' }
        { name: 'APP_KEY', value: vaultRef(vaultName, 'app-key') }
        { name: 'APP_URL', value: appUrl }
        { name: 'APP_LOCALE', value: 'ar' }
        { name: 'APP_FALLBACK_LOCALE', value: 'en' }
        { name: 'LOG_CHANNEL', value: 'stderr' }
        { name: 'LOG_LEVEL', value: 'info' }
        { name: 'DB_CONNECTION', value: 'pgsql' }
        { name: 'DB_HOST', value: postgres.properties.fullyQualifiedDomainName }
        { name: 'DB_PORT', value: '5432' }
        { name: 'DB_DATABASE', value: 'sahl' }
        // The app signs in with its managed identity: its Postgres role is named after the site, and the password
        // is an Entra token Laravel fetches itself (App\Database\EntraPostgresConnector). No DB_PASSWORD.
        { name: 'DB_USERNAME', value: siteName }
        { name: 'DB_ENTRA_AUTH', value: 'true' }
        { name: 'DB_SSLMODE', value: 'require' }
        { name: 'ENTRA_TENANT_ID', value: entraTenantId }
        { name: 'ENTRA_CLIENT_ID', value: entraClientId }
        { name: 'ENTRA_CLIENT_SECRET', value: vaultRef(vaultName, 'entra-client-secret') }
        { name: 'ENTRA_REDIRECT_URI', value: '${appUrl}/auth/staff/callback' }
        { name: 'ENTRA_CITIZEN_TENANT_ID', value: entraCitizenTenantId }
        { name: 'ENTRA_CITIZEN_CLIENT_ID', value: entraCitizenClientId }
        { name: 'ENTRA_CITIZEN_CLIENT_SECRET', value: vaultRef(vaultName, 'entra-citizen-client-secret') }
        { name: 'ENTRA_CITIZEN_REDIRECT_URI', value: '${appUrl}/auth/citizen/callback' }
        { name: 'ENTRA_CITIZEN_DOMAIN', value: entraCitizenDomain }
      ]
    }
  }

  // Container logs (PHP's stderr, where Laravel logs) kept on the app's file system: `az webapp log tail` shows them.
  resource logs 'config' = {
    name: 'logs'
    properties: {
      httpLogs: {
        fileSystem: {
          enabled: true
          retentionInDays: 3
          retentionInMb: 100
        }
      }
    }
  }
}

// The vault for the app's secrets. Access is by Azure roles (RBAC), not vault access policies.
resource vault 'Microsoft.KeyVault/vaults@2026-05-15' = {
  name: vaultName
  location: location
  properties: {
    tenantId: tenant().tenantId
    sku: {
      family: 'A'
      name: 'standard'
    }
    enableRbacAuthorization: true
    // Lets a deployment read a secret from here as a parameter value (getSecret() in main.bicepparam).
    enabledForTemplateDeployment: true
    // Deleted vaults stay recoverable (and their name reserved) this many days. 7 is the minimum.
    softDeleteRetentionInDays: 7
  }
}

// The web app may read secrets from this vault, and nothing else.
resource appReadsSecrets 'Microsoft.Authorization/roleAssignments@2022-04-01' = {
  scope: vault
  name: guid(vault.id, webapp.id, keyVaultSecretsUser)
  properties: {
    roleDefinitionId: subscriptionResourceId('Microsoft.Authorization/roleDefinitions', keyVaultSecretsUser)
    principalId: webapp.identity.principalId
    principalType: 'ServicePrincipal'
  }
}

// You may write secrets (being subscription Owner is not enough in an RBAC vault).
resource adminManagesSecrets 'Microsoft.Authorization/roleAssignments@2022-04-01' = {
  scope: vault
  name: guid(vault.id, adminPrincipalId, keyVaultSecretsOfficer)
  properties: {
    roleDefinitionId: subscriptionResourceId('Microsoft.Authorization/roleDefinitions', keyVaultSecretsOfficer)
    principalId: adminPrincipalId
    principalType: 'User'
  }
}

// GitHub Actions' identity in Azure. No password: Azure trusts the short-lived tokens GitHub issues to the workflow (OIDC).
resource githubIdentity 'Microsoft.ManagedIdentity/userAssignedIdentities@2023-01-31' = {
  name: 'id-sahl-github'
  location: location

  // Trusted: tokens for workflows running on the main branch of this repo. Forks, pull requests and other branches are not.
  resource mainBranch 'federatedIdentityCredentials' = {
    name: 'github-main'
    properties: {
      issuer: 'https://token.actions.githubusercontent.com'
      subject: 'repo:${githubRepo}:ref:refs/heads/main'
      audiences: [
        'api://AzureADTokenExchange'
      ]
    }
  }
}

// GitHub may deploy code to the web app, and nothing else (not the database, not the vault).
resource githubDeploysApp 'Microsoft.Authorization/roleAssignments@2022-04-01' = {
  scope: webapp
  name: guid(webapp.id, githubIdentity.id, websiteContributor)
  properties: {
    roleDefinitionId: subscriptionResourceId('Microsoft.Authorization/roleDefinitions', websiteContributor)
    principalId: githubIdentity.properties.principalId
    principalType: 'ServicePrincipal'
  }
}

output serverFqdn string = postgres.properties.fullyQualifiedDomainName
output appUrl string = 'https://${webapp.properties.defaultHostName}'
output vaultName string = vault.name
// Goes into GitHub as the variable AZURE_CLIENT_ID (not a secret).
output githubClientId string = githubIdentity.properties.clientId
