# Citizen sign-in with Entra External ID

**When:** 2026-10-11 23:20 (UTC+3)

## What was asked

> "let's do the citizen entra ext id"
>
> "b" … then "nvm let's just do A for now and then in v2 we'll do the actual stuff"
>
> "it worked, but it still requires an email, regularly dont they just put in their id and thats it"

## What we did

- **Azure side (the user, step by step):**
  - Created the external tenant **Sahl Citizens** (`sahlcitizens`, Germany, `rg-sahl-identity`).
  - Registered `Sahl (local)` there.
  - Added the custom attribute `NationalId` and the user flow `SignUpSignIn` (email + password).
  - Mapped the claim `national_id` into the token.
  - Every click is in **[docs/setup/02-entra-external-id-citizen-sign-in.md](../../setup/02-entra-external-id-citizen-sign-in.md)**.
- **Code:**
  - `App\Auth\EntraExternalIdProvider`: a Socialite driver `entra_citizen` for `{domain}.ciamlogin.com`. It validates the ID token itself (JWKS signature with RS256, issuer, audience, expiry) and doesn't call Graph.
  - `SignInCitizenWithEntra`: links by `oid`; one National ID per account (`national_id_taken`); an unclaimed seeded citizen is claimed by the first sign-in with that ID; a missing or invalid ID is refused (`no_national_id`); an email already in use is skipped.
  - `/auth/citizen/redirect` and `/callback`, plus `/account` (the citizen's details and requests, gate `access-citizen-account`).
  - Login page citizen tab: **Continue with Sahl ID**. The header links signed-in citizens to `/account`.
  - Migration: `users.email` is nullable.
- **Tests:**
  - `EntraExternalIdProviderTest`: real RSA signatures; forged, wrong-app, wrong-tenant and expired tokens are rejected; the provider makes no Graph call.
  - `CitizenSignInTest`: 11 cases.
  - 113 tests pass.
- **Bug found on the first real sign-in:** External ID's JWKS keys have no `alg` → "JWK must contain an alg parameter". Fixed by supplying RS256; the test keys now match Entra's real format.

## Decisions

- **The National ID is self-declared in V1 (option A).** The user preferred B (a mock national ID provider federated into External ID) and moved it to **V2**. Notes for V2:
  - External ID's OIDC federation maps only standard claims, so the plan is `sub` = National ID, read back through Graph identities.
  - The provider must be publicly reachable (a tunnel or Azure).
  - Email can be made optional, which gives the real GCC experience: type the ID, approve on the phone, no email.
- **Our own driver instead of the community Microsoft one,** because that one has `login.microsoftonline.com` hard-coded and validates against that issuer.
- **Citizen data lives in Germany** for the PoC. A real deployment needs in-country residency.

## Files

- `app/Auth/EntraExternalIdProvider.php`, `app/Actions/Auth/SignInCitizenWithEntra.php`, `app/Actions/Auth/SignInRefused.php`
- `app/Http/Controllers/Web/Auth/CitizenSignInController.php`, `app/Http/Controllers/Web/Citizen/AccountController.php`
- `app/Providers/AppServiceProvider.php`, `config/services.php`, `routes/web.php`, `.env.example`
- `database/migrations/*_make_email_nullable_on_users_table.php`
- `resources/js/pages/auth/login.tsx`, `resources/js/pages/citizen/account.tsx`, `resources/js/components/site/site-header.tsx`, `resources/js/types/global.d.ts`
- `tests/Feature/Auth/EntraExternalIdProviderTest.php`, `tests/Feature/Web/CitizenSignInTest.php`
- `docs/setup/02-entra-external-id-citizen-sign-in.md`, `CLAUDE.md`
