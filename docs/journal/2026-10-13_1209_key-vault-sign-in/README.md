# Key Vault, managed identity and sign-in on Azure

## What was asked

> "let's do that step"
>
> "write it down for me, i got the whole bicep idea"
>
> "server gives 500 interal now for some reason"
>
> "it works"

## What we did

1. **Bicep** (written by me on request): a Key Vault in RBAC mode, a system-assigned managed identity on the web app, and two role assignments: the app gets Secrets User, the user gets Secrets Officer. Secrets became Key Vault references through a small `func vaultRef()`. The Entra IDs and the Azure redirect URIs became App Settings.
2. **`infra/main.bicepparam`:** the IDs live here. The IP and DB password come from the shell via `readEnvironmentVariable()`.
3. **The user ran it:** registered `Microsoft.KeyVault`, deployed, set the four secrets, and restarted the app.
4. **500 after the deploy:**
   - The portal showed `AccessToKeyVaultDenied`, but the roles were correct.
   - Turning on container logging showed the real error: Postgres password authentication failed. The deploy password and the vault's `db-password` differed.
   - The user reset both from one prompt.
5. **Sign-in tested on Azure:** staff and citizen sign-in, plus the full flow. Both app registrations have the Azure redirect URIs.

Guide: [setup 06](../../setup/06-key-vault-sign-in.md).

## Decisions

- **Key Vault in RBAC mode**, not access policies. Access is plain Azure roles, the same model as the rest of Azure.
- **System-assigned identity** (not user-assigned): one app, so the identity can live and die with it.
- **Least privilege:** the app can only read secrets, and only in this vault.
- **Container logging on** (by hand for now; move into Bicep later).
- **Later:** read the DB password in the `.bicepparam` with `getSecret()`, so it is typed in one place only.

## Files

- `infra/main.bicep` (vault, identity, roles, Key Vault references, Entra settings)
- `infra/main.bicepparam` (new)
- `docs/setup/06-key-vault-sign-in.md` (new)

## Follow-up: one source for the DB password

> "A"

- `main.bicepparam` reads `administratorPassword` from the vault with `az.getSecret()`, so the deploy can no longer set a different password than the app uses.
- The vault has `enabledForTemplateDeployment: true` so the deploy can read it.
- Container logging (`config/logs`) is now in the Bicep.
- The guide gained step 6 and a rebuild checklist. The account move is cancelled: Sahl stays on this subscription.
