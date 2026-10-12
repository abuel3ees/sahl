param location string = resourceGroup().location
param administratorLogin string = 'sahladmin'
@secure()
param administratorPassword string
@minLength(7)   // the shortest IPv4 address, e.g. 1.1.1.1
param allowedClientIp string
@secure()
param appKey string = 'base64:xXM+bU/9maC4WM9+a1YWtV8LdPtDzPkrkaefJDan+6c='
// Server names are public DNS names (<name>.postgres.database.azure.com), so they must be unique worldwide.
// uniqueString() hashes the resource group ID: unique, but the same on every redeploy.
var serverName = 'psql-sahl-dev-sdc-${take(uniqueString(resourceGroup().id), 6)}'

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

output serverFqdn string = postgres.properties.fullyQualifiedDomainName

resource app 'Microsoft.Web/serverfarms@2025-03-01' = {
  name: 'asp-sahl-dev-sdc-${location}'
  location: location
  sku: {
    name: 'B1'
  }
  properties: {
    reserved: true
  }
}

