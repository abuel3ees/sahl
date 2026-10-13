// Values for main.bicep on the current subscription. On the new account, change the IDs here.
// Secrets are never written here: they come from your shell (readEnvironmentVariable) or from Key Vault (getSecret).
using 'main.bicep'

param allowedClientIp = readEnvironmentVariable('MYIP')
// The DB admin password is read from the vault, so the server and the app always use the same one.
// Azure reads it at deploy time; it never passes through your shell. Args: subscription, resource group, vault, secret.
param administratorPassword = az.getSecret('01cd0fe3-a684-4dd3-a5d5-759e27df84e4', 'rg-sahl-dev-sdc', 'kv-sahl-dev-sdc-vmi3r3', 'db-password')

param adminPrincipalId = '46ca405c-0005-44b3-a769-ad6adaf6cb9c'
// Your sign-in name contains your email address, so it is read from the shell instead of being published in the repo:
// export SAHL_ADMIN_UPN=$(az ad signed-in-user show --query userPrincipalName -o tsv)
param adminPrincipalName = readEnvironmentVariable('SAHL_ADMIN_UPN')
// Alert emails go here; also kept out of the repo: export SAHL_ALERT_EMAIL=you@example.com
param alertEmail = readEnvironmentVariable('SAHL_ALERT_EMAIL')

// Staff: workforce tenant swambfirehotmail.onmicrosoft.com, app "Sahl (local)"
param entraTenantId = '509dc1f4-3b62-4fa1-8b0b-bf0943e3812f'
param entraClientId = '2756182e-2801-4e3f-96c7-aab8e24faf7b'

// Citizens: external tenant sahlcitizens.onmicrosoft.com, app "Sahl (local)"
param entraCitizenTenantId = '004a7659-eda0-49a2-b4c5-597a44434dea'
param entraCitizenClientId = '9bfd5683-fd27-4006-bf7c-883a925b2a00'
param entraCitizenDomain = 'sahlcitizens'
