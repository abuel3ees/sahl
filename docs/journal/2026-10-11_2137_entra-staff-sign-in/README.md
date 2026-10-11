# Staff sign-in with Microsoft Entra ID

**When:** 2026-10-11 21:37 (UTC+3)

## What was asked

> "i think let's set up entraid on azure, in the end ill switch accounts to a new free plan, so we can test here at least"
>
> "before you implement it, we need to do it step by step, just tell me what to do"
>
> "from now on, you will document every step i did in a separate .md file as well"

## What we did

- **Azure side (done by the user, step by step):** found the tenant, created a test reviewer with MFA, registered the `Sahl (local)` app, and created and assigned the `MOI.Reviewer` app role with "Assignment required". Every click is in **[docs/setup/01-entra-id-staff-sign-in.md](../../setup/01-entra-id-staff-sign-in.md)**.
- **Code:**
  - Installed `laravel/socialite` and `socialiteproviders/microsoft`, registered as the `entra` driver.
  - Added `/auth/staff/redirect` and `/auth/staff/callback`. The callback requires a `{CODE}.Reviewer` role and creates or updates the employee by Entra object ID.
  - Added `/staff` (back-office home) and `POST /logout`.
  - Added a migration: `users.entra_object_id` (unique), and `password` is now nullable.
  - Login page: the staff tab shows "Continue with Microsoft", and refused sign-ins show an explanation (cancelled, failed, no role).
  - The header shows the signed-in user.
  - Added 11 tests that fake Microsoft's response. 72 tests in total pass.
- Added the "setup guides" rule to CLAUDE.md, set `APP_URL` to `http://localhost:8123`, and added the `ENTRA_*` keys to `.env.example`.

## Result

The user's first real sign-in hit "Need admin approval", because the tenant only lets users consent to apps from verified publishers. After the admin granted consent for `User.Read` (setup guide, step 5), the reviewer signed in with MFA and landed on `/staff`. Sahl created the `employee` row for MOI with no password.

## Decisions

- **Library: option A**, Socialite plus the community Microsoft provider, chosen by the user. It validates the ID token (signature via JWKS, issuer, audience, expiry) and exposes `roles`. Its sign-in address is fixed to `login.microsoftonline.com`, so citizens on External ID (`ciamlogin.com`) will probably need a small subclass.
- **Entra decides who is a reviewer.** The app role `MOI.Reviewer` maps to the gov entity with code `MOI`. Employee rows are created on first sign-in and refreshed every time. No pre-created staff accounts.
- **Accounts are linked by `oid`** (Entra object ID), never by email, so a matching email can't take over an existing account.
- **Sign-out ends the Sahl session only.** The Microsoft session stays, so the next sign-in skips the password. Single sign-out can come later.
- All Entra values live in `.env`, so moving to the new account is a new registration plus new values.

## Files

- `app/Actions/Auth/SignInStaffWithEntra.php`
- `app/Http/Controllers/Web/Auth/{StaffSignInController,SignOutController}.php`, `app/Http/Controllers/Web/Staff/HomeController.php`
- `app/Providers/AppServiceProvider.php`, `config/services.php`, `app/Http/Middleware/HandleInertiaRequests.php`, `app/Models/User.php`
- `database/migrations/2026_10_11_183424_add_entra_object_id_to_users_table.php`
- `routes/web.php`
- `resources/js/pages/auth/login.tsx`, `resources/js/pages/staff/home.tsx`, `resources/js/components/site/site-header.tsx`, `resources/js/types/global.d.ts`
- `tests/Feature/Web/StaffSignInTest.php`
- `docs/setup/01-entra-id-staff-sign-in.md`
