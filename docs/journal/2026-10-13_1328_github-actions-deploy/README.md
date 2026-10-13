# Deploy from GitHub Actions (OIDC)

## What was asked

> "how do we deploy from github"
>
> "A, a , yes"  (OIDC, deploy on push to main, tests first)
>
> "theres one thing im confused about managed identity, explain why and when we ar e using it"

## What we did

1. **Explained the pipeline:** runner, workflow, identity, trust. Gave three options for how GitHub signs in to Azure; the user chose OIDC.
2. **Bicep** (written by me):
   - user-assigned managed identity `id-sahl-github`
   - federated credential `github-main` trusting the repo's `main` branch
   - *Website Contributor* on the web app only
   - output `githubClientId`
3. **`.github/workflows/deploy.yml`:**
   - `test` job: PHP 8.4 + Postgres 17 service container
   - `deploy` job: `package.sh`, `azure/login` OIDC, `az webapp deploy`, `/up` smoke test
4. **The user ran it:** Bicep deploy, five repository variables, merged to `main`.
5. **Two failures, both fixed:**
   - `RoleDefinitionDoesNotExist`: I had mistyped the Website Contributor role ID from memory. Fixed by looking it up with `az role definition list`.
   - `AADSTS700213`: GitHub's OIDC subject includes the numeric owner and repo IDs (`abuel3ees@170485164/sahl@1414746207`). Updated the subject; also moved the actions to their Node 24 versions.
6. **Run `38382808700` green.**
7. **Explained managed identities:**
   - system-assigned: inside Azure, tied to one resource
   - user-assigned + federation: for outside callers like GitHub
   - how both differ from the sign-in app registrations

Guide: [setup 07](../../setup/07-github-actions-deploy.md).

## Decisions

- **OIDC with a user-assigned managed identity**, in Bicep. No publish profile, no client secret.
- **Deploy on push to `main`**, plus manual "Run workflow".
- **Tests gate the deploy.**
- **Still manual:** migrations (from the Mac) and Bicep deploys.

## Files

- `infra/main.bicep` (GitHub identity, federated credential, role)
- `.github/workflows/deploy.yml` (new)
- `docs/setup/07-github-actions-deploy.md` (new)
- `CLAUDE.md` (map row for the workflow)
