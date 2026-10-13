# 06 · Key Vault, managed identity and sign-in on Azure (V1 on Azure, step 3)

**Done:** 2026-10-12/13, current subscription, resource group `rg-sahl-dev-sdc` (Sweden Central). Builds on [05](05-azure-app-service.md).

**Result:** the app's secrets live in Key Vault `kv-sahl-dev-sdc-vmi3r3`, not in App Settings. The web app reads them with its own managed identity. Staff sign-in (Entra ID) and citizen sign-in (Sahl ID, Entra External ID) work on **https://app-sahl-dev-sdc-vmi3r3.azurewebsites.net**. The full V1 flow was tested on Azure: submit, approve, certificate, QR verify.

**Cost:** Key Vault Standard charges per operation, fractions of a cent for this app. Managed identities and role assignments are free.

---

## Words used here

| Term | Meaning |
|---|---|
| **Key Vault** (`Microsoft.KeyVault/vaults`) | A locked store for secrets. RBAC mode (`enableRbacAuthorization: true`): who may read or write is decided by Azure roles |
| **Managed identity** | An identity in Entra that Azure creates for the web app (`identity: { type: 'SystemAssigned' }`). The app proves who it is without any password; it lives and dies with the app |
| **Role assignment** | "This identity has this role on this resource". Here: the app gets **Key Vault Secrets User** (read), you get **Key Vault Secrets Officer** (read and write). Being subscription Owner does *not* let you read secrets in an RBAC vault |
| **Key Vault reference** | An App Setting value of the form `@Microsoft.KeyVault(VaultName=…;SecretName=…)`. App Service fetches the secret with the managed identity and gives PHP the plain value |
| **Resource provider** | The Azure service behind a resource type (`Microsoft.KeyVault`). A subscription must register it once before using that type |
| **`.bicepparam`** | A parameter file for a Bicep file. `infra/main.bicepparam` holds the IDs; secrets come from your shell via `readEnvironmentVariable()` |

## Step 1: Register the Key Vault provider (once per subscription)

```bash
az provider register -n Microsoft.KeyVault
az provider show -n Microsoft.KeyVault --query registrationState -o tsv    # wait for: Registered
```

On a **new account**, also register `Microsoft.DBforPostgreSQL` and `Microsoft.Web` (needed by [04](04-azure-postgres.md) and [05](05-azure-app-service.md)).

## Step 2: Infrastructure (Bicep)

`infra/main.bicep` gained:
- `identity: { type: 'SystemAssigned' }` on the web app
- the vault `kv-sahl-dev-sdc-<suffix>` (standard, RBAC, soft delete 7 days)
- two role assignments on the vault: the app's identity → **Secrets User**, you (`adminPrincipalId`) → **Secrets Officer**
- `func vaultRef(...)` and Key Vault references for `APP_KEY`, `DB_PASSWORD`, `ENTRA_CLIENT_SECRET` and `ENTRA_CITIZEN_CLIENT_SECRET`
- the Entra IDs and the Azure redirect URIs (`${appUrl}/auth/staff/callback`, `${appUrl}/auth/citizen/callback`) as App Settings
- the output `vaultName`

`infra/main.bicepparam` holds the non-secret values:

| Param | Value | Where it comes from |
|---|---|---|
| `adminPrincipalId` | `46ca405c-0005-44b3-a769-ad6adaf6cb9c` | `az ad signed-in-user show --query id -o tsv` |
| `entraTenantId` / `entraClientId` | `509dc1f4-…` / `2756182e-…` | Staff app registration ([01](01-entra-id-staff-sign-in.md)) |
| `entraCitizenTenantId` / `entraCitizenClientId` | `004a7659-…` / `9bfd5683-…` | Citizen app registration ([02](02-entra-external-id-citizen-sign-in.md)) |
| `entraCitizenDomain` | `sahlcitizens` | The external tenant's name |
| `allowedClientIp` | from `$MYIP` | Your shell, at deploy time |
| `administratorPassword` | `az.getSecret(<subscription>, 'rg-sahl-dev-sdc', 'kv-sahl-dev-sdc-vmi3r3', 'db-password')` | The vault (see step 6). Subscription `01cd0fe3-a684-4dd3-a5d5-759e27df84e4` |

The first deploy (before step 6 existed) took the password from the shell:

```bash
read -s "SAHL_DB_PASSWORD?DB admin password: "; echo; export SAHL_DB_PASSWORD
export MYIP=$(curl -s https://api.ipify.org); echo "IP: $MYIP"
az deployment group create -g rg-sahl-dev-sdc --parameters infra/main.bicepparam
unset SAHL_DB_PASSWORD
```

**Every deploy sets the Postgres admin password.** That is why it is now read from the vault (step 6) instead.

## Step 3: Put the secrets in the vault

```bash
KV=kv-sahl-dev-sdc-vmi3r3
read -s "S?Value: "; echo; az keyvault secret set --vault-name $KV -n app-key --value "$S" -o none; unset S
# repeat for each of: db-password, entra-client-secret, entra-citizen-client-secret
```

| Secret | Value |
|---|---|
| `app-key` | A new key: `php artisan key:generate --show` (never your local key, never the burned one from the repo history) |
| `db-password` | The Postgres admin password (the same as `SAHL_DB_PASSWORD`) |
| `entra-client-secret` | The staff app registration's client secret |
| `entra-citizen-client-secret` | The citizen app registration's client secret |

