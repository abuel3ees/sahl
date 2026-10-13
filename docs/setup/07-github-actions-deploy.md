# 07 · Deploy from GitHub Actions (OIDC, no secrets)

**Started:** 2026-10-13, current subscription, resource group `rg-sahl-dev-sdc`. Builds on [05](05-azure-app-service.md) and [06](06-key-vault-sign-in.md).

**Goal:** every push to `main` runs the tests, builds the app and deploys it to **https://app-sahl-dev-sdc-vmi3r3.azurewebsites.net**. You no longer run `package.sh` and `az webapp deploy` on your Mac.

**Cost:** free. GitHub Actions is free for public repos, and managed identities are free.

---

## Words used here

| Term | Meaning |
|---|---|
| **GitHub Actions / workflow** | GitHub runs the steps in `.github/workflows/deploy.yml` on a fresh Linux VM ("runner") when you push |
| **OIDC (OpenID Connect) federation** | GitHub issues each workflow run a short-lived signed token saying "I am repo X, branch Y". Azure is told to trust exactly that token, so there's no password to store or leak |
| **User-assigned managed identity** | A managed identity that exists on its own (unlike the web app's system-assigned one). `id-sahl-github` is GitHub's identity in Azure |
| **Federated credential** | A child of that identity: "trust tokens from issuer `token.actions.githubusercontent.com` with subject `repo:abuel3ees@170485164/sahl@1414746207:ref:refs/heads/main`" |
| **Website Contributor** | A built-in role: manage and deploy one web app. It can't read the vault or touch the database |
| **Repository variables** | Non-secret settings stored in GitHub (`vars.X` in the workflow). Unlike secrets, they are visible in logs |

## Decisions

- **Sign-in: OIDC with a user-assigned managed identity**, defined in Bicep. This was chosen over a publish profile or a client secret: there's no long-lived password, and nothing expires.
- **Trigger:** push to `main`, plus a manual "Run workflow" button.
- **Tests gate the deploy:** if a test fails, nothing is deployed.
- **Not in the pipeline yet:** migrations (they still run from the Mac) and the Bicep deploy.

## Step 1: Infrastructure (Bicep)

`infra/main.bicep` gained:
- `var githubRepo = 'abuel3ees@170485164/sahl@1414746207'`: owner and repo **with their numeric IDs** (see below)
- the identity `id-sahl-github`, with the child federated credential `github-main` (subject `repo:abuel3ees@170485164/sahl@1414746207:ref:refs/heads/main`, audience `api://AzureADTokenExchange`)
- the role assignment **Website Contributor** (`de139f84-1756-47ae-9be6-808fbbe84772`), scoped to the web app only
- the output `githubClientId`

```bash
export MYIP=$(curl -s https://api.ipify.org); echo "IP: $MYIP"
az deployment group create -g rg-sahl-dev-sdc --parameters infra/main.bicepparam --query properties.outputs.githubClientId.value -o tsv
```

The last line prints the identity's client ID. You need it in step 2.

**What went wrong the first time:** `RoleDefinitionDoesNotExist`. The Website Contributor ID in the Bicep was mistyped, from memory. Don't trust a remembered role ID; look it up:
`az role definition list --name "Website Contributor" --query "[0].name" -o tsv`

**What went wrong in the first workflow run:** `AADSTS700213: No matching federated identity record found for presented assertion subject 'repo:abuel3ees@170485164/sahl@1414746207:ref:refs/heads/main'`.
- GitHub's tokens name the repo **with numeric IDs** (`owner@ownerId/repo@repoId`). That way, if the account or repo is deleted and someone re-creates it under the same name, they don't inherit the trust.
- The credential's subject must match the token **exactly**. Fix: use the IDs in `githubRepo`, then deploy the Bicep again.
- Look the IDs up with: `gh api repos/abuel3ees/sahl --jq '.owner.id, .id'`
- The Azure error always shows the exact subject GitHub sent. Copy it from there.

## Step 2: Repository variables in GitHub

These are IDs, not secrets. Either use **GitHub → the repo → Settings → Secrets and variables → Actions → Variables**, or run:

```bash
gh variable set AZURE_CLIENT_ID       --body "<githubClientId from step 1>"
gh variable set AZURE_TENANT_ID       --body "$(az account show --query tenantId -o tsv)"
gh variable set AZURE_SUBSCRIPTION_ID --body "$(az account show --query id -o tsv)"
gh variable set AZURE_RESOURCE_GROUP  --body rg-sahl-dev-sdc
gh variable set AZURE_WEBAPP_NAME     --body app-sahl-dev-sdc-vmi3r3
gh variable list
```

## Step 3: The workflow

`.github/workflows/deploy.yml` has two jobs:

| Job | What it does |
|---|---|
| `test` | PHP 8.4 and a Postgres 17 service container (user `sahl`, password `secret`, database `sahl_testing`, the same as local), then `php artisan test` |
| `deploy` (only if `test` passed) | `npm ci`, then `infra/app-service/package.sh`, then `azure/login` (OIDC), `az webapp deploy`, and a smoke test of `/up` |

`permissions: id-token: write` is what allows the workflow to request the OIDC token.

## Step 4: Merge to main and watch it

Commit, push, and merge your branch into `main`. Then follow the run under **GitHub → Actions → Deploy**, or with:

```bash
gh run watch
```

---

## Checklist for a rebuild

- [ ] `Microsoft.ManagedIdentity` provider registered
- [ ] Bicep deployed; note `githubClientId`
- [ ] The five repository variables set (a new identity has a **new** client ID)
- [ ] A push to `main`, or Actions → Deploy → **Run workflow**: green, and `/up` returns 200