Then restart, so App Service fetches the values:

```bash
az webapp restart -g rg-sahl-dev-sdc -n app-sahl-dev-sdc-vmi3r3
```

## Step 4: Azure redirect URIs in both app registrations

Entra only sends users back to registered URLs. Under **App registrations → the app → Authentication → Web → Redirect URIs**, add:

| Tenant | Redirect URI |
|---|---|
| Workforce (staff) | `https://app-sahl-dev-sdc-vmi3r3.azurewebsites.net/auth/staff/callback` |
| `sahlcitizens` (citizens) | `https://app-sahl-dev-sdc-vmi3r3.azurewebsites.net/auth/citizen/callback` |

Keep the `http://localhost:8123/...` ones for local development.

## Step 5: Turn on container logs

Without this, Laravel's errors (it logs to stderr) go nowhere and you only see a bare 500:

```bash
az webapp log config -g rg-sahl-dev-sdc -n app-sahl-dev-sdc-vmi3r3 --docker-container-logging filesystem
az webapp log tail -g rg-sahl-dev-sdc -n app-sahl-dev-sdc-vmi3r3
```

Now also in the Bicep (`Microsoft.Web/sites/config` named `logs`, step 6), so a deploy keeps it on.

## What went wrong: a 500 after the vault deploy

- **Symptom:** every page returned 500. The portal's Key Vault reference status said `AccessToKeyVaultDenied` for all four secrets, even though the role assignment was correct.
- **Real cause (from the container log):** `FATAL: password authentication failed for user "sahladmin"`. The password typed for the deploy (which resets the server's password) and the one typed into the vault as `db-password` were different.
- **Fix:** set both from one prompt:
  ```bash
  read -s "S?DB admin password: "; echo
  az postgres flexible-server update -g rg-sahl-dev-sdc -n psql-sahl-dev-sdc-vmi3r3 --admin-password "$S" -o none
  az keyvault secret set --vault-name kv-sahl-dev-sdc-vmi3r3 -n db-password --value "$S" -o none
  unset S
  az webapp restart -g rg-sahl-dev-sdc -n app-sahl-dev-sdc-vmi3r3
  ```
- **Lessons:**
  - The reference status page can lag behind. Trust the logs.
  - A role assignment can take a few minutes to apply after a deploy. A restart after that picks the secrets up.
  - Typing one password in two places invites this. Later: have the `.bicepparam` read it from the vault with `getSecret()`, so there is one source.

## Step 6: One source for the DB password (2026-10-13)

Typing the password twice caused the 500 above. Now the deploy reads it from the vault:

- `main.bicep`:
  - the vault has `enabledForTemplateDeployment: true`, which lets a deployment read secrets from it
  - the web app has a child `config` resource named `logs` that turns container logging on (step 5)
- `main.bicepparam`: `administratorPassword = az.getSecret(...)`. Azure Resource Manager fetches `db-password` itself during the deploy. The password never passes through your shell.

The vault had to allow template deployment before the first deploy that uses `getSecret()`, so that was switched on once by hand:

```bash
az keyvault update -n kv-sahl-dev-sdc-vmi3r3 --enabled-for-template-deployment true -o none
export MYIP=$(curl -s https://api.ipify.org); echo "IP: $MYIP"
az deployment group create -g rg-sahl-dev-sdc --parameters infra/main.bicepparam     # no password prompt any more
```

**To change the DB password from now on:** change the secret, deploy, restart.

```bash
read -s "S?New DB admin password: "; echo
az keyvault secret set --vault-name kv-sahl-dev-sdc-vmi3r3 -n db-password --value "$S" -o none; unset S
az deployment group create -g rg-sahl-dev-sdc --parameters infra/main.bicepparam     # sets the server's password from the vault
az webapp restart -g rg-sahl-dev-sdc -n app-sahl-dev-sdc-vmi3r3                      # the app reads the new value
```

**Who may deploy:** `getSecret()` needs the permission `Microsoft.KeyVault/vaults/deploy/action` on the vault. Owner and Contributor have it.

---

## Checklist for a rebuild (e.g. after deleting the resource group)

The vault must exist before `getSecret()` can read it. So the first deploy of a rebuild passes the password inline: a `--parameters` given after the `.bicepparam` overrides its value.

- [ ] [05](05-azure-app-service.md) done (code ready to deploy)
- [ ] Providers registered: `Microsoft.KeyVault`, `Microsoft.DBforPostgreSQL`, `Microsoft.Web` (`Registered`)
- [ ] A deleted vault stays **soft-deleted for 7 days** and keeps its name. Either `az keyvault recover -n kv-sahl-dev-sdc-<suffix>` (the secrets come back too), or `az keyvault purge -n …` to start clean
- [ ] First deploy: `read -s "S?DB admin password: "; echo`, `export MYIP=…`, then `az deployment group create -g rg-sahl-dev-sdc --parameters infra/main.bicepparam --parameters administratorPassword="$S"`
- [ ] Set the four secrets (`app-key` new, `db-password` = the same `$S`, both Entra client secrets), then `unset S`
- [ ] From then on, deploy without the override
- [ ] Both app registrations have the Azure redirect URIs (they survive a resource group delete)
- [ ] `az webapp restart`, then sign in as staff (lands on `/staff`) and as a citizen (lands on `/account`)
